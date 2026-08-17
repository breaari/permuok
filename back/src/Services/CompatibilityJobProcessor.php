<?php

namespace App\Services;

use Exception;
use App\Services\AI\CompatibilityEngine;
use App\Services\AI\PublicationQualityService;
use App\Services\AI\PublicationAIAnalysisService;

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
            CompatibilityEngine::calculateForProperty(
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
            'search_request_recalculate' =>
            CompatibilityEngine::calculateForSearchRequest(
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
}
