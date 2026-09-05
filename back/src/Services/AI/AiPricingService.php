<?php

namespace App\Services\AI;

class AiPricingService
{
    /*
     * Precios estándar de OpenAI por 1 millón
     * de tokens.
     *
     * Snapshot de precios utilizado por PermuOK.
     *
     * El costo calculado se guarda luego en
     * ai_usage_logs.estimated_cost_usd, por lo que
     * cada registro conserva el costo estimado
     * correspondiente al momento del consumo.
     */
    private const OPENAI_PRICING = [
        'gpt-5' => [
            'input' =>
                1.25,

            'cached_input' =>
                0.125,

            'output' =>
                10.00,
        ],

        'gpt-5-mini' => [
            'input' =>
                0.25,

            'cached_input' =>
                0.025,

            'output' =>
                2.00,
        ],

        'gpt-5-nano' => [
            'input' =>
                0.05,

            'cached_input' =>
                0.005,

            'output' =>
                0.40,
        ],
    ];

    public static function calculateEstimatedCostUsd(
        string $provider,
        ?string $modelName,
        int $inputTokens,
        int $cachedInputTokens,
        int $outputTokens
    ): float {
        $provider =
            strtolower(
                trim($provider)
            );

        if ($provider !== 'openai') {
            return 0.0;
        }

        $pricing =
            self::resolveOpenAIPricing(
                $modelName
            );

        /*
         * Si aparece un modelo para el cual todavía
         * no cargamos precio, preferimos costo 0
         * antes que guardar un valor incorrecto.
         */
        if ($pricing === null) {
            return 0.0;
        }

        $inputTokens =
            max(
                0,
                $inputTokens
            );

        $cachedInputTokens =
            max(
                0,
                $cachedInputTokens
            );

        /*
         * Cached input forma parte del input total.
         * Nunca puede cobrarse dos veces.
         */
        $cachedInputTokens =
            min(
                $cachedInputTokens,
                $inputTokens
            );

        $regularInputTokens =
            max(
                0,
                $inputTokens -
                $cachedInputTokens
            );

        $outputTokens =
            max(
                0,
                $outputTokens
            );

        $regularInputCost =
            (
                $regularInputTokens /
                1_000_000
            ) *
            $pricing['input'];

        $cachedInputCost =
            (
                $cachedInputTokens /
                1_000_000
            ) *
            $pricing['cached_input'];

        $outputCost =
            (
                $outputTokens /
                1_000_000
            ) *
            $pricing['output'];

        return round(
            $regularInputCost +
            $cachedInputCost +
            $outputCost,
            8
        );
    }

    private static function resolveOpenAIPricing(
        ?string $modelName
    ): ?array {
        $modelName =
            strtolower(
                trim(
                    (string)$modelName
                )
            );

        if ($modelName === '') {
            return null;
        }

        /*
         * Primero buscamos coincidencia exacta.
         */
        if (
            isset(
                self::OPENAI_PRICING[
                    $modelName
                ]
            )
        ) {
            return self::OPENAI_PRICING[
                $modelName
            ];
        }

        /*
         * OpenAI puede devolver el snapshot real:
         *
         * gpt-5-mini-2025-08-07
         *
         * aunque nosotros hayamos solicitado:
         *
         * gpt-5-mini
         *
         * Comprobamos primero los nombres más
         * específicos para evitar que gpt-5-mini
         * termine clasificado como gpt-5.
         */
        foreach (
            [
                'gpt-5-mini',
                'gpt-5-nano',
                'gpt-5',
            ]
            as $baseModel
        ) {
            if (
                str_starts_with(
                    $modelName,
                    $baseModel . '-'
                )
            ) {
                return self::OPENAI_PRICING[
                    $baseModel
                ];
            }
        }

        return null;
    }
}