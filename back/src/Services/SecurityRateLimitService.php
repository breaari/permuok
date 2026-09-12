<?php

namespace App\Services;

use App\Helpers\ResponseHelper;
use PDO;

class SecurityRateLimitService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    /**
     * Registra un intento y detiene la solicitud
     * cuando se supera el límite configurado.
     */
    public static function hit(
        string $action,
        string $identifier,
        int $maxAttempts,
        int $windowSeconds
    ): void {
        $action = trim($action);
        $identifier = trim($identifier);

        if (
            $action === '' ||
            $identifier === '' ||
            $maxAttempts <= 0 ||
            $windowSeconds <= 0
        ) {
            throw new \InvalidArgumentException(
                'Configuración de rate limit inválida.'
            );
        }

        $pdo = self::db();

        $identifierHash = hash(
            'sha256',
            $identifier
        );

        $windowCutoff = date(
            'Y-m-d H:i:s',
            time() - $windowSeconds
        );

        /*
         * La actualización es atómica.
         *
         * Si dos solicitudes llegan simultáneamente,
         * MySQL incrementa correctamente el contador.
         */
        $stmt = $pdo->prepare("
            INSERT INTO security_rate_limits (
                action,
                identifier_hash,
                window_started_at,
                attempts
            )
            VALUES (
                :action,
                :identifier_hash,
                NOW(),
                1
            )
            ON DUPLICATE KEY UPDATE
                attempts = CASE
                    WHEN window_started_at < :window_cutoff
                        THEN 1
                    ELSE attempts + 1
                END,

                window_started_at = CASE
                    WHEN window_started_at < :window_cutoff_reset
                        THEN NOW()
                    ELSE window_started_at
                END,

                updated_at = NOW()
        ");

        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => $identifierHash,
            ':window_cutoff' => $windowCutoff,
            ':window_cutoff_reset' => $windowCutoff,
        ]);

        $stmt = $pdo->prepare("
            SELECT
                attempts,
                window_started_at
            FROM security_rate_limits
            WHERE action = :action
              AND identifier_hash = :identifier_hash
            LIMIT 1
        ");

        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => $identifierHash,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return;
        }

        $attempts = (int)$row['attempts'];

        if ($attempts <= $maxAttempts) {
            return;
        }

        $windowStartedAt =
            strtotime((string)$row['window_started_at'])
            ?: time();

        $retryAfter = max(
            1,
            ($windowStartedAt + $windowSeconds) - time()
        );

        header(
            'Retry-After: ' . $retryAfter
        );

        ResponseHelper::fail(
            'Demasiados intentos. Esperá unos minutos y volvé a intentar.',
            429
        );
    }

    /**
     * Reinicia un contador, por ejemplo después
     * de un inicio de sesión correcto.
     */
    public static function clear(
        string $action,
        string $identifier
    ): void {
        $action = trim($action);
        $identifier = trim($identifier);

        if ($action === '' || $identifier === '') {
            return;
        }

        $pdo = self::db();

        $stmt = $pdo->prepare("
            DELETE FROM security_rate_limits
            WHERE action = :action
              AND identifier_hash = :identifier_hash
        ");

        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => hash(
                'sha256',
                $identifier
            ),
        ]);
    }

    /**
     * Devuelve la IP observada directamente por PHP.
     *
     * No confiamos automáticamente en
     * X-Forwarded-For porque el cliente puede falsificarlo
     * si el servidor no está detrás de un proxy configurado.
     */
    public static function clientIp(): string
    {
        $ip = trim(
            (string)(
                $_SERVER['REMOTE_ADDR']
                ?? 'unknown'
            )
        );

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP
        )
            ? $ip
            : 'unknown';
    }

    /**
     * Permite eliminar registros viejos ocasionalmente.
     */
    public static function cleanup(
        int $olderThanSeconds = 172800
    ): void {
        $olderThanSeconds = max(
            3600,
            $olderThanSeconds
        );

        $cutoff = date(
            'Y-m-d H:i:s',
            time() - $olderThanSeconds
        );

        $pdo = self::db();

        $stmt = $pdo->prepare("
            DELETE FROM security_rate_limits
            WHERE updated_at < :cutoff
        ");

        $stmt->execute([
            ':cutoff' => $cutoff,
        ]);
    }
}