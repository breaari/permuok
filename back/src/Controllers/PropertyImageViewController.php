<?php

namespace App\Controllers;

use App\Services\ImageUrlSignatureService;
use PDO;

class PropertyImageViewController
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    private static function getUploadsDir(): string
    {
        $base =
            rtrim(
                (string)(
                    $_ENV['UPLOADS_DIR']
                    ?? ''
                ),
                '/\\'
            );

        if ($base === '') {
            $base =
                dirname(__DIR__, 2) .
                '/uploads';
        }

        return $base;
    }

    private static function notFound(): void
    {
        http_response_code(404);

        header(
            'Cache-Control: no-store'
        );

        exit;
    }

    public static function show(): void
    {
        $imageId = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($imageId === false) {
            self::notFound();
        }

        $imageId = (int)$imageId;

        $expires =
            $_GET['expires'] ?? null;

        $signature =
            $_GET['signature'] ?? null;

        if (
            !ImageUrlSignatureService::validate(
                'property',
                $imageId,
                $expires,
                $signature
            )
        ) {
            self::notFound();
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
        SELECT
            pi.id,
            pi.file_path
        FROM property_images pi

        INNER JOIN properties property
            ON property.id = pi.property_id
           AND property.deleted_at IS NULL

        WHERE pi.id = :id
          AND pi.deleted_at IS NULL

        LIMIT 1
    ");

        $stmt->execute([
            ':id' => $imageId,
        ]);

        $image =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$image ||
            empty($image['file_path'])
        ) {
            self::notFound();
        }

        $relativePath = ltrim(
            str_replace(
                '\\',
                '/',
                (string)$image['file_path']
            ),
            '/'
        );

        if (
            !str_starts_with(
                $relativePath,
                'properties/'
            )
        ) {
            self::notFound();
        }

        $uploadsDir = realpath(
            self::getUploadsDir()
        );

        if ($uploadsDir === false) {
            self::notFound();
        }

        $fullPath = realpath(
            $uploadsDir .
                DIRECTORY_SEPARATOR .
                $relativePath
        );

        if (
            $fullPath === false ||
            !is_file($fullPath) ||
            !is_readable($fullPath)
        ) {
            self::notFound();
        }

        $allowedPrefix =
            rtrim(
                $uploadsDir,
                DIRECTORY_SEPARATOR
            ) .
            DIRECTORY_SEPARATOR;

        if (
            !str_starts_with(
                $fullPath,
                $allowedPrefix
            )
        ) {
            self::notFound();
        }

        /*
     * Se comprueba nuevamente el contenido real
     * antes de entregarlo al navegador.
     */
        $finfo = new \finfo(
            FILEINFO_MIME_TYPE
        );

        $mime =
            $finfo->file($fullPath);

        if (
            !is_string($mime) ||
            !in_array(
                $mime,
                self::ALLOWED_MIME_TYPES,
                true
            )
        ) {
            self::notFound();
        }

        $imageInfo =
            @getimagesize($fullPath);

        if (
            $imageInfo === false ||
            ($imageInfo['mime'] ?? '') !== $mime
        ) {
            self::notFound();
        }

        $fileSize =
            filesize($fullPath);

        if (
            $fileSize === false ||
            $fileSize <= 0
        ) {
            self::notFound();
        }

        $expiresTimestamp =
            (int)$expires;

        $cacheSeconds = max(
            0,
            min(
                3600,
                $expiresTimestamp - time()
            )
        );

        header(
            'Content-Type: ' . $mime
        );

        header(
            'Content-Length: ' . $fileSize
        );

        header(
            'Content-Disposition: inline'
        );

        header(
            'Cache-Control: private, max-age=' .
                $cacheSeconds
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        header(
            "Content-Security-Policy: default-src 'none'; sandbox"
        );

        readfile($fullPath);

        exit;
    }
}
