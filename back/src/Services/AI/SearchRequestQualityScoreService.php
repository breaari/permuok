<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class SearchRequestQualityScoreService
{
    private const VERSION = '1.0';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function getScore(
        int $searchRequestId
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido.'
            );
        }

        /*
         * ========================================
         * PARTE OBJETIVA
         * ========================================
         *
         * Máximo:
         *
         * criteria  30
         * location  15
         * payment   15
         *
         * Total     60
         */
        $objective =
            SearchRequestQualityService::analyze(
                $searchRequestId
            );

        $objectiveSections =
            $objective['sections'] ?? [];

        $criteria =
            self::sectionScore(
                $objectiveSections,
                'criteria',
                30
            );

        $location =
            self::sectionScore(
                $objectiveSections,
                'location',
                15
            );

        $payment =
            self::sectionScore(
                $objectiveSections,
                'payment',
                15
            );

        $objectiveScore =
            round(
                $criteria +
                    $location +
                    $payment,
                2
            );

        /*
         * Buscamos únicamente un análisis IA
         * correspondiente exactamente al contenido
         * actual de la búsqueda.
         */
        $ai =
            self::getCurrentAIAnalysis(
                $searchRequestId
            );

        if (
            $ai === null ||
            ($ai['status'] ?? null) !== 'completed'
        ) {
            $aiStatus =
                $ai['status']
                ?? null;

            $isProcessing =
                in_array(
                    $aiStatus,
                    [
                        'pending',
                        'processing',
                    ],
                    true
                );

            return [
                /*
         * waiting_ai:
         * hay un análisis realmente en curso.
         *
         * needs_ai:
         * no existe uno vigente, falló
         * o fue descartado.
         */
                'status' =>
                $isProcessing
                    ? 'waiting_ai'
                    : 'needs_ai',

                'ai_status' =>
                $aiStatus,

                'score' =>
                null,

                'quality_level' =>
                null,

                'objective_progress' =>
                $objectiveScore,

                'suggestions' =>
                $objective['suggestions']
                    ?? [],

                'sections' => [
                    'criteria' => [
                        'score' =>
                        $criteria,

                        'max_score' =>
                        30,
                    ],

                    'location' => [
                        'score' =>
                        $location,

                        'max_score' =>
                        15,
                    ],

                    'payment' => [
                        'score' =>
                        $payment,

                        'max_score' =>
                        15,
                    ],
                ],

                'version' =>
                self::VERSION,
            ];
        }

        /*
         * ========================================
         * PARTE IA
         * ========================================
         *
         * Los valores almacenados vienen 0-100.
         *
         * Los convertimos a:
         *
         * title          10
         * description    15
         * consistency    10
         * matchability    5
         *
         * Total          40
         */
        $title =
            self::aiWeightedScore(
                $ai['title_score']
                    ?? null,
                10
            );

        $description =
            self::aiWeightedScore(
                $ai['description_score']
                    ?? null,
                15
            );

        $consistency =
            self::aiWeightedScore(
                $ai['consistency_score']
                    ?? null,
                10
            );

        $matchability =
            self::aiWeightedScore(
                $ai['matchability_score']
                    ?? null,
                5
            );

        if (
            $title === null ||
            $description === null ||
            $consistency === null ||
            $matchability === null
        ) {
            return [
                'status' =>
                'needs_ai',

                'ai_status' =>
                'invalid',

                'score' =>
                null,

                'quality_level' =>
                null,

                'objective_progress' =>
                $objectiveScore,

                'suggestions' =>
                $objective['suggestions']
                    ?? [],

                'version' =>
                self::VERSION,
            ];
        }
        $rawScore =
            $criteria +
            $location +
            $payment +
            $title +
            $description +
            $consistency +
            $matchability;

        $rawScore =
            round(
                self::clamp(
                    $rawScore,
                    0,
                    100
                ),
                2
            );

        /*
         * Por ahora no aplicamos caps críticos.
         *
         * Más adelante agregaremos:
         *
         * unusable_search
         *
         * cuando definamos exactamente cuándo
         * una búsqueda debe quedar limitada.
         */
        $flags = [];

        $score =
            $rawScore;

        $qualityLevel =
            self::resolveQualityLevel(
                $score
            );

        $objectiveSuggestions =
            is_array(
                $objective['suggestions']
                    ?? null
            )
            ? $objective['suggestions']
            : [];

        $aiSuggestions =
            self::decodeJsonArray(
                $ai['suggestions_json']
                    ?? null
            );

        $contradictions =
            self::decodeJsonArray(
                $ai['contradictions_json']
                    ?? null
            );

        return [
            'status' =>
            'completed',

            'score' =>
            $score,

            'raw_score' =>
            $rawScore,

            'quality_level' =>
            $qualityLevel,

            'flags' =>
            $flags,

            'sections' => [
                'criteria' => [
                    'score' =>
                    round($criteria, 2),

                    'max_score' =>
                    30,
                ],

                'location' => [
                    'score' =>
                    round($location, 2),

                    'max_score' =>
                    15,
                ],

                'payment' => [
                    'score' =>
                    round($payment, 2),

                    'max_score' =>
                    15,
                ],

                'title' => [
                    'score' =>
                    round($title, 2),

                    'max_score' =>
                    10,

                    'raw_ai_score' =>
                    (float)$ai['title_score'],
                ],

                'description' => [
                    'score' =>
                    round(
                        $description,
                        2
                    ),

                    'max_score' =>
                    15,

                    'raw_ai_score' =>
                    (float)$ai['description_score'],
                ],

                'consistency' => [
                    'score' =>
                    round(
                        $consistency,
                        2
                    ),

                    'max_score' =>
                    10,

                    'raw_ai_score' =>
                    (float)$ai['consistency_score'],
                ],

                'matchability' => [
                    'score' =>
                    round(
                        $matchability,
                        2
                    ),

                    'max_score' =>
                    5,

                    'raw_ai_score' =>
                    (float)$ai['matchability_score'],
                ],
            ],

            'suggestions' =>
            array_merge(
                $objectiveSuggestions,
                $aiSuggestions
            ),

            'contradictions' =>
            $contradictions,

            'sources' => [
                'objective_algorithm_version' =>
                $objective['version']
                    ?? null,

                'ai_analysis_id' =>
                (int)$ai['id'],

                'ai_prompt_version' =>
                $ai['prompt_version']
                    ?? null,
            ],

            'version' =>
            self::VERSION,
        ];
    }

    public static function recalculateAndPersist(
        int $searchRequestId
    ): array {
        $result =
            self::getScore(
                $searchRequestId
            );

        /*
         * Guardamos la parte objetiva incluso
         * mientras esperamos la IA.
         */
        self::persist(
            $searchRequestId,
            $result
        );

        return $result;
    }

    private static function persist(
        int $searchRequestId,
        array $result
    ): void {
        $pdo =
            self::db();

        $status =
            $result['status']
            ?? null;

        $sections =
            $result['sections']
            ?? [];

        /*
         * Las primeras tres secciones son siempre
         * las objetivas de Search Request.
         */
        $objectiveSections = [
            'criteria' =>
            $sections['criteria']
                ?? null,

            'location' =>
            $sections['location']
                ?? null,

            'payment' =>
            $sections['payment']
                ?? null,
        ];

        try {
            $objectiveSectionsJson =
                json_encode(
                    $objectiveSections,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                );

            $suggestionsJson =
                json_encode(
                    $result['suggestions']
                        ?? [],
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                );

            $officialSectionsJson =
                $status === 'completed'
                ? json_encode(
                    $sections,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                )
                : null;

            $flagsJson =
                $status === 'completed'
                ? json_encode(
                    $result['flags']
                        ?? [],
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                )
                : null;
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo serializar el score de la búsqueda.',
                0,
                $e
            );
        }

        /*
         * score contiene únicamente la parte
         * objetiva para esta entidad.
         *
         * official_score contiene el score híbrido
         * definitivo cuando la IA está completa.
         */
        $objectiveScore = 0.0;

        foreach (
            ['criteria', 'location', 'payment']
            as $key
        ) {
            $objectiveScore +=
                (float)(
                    $sections[$key]['score']
                    ?? 0
                );
        }

        $sources =
            $result['sources']
            ?? [];

        $st =
            $pdo->prepare("
                INSERT INTO publication_quality_scores (
                    entity_type,
                    entity_id,

                    score,
                    quality_level,

                    suggestions_json,
                    objective_sections_json,

                    algorithm_version,
                    analyzed_at,

                    official_score,
                    official_quality_level,
                    official_score_version,
                    official_ai_analysis_id,
                    official_ai_prompt_version,
                    official_flags_json,
                    official_sections_json,
                    official_calculated_at
                )
                VALUES (
                    'search_request',
                    :entity_id,

                    :score,
                    :quality_level,

                    :suggestions_json,
                    :objective_sections_json,

                    :algorithm_version,
                    NOW(),

                    :official_score,
                    :official_quality_level,
                    :official_score_version,
                    :official_ai_analysis_id,
                    :official_ai_prompt_version,
                    :official_flags_json,
                    :official_sections_json,
                    :official_calculated_at
                )

                ON DUPLICATE KEY UPDATE
                    score =
                        VALUES(score),

                    quality_level =
                        VALUES(quality_level),

                    suggestions_json =
                        VALUES(suggestions_json),

                    objective_sections_json =
                        VALUES(objective_sections_json),

                    algorithm_version =
                        VALUES(algorithm_version),

                    analyzed_at =
                        NOW(),

                    official_score =
                        VALUES(official_score),

                    official_quality_level =
                        VALUES(official_quality_level),

                    official_score_version =
                        VALUES(official_score_version),

                    official_ai_analysis_id =
                        VALUES(official_ai_analysis_id),

                    official_ai_prompt_version =
                        VALUES(official_ai_prompt_version),

                    official_flags_json =
                        VALUES(official_flags_json),

                    official_sections_json =
                        VALUES(official_sections_json),

                    official_calculated_at =
                        VALUES(official_calculated_at)
            ");

        $completed =
            $status === 'completed';

        $st->execute([
            'entity_id' =>
            $searchRequestId,

            'score' =>
            round(
                $objectiveScore,
                2
            ),

            /*
             * quality_level legacy/objective.
             *
             * Como el objetivo vale solamente 60,
             * no usamos acá los niveles /100.
             */
            'quality_level' =>
            self::resolveObjectiveQualityLevel(
                $objectiveScore
            ),

            'suggestions_json' =>
            $suggestionsJson,

            'objective_sections_json' =>
            $objectiveSectionsJson,

            'algorithm_version' =>
            'search-' .
                (
                    $result['sources']['objective_algorithm_version']
                    ?? self::VERSION
                ),

            'official_score' =>
            $completed
                ? $result['score']
                : null,

            'official_quality_level' =>
            $completed
                ? $result['quality_level']
                : null,

            'official_score_version' =>
            $completed
                ? self::VERSION
                : null,

            'official_ai_analysis_id' =>
            $completed
                ? (
                    $sources['ai_analysis_id']
                    ?? null
                )
                : null,

            'official_ai_prompt_version' =>
            $completed
                ? (
                    $sources['ai_prompt_version']
                    ?? null
                )
                : null,

            'official_flags_json' =>
            $flagsJson,

            'official_sections_json' =>
            $officialSectionsJson,

            'official_calculated_at' =>
            $completed
                ? date('Y-m-d H:i:s')
                : null,
        ]);
    }

    private static function getCurrentAIAnalysis(
        int $searchRequestId
    ): ?array {
        $pdo =
            self::db();

        /*
         * buildInputHash() incluye la versión
         * de prompt dentro del hash.
         *
         * Por eso basta con exigir el hash exacto
         * del contenido actual.
         */
        $inputHash =
            SearchRequestAIAnalysisService::buildInputHash(
                $searchRequestId
            );

        $st =
            $pdo->prepare("
                SELECT *
                FROM publication_ai_analyses
                WHERE entity_type = 'search_request'
                  AND entity_id = :entity_id
                  AND input_hash = :input_hash
                ORDER BY id DESC
                LIMIT 1
            ");

        $st->execute([
            'entity_id' =>
            $searchRequestId,

            'input_hash' =>
            $inputHash,
        ]);

        $row =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        return
            $row ?: null;
    }

    private static function sectionScore(
        array $sections,
        string $key,
        float $max
    ): float {
        $score =
            (float)(
                $sections[$key]['score']
                ?? 0
            );

        return
            self::clamp(
                $score,
                0,
                $max
            );
    }

    private static function aiWeightedScore(
        mixed $score,
        float $max
    ): ?float {
        if (
            $score === null ||
            !is_numeric($score)
        ) {
            return null;
        }

        $percentage =
            self::clamp(
                (float)$score,
                0,
                100
            ) / 100;

        return
            $percentage *
            $max;
    }

    private static function resolveObjectiveQualityLevel(
        float $objectiveScore
    ): string {
        /*
     * La parte objetiva vale 60 puntos.
     * La convertimos proporcionalmente a 100
     * únicamente para mantener compatible
     * el quality_level legacy.
     */
        $normalized =
            self::clamp(
                ($objectiveScore / 60) * 100,
                0,
                100
            );

        if ($normalized >= 85) {
            return 'excellent';
        }

        if ($normalized >= 60) {
            return 'good';
        }

        if ($normalized >= 40) {
            return 'basic';
        }

        return 'poor';
    }

    private static function resolveQualityLevel(
        float $score
    ): string {
        if ($score >= 90) {
            return 'excellent';
        }

        if ($score >= 80) {
            return 'very_good';
        }

        if ($score >= 70) {
            return 'good';
        }

        if ($score >= 50) {
            return 'needs_improvement';
        }

        return 'poor';
    }

    private static function decodeJsonArray(
        mixed $value
    ): array {
        if (
            $value === null ||
            trim((string)$value) === ''
        ) {
            return [];
        }

        $decoded =
            json_decode(
                (string)$value,
                true
            );

        return
            is_array($decoded)
            ? $decoded
            : [];
    }

    private static function clamp(
        float $value,
        float $min,
        float $max
    ): float {
        return
            max(
                $min,
                min(
                    $max,
                    $value
                )
            );
    }
}
