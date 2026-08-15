<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\AI\AiEnrichmentService;
use App\Services\AI\OpenAIProvider;
use Throwable;

class AiEnrichmentController
{
    /**
     * Analiza o recalcula una propiedad.
     */
    public static function analyzeProperty(int $propertyId): void
    {
        try {
            $user = AuthHelper::requireUser();

            if ($propertyId <= 0) {
                throw new \Exception(
                    'Propiedad inválida.',
                    422
                );
            }

            self::configureProvider();

            $payload = json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];

            $force = filter_var(
                $payload['force'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            $result = AiEnrichmentService::enrichProperty(
                $propertyId,
                $force
            );

            ResponseHelper::ok([
                'analysis' => $result,
            ]);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    /**
     * Devuelve el análisis vigente sin llamar a OpenAI.
     */
    public static function propertyAnalysis(
        int $propertyId
    ): void {
        try {
            AuthHelper::requireUser();

            if ($propertyId <= 0) {
                throw new \Exception(
                    'Propiedad inválida.',
                    422
                );
            }

            $analysis =
                AiEnrichmentService::getPropertyEnrichment(
                    $propertyId
                );

            ResponseHelper::ok([
                'analysis' => $analysis,
                'available' => $analysis !== null,
            ]);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    /**
     * Se conecta al proveedor solamente cuando existen
     * la clave y el modelo.
     */
    private static function configureProvider(): void
    {
        $apiKey = trim(
            (string)(
                $_ENV['OPENAI_API_KEY']
                ?? getenv('OPENAI_API_KEY')
                ?: ''
            )
        );

        $model = trim(
            (string)(
                $_ENV['OPENAI_MODEL']
                ?? getenv('OPENAI_MODEL')
                ?: 'gpt-5-mini'
            )
        );

        error_log(
            '[OPENAI] API KEY: ' .
                ($apiKey !== '' ? 'CARGADA' : 'NO CARGADA')
        );

        error_log(
            '[OPENAI] MODEL: ' .
                ($model !== '' ? $model : 'NO CARGADO')
        );

        if ($apiKey === '') {
            throw new \Exception(
                'OPENAI_API_KEY no está configurada.',
                503
            );
        }

        AiEnrichmentService::setProvider(
            new OpenAIProvider(
                apiKey: $apiKey,
                model: $model
            )
        );
    }
    private static function fail(Throwable $e): void
    {
        $code = (int)$e->getCode();

        if ($code < 400 || $code > 599) {
            $code = 500;
        }

        ResponseHelper::fail(
            $e->getMessage() ?: 'No se pudo procesar el análisis.',
            $code
        );
    }

    public static function analyzeSearchRequest(
        int $searchRequestId
    ): void {
        try {
            AuthHelper::requireUser();

            if ($searchRequestId <= 0) {
                throw new \Exception(
                    'Búsqueda inválida.',
                    422
                );
            }

            self::configureProvider();

            $payload = json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];

            $force = filter_var(
                $payload['force'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            $result =
                AiEnrichmentService::enrichSearchRequest(
                    $searchRequestId,
                    $force
                );

            ResponseHelper::ok([
                'analysis' => $result,
            ]);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }
    public static function searchRequestAnalysis(
        int $searchRequestId
    ): void {
        try {
            AuthHelper::requireUser();

            if ($searchRequestId <= 0) {
                throw new \Exception(
                    'Búsqueda inválida.',
                    422
                );
            }

            $analysis =
                AiEnrichmentService::getSearchRequestEnrichment(
                    $searchRequestId
                );

            ResponseHelper::ok([
                'analysis' => $analysis,
                'available' => $analysis !== null,
            ]);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }
}
