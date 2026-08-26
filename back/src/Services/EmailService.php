<?php

namespace App\Services;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    public static function send(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?string $recipientName = null
    ): void {
        $to = trim($to);

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(
                'El email destinatario no es válido.'
            );
        }

        $host = trim((string)($_ENV['MAIL_HOST'] ?? ''));
        $port = (int)($_ENV['MAIL_PORT'] ?? 587);
        $username = trim((string)($_ENV['MAIL_USERNAME'] ?? ''));
        $password = (string)($_ENV['MAIL_PASSWORD'] ?? '');
        $encryption = strtolower(
            trim((string)($_ENV['MAIL_ENCRYPTION'] ?? 'tls'))
        );

        $fromAddress = trim(
            (string)($_ENV['MAIL_FROM_ADDRESS'] ?? '')
        );

        $fromName = trim(
            (string)($_ENV['MAIL_FROM_NAME'] ?? 'Permuok')
        );

        if ($host === '') {
            throw new Exception('MAIL_HOST no está configurado.');
        }

        if ($username === '') {
            throw new Exception('MAIL_USERNAME no está configurado.');
        }

        if ($password === '') {
            throw new Exception('MAIL_PASSWORD no está configurado.');
        }

        if (
            $fromAddress === '' ||
            !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)
        ) {
            throw new Exception(
                'MAIL_FROM_ADDRESS no está configurado correctamente.'
            );
        }

        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = $host;
        $mail->Port = $port;

        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;

        if ($encryption === 'ssl' || $encryption === 'smtps') {
            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls' || $encryption === 'starttls') {
            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'none' || $encryption === '') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            throw new Exception(
                'MAIL_ENCRYPTION debe ser tls, ssl o none.'
            );
        }

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $fromAddress,
            $fromName
        );

        $mail->addAddress(
            $to,
            $recipientName ?: ''
        );

        $mail->Subject = $subject;

        $mail->isHTML(true);
        $mail->Body = $htmlBody;

        $mail->AltBody =
            $textBody !== null && trim($textBody) !== ''
            ? $textBody
            : trim(
                html_entity_decode(
                    strip_tags(
                        preg_replace(
                            '/<br\s*\/?>/i',
                            "\n",
                            $htmlBody
                        )
                    ),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );

        $mail->send();
    }
}