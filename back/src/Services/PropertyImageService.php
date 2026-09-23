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

    private static function assertActiveMembership(
        int $realEstateId
    ): void {
        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT id
        FROM memberships
        WHERE real_estate_id = :real_estate_id
          AND status = 1
          AND deleted_at IS NULL
          AND end_date >= CURDATE()
        ORDER BY end_date DESC, id DESC
        LIMIT 1
    ");

        $stmt->execute([
            'real_estate_id' => $realEstateId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new Exception(
                'Tu membresía no está activa. Regularizá tu plan para continuar usando esta función.',
                402
            );
        }
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

        self::assertActiveMembership(
            (int)$user['real_estate_id']
        );

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

    private static function getOwnedImage(
        int $userId,
        int $imageId
    ): array {
        $pdo = self::db();

        $stUser = $pdo->prepare("
        SELECT
            id,
            role,
            real_estate_id,
            is_active
        FROM users
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");

        $stUser->execute([
            'id' => $userId,
        ]);

        $user = $stUser->fetch();

        if (!$user) {
            throw new Exception(
                'Usuario no encontrado'
            );
        }

        if (
            !in_array(
                (int)$user['role'],
                [2, 3],
                true
            )
        ) {
            throw new Exception(
                'No tenés permisos para administrar imágenes'
            );
        }

        if ((int)$user['is_active'] !== 1) {
            throw new Exception(
                'Tu cuenta está inactiva'
            );
        }

        if (empty($user['real_estate_id'])) {
            throw new Exception(
                'El usuario no está vinculado a una inmobiliaria'
            );
        }

        self::assertActiveMembership(
            (int)$user['real_estate_id']
        );

        $st = $pdo->prepare("
        SELECT
            pi.*,
            p.real_estate_id
        FROM property_images pi

        INNER JOIN properties p
            ON p.id = pi.property_id

        WHERE pi.id = :id
          AND pi.deleted_at IS NULL
          AND p.deleted_at IS NULL
          AND p.real_estate_id = :real_estate_id

        LIMIT 1
    ");

        $st->execute([
            'id' => $imageId,
            'real_estate_id' =>
            (int)$user['real_estate_id'],
        ]);

        $image = $st->fetch();

        if (!$image) {
            throw new Exception(
                'Imagen no encontrada'
            );
        }

        return [
            $user,
            $image,
        ];
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

    private static function storeUploadedFile(
        array $file,
        int $propertyId
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

        /*
     * No confiamos en el tamaño enviado por el cliente.
     * Medimos el archivo temporal creado por PHP.
     */
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

        /*
     * Detectamos el MIME desde el contenido real.
     */
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

        /*
     * Verificamos que el archivo pueda interpretarse
     * realmente como una imagen.
     */
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

        /*
     * Límite preventivo contra imágenes diseñadas
     * para consumir demasiada memoria al procesarse.
     */
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
            'property_%d_%s.%s',
            $propertyId,
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

        return 'properties/' . $filename;
    }

    private static function removeStoredFile(
        string $relativePath
    ): void {
        if (
            !str_starts_with(
                $relativePath,
                'properties/'
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
                '[PROPERTY IMAGE CLEANUP] ' .
                    'No se pudo eliminar el archivo: ' .
                    $fileRealPath
            );
        }
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

    public static function upload(
        int $userId,
        int $propertyId,
        array $files
    ): array {
        [, $property] =
            self::getOwnedPropertyRow(
                $userId,
                $propertyId
            );

        if (
            !in_array(
                $property['status'],
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
                'No se pueden cargar imágenes en el estado actual de la propiedad'
            );
        }

        $normalizedFiles =
            self::normalizeFilesArray($files);

        if (!$normalizedFiles) {
            throw new Exception(
                'No se recibieron imágenes'
            );
        }

        /*
     * Una sola solicitud nunca puede contener
     * más imágenes que el máximo permitido.
     */
        if (
            count($normalizedFiles)
            > self::MAX_IMAGES
        ) {
            throw new Exception(
                'Podés tener hasta 5 imágenes por propiedad'
            );
        }

        $pdo = self::db();
        $storedPaths = [];

        $pdo->beginTransaction();

        try {
            /*
         * Bloqueamos la propiedad durante la carga.
         * Otra carga simultánea deberá esperar y volver
         * a contar las imágenes después de esta operación.
         */
            $lockStmt = $pdo->prepare("
            SELECT id
            FROM properties
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $lockStmt->execute([
                'id' => $propertyId,
            ]);

            if (!$lockStmt->fetchColumn()) {
                throw new Exception(
                    'Propiedad no encontrada'
                );
            }

            $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
        ");

            $countStmt->execute([
                'property_id' => $propertyId,
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
                    'Podés tener hasta 5 imágenes por propiedad'
                );
            }

            $sortStmt = $pdo->prepare("
            SELECT COALESCE(
                MAX(sort_order),
                -1
            )
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
        ");

            $sortStmt->execute([
                'property_id' => $propertyId,
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
                        $propertyId
                    );

                $storedPaths[] = $relativePath;

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
        [, $ownedImage] =
            self::getOwnedImage(
                $userId,
                $imageId
            );

        $propertyId =
            (int)$ownedImage['property_id'];

        $pdo = self::db();
        $filePath = '';

        $pdo->beginTransaction();

        try {
            /*
         * Usamos el mismo orden de bloqueo que upload()
         * y reorder(): primero la propiedad y después
         * sus imágenes.
         */
            $stProperty = $pdo->prepare("
            SELECT status
            FROM properties
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $stProperty->execute([
                'id' => $propertyId,
            ]);

            $property =
                $stProperty->fetch();

            if (!$property) {
                throw new Exception(
                    'Propiedad no encontrada',
                    404
                );
            }

            if (
                !in_array(
                    $property['status'],
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
                    'No se pueden eliminar imágenes en el estado actual de la propiedad',
                    422
                );
            }

            /*
         * Volvemos a comprobar la imagen dentro
         * de la transacción y la bloqueamos.
         */
            $stImage = $pdo->prepare("
            SELECT file_path
            FROM property_images
            WHERE id = :id
              AND property_id = :property_id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $stImage->execute([
                'id' => $imageId,
                'property_id' => $propertyId,
            ]);

            $image =
                $stImage->fetch();

            if (!$image) {
                throw new Exception(
                    'Imagen no encontrada',
                    404
                );
            }

            $filePath =
                (string)($image['file_path'] ?? '');

            $stDelete = $pdo->prepare("
            UPDATE property_images
            SET deleted_at = NOW()
            WHERE id = :id
              AND property_id = :property_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

            $stDelete->execute([
                'id' => $imageId,
                'property_id' => $propertyId,
            ]);

            if ($stDelete->rowCount() !== 1) {
                throw new Exception(
                    'La imagen fue modificada por otra operación. Actualizá la página e intentá nuevamente.',
                    409
                );
            }

            self::ensureSingleCover(
                $propertyId
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        /*
     * El archivo físico solamente se elimina
     * después de confirmar la transacción.
     */
        if ($filePath !== '') {
            self::removeStoredFile(
                $filePath
            );
        }

        self::queueQualityRecalculation(
            $propertyId
        );

        return PropertyService::getDetail(
            $userId,
            $propertyId
        );
    }

    public static function reorder(
        int $userId,
        int $propertyId,
        mixed $images
    ): array {
        [, $property] =
            self::getOwnedPropertyRow(
                $userId,
                $propertyId
            );

        if (
            !in_array(
                $property['status'],
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
                'No se pueden reordenar imágenes en el estado actual de la propiedad'
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
            /*
         * Bloqueamos la propiedad para serializar cambios
         * simultáneos sobre sus imágenes.
         */
            $lockProperty = $pdo->prepare("
            SELECT id
            FROM properties
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $lockProperty->execute([
                'id' => $propertyId,
            ]);

            if (!$lockProperty->fetchColumn()) {
                throw new Exception(
                    'Propiedad no encontrada',
                    404
                );
            }

            $st = $pdo->prepare("
            SELECT id
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY id ASC
            FOR UPDATE
        ");

            $st->execute([
                'property_id' => $propertyId,
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
                    'Las imágenes enviadas no coinciden con las imágenes activas de la propiedad',
                    422
                );
            }

            $stUpdate = $pdo->prepare("
            UPDATE property_images
            SET
                sort_order = :sort_order,
                is_cover = :is_cover
            WHERE id = :id
              AND property_id = :property_id
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
                    'property_id' => $propertyId,
                ]);
            }

            $pdo->commit();

            self::queueQualityRecalculation(
                $propertyId
            );

            return PropertyService::getDetail(
                $userId,
                $propertyId
            );
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
