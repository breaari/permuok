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
        $imageId =
            (int)($_GET['id'] ?? 0);

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

        $relativePath =
            ltrim(
                str_replace(
                    '\\',
                    '/',
                    (string)$image['file_path']
                ),
                '/'
            );

        /*
         * Una imagen de propiedad solamente puede
         * apuntar a su carpeta correspondiente.
         */
        if (
            !str_starts_with(
                $relativePath,
                'properties/'
            )
        ) {
            self::notFound();
        }

        $uploadsDir =
            realpath(
                self::getUploadsDir()
            );

        if ($uploadsDir === false) {
            self::notFound();
        }

        $fullPath =
            realpath(
                $uploadsDir .
                    DIRECTORY_SEPARATOR .
                    $relativePath
            );

        if (
            $fullPath === false ||
            !is_file($fullPath)
        ) {
            self::notFound();
        }

        /*
         * Evita que una ruta almacenada pueda
         * salir del directorio uploads.
         */
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

        $mime =
            mime_content_type($fullPath);

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
            'Content-Length: ' .
                filesize($fullPath)
        );

        header(
            'Cache-Control: private, max-age=' .
                $cacheSeconds
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        header(
            "Content-Security-Policy: default-src 'none'"
        );

        readfile($fullPath);

        exit;
    }
}
