<?php

namespace App\Services;

use PDO;
use Exception;

class DevelopmentImageService
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

    private static function getUploadBaseDir(): string
    {
        $base = rtrim((string)($_ENV['UPLOADS_DIR'] ?? ''), '/');

        if ($base === '') {
            $base = dirname(__DIR__, 2) . '/uploads';
        }

        $dir = $base . '/developments';

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception('No se pudo crear el directorio de imágenes');
            }
        }

        return $dir;
    }

    private static function assertMembershipAllowsPublishing(int $realEstateId): void
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, status, can_publish_projects, end_date
            FROM memberships
            WHERE real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->execute(['real_estate_id' => $realEstateId]);
        $membership = $st->fetch();

        if (!$membership) {
            throw new Exception("La inmobiliaria no tiene una membresía activa");
        }

        if ((int)($membership['status'] ?? -1) !== 1) {
            throw new Exception("La membresía de la inmobiliaria no está activa");
        }

        if ((int)($membership['can_publish_projects'] ?? 0) !== 1) {
            throw new Exception("Tu plan no permite publicar desarrollos");
        }

        if (
            !empty($membership['end_date']) &&
            strtotime((string)$membership['end_date']) < strtotime(date('Y-m-d'))
        ) {
            throw new Exception("La membresía de la inmobiliaria está vencida");
        }
    }

    private static function getOwnedDevelopmentRow(int $userId, int $developmentId): array
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

        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);

        $stDevelopment = $pdo->prepare("
            SELECT *
            FROM developments
            WHERE id = :id
              AND real_estate_id = :real_estate_id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stDevelopment->execute([
            'id' => $developmentId,
            'real_estate_id' => (int)$user['real_estate_id'],
        ]);
        $development = $stDevelopment->fetch();

        if (!$development) {
            throw new Exception("Desarrollo no encontrado");
        }

        return [$user, $development];
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

        if ((int)$user['is_active'] !== 1) {
            throw new Exception("Tu cuenta está inactiva");
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception("El usuario no está vinculado a una inmobiliaria");
        }

        self::assertMembershipAllowsPublishing((int)$user['real_estate_id']);

        $st = $pdo->prepare("
            SELECT di.*, d.real_estate_id
            FROM development_images di
            INNER JOIN developments d ON d.id = di.development_id
            WHERE di.id = :id
              AND di.deleted_at IS NULL
              AND d.deleted_at IS NULL
              AND d.real_estate_id = :real_estate_id
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

    private static function countActiveImages(int $developmentId): int
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
        ");
        $st->execute(['development_id' => $developmentId]);

        return (int)($st->fetch()['total'] ?? 0);
    }

    private static function nextSortOrder(int $developmentId): int
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT COALESCE(MAX(sort_order), -1) AS max_sort
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
        ");
        $st->execute(['development_id' => $developmentId]);

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

    private static function storeUploadedFile(array $file, int $developmentId): string
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
            'development_%d_%s.%s',
            $developmentId,
            bin2hex(random_bytes(12)),
            $ext
        );

        $target = $baseDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            throw new Exception("No se pudo guardar una de las imágenes");
        }

        return 'developments/' . $filename;
    }

    private static function ensureSingleCover(int $developmentId): void
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, is_cover
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");
        $st->execute(['development_id' => $developmentId]);
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
            UPDATE development_images
            SET is_cover = 0
            WHERE development_id = :development_id
              AND deleted_at IS NULL
        ")->execute(['development_id' => $developmentId]);

        $pdo->prepare("
            UPDATE development_images
            SET is_cover = 1
            WHERE id = :id
            LIMIT 1
        ")->execute(['id' => $firstId]);
    }

    public static function upload(int $userId, int $developmentId, array $files): array
    {
        [, $development] = self::getOwnedDevelopmentRow($userId, $developmentId);

        if (!in_array($development['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden cargar imágenes en el estado actual del desarrollo");
        }

        $normalizedFiles = self::normalizeFilesArray($files);
        if (!$normalizedFiles) {
            throw new Exception("No se recibieron imágenes");
        }

        $currentCount = self::countActiveImages($developmentId);
        if (($currentCount + count($normalizedFiles)) > self::MAX_IMAGES) {
            throw new Exception("Podés tener hasta 5 imágenes por desarrollo");
        }

        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $sortOrder = self::nextSortOrder($developmentId);
            $isFirstImage = $currentCount === 0;

            foreach ($normalizedFiles as $index => $file) {
                $relativePath = self::storeUploadedFile($file, $developmentId);

                $st = $pdo->prepare("
                    INSERT INTO development_images (
                        development_id,
                        file_path,
                        sort_order,
                        is_cover
                    ) VALUES (
                        :development_id,
                        :file_path,
                        :sort_order,
                        :is_cover
                    )
                ");
                $st->execute([
                    'development_id' => $developmentId,
                    'file_path' => $relativePath,
                    'sort_order' => $sortOrder + $index,
                    'is_cover' => ($isFirstImage && $index === 0) ? 1 : 0,
                ]);
            }

            self::ensureSingleCover($developmentId);

            $pdo->commit();
            return DevelopmentService::getDetail($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $userId, int $imageId): array
    {
        [, $image] = self::getOwnedImage($userId, $imageId);
        $pdo = self::db();

        $stDevelopment = $pdo->prepare("
            SELECT status
            FROM developments
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stDevelopment->execute(['id' => (int)$image['development_id']]);
        $development = $stDevelopment->fetch();

        if (!$development) {
            throw new Exception("Desarrollo no encontrado");
        }

        if (!in_array($development['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden eliminar imágenes en el estado actual del desarrollo");
        }

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
                UPDATE development_images
                SET deleted_at = NOW()
                WHERE id = :id
                LIMIT 1
            ");
            $st->execute(['id' => $imageId]);

            self::ensureSingleCover((int)$image['development_id']);

            $pdo->commit();
            return DevelopmentService::getDetail($userId, (int)$image['development_id']);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function reorder(int $userId, int $developmentId, array $images): array
    {
        [, $development] = self::getOwnedDevelopmentRow($userId, $developmentId);

        if (!in_array($development['status'], ['draft', 'paused', 'archived', 'published'], true)) {
            throw new Exception("No se pueden reordenar imágenes en el estado actual del desarrollo");
        }

        if (!is_array($images) || !$images) {
            throw new Exception("Debés enviar el listado de imágenes");
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");
        $st->execute(['development_id' => $developmentId]);
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
            throw new Exception("Las imágenes enviadas no coinciden con las imágenes activas del desarrollo");
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
                    UPDATE development_images
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
            return DevelopmentService::getDetail($userId, $developmentId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}