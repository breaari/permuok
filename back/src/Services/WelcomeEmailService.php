<?php

namespace App\Services;

use PDO;

class WelcomeEmailService
{
    private const EMAIL_TYPE =
    'welcome_email_verified';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function enqueueForUser(
        int $userId
    ): void {
        if ($userId <= 0) {
            return;
        }

        $pdo =
            self::db();

        /*
        |--------------------------------------------------------------------------
        | Usuario
        |--------------------------------------------------------------------------
        |
        | El correo de bienvenida solamente debe enviarse a:
        |
        | - usuarios existentes
        | - usuarios activos
        | - usuarios con el email ya verificado
        |
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
        ");

        $stmt->execute([
            'id' =>
            $userId,
        ]);

        $user =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$user) {
            return;
        }

        if (
            (int)$user['is_active']
            !== 1
        ) {
            return;
        }

        if (
            $user['email_verified_at']
            === null
        ) {
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
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Evitar duplicados
        |--------------------------------------------------------------------------
        |
        | Aunque verify() solamente debería completar la validación una vez,
        | evitamos generar dos correos de bienvenida para el mismo usuario.
        |
        */
        $existing = $pdo->prepare("
            SELECT id
            FROM email_jobs
            WHERE user_id = :user_id
              AND email_type = :email_type
            LIMIT 1
        ");

        $existing->execute([
            'user_id' =>
            $userId,

            'email_type' =>
            self::EMAIL_TYPE,
        ]);

        if (
            $existing->fetchColumn()
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | URL frontend
        |--------------------------------------------------------------------------
        */
        $frontendUrl =
            rtrim(
                (string)(
                    $_ENV['FRONTEND_URL']
                    ?? 'https://permuok.com'
                ),
                '/'
            );

        $profileUrl =
            $frontendUrl .
            '/my-profile';

        $safeProfileUrl =
            htmlspecialchars(
                $profileUrl,
                ENT_QUOTES,
                'UTF-8'
            );

        $firstName =
            trim(
                (string)(
                    $user['first_name']
                    ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        |
        | Basado en el diseño exportado desde Canva.
        |
        | Se removieron:
        |
        | - scripts agregados por el export
        | - preload de imágenes
        | - código ajeno al email
        |
        | Se reemplazó la URL fija del botón por FRONTEND_URL.
        |
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

            .mobile-title {
                font-size: 23px !important;
            }

            .mobile-content {
                padding-left: 24px !important;
                padding-right: 24px !important;
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
                                        padding:41px 24px 33px;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/43929d463a5cdd8f4d06796a4c529884.png"
                                        width="253"
                                        alt="Permuok"
                                        style="
                                            display:block;
                                            width:253px;
                                            height:auto;
                                            max-width:100%;
                                            border:0;
                                        "
                                    />
                                </td>
                            </tr>

                            <!-- IMAGEN PRINCIPAL -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0 24px 16px;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/756c3371f6e4a8bb0a22268df5bcd9b2.png"
                                        width="552"
                                        alt="Bienvenido a Permuok"
                                        style="
                                            display:block;
                                            width:552px;
                                            height:auto;
                                            max-width:100%;
                                            border:0;
                                        "
                                    />
                                </td>
                            </tr>

                            <!-- ESPACIO -->
                            <tr>
                                <td
                                    style="
                                        height:18px;
                                        line-height:18px;
                                        font-size:0;
                                    "
                                >
                                    &nbsp;
                                </td>
                            </tr>

                            <!-- TÍTULO -->
                            <tr>
                                <td
                                    class="mobile-title"
                                    align="center"
                                    style="
                                        padding:0 24px 16px;
                                        color:#0e1b10;
                                        font-family:Manrope, Arial, Helvetica, sans-serif;
                                        font-size:29px;
                                        font-weight:700;
                                        letter-spacing:-0.04em;
                                        line-height:1.1;
                                    "
                                >
                                    Tu cuenta está casi lista.
                                </td>
                            </tr>

                            <!-- TEXTO -->
                            <tr>
                                <td
                                    class="mobile-content"
                                    align="center"
                                    style="
                                        padding:0 48px 18px;
                                        color:#0e1b10;
                                        font-family:Manrope, Arial, Helvetica, sans-serif;
                                        font-size:16px;
                                        line-height:1.45;
                                    "
                                >
                                    Ahora completá los datos de tu inmobiliaria para
                                    <br />
                                    empezar a formar parte de la red.
                                </td>
                            </tr>

                            <!-- BOTÓN -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:4px 24px 34px;
                                    "
                                >

                                    <!--[if mso]>
                                    <v:roundrect
                                        xmlns:v="urn:schemas-microsoft-com:vml"
                                        xmlns:w="urn:schemas-microsoft-com:office:word"
                                        href="{$safeProfileUrl}"
                                        style="
                                            height:68px;
                                            width:276px;
                                            v-text-anchor:middle;
                                        "
                                        arcsize="22%"
                                        fillcolor="#68a636"
                                        strokecolor="#68a636"
                                    >
                                        <w:anchorlock/>

                                        <center
                                            style="
                                                color:#ffffff;
                                                font-family:Arial,sans-serif;
                                                font-size:16px;
                                                font-weight:700;
                                            "
                                        >
                                            Completar mi perfil
                                        </center>
                                    </v:roundrect>
                                    <![endif]-->

                                    <!--[if !mso]><!-->
                                    <table
                                        role="presentation"
                                        cellpadding="0"
                                        cellspacing="0"
                                        border="0"
                                        width="276"
                                        style="
                                            width:276px;
                                            max-width:100%;
                                            border-collapse:separate;
                                            border-spacing:0;
                                        "
                                    >
                                        <tbody>
                                            <tr>
                                                <td
                                                    bgcolor="#68a636"
                                                    style="
                                                        background-color:#68a636;
                                                        border-radius:15px;
                                                    "
                                                >
                                                    <a
                                                        href="{$safeProfileUrl}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        style="
                                                            color:#ffffff;
                                                            text-decoration:none;
                                                            display:block;
                                                            padding:26px 8px;
                                                            text-align:center;
                                                            font-family:Manrope, Arial, Helvetica, sans-serif;
                                                            font-size:16px;
                                                            font-weight:700;
                                                            line-height:16px;
                                                            box-sizing:border-box;
                                                        "
                                                    >
                                                        Completar mi perfil
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <!--<![endif]-->

                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                               <td
    bgcolor="#0f172a"
    style="
        background-color:#0f172a;
        padding:38px 46px;
        font-family:Manrope, Arial, Helvetica, sans-serif;
        text-align:left;
        border-radius:0 0 15px 15px;
    "
>
                                    <div
                                        style="
                                            color:#f6f5f1;
                                            font-size:24px;
                                            font-weight:700;
                                            line-height:1.2;
                                            margin-bottom:18px;
                                        "
                                    >
                                        Estamos para acompañarte.
                                    </div>

                                    <div
                                        style="
                                            color:#f6f5f1;
                                            font-size:16px;
                                            line-height:1.45;
                                            margin-bottom:18px;
                                        "
                                    >
                                        Si tenés dudas durante el registro de tu inmobiliaria,
                                        escribinos y te acompañamos en el proceso.
                                        <br /><br />

                                        <a
                                            href="mailto:info@permuok.com"
                                            target="_blank"
                                            rel="noopener"
                                            style="
                                                color:#ffffff;
                                                font-weight:700;
                                                text-decoration:underline;
                                            "
                                        >
                                            info@permuok.com
                                        </a>
                                    </div>

                                    <div
                                        style="
                                            color:#bfc3c8;
                                            font-size:13px;
                                            line-height:1.45;
                                        "
                                    >
                                        Recibís este correo porque creaste una cuenta en Permuok.
                                        <br />
                                        Si no fuiste vos, podés ignorar este mensaje.
                                    </div>
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
        | Versión texto plano
        |--------------------------------------------------------------------------
        */
        $textBody =
            "Tu cuenta está casi lista.\n\n" .
            "Ahora completá los datos de tu inmobiliaria para empezar a formar parte de la red.\n\n" .
            "Completar mi perfil:\n" .
            $profileUrl .
            "\n\n" .
            "Estamos para acompañarte.\n\n" .
            "Si tenés dudas durante el registro de tu inmobiliaria, escribinos y te acompañamos en el proceso.\n\n" .
            "info@permuok.com\n\n" .
            "Recibís este correo porque creaste una cuenta en Permuok.\n" .
            "Si no fuiste vos, podés ignorar este mensaje.";

        /*
        |--------------------------------------------------------------------------
        | Encolar
        |--------------------------------------------------------------------------
        */
        EmailJobService::enqueue(
            $email,
            self::EMAIL_TYPE,
            'Tu cuenta está casi lista',
            $htmlBody,
            $textBody,
            $userId,
            $firstName !== ''
                ? $firstName
                : null,
            'user',
            $userId,
            7
        );
    }
}
