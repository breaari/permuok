<?php

namespace App\Services;

use Exception;
use App\Services\AI\CompatibilityEngine;
use App\Services\AI\PublicationQualityService;
use App\Services\AI\PublicationAIAnalysisService;
use App\Services\AI\SearchRequestAIAnalysisService;
use App\Services\AI\DevelopmentAIAnalysisService;
use App\Services\CurrencyConversionService;
use App\Services\AI\MultilateralOperationService;
use App\Services\MatchDigestService;

class CompatibilityJobProcessor
{
    public static function process(
        array $job
    ): array {
        $jobType =
            (string)($job['job_type'] ?? '');

        $entityId =
            (int)($job['entity_id'] ?? 0);

        if ($entityId <= 0) {
            throw new Exception(
                'El job no contiene una entidad válida.'
            );
        }

        return match ($jobType) {
            'property_recalculate' =>
            self::processPropertyRecalculation(
                $entityId
            ),
            'property_quality_recalculate' =>
            PublicationQualityService::analyzeProperty(
                $entityId
            ),
            'property_ai_analyze' =>
            PublicationAIAnalysisService::processPropertyAnalysis(
                $entityId,
                (int)($job['reference_id'] ?? 0),
                (int)($job['attempts'] ?? 1),
                (int)($job['max_attempts'] ?? 3)
            ),
            'multilateral_recalculate' =>
            MultilateralOperationService::recalculate(),
            'currency_rate_update' =>
            self::processCurrencyRateUpdate(),
            'match_daily_digest' =>
            MatchDigestService::process(),
            'search_request_ai_analyze' =>
            SearchRequestAIAnalysisService::processAnalysis(
                $entityId,
                (int)($job['reference_id'] ?? 0),
                (int)($job['attempts'] ?? 1),
                (int)($job['max_attempts'] ?? 3)
            ),
            'development_ai_analyze' =>
            DevelopmentAIAnalysisService::processAnalysis(
                $entityId,
                (int)($job['reference_id'] ?? 0),
                (int)($job['attempts'] ?? 1),
                (int)($job['max_attempts'] ?? 3)
            ),
            'search_request_recalculate' =>
            self::processSearchRequestRecalculation(
                $entityId
            ),

            'property_archive' =>
            CompatibilityEngine::archiveForProperty(
                $entityId
            ),

            'search_request_archive' =>
            CompatibilityEngine::archiveForSearchRequest(
                $entityId
            ),

            default =>
            throw new Exception(
                'Tipo de job desconocido: ' .
                    $jobType
            ),
        };
    }
    private static function processCurrencyRateUpdate(): array
    {
        $result =
            CurrencyConversionService::refreshOfficialRate();

        /*
     * Solamente recalculamos matches cuando
     * efectivamente apareció una cotización nueva.
     *
     * Si DolarHoy devuelve el mismo valor,
     * no generamos trabajo innecesario.
     */
        if (!empty($result['updated'])) {
            $result['recalculation_jobs_affected'] =
                CompatibilityJobService::enqueueAllPublishedSearchRequestRecalculations(
                    4
                );
        } else {
            $result['recalculation_jobs_affected'] =
                0;
        }

        return $result;
    }

    private static function processPropertyRecalculation(
        int $propertyId
    ): array {
        $result =
            CompatibilityEngine::calculateForProperty(
                $propertyId
            );

        CompatibilityJobService::enqueueMultilateralRecalculation(
            3
        );

        return $result;
    }

    private static function processSearchRequestRecalculation(
        int $searchRequestId
    ): array {
        $result =
            CompatibilityEngine::calculateForSearchRequest(
                $searchRequestId
            );

        CompatibilityJobService::enqueueMultilateralRecalculation(
            3
        );

        return $result;
    }
}
