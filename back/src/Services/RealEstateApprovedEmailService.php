<?php

namespace App\Services;

use PDO;

class RealEstateApprovedEmailService
{
    private const EMAIL_TYPE =
    'real_estate_approved';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function enqueueForRealEstate(
        int $realEstateId
    ): void {
        if ($realEstateId <= 0) {
            return;
        }

        $pdo = self::db();

        /*
        |--------------------------------------------------------------------------
        | Usuario destinatario
        |--------------------------------------------------------------------------
        |
        | Buscamos al usuario inmobiliaria asociado.
        | Para la aprobación inicial tomamos el usuario
        | de rol inmobiliaria vinculado a esa inmobiliaria.
        |
        */
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.first_name,
                u.email,
                u.email_verified_at,
                u.is_active
            FROM users u
            WHERE u.real_estate_id = :real_estate_id
              AND u.role = 2
              AND u.deleted_at IS NULL
            ORDER BY u.id ASC
            LIMIT 1
        ");

        $stmt->execute([
            'real_estate_id' =>
            $realEstateId,
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
            empty($user['email_verified_at'])
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

        $userId =
            (int)$user['id'];

        $firstName =
            trim(
                (string)(
                    $user['first_name']
                    ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Evitar duplicados
        |--------------------------------------------------------------------------
        |
        | Este mail corresponde a la aprobación inicial.
        | Si por cualquier motivo el método vuelve a ejecutarse,
        | no generamos otro trabajo para la misma inmobiliaria.
        |
        */
        $existing = $pdo->prepare("
            SELECT id
            FROM email_jobs
            WHERE email_type = :email_type
              AND related_type = 'real_estate'
              AND related_id = :related_id
            LIMIT 1
        ");

        $existing->execute([
            'email_type' =>
            self::EMAIL_TYPE,

            'related_id' =>
            $realEstateId,
        ]);

        if (
            $existing->fetchColumn()
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | URL de membresía
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

        $billingUrl =
            $frontendUrl .
            '/billing';

        $safeBillingUrl =
            htmlspecialchars(
                $billingUrl,
                ENT_QUOTES,
                'UTF-8'
            );

        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        |
        | Basado en el diseño exportado desde Canva.
        | Se eliminaron scripts y recursos innecesarios
        | y se hizo dinámica la URL de membresía.
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

            .email-copy {
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
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/7dd6b5ae159b6e7e4d8d75007af379b9.png"
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
                                        padding:0 24px 16px;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/2ae1f98d60b147173c743e540fccdda7.png"
                                        width="552"
                                        alt="Tu inmobiliaria está verificada"
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

                            <!-- TEXTO -->
                            <tr>
                                <td
                                    class="email-copy"
                                    align="center"
                                    style="
                                        padding:10px 40px 30px;
                                        color:#0e1b10;
                                        font-family:Arial, Helvetica, sans-serif;
                                        font-size:16px;
                                        line-height:1.4;
                                        text-align:center;
                                    "
                                >
                                    Elegí el plan que mejor se adapte a tu estructura
                                    <br />
                                    y accedé a todas las herramientas de la red.
                                </td>
                            </tr>

                            <!-- BOTÓN -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0 24px 40px;
                                    "
                                >

                                    <!--[if mso]>
                                    <v:roundrect
                                        xmlns:v="urn:schemas-microsoft-com:vml"
                                        xmlns:w="urn:schemas-microsoft-com:office:word"
                                        href="{$safeBillingUrl}"
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
                                            Activar mi membresía
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
                                                        href="{$safeBillingUrl}"
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
                                                        Activar mi membresía
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <!--<![endif]-->

                                </td>
                            </tr>

                            <!-- FOOTER GRÁFICO -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/36c8c6b3ab1025265561ff07dc95b13f.png"
                                        width="600"
                                        alt="Estamos para acompañarte"
                                        style="
                                            display:block;
                                            width:600px;
                                            height:auto;
                                            max-width:100%;
                                            border:0;
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
            "Tu inmobiliaria está verificada.\n\n" .
            "Tu perfil ya está aprobado. Ahora activá tu membresía y empezá a operar en Permuok.\n\n" .
            "Elegí el plan que mejor se adapte a tu estructura y accedé a todas las herramientas de la red.\n\n" .
            "Activar mi membresía:\n" .
            $billingUrl .
            "\n\n" .
            "Estamos para acompañarte.\n\n" .
            "Si necesitás ayuda para elegir un plan o tenés dudas sobre la activación, escribinos a info@permuok.com.";

        /*
        |--------------------------------------------------------------------------
        | Cola
        |--------------------------------------------------------------------------
        */
        EmailJobService::enqueue(
            $email,
            self::EMAIL_TYPE,
            'Tu inmobiliaria fue aprobada en Permuok',
            $htmlBody,
            $textBody,
            $userId,
            $firstName !== ''
                ? $firstName
                : null,
            'real_estate',
            $realEstateId,
            8
        );
    }
}
