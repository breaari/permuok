<?php

require_once __DIR__ . '/config.php';

function pdo(bool $forceReconnect = false): PDO
{
    static $pdo = null;

    if ($forceReconnect) {
        $pdo = null;
    }

    /*
     * Si ya existe conexión, verificamos que siga viva.
     *
     * Esto es importante para workers permanentes:
     * durante una llamada larga a OpenAI el servidor MySQL
     * puede cerrar la conexión por inactividad.
     */
    if ($pdo instanceof PDO) {
        try {
            $pdo->query('SELECT 1');

            return $pdo;
        } catch (PDOException $e) {
            /*
             * La conexión dejó de ser utilizable.
             * La descartamos y abrimos una nueva abajo.
             */
            $pdo = null;
        }
    }

    $host = env('DB_HOST');
    $port = env('DB_PORT', '3306');
    $db   = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASS');

    $dsn =
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE =>
            PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
            PDO::FETCH_ASSOC,

            PDO::ATTR_TIMEOUT =>
            10,
        ]
    );
    $pdo->exec("SET time_zone = '-03:00'");
    return $pdo;
}
