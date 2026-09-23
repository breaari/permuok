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

    private static function storeUploadedFile(
        array $file,
        int $developmentId
    ): string {
        if (
            ($file['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_OK
        ) {
            throw new Exception(
                'Una de las imágenes no pudo subirse',
                422
            );
        }

        $tmp = (string)($file['tmp_name'] ?? '');

        if (
            $tmp === '' ||
            !is_uploaded_file($tmp)
        ) {
            throw new Exception(
                'Archivo subido inválido',
                422
            );
        }

        $realSize = filesize($tmp);

        if (
            $realSize === false ||
            $realSize <= 0 ||
            $realSize > self::MAX_FILE_SIZE
        ) {
            throw new Exception(
                'Cada imagen debe pesar hasta 5 MB',
                422
            );
        }

        $finfo = new \finfo(
            FILEINFO_MIME_TYPE
        );

        $mime = $finfo->file($tmp);

        if (
            !is_string($mime) ||
            !isset(self::ALLOWED_MIME_TYPES[$mime])
        ) {
            throw new Exception(
                'Formato de imagen no permitido. Usá JPG, PNG o WebP',
                422
            );
        }

        $imageInfo = @getimagesize($tmp);

        if ($imageInfo === false) {
            throw new Exception(
                'El archivo recibido no es una imagen válida',
                422
            );
        }

        $imageMime =
            (string)($imageInfo['mime'] ?? '');

        if (
            $imageMime !== $mime ||
            !isset(self::ALLOWED_MIME_TYPES[$imageMime])
        ) {
            throw new Exception(
                'El contenido de la imagen no coincide con su formato',
                422
            );
        }

        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);

        if ($width <= 0 || $height <= 0) {
            throw new Exception(
                'La imagen tiene dimensiones inválidas',
                422
            );
        }

        if (
            $width > 12000 ||
            $height > 12000 ||
            ($width * $height) > 40000000
        ) {
            throw new Exception(
                'La imagen tiene dimensiones demasiado grandes',
                422
            );
        }

        $extension =
            self::ALLOWED_MIME_TYPES[$imageMime];

        $baseDir =
            self::getUploadBaseDir();

        $filename = sprintf(
            'development_%d_%s.%s',
            $developmentId,
            bin2hex(random_bytes(12)),
            $extension
        );

        $target =
            $baseDir . '/' . $filename;

        if (
            !move_uploaded_file(
                $tmp,
                $target
            )
        ) {
            throw new Exception(
                'No se pudo guardar una de las imágenes'
            );
        }

        return 'developments/' . $filename;
    }

    private static function removeStoredFile(
        string $relativePath
    ): void {
        if (
            !str_starts_with(
                $relativePath,
                'developments/'
            )
        ) {
            return;
        }

        $filename = basename($relativePath);

        if ($filename === '') {
            return;
        }

        $baseDir = self::getUploadBaseDir();
        $baseRealPath = realpath($baseDir);

        $filePath = $baseDir . '/' . $filename;
        $fileRealPath = realpath($filePath);

        if (
            $baseRealPath === false ||
            $fileRealPath === false ||
            dirname($fileRealPath) !== $baseRealPath ||
            !is_file($fileRealPath)
        ) {
            return;
        }

        if (!unlink($fileRealPath)) {
            error_log(
                '[DEVELOPMENT IMAGE CLEANUP] ' .
                    'No se pudo eliminar el archivo: ' .
                    $fileRealPath
            );
        }
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

    public static function upload(
        int $userId,
        int $developmentId,
        array $files
    ): array {
        [, $development] =
            self::getOwnedDevelopmentRow(
                $userId,
                $developmentId
            );

        if (
            !in_array(
                $development['status'],
                [
                    'draft',
                    'paused',
                    'archived',
                    'published',
                ],
                true
            )
        ) {
            throw new Exception(
                'No se pueden cargar imágenes en el estado actual del desarrollo'
            );
        }

        $normalizedFiles =
            self::normalizeFilesArray($files);

        if (!$normalizedFiles) {
            throw new Exception(
                'No se recibieron imágenes'
            );
        }

        if (
            count($normalizedFiles)
            > self::MAX_IMAGES
        ) {
            throw new Exception(
                'Podés tener hasta 5 imágenes por desarrollo'
            );
        }

        $pdo = self::db();
        $storedPaths = [];

        $pdo->beginTransaction();

        try {
            /*
         * Serializa las cargas pertenecientes
         * al mismo desarrollo.
         */
            $lockStmt = $pdo->prepare("
            SELECT id
            FROM developments
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $lockStmt->execute([
                'id' => $developmentId,
            ]);

            if (!$lockStmt->fetchColumn()) {
                throw new Exception(
                    'Desarrollo no encontrado'
                );
            }

            $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
        ");

            $countStmt->execute([
                'development_id' =>
                $developmentId,
            ]);

            $currentCount =
                (int)$countStmt->fetchColumn();

            if (
                (
                    $currentCount
                    + count($normalizedFiles)
                ) > self::MAX_IMAGES
            ) {
                throw new Exception(
                    'Podés tener hasta 5 imágenes por desarrollo'
                );
            }

            $sortStmt = $pdo->prepare("
            SELECT COALESCE(
                MAX(sort_order),
                -1
            )
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
        ");

            $sortStmt->execute([
                'development_id' =>
                $developmentId,
            ]);

            $sortOrder =
                ((int)$sortStmt->fetchColumn()) + 1;

            $isFirstImage =
                $currentCount === 0;

            foreach (
                $normalizedFiles as $index => $file
            ) {
                $relativePath =
                    self::storeUploadedFile(
                        $file,
                        $developmentId
                    );

                $storedPaths[] = $relativePath;

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
                    'development_id' =>
                    $developmentId,
                    'file_path' =>
                    $relativePath,
                    'sort_order' =>
                    $sortOrder + $index,
                    'is_cover' => (
                        $isFirstImage
                        && $index === 0
                    )
                        ? 1
                        : 0,
                ]);
            }

            self::ensureSingleCover(
                $developmentId
            );

            $pdo->commit();

            return DevelopmentService::getDetail(
                $userId,
                $developmentId
            );
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($storedPaths as $storedPath) {
                self::removeStoredFile(
                    $storedPath
                );
            }

            throw $e;
        }
    }

    public static function delete(
        int $userId,
        int $imageId
    ): array {
        [, $image] =
            self::getOwnedImage(
                $userId,
                $imageId
            );

        $pdo = self::db();

        $developmentId =
            (int)$image['development_id'];

        $filePath =
            (string)($image['file_path'] ?? '');

        $stDevelopment = $pdo->prepare("
        SELECT status
        FROM developments
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $stDevelopment->execute([
            'id' => $developmentId,
        ]);

        $development =
            $stDevelopment->fetch();

        if (!$development) {
            throw new Exception(
                'Desarrollo no encontrado'
            );
        }

        if (
            !in_array(
                $development['status'],
                [
                    'draft',
                    'paused',
                    'archived',
                    'published',
                ],
                true
            )
        ) {
            throw new Exception(
                'No se pueden eliminar imágenes en el estado actual del desarrollo'
            );
        }

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare("
            UPDATE development_images
            SET deleted_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $st->execute([
                'id' => $imageId,
            ]);

            self::ensureSingleCover(
                $developmentId
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        /*
     * El archivo se elimina solamente después
     * de confirmar la eliminación en la base.
     */
        if ($filePath !== '') {
            self::removeStoredFile(
                $filePath
            );
        }

        return DevelopmentService::getDetail(
            $userId,
            $developmentId
        );
    }

    public static function reorder(
        int $userId,
        int $developmentId,
        mixed $images
    ): array {
        [, $development] =
            self::getOwnedDevelopmentRow(
                $userId,
                $developmentId
            );

        if (
            !in_array(
                $development['status'],
                [
                    'draft',
                    'paused',
                    'archived',
                    'published',
                ],
                true
            )
        ) {
            throw new Exception(
                'No se pueden reordenar imágenes en el estado actual del desarrollo'
            );
        }

        if (
            !is_array($images) ||
            $images === []
        ) {
            throw new Exception(
                'Debés enviar el listado de imágenes',
                422
            );
        }

        $normalizedImages = [];
        $seenIds = [];
        $coverCount = 0;

        foreach ($images as $image) {
            if (!is_array($image)) {
                throw new Exception(
                    'El listado de imágenes es inválido',
                    422
                );
            }

            $rawId = $image['id'] ?? null;

            $imageId = filter_var(
                $rawId,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($imageId === false) {
                throw new Exception(
                    'Una de las imágenes tiene un identificador inválido',
                    422
                );
            }

            if (isset($seenIds[$imageId])) {
                throw new Exception(
                    'El listado contiene imágenes repetidas',
                    422
                );
            }

            $rawCover =
                $image['is_cover'] ?? null;

            if (
                !in_array(
                    $rawCover,
                    [
                        true,
                        false,
                        1,
                        0,
                        '1',
                        '0',
                    ],
                    true
                )
            ) {
                throw new Exception(
                    'El valor de portada es inválido',
                    422
                );
            }

            $isCover = in_array(
                $rawCover,
                [true, 1, '1'],
                true
            );

            if ($isCover) {
                $coverCount++;
            }

            $seenIds[$imageId] = true;

            $normalizedImages[] = [
                'id' => (int)$imageId,
                'is_cover' => $isCover ? 1 : 0,
            ];
        }

        if ($coverCount !== 1) {
            throw new Exception(
                'Debés definir exactamente una imagen de portada',
                422
            );
        }

        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $lockDevelopment = $pdo->prepare("
            SELECT id
            FROM developments
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $lockDevelopment->execute([
                'id' => $developmentId,
            ]);

            if (!$lockDevelopment->fetchColumn()) {
                throw new Exception(
                    'Desarrollo no encontrado',
                    404
                );
            }

            $st = $pdo->prepare("
            SELECT id
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY id ASC
            FOR UPDATE
        ");

            $st->execute([
                'development_id' => $developmentId,
            ]);

            $currentIds = array_map(
                'intval',
                $st->fetchAll(PDO::FETCH_COLUMN) ?: []
            );

            $incomingIds = array_column(
                $normalizedImages,
                'id'
            );

            sort($currentIds);
            sort($incomingIds);

            if ($currentIds !== $incomingIds) {
                throw new Exception(
                    'Las imágenes enviadas no coinciden con las imágenes activas del desarrollo',
                    422
                );
            }

            $stUpdate = $pdo->prepare("
            UPDATE development_images
            SET
                sort_order = :sort_order,
                is_cover = :is_cover
            WHERE id = :id
              AND development_id = :development_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            foreach (
                $normalizedImages as $index => $image
            ) {
                $stUpdate->execute([
                    'sort_order' => $index,
                    'is_cover' => $image['is_cover'],
                    'id' => $image['id'],
                    'development_id' => $developmentId,
                ]);
            }

            $pdo->commit();

            return DevelopmentService::getDetail(
                $userId,
                $developmentId
            );
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
