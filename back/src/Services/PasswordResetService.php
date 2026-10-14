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
            strtolower(
                trim($email)
            );

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

        $pdo =
            self::db();

        $stmt = $pdo->prepare("
        SELECT
            id,
            first_name,
            last_name,
            email,
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
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

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
            bin2hex(
                random_bytes(32)
            );

        $tokenHash =
            hash(
                'sha256',
                $token
            );

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
                ) .
                    ' ' .
                    (string)(
                        $user['last_name']
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

        /*
    |--------------------------------------------------------------------------
    | HTML
    |--------------------------------------------------------------------------
    */
        $htmlBody = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html
    xmlns="http://www.w3.org/1999/xhtml"
    xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office"
>
<head>
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    />

    <meta
        http-equiv="Content-Type"
        content="text/html; charset=UTF-8"
    />

    <meta
        name="format-detection"
        content="telephone=no, date=no, address=no, email=no"
    />

    <meta
        name="x-apple-disable-message-reformatting"
    />

    <style>
        body {
            margin:0;
            padding:0;
        }

        table {
            mso-table-lspace:0;
            mso-table-rspace:0;
        }

        p,
        span,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            margin:0;
            padding:0;
        }

        a[x-apple-data-detectors] {
            color:inherit !important;
            text-decoration:inherit !important;
        }

        #MessageViewBody a {
            color:inherit;
            text-decoration:none;
        }

        img + div {
            display:none;
        }

        @media (max-width:599px) {
            .email-container {
                width:100% !important;
                min-width:0 !important;
            }

            .email-content {
                padding-left:28px !important;
                padding-right:28px !important;
            }

            .email-title {
                font-size:18px !important;
            }
        }
    </style>

    <!--[if mso]>
    <noscript>
        <xml>
            <w:WordDocument
                xmlns:w="urn:schemas-microsoft-com:office:word"
            >
                <w:DontUseAdvancedTypographyReadingMail/>
            </w:WordDocument>

            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>

<body
    style="
        width:100%;
        -webkit-text-size-adjust:100%;
        text-size-adjust:100%;
        background-color:#f0f1f5;
        margin:0;
        padding:0;
    "
>
    <table
        width="100%"
        border="0"
        cellpadding="0"
        cellspacing="0"
        bgcolor="#f0f1f5"
        role="presentation"
        style="
            background-color:#f0f1f5;
        "
    >
        <tbody>
            <tr>
                <td
                    align="center"
                    style="
                        background-color:#f0f1f5;
                        padding:24px 12px;
                    "
                >

                    <!--[if mso]>
                    <table
                        align="center"
                        border="0"
                        cellpadding="0"
                        cellspacing="0"
                        width="600"
                    >
                        <tbody>
                            <tr>
                                <td>
                    <![endif]-->

                    <table
                        align="center"
                        width="600"
                        border="0"
                        cellpadding="0"
                        cellspacing="0"
                        role="presentation"
                        class="email-container"
                        style="
                            max-width:600px;
                            width:600px;
                            min-width:600px;
                            margin:0 auto;
                            background-color:#ffffff;
                            border-radius:15px;
                            overflow:hidden;
                        "
                    >
                        <tbody>

                            <!-- LOGO -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:41px 24px 34px;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/5c81845de64595f7c957ccdbef7b99c0.png"
                                        width="234"
                                        alt="Permuok"
                                        style="
                                            display:block;
                                            width:234px;
                                            height:auto;
                                            max-width:100%;
                                            border:0;
                                        "
                                    />
                                </td>
                            </tr>

                            <!-- TEXTO PRINCIPAL -->
                            <tr>
                                <td
                                    class="email-content"
                                    style="
                                        padding:0 56px 28px;
                                        color:#0e1b10;
                                        font-family:Arial, Helvetica, sans-serif;
                                        text-align:left;
                                    "
                                >
                                    <div
                                        class="email-title"
                                        style="
                                            font-size:20px;
                                            font-weight:700;
                                            line-height:1;
                                            margin-bottom:20px;
                                        "
                                    >
                                        Restablecé tu contraseña
                                    </div>

                                    <div
                                        style="
                                            font-size:16px;
                                            line-height:1;
                                        "
                                    >
                                        {$safeName}, recibimos una solicitud para crear una nueva contraseña para tu cuenta de Permuok.
                                    </div>
                                </td>
                            </tr>

                            <!-- BOTÓN -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0 24px 34px;
                                    "
                                >

                                    <!--[if mso]>
                                    <v:roundrect
                                        xmlns:v="urn:schemas-microsoft-com:vml"
                                        xmlns:w="urn:schemas-microsoft-com:office:word"
                                        href="{$safeUrl}"
                                        style="
                                            height:64px;
                                            width:336px;
                                            v-text-anchor:middle;
                                        "
                                        arcsize="23%"
                                        fillcolor="#61b228"
                                        strokecolor="#61b228"
                                    >
                                        <w:anchorlock/>

                                        <center
                                            style="
                                                color:#ffffff;
                                                font-family:Arial,sans-serif;
                                                font-size:18px;
                                                font-weight:700;
                                            "
                                        >
                                            Crear nueva contraseña
                                        </center>
                                    </v:roundrect>
                                    <![endif]-->

                                    <!--[if !mso]><!-->
                                    <table
                                        role="presentation"
                                        cellpadding="0"
                                        cellspacing="0"
                                        border="0"
                                        width="336"
                                        style="
                                            width:336px;
                                            max-width:100%;
                                            border-collapse:separate;
                                            border-spacing:0;
                                        "
                                    >
                                        <tbody>
                                            <tr>
                                                <td
                                                    bgcolor="#61b228"
                                                    style="
                                                        background-color:#61b228;
                                                        border-radius:15px;
                                                    "
                                                >
                                                    <a
                                                        href="{$safeUrl}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        style="
                                                            color:#ffffff;
                                                            text-decoration:none;
                                                            display:block;
                                                            padding:19px 8px;
                                                            text-align:center;
                                                            font-family:Arial, Helvetica, sans-serif;
                                                            font-size:18px;
                                                            font-weight:700;
                                                            line-height:26px;
                                                            box-sizing:border-box;
                                                        "
                                                    >
                                                        Crear nueva contraseña
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <!--<![endif]-->

                                </td>
                            </tr>

                            <!-- AVISO -->
                            <tr>
                                <td
                                    class="email-content"
                                    style="
                                        padding:0 56px 38px;
                                        color:#0e1b10;
                                        font-family:Arial, Helvetica, sans-serif;
                                        font-size:13px;
                                        line-height:1;
                                        text-align:left;
                                    "
                                >
                                    Por seguridad, este enlace vence en 30 minutos y puede utilizarse una sola vez.
                                    <br /><br />

                                    Si no solicitaste este cambio, podés ignorar este correo.
                                    <br />
                                    Tu contraseña actual seguirá siendo la misma.
                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0;
                                        border-radius:0 0 15px 15px;
                                        overflow:hidden;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/501d4de26b08500ce5411130a1f950d2.png"
                                        width="600"
                                        alt="Estamos para acompañarte"
                                        style="
                                            display:block;
                                            width:600px;
                                            height:auto;
                                            max-width:100%;
                                            border:0;
                                            border-radius:0 0 15px 15px;
                                        "
                                    />
                                </td>
                            </tr>

                        </tbody>
                    </table>

                    <!--[if mso]>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <![endif]-->

                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
HTML;

        /*
    |--------------------------------------------------------------------------
    | Texto plano
    |--------------------------------------------------------------------------
    */
        $textBody =
            "Restablecé tu contraseña\n\n" .
            (
                $recipientName !== ''
                ? $recipientName . ", "
                : ''
            ) .
            "recibimos una solicitud para crear una nueva contraseña para tu cuenta de Permuok.\n\n" .
            "Crear nueva contraseña:\n" .
            $resetUrl .
            "\n\n" .
            "Por seguridad, este enlace vence en 30 minutos y puede utilizarse una sola vez.\n\n" .
            "Si no solicitaste este cambio, podés ignorar este correo. Tu contraseña actual seguirá siendo la misma.";

        $pdo->beginTransaction();

        try {
            /*
         * Serializamos las solicitudes de recuperación
         * de una misma cuenta.
         */
            $lockUser = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = :user_id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");

            $lockUser->execute([
                'user_id' =>
                (int)$user['id'],
            ]);

            if (
                !$lockUser->fetchColumn()
            ) {
                $pdo->rollBack();

                return;
            }

            /*
         * Invalidamos enlaces anteriores.
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
            )
            VALUES (
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
                'Restablecé tu contraseña',
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
            if (
                $pdo->inTransaction()
            ) {
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

        $passwordError =
            PasswordPolicyService::validationError(
                $password
            );

        if ($passwordError !== null) {
            throw new Exception(
                $passwordError,
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
                    users.is_active,
users.email,
users.first_name
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
            /*
 * El cambio ya fue confirmado.
 * Si el aviso por correo falla, no revertimos
 * la contraseña ni mostramos un error falso.
 */
            try {
                $recipientName =
                    trim(
                        (string)(
                            $resetToken['first_name']
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

                $htmlBody = "
        <div style=\"font-family:Arial,sans-serif;color:#0f172a;line-height:1\">
            <h2>Tu contraseña fue actualizada</h2>

            <p>{$safeName}, la contraseña de tu cuenta de PermuOK se cambió correctamente.</p>

            <p>Por seguridad, cerramos todas las sesiones que estaban abiertas.</p>

            <p>Si realizaste este cambio, no necesitás hacer nada más.</p>

            <p><strong>Si no fuiste vos, solicitá inmediatamente un nuevo restablecimiento de contraseña y contactá al administrador de tu inmobiliaria.</strong></p>
        </div>
    ";

                $textBody =
                    "Tu contraseña de PermuOK fue actualizada correctamente.\n\n" .
                    "Por seguridad, cerramos todas las sesiones abiertas.\n\n" .
                    "Si no realizaste este cambio, solicitá inmediatamente un nuevo restablecimiento y contactá al administrador de tu inmobiliaria.";

                EmailJobService::enqueue(
                    (string)$resetToken['email'],
                    'password_changed',
                    'Tu contraseña de PermuOK fue actualizada',
                    $htmlBody,
                    $textBody,
                    $userId,
                    $recipientName !== ''
                        ? $recipientName
                        : null,
                    'user',
                    $userId,
                    10
                );
            } catch (Throwable $emailError) {
                error_log(
                    '[PASSWORD CHANGED EMAIL] ' .
                        $emailError->getMessage()
                );
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
