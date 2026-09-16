<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class MercadoPagoWebhookSignatureService
{
    public static function validate(
        array $query,
        array $body,
        ?string $signatureHeader,
        ?string $requestIdHeader
    ): bool {
        $secret = trim(
            (string)(
                $_ENV[
                    'MERCADOPAGO_WEBHOOK_SECRET'
                ]
                ?? ''
            )
        );

        if ($secret === '') {
            throw new RuntimeException(
                'MERCADOPAGO_WEBHOOK_SECRET no está configurada'
            );
        }

        $signatureHeader =
            trim(
                (string)$signatureHeader
            );

        $requestIdHeader =
            trim(
                (string)$requestIdHeader
            );

        if ($signatureHeader === '') {
            return false;
        }

        $signatureParts = [];

        foreach (
            explode(',', $signatureHeader)
            as $part
        ) {
            $pieces =
                explode(
                    '=',
                    trim($part),
                    2
                );

            if (count($pieces) !== 2) {
                continue;
            }

            $key = trim($pieces[0]);
            $value = trim($pieces[1]);

            if (
                $key !== ''
                && $value !== ''
            ) {
                $signatureParts[$key] =
                    $value;
            }
        }

        $timestamp =
            trim(
                (string)(
                    $signatureParts['ts']
                    ?? ''
                )
            );

        $receivedSignature =
            strtolower(
                trim(
                    (string)(
                        $signatureParts['v1']
                        ?? ''
                    )
                )
            );

        if (
            $timestamp === ''
            || $receivedSignature === ''
        ) {
            return false;
        }

        /*
         * PHP convierte puntos de los parámetros
         * query-string en guiones bajos.
         *
         * Por eso admitimos tanto data.id
         * como data_id.
         */
        $dataId =
            $query['data.id']
            ?? $query['data_id']
            ?? ($body['data']['id'] ?? null);

        $manifest = '';

        if (
            $dataId !== null
            && trim((string)$dataId) !== ''
        ) {
            $manifest .=
                'id:' .
                strtolower(
                    trim(
                        (string)$dataId
                    )
                ) .
                ';';
        }

        if ($requestIdHeader !== '') {
            $manifest .=
                'request-id:' .
                $requestIdHeader .
                ';';
        }

        $manifest .=
            'ts:' .
            $timestamp .
            ';';

        $expectedSignature =
            hash_hmac(
                'sha256',
                $manifest,
                $secret
            );

        return hash_equals(
            $expectedSignature,
            $receivedSignature
        );
    }
}