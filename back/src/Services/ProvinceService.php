<?php

namespace App\Services;

use PDO;

final class ProvinceService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';
        return pdo();
    }

    public static function listActive(): array
    {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT id, code, name
            FROM provinces
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ");
        $st->execute();

        return $st->fetchAll() ?: [];
    }
}