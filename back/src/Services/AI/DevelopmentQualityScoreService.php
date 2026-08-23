<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class DevelopmentQualityScoreService
{
    private const VERSION = '1.0';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function getScore(
        int $developmentId
    ): array {
        if ($developmentId <= 0) {
            throw new Exception(
                'El ID del desarrollo no es válido.'
            );
        }

        /*
         * ========================================
         * PARTE OBJETIVA
         * ========================================
         *
         * project        10
         * location       10
         * commercial     15
         * unit_types     15
         * amenities       5
         * images          5
         *
         * Total          60
         */
        $objective =
            DevelopmentQualityService::analyze(
                $developmentId
            );

        $objectiveSections =
            $objective['sections'] ?? [];

        $project =
            self::sectionScore(
                $objectiveSections,
                'project',
                10
            );

        $location =
            self::sectionScore(
                $objectiveSections,
                'location',
                10
            );

        $commercial =
            self::sectionScore(
                $objectiveSections,
                'commercial',
                15
            );

        $unitTypes =
            self::sectionScore(
                $objectiveSections,
                'unit_types',
                15
            );

        $amenities =
            self::sectionScore(
                $objectiveSections,
                'amenities',
                5
            );

        $images =
            self::sectionScore(
                $objectiveSections,
                'images',
                5
            );

        $objectiveScore =
            round(
                $project +
                    $location +
                    $commercial +
                    $unitTypes +
                    $amenities +
                    $images,
                2
            );

        /*
         * Buscamos únicamente un análisis IA
         * correspondiente exactamente al contenido
         * actual del desarrollo.
         */
        $ai =
            self::getCurrentAIAnalysis(
                $developmentId
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
                    'project' => [
                        'score' =>
                            $project,

                        'max_score' =>
                            10,
                    ],

                    'location' => [
                        'score' =>
                            $location,

                        'max_score' =>
                            10,
                    ],

                    'commercial' => [
                        'score' =>
                            $commercial,

                        'max_score' =>
                            15,
                    ],

                    'unit_types' => [
                        'score' =>
                            $unitTypes,

                        'max_score' =>
                            15,
                    ],

                    'amenities' => [
                        'score' =>
                            $amenities,

                        'max_score' =>
                            5,
                    ],

                    'images' => [
                        'score' =>
                            $images,

                        'max_score' =>
                            5,
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
         * title             10
         * description       15
         * consistency       10
         * matchability       5
         *
         * Total             40
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

                'sections' => [
                    'project' => [
                        'score' => $project,
                        'max_score' => 10,
                    ],

                    'location' => [
                        'score' => $location,
                        'max_score' => 10,
                    ],

                    'commercial' => [
                        'score' => $commercial,
                        'max_score' => 15,
                    ],

                    'unit_types' => [
                        'score' => $unitTypes,
                        'max_score' => 15,
                    ],

                    'amenities' => [
                        'score' => $amenities,
                        'max_score' => 5,
                    ],

                    'images' => [
                        'score' => $images,
                        'max_score' => 5,
                    ],
                ],

                'version' =>
                    self::VERSION,
            ];
        }

        $rawScore =
            $project +
            $location +
            $commercial +
            $unitTypes +
            $amenities +
            $images +
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
         * Por ahora no agregamos caps críticos.
         *
         * Más adelante podemos definir flags como:
         *
         * unusable_development
         * critical_contradiction
         *
         * si aparecen casos donde el desarrollo
         * debe tener un techo de score.
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
                'project' => [
                    'score' =>
                        round($project, 2),

                    'max_score' =>
                        10,
                ],

                'location' => [
                    'score' =>
                        round($location, 2),

                    'max_score' =>
                        10,
                ],

                'commercial' => [
                    'score' =>
                        round($commercial, 2),

                    'max_score' =>
                        15,
                ],

                'unit_types' => [
                    'score' =>
                        round($unitTypes, 2),

                    'max_score' =>
                        15,
                ],

                'amenities' => [
                    'score' =>
                        round($amenities, 2),

                    'max_score' =>
                        5,
                ],

                'images' => [
                    'score' =>
                        round($images, 2),

                    'max_score' =>
                        5,
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
        int $developmentId
    ): array {
        $result =
            self::getScore(
                $developmentId
            );

        self::persist(
            $developmentId,
            $result
        );

        return $result;
    }

    private static function persist(
        int $developmentId,
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

        $objectiveSections = [
            'project' =>
                $sections['project']
                ?? null,

            'location' =>
                $sections['location']
                ?? null,

            'commercial' =>
                $sections['commercial']
                ?? null,

            'unit_types' =>
                $sections['unit_types']
                ?? null,

            'amenities' =>
                $sections['amenities']
                ?? null,

            'images' =>
                $sections['images']
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
                'No se pudo serializar el score del desarrollo.',
                0,
                $e
            );
        }

        $objectiveScore = 0.0;

        foreach (
            [
                'project',
                'location',
                'commercial',
                'unit_types',
                'amenities',
                'images',
            ]
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
                    'development',
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
                $developmentId,

            'score' =>
                round(
                    $objectiveScore,
                    2
                ),

            'quality_level' =>
                self::resolveObjectiveQualityLevel(
                    $objectiveScore
                ),

            'suggestions_json' =>
                $suggestionsJson,

            'objective_sections_json' =>
                $objectiveSectionsJson,

            'algorithm_version' =>
                'development-' .
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
        int $developmentId
    ): ?array {
        $pdo =
            self::db();

        /*
         * DevelopmentAIAnalysisService se crea
         * en el siguiente paso.
         *
         * El hash debe representar exactamente
         * el contenido actual del desarrollo.
         */
        $inputHash =
            DevelopmentAIAnalysisService::buildInputHash(
                $developmentId
            );

        $st =
            $pdo->prepare("
                SELECT *
                FROM publication_ai_analyses
                WHERE entity_type = 'development'
                  AND entity_id = :entity_id
                  AND input_hash = :input_hash
                ORDER BY id DESC
                LIMIT 1
            ");

        $st->execute([
            'entity_id' =>
                $developmentId,

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