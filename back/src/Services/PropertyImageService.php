<?php

namespace App\Services;

use PDO;
use Exception;

class PropertyImageService
{
    private const MAX_IMAGES = 5;
    private const MAX_FILE_SIZE = 5242880; // 5 MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function queueQualityRecalculation(
        int $propertyId
    ): void {
        try {
            CompatibilityJobService::enqueuePropertyQualityRecalculation(
                $propertyId
            );
        } catch (\Throwable $e) {
            error_log(
                '[PROPERTY IMAGE QUALITY QUEUE] ' .
                    'No se pudo encolar el recálculo ' .
                    'de calidad de la propiedad ' .
                    $propertyId . ': ' .
                    $e->getMessage()
            );
        }
    }

    private static function getUploadBaseDir(): string
    {
        $base = rtrim((string)($_ENV['UPLOADS_DIR'] ?? ''), '/');

        if ($base === '') {
            $base = dirname(__DIR__, 2) . '/uploads';
        }

        $dir = $base . '/properties';

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception('No se pudo crear el directorio de imágenes');
            }
        }

        return $dir;
    }

    private static function getOwnedPropertyRow(int $userId, int $propertyId): array
    {
        $pdo = self::db();

        $stUser = $pdo->prepare("
            SELECT id, role, real_estate_id, is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stUser->execute(['id' => $userId]);
        $user = $stUser->fetch();

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        if (!in_array((int)$user['role'], [2, 3], true)) {
            throw new Exception("No tenés permisos para administrar imágenes");
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception("El usuario no está vinculado a una inmobiliaria");
        }

        $stProperty = $pdo->prepare("
            SELECT *
            FROM properties
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stProperty->execute([
            'id' => $propertyId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);
        $property = $stProperty->fetch();

        if (!$property) {
            throw new Exception("Propiedad no encontrada");
        }

        return [$user, $property];
    }

    private static function getOwnedImage(int $userId, int $imageId): array
    {
        $pdo = self::db();

        $stUser = $pdo->prepare("
            SELECT id, role, real_estate_id, is_active
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stUser->execute(['id' => $userId]);
        $user = $stUser->fetch();

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        if (!in_array((int)$user['role'], [2, 3], true)) {
            throw new Exception("No tenés permisos para administrar imágenes");
        }

        $st = $pdo->prepare("
            SELECT pi.*, p.real_estate_id
            FROM property_images pi
            INNER JOIN properties p ON p.id = pi.property_id
            WHERE pi.id = :id
              AND pi.deleted_at IS NULL
              AND p.deleted_at IS NULL
              AND p.real_estate_id = :real_estate_id
            LIMIT 1
        ");
        $st->execute([
            'id' => $imageId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);
        $image = $st->fetch();

        if (!$image) {
            throw new Exception("Imagen no encontrada");
        }

        return [$user, $image];
    }

    private static function countActiveImages(int $propertyId): int
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
        ");
        $st->execute(['property_id' => $propertyId]);

        return (int)($st->fetch()['total'] ?? 0);
    }

    private static function nextSortOrder(int $propertyId): int
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT COALESCE(MAX(sort_order), -1) AS max_sort
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
        ");
        $st->execute(['property_id' => $propertyId]);

        return ((int)($st->fetch()['max_sort'] ?? -1)) + 1;
    }

    private static function normalizeFilesArray(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }

        return $normalized;
    }

    private static function storeUploadedFile(array $file, int $propertyId): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception("Una de las imágenes no pudo subirse");
        }

        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new Exception("Archivo subido inválido");
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new Exception("Cada imagen debe pesar hasta 5 MB");
        }

        $mime = mime_content_type($tmp);
        if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
            throw new Exception("Formato de imagen no permitido. Usá JPG, PNG o WebP");
        }

        $ext = self::ALLOWED_MIME_TYPES[$mime];
        $baseDir = self::getUploadBaseDir();

        $filename = sprintf(
            'property_%d_%s.%s',
            $propertyId,
            bin2hex(random_bytes(12)),
            $ext
        );

        $target = $baseDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            throw new Exception("No se pudo guardar una de las imágenes");
        }

        return 'properties/' . $filename;
    }

    private static function ensureSingleCover(int $propertyId): void
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, is_cover
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");
        $st->execute(['property_id' => $propertyId]);
        $images = $st->fetchAll() ?: [];

        if (!$images) {
            return;
        }

        $coverIds = array_values(array_filter(
            $images,
            fn($img) => (int)$img['is_cover'] === 1
        ));

        if (count($coverIds) === 1) {
            return;
        }

        $firstId = (int)$images[0]['id'];

        $pdo->prepare("
            UPDATE property_images
            SET is_cover = 0
            WHERE property_id = :property_id
              AND deleted_at IS NULL
        ")->execute(['property_id' => $propertyId]);

        $pdo->prepare("
            UPDATE property_images
            SET is_cover = 1
            WHERE id = :id
            LIMIT 1
        ")->execute(['id' => $firstId]);
    }

    public static function upload(int $userId, int $propertyId, array $files): array
    {
        [, $property] = self::getOwnedPropertyRow($userId, $propertyId);

        if (!in_array($property['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden cargar imágenes en el estado actual de la propiedad");
        }

        $normalizedFiles = self::normalizeFilesArray($files);
        if (!$normalizedFiles) {
            throw new Exception("No se recibieron imágenes");
        }

        $currentCount = self::countActiveImages($propertyId);
        if (($currentCount + count($normalizedFiles)) > self::MAX_IMAGES) {
            throw new Exception("Podés tener hasta 5 imágenes por propiedad");
        }

        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $sortOrder = self::nextSortOrder($propertyId);
            $isFirstImage = $currentCount === 0;

            foreach ($normalizedFiles as $index => $file) {
                $relativePath = self::storeUploadedFile($file, $propertyId);

                $st = $pdo->prepare("
                    INSERT INTO property_images (
                        property_id,
                        file_path,
                        sort_order,
                        is_cover
                    ) VALUES (
                        :property_id,
                        :file_path,
                        :sort_order,
                        :is_cover
                    )
                ");
                $st->execute([
                    'property_id' => $propertyId,
                    'file_path' => $relativePath,
                    'sort_order' => $sortOrder + $index,
                    'is_cover' => ($isFirstImage && $index === 0) ? 1 : 0,
                ]);
            }

            self::ensureSingleCover(
                $propertyId
            );

            $pdo->commit();

            self::queueQualityRecalculation(
                $propertyId
            );

            return PropertyService::getDetail(
                $userId,
                $propertyId
            );
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    public static function delete(int $userId, int $imageId): array
    {
        [, $image] = self::getOwnedImage($userId, $imageId);
        $pdo = self::db();

        $stProperty = $pdo->prepare("
        SELECT status
        FROM properties
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");
        $stProperty->execute(['id' => (int)$image['property_id']]);
        $property = $stProperty->fetch();

        if (!$property) {
            throw new Exception("Propiedad no encontrada");
        }

        if (!in_array($property['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden eliminar imágenes en el estado actual de la propiedad");
        }

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
            UPDATE property_images
            SET deleted_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
            $st->execute(['id' => $imageId]);

            self::ensureSingleCover((int)$image['property_id']);

            $pdo->commit();
            self::queueQualityRecalculation(
                (int)$image['property_id']
            );

            return PropertyService::getDetail(
                $userId,
                (int)$image['property_id']
            );
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function reorder(int $userId, int $propertyId, array $images): array
    {
        [, $property] = self::getOwnedPropertyRow($userId, $propertyId);

        if (!in_array($property['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden reordenar imágenes en el estado actual de la propiedad");
        }

        if (!is_array($images) || !$images) {
            throw new Exception("Debés enviar el listado de imágenes");
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");
        $st->execute(['property_id' => $propertyId]);
        $current = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $currentIds = array_map('intval', $current);

        $incomingIds = array_map(
            fn($img) => (int)($img['id'] ?? 0),
            $images
        );

        sort($currentIds);
        $sortedIncoming = $incomingIds;
        sort($sortedIncoming);

        if ($currentIds !== $sortedIncoming) {
            throw new Exception("Las imágenes enviadas no coinciden con las imágenes activas de la propiedad");
        }

        $coverCount = 0;
        foreach ($images as $img) {
            if (!empty($img['is_cover'])) {
                $coverCount++;
            }
        }

        if ($coverCount !== 1) {
            throw new Exception("Debés definir exactamente una imagen de portada");
        }

        $pdo->beginTransaction();

        try {
            foreach ($images as $index => $img) {
                $stUpdate = $pdo->prepare("
                    UPDATE property_images
                    SET
                        sort_order = :sort_order,
                        is_cover = :is_cover
                    WHERE id = :id
                    LIMIT 1
                ");
                $stUpdate->execute([
                    'sort_order' => $index,
                    'is_cover' => !empty($img['is_cover']) ? 1 : 0,
                    'id' => (int)$img['id'],
                ]);
            }

            $pdo->commit();

            self::queueQualityRecalculation(
                $propertyId
            );

            return PropertyService::getDetail($userId, $propertyId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
