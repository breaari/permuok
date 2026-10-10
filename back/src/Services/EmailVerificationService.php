<?php

namespace App\Services;

use Exception;
use PDO;
use Throwable;

class EmailVerificationService
{
    private const TOKEN_TTL_HOURS = 24;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    /**
     * Genera un nuevo token de verificación
     * y encola el correo correspondiente.
     *
     * Puede recibir una conexión PDO existente
     * para formar parte de otra transacción,
     * por ejemplo durante el registro.
     */
    public static function issueForUser(
        int $userId,
        ?PDO $pdo = null
    ): void {
        if ($userId <= 0) {
            throw new Exception(
                'Usuario inválido.',
                422
            );
        }

        $pdo ??= self::db();

        $ownsTransaction =
            !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            /*
             * Bloqueamos el usuario para evitar que
             * dos solicitudes simultáneas generen
             * varios enlaces válidos.
             */
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    first_name,
                    email,
                    email_verified_at,
                    is_active
                FROM users
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                'id' => $userId,
            ]);

            $user =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception(
                    'Usuario no encontrado.',
                    404
                );
            }

            if ((int)$user['is_active'] !== 1) {
                throw new Exception(
                    'La cuenta no se encuentra habilitada.',
                    403
                );
            }

            /*
             * Si ya está verificado no generamos
             * nuevos tokens ni emails.
             */
            if (
                $user['email_verified_at'] !== null
            ) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }

                return;
            }

            $email =
                strtolower(
                    trim(
                        (string)$user['email']
                    )
                );

            if (
                $email === '' ||
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new Exception(
                    'El usuario no tiene un email válido.',
                    422
                );
            }

            /*
             * Invalidamos cualquier enlace anterior
             * que todavía pudiera utilizarse.
             */
            $invalidate = $pdo->prepare("
                UPDATE email_verification_tokens
                SET used_at = NOW()
                WHERE user_id = :user_id
                  AND used_at IS NULL
            ");

            $invalidate->execute([
                'user_id' => $userId,
            ]);

            $token =
                bin2hex(
                    random_bytes(32)
                );

            $tokenHash =
                hash(
                    'sha256',
                    $token
                );

            $insert = $pdo->prepare("
                INSERT INTO email_verification_tokens (
                    user_id,
                    token_hash,
                    expires_at
                ) VALUES (
                    :user_id,
                    :token_hash,
                    DATE_ADD(
                        NOW(),
                        INTERVAL 24 HOUR
                    )
                )
            ");

            $insert->execute([
                'user_id' =>
                $userId,

                'token_hash' =>
                $tokenHash,
            ]);

            $verificationTokenId =
                (int)$pdo->lastInsertId();

            $frontendUrl =
                rtrim(
                    (string)(
                        $_ENV['FRONTEND_URL']
                        ?? 'https://permuok.com'
                    ),
                    '/'
                );

            $verificationUrl =
                $frontendUrl .
                '/verify-email?token=' .
                rawurlencode($token);

            $recipientName =
                trim(
                    (string)(
                        $user['first_name']
                        ?? ''
                    )
                );

            $safeName =
                htmlspecialchars(
                    $recipientName !== ''
                        ? $recipientName
                        : 'Hola',
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeUrl =
                htmlspecialchars(
                    $verificationUrl,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $htmlBody = "
                <div style=\"font-family:Arial,sans-serif;color:#30363d;line-height:1.6\">
                    <h2>Verificá tu email</h2>

                    <p>
                        {$safeName}, gracias por registrarte en Permuok.
                    </p>

                    <p>
                        Para confirmar que esta dirección de correo te pertenece,
                        verificá tu email desde el siguiente botón.
                    </p>

                    <p>
                        <a
                            href=\"{$safeUrl}\"
                            style=\"display:inline-block;padding:12px 20px;background:#76bc21;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold\"
                        >
                            Verificar mi email
                        </a>
                    </p>

                    <p>
                        Este enlace vence en 24 horas y puede utilizarse una sola vez.
                    </p>

                    <p>
                        Si no creaste una cuenta en Permuok, podés ignorar este mensaje.
                    </p>
                </div>
            ";

            $textBody =
                "Verificá tu email en Permuok\n\n" .
                $verificationUrl .
                "\n\n" .
                "Este enlace vence en 24 horas y puede utilizarse una sola vez.";

            EmailJobService::enqueue(
                $email,
                'email_verification',
                'Verificá tu email en Permuok',
                $htmlBody,
                $textBody,
                $userId,
                $recipientName !== ''
                    ? $recipientName
                    : null,
                'email_verification',
                $verificationTokenId,
                10,
                $pdo
            );

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if (
                $ownsTransaction &&
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Verifica y consume un token.
     */
    public static function verify(
        string $token
    ): array {
        $token =
            trim($token);

        if (
            $token === '' ||
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $token
            )
        ) {
            throw new Exception(
                'El enlace es inválido o venció.',
                400
            );
        }

        $tokenHash =
            hash(
                'sha256',
                $token
            );

        $pdo =
            self::db();

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT
                    evt.id,
                    evt.user_id,
                    evt.expires_at,
                    evt.used_at,

                    u.email,
                    u.email_verified_at,
                    u.is_active

                FROM email_verification_tokens evt

                INNER JOIN users u
                    ON u.id = evt.user_id
                   AND u.deleted_at IS NULL

                WHERE evt.token_hash = :token_hash

                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                'token_hash' =>
                $tokenHash,
            ]);

            $verificationToken =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verificationToken) {
                $pdo->rollBack();

                throw new Exception(
                    'El enlace es inválido o venció.',
                    400
                );
            }

            if (
                $verificationToken['used_at'] !== null ||
                strtotime(
                    (string)$verificationToken['expires_at']
                ) < time()
            ) {
                $pdo->rollBack();

                throw new Exception(
                    'El enlace es inválido o venció.',
                    400
                );
            }

            if (
                (int)$verificationToken['is_active']
                !== 1
            ) {
                $pdo->rollBack();

                throw new Exception(
                    'La cuenta no se encuentra habilitada.',
                    403
                );
            }

            $userId =
                (int)$verificationToken['user_id'];

            /*
             * Si todavía no estaba verificado,
             * marcamos el momento de validación.
             */
            if (
                $verificationToken['email_verified_at']
                === null
            ) {
                $updateUser = $pdo->prepare("
                    UPDATE users
                    SET email_verified_at = NOW()
                    WHERE id = :id
                      AND deleted_at IS NULL
                    LIMIT 1
                ");

                $updateUser->execute([
                    'id' =>
                    $userId,
                ]);

                if (
                    $updateUser->rowCount() !== 1
                ) {
                    throw new Exception(
                        'No se pudo verificar el email.',
                        500
                    );
                }
            }

            /*
             * Consumimos todos los tokens pendientes
             * del usuario para que ningún enlace
             * anterior pueda utilizarse después.
             */
            $consumeTokens = $pdo->prepare("
                UPDATE email_verification_tokens
                SET used_at = NOW()
                WHERE user_id = :user_id
                  AND used_at IS NULL
            ");

            $consumeTokens->execute([
                'user_id' =>
                $userId,
            ]);

            $pdo->commit();

            return [
                'user_id' =>
                $userId,

                'email' =>
                (string)$verificationToken['email'],

                'verified' =>
                true,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Solicita un nuevo enlace para una dirección
     * todavía no verificada.
     *
     * La respuesta pública debe ser genérica
     * para no revelar qué emails existen.
     */
    public static function resend(
        string $email
    ): void {
        $email =
            strtolower(
                trim($email)
            );

        if (
            $email === '' ||
            strlen($email) > 254 ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new Exception(
                'Ingresá un email válido.',
                422
            );
        }

        $pdo =
            self::db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                email_verified_at,
                is_active
            FROM users
            WHERE email = :email
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'email' =>
            $email,
        ]);

        $user =
            $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * No revelamos si el email existe,
         * está inactivo o ya fue verificado.
         */
        if (
            !$user ||
            (int)$user['is_active'] !== 1 ||
            $user['email_verified_at'] !== null
        ) {
            return;
        }

        self::issueForUser(
            (int)$user['id']
        );
    }
}
