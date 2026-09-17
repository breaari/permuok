<?php

namespace App\Services;

use Exception;
use PDO;
use Throwable;

class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 30;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function request(
        string $email
    ): void {
        $email =
            strtolower(trim($email));

        if (
            $email === '' ||
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

        $pdo = self::db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                first_name,
                email,
                is_active
            FROM users
            WHERE email = :email
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email,
        ]);

        $user =
            $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * La respuesta pública será siempre igual.
         * Si el usuario no existe o está inactivo,
         * no generamos ningún correo.
         */
        if (
            !$user ||
            (int)$user['is_active'] !== 1
        ) {
            return;
        }

        $token =
            bin2hex(random_bytes(32));

        $tokenHash =
            hash('sha256', $token);

        $frontendUrl =
            rtrim(
                (string)(
                    $_ENV['FRONTEND_URL']
                    ?? 'https://permuok.com'
                ),
                '/'
            );

        $resetUrl =
            $frontendUrl .
            '/reset-password?token=' .
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
                $resetUrl,
                ENT_QUOTES,
                'UTF-8'
            );

        $htmlBody = "
            <div style=\"font-family:Arial,sans-serif;color:#0f172a;line-height:1.6\">
                <h2>Restablecé tu contraseña</h2>

                <p>{$safeName}, recibimos una solicitud para cambiar la contraseña de tu cuenta de PermuOK.</p>

                <p>
                    <a
                        href=\"{$safeUrl}\"
                        style=\"display:inline-block;padding:12px 20px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold\"
                    >
                        Crear nueva contraseña
                    </a>
                </p>

                <p>Este enlace vence en 30 minutos y puede utilizarse una sola vez.</p>

                <p>Si no realizaste esta solicitud, podés ignorar este mensaje.</p>
            </div>
        ";

        $textBody =
            "Restablecé tu contraseña de PermuOK:\n\n" .
            $resetUrl .
            "\n\nEl enlace vence en 30 minutos y puede utilizarse una sola vez.";

        $pdo->beginTransaction();

        try {
            /*
             * Invalidamos enlaces anteriores que todavía
             * no hayan sido utilizados.
             */
            $invalidate = $pdo->prepare("
                UPDATE password_reset_tokens
                SET used_at = NOW()
                WHERE user_id = :user_id
                  AND used_at IS NULL
            ");

            $invalidate->execute([
                'user_id' =>
                (int)$user['id'],
            ]);

            $insert = $pdo->prepare("
                INSERT INTO password_reset_tokens (
                    user_id,
                    token_hash,
                    expires_at
                ) VALUES (
                    :user_id,
                    :token_hash,
                    DATE_ADD(
                        NOW(),
                        INTERVAL 30 MINUTE
                    )
                )
            ");

            $insert->execute([
                'user_id' =>
                (int)$user['id'],

                'token_hash' =>
                $tokenHash,
            ]);

            $resetTokenId =
                (int)$pdo->lastInsertId();

            EmailJobService::enqueue(
                (string)$user['email'],
                'password_reset',
                'Restablecé tu contraseña de PermuOK',
                $htmlBody,
                $textBody,
                (int)$user['id'],
                $recipientName !== ''
                    ? $recipientName
                    : null,
                'password_reset',
                $resetTokenId,
                10,
                $pdo
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function reset(
        string $token,
        string $password
    ): void {
        $token = trim($token);

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

        $passwordLength =
            strlen($password);

        if (
            $passwordLength < 8 ||
            $passwordLength > 72
        ) {
            throw new Exception(
                'La contraseña debe tener entre 8 y 72 caracteres.',
                422
            );
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception(
                'La contraseña debe incluir al menos una mayúscula.',
                422
            );
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception(
                'La contraseña debe incluir al menos una minúscula.',
                422
            );
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception(
                'La contraseña debe incluir al menos un número.',
                422
            );
        }

        $tokenHash =
            hash('sha256', $token);

        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT
                    password_reset_tokens.id,
                    password_reset_tokens.user_id,
                    password_reset_tokens.expires_at,
                    password_reset_tokens.used_at,
                    users.is_active
                FROM password_reset_tokens

                INNER JOIN users
                    ON users.id =
                        password_reset_tokens.user_id
                   AND users.deleted_at IS NULL

                WHERE password_reset_tokens.token_hash =
                    :token_hash

                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                'token_hash' =>
                $tokenHash,
            ]);

            $resetToken =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$resetToken ||
                $resetToken['used_at'] !== null ||
                strtotime(
                    (string)$resetToken['expires_at']
                ) < time() ||
                (int)$resetToken['is_active'] !== 1
            ) {
                $pdo->rollBack();

                throw new Exception(
                    'El enlace es inválido o venció.',
                    400
                );
            }

            $userId =
                (int)$resetToken['user_id'];

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            if (!is_string($passwordHash)) {
                throw new Exception(
                    'No se pudo actualizar la contraseña.',
                    500
                );
            }

            $updateUser = $pdo->prepare("
                UPDATE users
                SET password_hash = :password_hash
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ");

            $updateUser->execute([
                'password_hash' =>
                $passwordHash,

                'id' =>
                $userId,
            ]);

            if ($updateUser->rowCount() !== 1) {
                throw new Exception(
                    'No se pudo actualizar la contraseña.',
                    500
                );
            }

            /*
             * Ningún enlace anterior del usuario
             * puede seguir utilizándose.
             */
            $consumeTokens = $pdo->prepare("
                UPDATE password_reset_tokens
                SET used_at = NOW()
                WHERE user_id = :user_id
                  AND used_at IS NULL
            ");

            $consumeTokens->execute([
                'user_id' => $userId,
            ]);

            /*
             * Cerramos todas las sesiones existentes.
             */
            $revokeSessions = $pdo->prepare("
                UPDATE refresh_tokens
                SET revoked_at = NOW()
                WHERE user_id = :user_id
                  AND revoked_at IS NULL
            ");

            $revokeSessions->execute([
                'user_id' => $userId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
