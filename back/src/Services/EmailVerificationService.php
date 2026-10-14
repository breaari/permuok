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

            if ((int) $user['is_active'] !== 1) {
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
                        (string) $user['email']
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
                (int) $pdo->lastInsertId();

            $frontendUrl =
                rtrim(
                    (string) (
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
                    (string) (
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

            /*
             * Plantilla diseñada en Canva.
             * Se eliminan scripts y recursos innecesarios
             * para mejorar compatibilidad con clientes de email.
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
            margin: 0;
            padding: 0;
        }

        table {
            mso-table-lspace: 0;
            mso-table-rspace: 0;
        }

        p,
        span,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            margin: 0;
            padding: 0;
        }

        p {
            line-height: inherit;
        }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: inherit !important;
        }

        #MessageViewBody a {
            color: inherit;
            text-decoration: none;
        }

        img + div {
            display: none;
        }

        @media (max-width: 599px) {
            .email-container {
                width: 100% !important;
                min-width: 0 !important;
            }

            .email-content {
                padding-left: 28px !important;
                padding-right: 28px !important;
            }

            .email-title {
                font-size: 20px !important;
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
        style="background-color:#f0f1f5;"
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
                            border-radius:16px;
                            overflow:hidden;
                        "
                    >
                        <tbody>

                            <!-- LOGO -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:38px 24px 26px 24px;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/e889ee007df2bd5865b81eaa0d7d9445.png"
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

                            <!-- HERO -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0 24px 16px 24px;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/597264010270a1ad652f2d4cdced9acd.png"
                                        width="552"
                                        alt="Confirmá tu email"
                                        style="
                                            display:block;
                                            width:552px;
                                            height:auto;
                                            max-width:100%;
                                            border:0;
                                            border-radius:12px;
                                        "
                                    />
                                </td>
                            </tr>

                            <!-- CONTENIDO -->
                            <tr>
                                <td
                                    class="email-content"
                                    style="
                                        padding:26px 56px 16px 56px;
                                        font-family:Arial, Helvetica, sans-serif;
                                        color:#30363d;
                                        text-align:left;
                                    "
                                >
                                    <div
                                        class="email-title"
                                        style="
                                            font-size:24px;
                                            font-weight:700;
                                            line-height:1;
                                            color:#30363d;
                                            margin-bottom:28px;
                                        "
                                    >
                                        Hola {$safeName},
                                    </div>

                                    <div
                                        style="
                                            font-size:16px;
                                            line-height:1;
                                            color:#30363d;
                                        "
                                    >
                                        Necesitamos validar esta dirección de correo para
                                        <br />
                                        activar tu cuenta en Permuok.
                                    </div>
                                </td>
                            </tr>

                            <!-- BOTÓN -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:16px 24px 26px 24px;
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
                                            Verificar mi email
                                        </center>
                                    </v:roundrect>
                                    <![endif]-->

                                    <!--[if !mso]><!-->
                                    <table
                                        cellpadding="0"
                                        cellspacing="0"
                                        border="0"
                                        role="presentation"
                                        style="
                                            width:100%;
                                            max-width:336px;
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
                                                        Verificar mi email
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <!--<![endif]-->

                                </td>
                            </tr>

                            <!-- ACLARACIONES -->
                            <tr>
                                <td
                                    class="email-content"
                                    style="
                                        padding:4px 56px 30px 56px;
                                        font-family:Arial, Helvetica, sans-serif;
                                        font-size:13px;
                                        line-height:1;
                                        color:#30363d;
                                        text-align:left;
                                    "
                                >
                                    Este enlace vence en 24 horas y puede usarse una sola vez.

                                    <br />

                                    <strong>
                                        ¿No fuiste vos?
                                    </strong>

                                    Si no creaste esta cuenta, podés ignorar este mensaje.
                                </td>
                            </tr>

                            <!-- LINK DE RESPALDO -->
                            <tr>
                                <td
                                    class="email-content"
                                    style="
                                        padding:0 56px 30px 56px;
                                        font-family:Arial, Helvetica, sans-serif;
                                        font-size:11px;
                                        line-height:1;
                                        color:#64748b;
                                        text-align:left;
                                    "
                                >
                                    Si el botón no funciona, copiá y pegá este enlace en tu navegador:

                                    <br />

                                    <a
                                        href="{$safeUrl}"
                                        target="_blank"
                                        rel="noopener"
                                        style="
                                            color:#3974ba;
                                            text-decoration:underline;
                                            word-break:break-all;
                                        "
                                    >
                                        {$safeUrl}
                                    </a>
                                </td>
                            </tr>

                            <!-- FOOTER CANVA -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/57e4f2ef5fc9b2d7b9f49554ec721b07.png"
                                        width="600"
                                        alt="¿Necesitás ayuda? info@permuok.com"
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

            $textBody =
                "Confirmá tu email en Permuok\n\n" .
                "Hola " .
                (
                    $recipientName !== ''
                    ? $recipientName
                    : ''
                ) .
                ",\n\n" .
                "Necesitamos validar esta dirección de correo para activar tu cuenta en Permuok.\n\n" .
                "Verificá tu email desde este enlace:\n" .
                $verificationUrl .
                "\n\n" .
                "Este enlace vence en 24 horas y puede usarse una sola vez.\n\n" .
                "Si no creaste esta cuenta, podés ignorar este mensaje.";

            EmailJobService::enqueue(
                $email,
                'email_verification',
                'Confirmá tu email en Permuok',
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
                    (string) $verificationToken['expires_at']
                ) < time()
            ) {
                $pdo->rollBack();

                throw new Exception(
                    'El enlace es inválido o venció.',
                    400
                );
            }

            if (
                (int) $verificationToken['is_active']
                !== 1
            ) {
                $pdo->rollBack();

                throw new Exception(
                    'La cuenta no se encuentra habilitada.',
                    403
                );
            }

            $userId =
                (int) $verificationToken['user_id'];

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

            /*
            |--------------------------------------------------------------------------
            | Email de bienvenida
            |--------------------------------------------------------------------------
            |
            | La verificación ya quedó confirmada en base de datos.
            |
            | Si por algún motivo el email de bienvenida no pudiera
            | encolarse, no debemos revertir ni impedir la validación
            | de la cuenta.
            |
            */
            try {
                WelcomeEmailService::enqueueForUser(
                    $userId
                );
            } catch (Throwable $welcomeError) {
                error_log(
                    '[WELCOME EMAIL] ' .
                        $welcomeError->getMessage()
                );
            }

            return [
                'user_id' =>
                $userId,

                'email' =>
                (string) $verificationToken['email'],

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
            (int) $user['is_active'] !== 1 ||
            $user['email_verified_at'] !== null
        ) {
            return;
        }

        self::issueForUser(
            (int) $user['id']
        );
    }
}
