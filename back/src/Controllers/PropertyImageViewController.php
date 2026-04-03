<?php

namespace App\Controllers;

use PDO;

class PropertyImageViewController
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    private static function getUploadsDir(): string
    {
        $base = rtrim((string)($_ENV['UPLOADS_DIR'] ?? ''), '/');

        if ($base === '') {
            throw new \Exception("UPLOADS_DIR no configurado");
        }

        return $base;
    }

    public static function show(): void
    {
        $imageId = (int)($_GET['id'] ?? 0);

        if ($imageId <= 0) {
            http_response_code(404);
            exit;
        }

        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, file_path
            FROM property_images
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute(['id' => $imageId]);
        $image = $st->fetch();

        if (!$image || empty($image['file_path'])) {
            http_response_code(404);
            exit;
        }

        $fullPath = self::getUploadsDir() . '/' . ltrim($image['file_path'], '/');

        if (!is_file($fullPath)) {
            http_response_code(404);
            exit;
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: public, max-age=86400');

        readfile($fullPath);
        exit;
    }
}