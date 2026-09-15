<?php

namespace App\Services;

use Exception;

class ImageUrlSignatureService
{
    private const DEFAULT_TTL_SECONDS = 3600;

    private const ALLOWED_TYPES = [
        'property',
        'development',
    ];

    private static function getSecret(): string
    {
        $secret = '';

        if (function_exists('env')) {
            $secret =
                trim(
                    (string)env(
                        'IMAGE_URL_SIGNING_KEY',
                        ''
                    )
                );
        }

        if ($secret === '') {
            $secret =
                trim(
                    (string)(
                        $_ENV['IMAGE_URL_SIGNING_KEY']
                        ?? getenv(
                            'IMAGE_URL_SIGNING_KEY'
                        )
                        ?: ''
                    )
                );
        }

        if (strlen($secret) < 32) {
            throw new Exception(
                'IMAGE_URL_SIGNING_KEY no está configurada correctamente.'
            );
        }

        return $secret;
    }

    private static function validateType(
        string $type
    ): string {
        $type =
            strtolower(trim($type));

        if (
            !in_array(
                $type,
                self::ALLOWED_TYPES,
                true
            )
        ) {
            throw new Exception(
                'Tipo de imagen inválido.'
            );
        }

        return $type;
    }

    private static function payload(
        string $type,
        int $imageId,
        int $expires
    ): string {
        return implode(
            '|',
            [
                $type,
                $imageId,
                $expires,
            ]
        );
    }

    private static function signature(
        string $type,
        int $imageId,
        int $expires
    ): string {
        return hash_hmac(
            'sha256',
            self::payload(
                $type,
                $imageId,
                $expires
            ),
            self::getSecret()
        );
    }

    /**
     * Genera una URL temporal firmada.
     */
    public static function generate(
        string $type,
        int $imageId,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ): string {
        $type =
            self::validateType($type);

        if ($imageId <= 0) {
            throw new Exception(
                'El ID de la imagen no es válido.'
            );
        }

        $ttlSeconds = max(
            60,
            min(
                $ttlSeconds,
                24 * 60 * 60
            )
        );

        $expires =
            time() + $ttlSeconds;

        $signature =
            self::signature(
                $type,
                $imageId,
                $expires
            );

        $path =
            $type === 'property'
                ? '/property-images/' .
                    $imageId .
                    '/view'
                : '/development-images/' .
                    $imageId .
                    '/view';

        return $path .
            '?' .
            http_build_query(
                [
                    'expires' => $expires,
                    'signature' => $signature,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    /**
     * Valida la firma y el vencimiento recibidos.
     */
    public static function validate(
        string $type,
        int $imageId,
        $expires,
        $signature
    ): bool {
        try {
            $type =
                self::validateType($type);
        } catch (\Throwable $e) {
            return false;
        }

        if (
            $imageId <= 0 ||
            !is_scalar($expires) ||
            !is_scalar($signature)
        ) {
            return false;
        }

        $expiresString =
            trim((string)$expires);

        $providedSignature =
            strtolower(
                trim((string)$signature)
            );

        if (
            $expiresString === '' ||
            !ctype_digit($expiresString) ||
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $providedSignature
            )
        ) {
            return false;
        }

        $expiresTimestamp =
            (int)$expiresString;

        if ($expiresTimestamp < time()) {
            return false;
        }

        /*
         * Una firma nunca puede tener más de
         * 24 horas de vigencia.
         */
        if (
            $expiresTimestamp >
            time() + (24 * 60 * 60)
        ) {
            return false;
        }

        $expectedSignature =
            self::signature(
                $type,
                $imageId,
                $expiresTimestamp
            );

        return hash_equals(
            $expectedSignature,
            $providedSignature
        );
    }
}