<?php

namespace App\Services;

use PDO;

class RealEstateChangesApprovedEmailService
{
    private const EMAIL_TYPE =
    'real_estate_changes_approved';

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
        | HTML
        |--------------------------------------------------------------------------
        |
        | Versión compacta del diseño exportado desde Canva.
        |
        | No usamos min-height y tampoco agregamos un spacer
        | flexible antes del footer.
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

            .email-content {
                padding-left: 28px !important;
                padding-right: 28px !important;
            }

            .email-title {
                font-size: 18px !important;
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
                                        padding:38px 24px 24px;
                                    "
                                >
                                    <img
                                        src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/7c4be34b38b9579574c3ee232e624dae.png"
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

                            <!-- TEXTO -->
                            <tr>
                                <td
                                    class="email-content"
                                    style="
                                        padding:12px 56px 42px;
                                        font-family:Arial, Helvetica, sans-serif;
                                        color:#0e1b10;
                                        text-align:left;
                                    "
                                >
                                    <div
                                        class="email-title"
                                        style="
                                            font-size:20px;
                                            font-weight:700;
                                            line-height:1.35;
                                            margin-bottom:18px;
                                        "
                                    >
                                        Los cambios en tu perfil ya fueron aprobados.
                                    </div>

                                    <div
                                        style="
                                            font-size:16px;
                                            line-height:1.5;
                                        "
                                    >
                                        Actualizamos la información de tu inmobiliaria y todo volvió a quedar validado.
                                    </div>
                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:0;
                                    "
                                >
                                    <img
    src="https://bq5galnul0ejsuneypmqfsatucvdbtiujlng6snj7ao.canva-cdn.email/974650f371f097ca72ad38323b9cb26e.png"
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
            "Los cambios en tu perfil ya fueron aprobados.\n\n" .
            "Actualizamos la información de tu inmobiliaria y todo volvió a quedar validado.\n\n" .
            "Si tenés dudas, escribinos a info@permuok.com.";

        /*
        |--------------------------------------------------------------------------
        | Cola
        |--------------------------------------------------------------------------
        |
        | A diferencia del email de aprobación inicial,
        | este puede enviarse varias veces a lo largo
        | de la vida de una inmobiliaria.
        |
        | No aplicamos una deduplicación permanente
        | por real_estate_id.
        |
        */
        EmailJobService::enqueue(
            $email,
            self::EMAIL_TYPE,
            'Los cambios de tu perfil fueron aprobados',
            $htmlBody,
            $textBody,
            $userId,
            $firstName !== ''
                ? $firstName
                : null,
            'real_estate',
            $realEstateId,
            7
        );
    }
}
