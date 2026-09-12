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
    ): array {
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
                WHEN window_started_at <
                    DATE_SUB(
                        NOW(),
                        INTERVAL {$windowSeconds} SECOND
                    )
                    THEN 1
                ELSE attempts + 1
            END,

            window_started_at = CASE
                WHEN window_started_at <
                    DATE_SUB(
                        NOW(),
                        INTERVAL {$windowSeconds} SECOND
                    )
                    THEN NOW()
                ELSE window_started_at
            END,

            updated_at = NOW()
    ");

        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => $identifierHash,
        ]);

        $stmt = $pdo->prepare("
    SELECT
        attempts,
        window_started_at,
        GREATEST(
            1,
            TIMESTAMPDIFF(
                SECOND,
                NOW(),
                DATE_ADD(
                    window_started_at,
                    INTERVAL {$windowSeconds} SECOND
                )
            )
        ) AS retry_after
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
            return [
                'attempts' => 0,
                'remaining' => $maxAttempts,
                'retry_after' => 0,
            ];
        }

        $attempts = (int)$row['attempts'];

        $remaining = max(
            0,
            $maxAttempts - $attempts
        );

        $retryAfter = max(
            1,
            (int)($row['retry_after'] ?? $windowSeconds)
        );

        header(
            'X-RateLimit-Limit: ' . $maxAttempts
        );

        header(
            'X-RateLimit-Remaining: ' . $remaining
        );

        if ($attempts >= $maxAttempts) {
            header(
                'Retry-After: ' . $retryAfter
            );

            ResponseHelper::fail(
                'Por seguridad, pausamos temporalmente los intentos de inicio de sesión. Podrás volver a probar en ' .
                    $retryAfter .
                    ' segundos.',
                429,
                [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'retry_after' => $retryAfter,
                    'remaining_attempts' => 0,
                ]
            );
        }

        return [
            'attempts' => $attempts,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
        ];
    }

    /**
 * Comprueba si un identificador tiene un bloqueo activo.
 *
 * No incrementa intentos.
 */
public static function check(
    string $action,
    string $identifier
): void {
    $action = trim($action);
    $identifier = trim($identifier);

    if ($action === '' || $identifier === '') {
        throw new \InvalidArgumentException(
            'Configuración de rate limit inválida.'
        );
    }

    $pdo = self::db();

    $identifierHash = hash(
        'sha256',
        $identifier
    );

    $stmt = $pdo->prepare("
        SELECT
            GREATEST(
                0,
                TIMESTAMPDIFF(
                    SECOND,
                    NOW(),
                    blocked_until
                )
            ) AS retry_after
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

    $retryAfter = (int)($row['retry_after'] ?? 0);

    if ($retryAfter <= 0) {
        return;
    }

    header(
        'Retry-After: ' . $retryAfter
    );

    ResponseHelper::fail(
        'Alcanzaste el límite de intentos. Esperá un momento para volver a probar.',
        429,
        [
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $retryAfter,
            'remaining_attempts' => 0,
        ]
    );
}

/**
 * Registra exclusivamente un intento fallido.
 *
 * Los bloqueos progresivos son:
 * 1.º: 30 segundos
 * 2.º: 5 minutos
 * 3.º y siguientes: 15 minutos
 */
public static function recordFailure(
    string $action,
    string $identifier,
    int $maxAttempts = 5,
    array $penalties = [30, 300, 900]
): array {
    $action = trim($action);
    $identifier = trim($identifier);

    $penalties = array_values(
        array_filter(
            array_map('intval', $penalties),
            static fn (int $seconds): bool =>
                $seconds > 0
        )
    );

    if (
        $action === '' ||
        $identifier === '' ||
        $maxAttempts <= 0 ||
        $penalties === []
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

    /*
     * Si dos intentos llegan al mismo tiempo,
     * la transacción mantiene consistente el contador.
     */
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            SELECT
                attempts,
                penalty_level,
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        blocked_until
                    )
                ) AS retry_after
            FROM security_rate_limits
            WHERE action = :action
              AND identifier_hash = :identifier_hash
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => $identifierHash,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * Primer error para este identificador.
         */
        if (!$row) {
            $stmt = $pdo->prepare("
                INSERT INTO security_rate_limits (
                    action,
                    identifier_hash,
                    window_started_at,
                    attempts,
                    blocked_until,
                    penalty_level
                )
                VALUES (
                    :action,
                    :identifier_hash,
                    NOW(),
                    1,
                    NULL,
                    0
                )
            ");

            $stmt->execute([
                ':action' => $action,
                ':identifier_hash' => $identifierHash,
            ]);

            $pdo->commit();

            return [
                'attempts' => 1,
                'remaining' => max(
                    0,
                    $maxAttempts - 1
                ),
                'retry_after' => 0,
            ];
        }

        /*
         * Defensa adicional por si otro pedido alcanzó
         * el bloqueo mientras este estaba esperando.
         */
        $activeRetryAfter =
            (int)($row['retry_after'] ?? 0);

        if ($activeRetryAfter > 0) {
            $pdo->commit();

            header(
                'Retry-After: ' . $activeRetryAfter
            );

            ResponseHelper::fail(
                'Alcanzaste el límite de intentos. Esperá un momento para volver a probar.',
                429,
                [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'retry_after' => $activeRetryAfter,
                    'remaining_attempts' => 0,
                ]
            );
        }

        $attempts =
            (int)$row['attempts'] + 1;

        /*
         * Todavía puede volver a intentarlo.
         */
        if ($attempts < $maxAttempts) {
            $stmt = $pdo->prepare("
                UPDATE security_rate_limits
                SET
                    attempts = :attempts,
                    blocked_until = NULL,
                    updated_at = NOW()
                WHERE action = :action
                  AND identifier_hash = :identifier_hash
            ");

            $stmt->execute([
                ':attempts' => $attempts,
                ':action' => $action,
                ':identifier_hash' => $identifierHash,
            ]);

            $pdo->commit();

            return [
                'attempts' => $attempts,
                'remaining' => max(
                    0,
                    $maxAttempts - $attempts
                ),
                'retry_after' => 0,
            ];
        }

        /*
         * Alcanzó el límite.
         */
        $currentPenaltyLevel =
            (int)$row['penalty_level'];

        $newPenaltyLevel = min(
            $currentPenaltyLevel + 1,
            count($penalties)
        );

        $penaltySeconds =
            $penalties[$newPenaltyLevel - 1];

        $stmt = $pdo->prepare("
            UPDATE security_rate_limits
            SET
                attempts = 0,
                blocked_until = DATE_ADD(
                    NOW(),
                    INTERVAL {$penaltySeconds} SECOND
                ),
                penalty_level = :penalty_level,
                window_started_at = NOW(),
                updated_at = NOW()
            WHERE action = :action
              AND identifier_hash = :identifier_hash
        ");

        $stmt->execute([
            ':penalty_level' => $newPenaltyLevel,
            ':action' => $action,
            ':identifier_hash' => $identifierHash,
        ]);

        $pdo->commit();

        header(
            'Retry-After: ' . $penaltySeconds
        );

        ResponseHelper::fail(
            'Alcanzaste el límite de intentos. Esperá un momento para volver a probar.',
            429,
            [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $penaltySeconds,
                'remaining_attempts' => 0,
                'penalty_level' => $newPenaltyLevel,
            ]
        );
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
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
