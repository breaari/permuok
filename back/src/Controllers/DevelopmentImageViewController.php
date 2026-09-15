<?php

namespace App\Controllers;

use App\Services\ImageUrlSignatureService;
use PDO;

class DevelopmentImageViewController
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
                'development',
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
                di.id,
                di.file_path

            FROM development_images di

            INNER JOIN developments development
                ON development.id =
                    di.development_id
               AND development.deleted_at IS NULL

            WHERE di.id = :id
              AND di.deleted_at IS NULL

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

        if (
            !str_starts_with(
                $relativePath,
                'developments/'
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
