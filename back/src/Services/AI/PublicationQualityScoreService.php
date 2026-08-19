<?php

namespace App\Services\AI;

use PDO;
use Exception;

class PublicationQualityScoreService
{
    private const VERSION = '2.1';

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function getPropertyScore(
        int $propertyId
    ): ?array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        $objective =
            self::getObjectiveQuality(
                $propertyId
            );

        if ($objective === null) {
            return null;
        }

        /*
         * getCurrentPropertyAnalysis() ya verifica
         * que el análisis corresponda exactamente
         * al contenido actual de la propiedad.
         */
        $ai =
            PublicationAIAnalysisService::getCurrentPropertyAnalysis(
                $propertyId
            );

        /*
         * Mientras la IA no terminó, todavía no
         * existe un score oficial V2.
         */
        if (
            $ai === null ||
            ($ai['status'] ?? null) !== 'completed'
        ) {
            return [
                'status' => 'waiting_ai',

                'score' => null,

                'quality_level' => null,

                'objective_progress' =>
                self::calculateObjectiveProgress(
                    $objective
                ),

                'version' =>
                self::VERSION,
            ];
        }

        $scores =
            $ai['scores'] ?? [];

        /*
         * ================================
         * COMPONENTES OBJETIVOS
         * ================================
         */

        $structure =
            self::clamp(
                (float)$objective['structure_score_v2'],
                0,
                10
            );

        $location =
            self::normalize(
                (float)$objective['location_score'],
                20,
                10
            );

        $features =
            self::normalize(
                (float)$objective['features_score'],
                20,
                10
            );

        /*
         * Imágenes:
         *
         * 5 puntos objetivos
         * +
         * 10 puntos IA.
         */
        $mediaObjective =
            self::normalize(
                (float)$objective['media_score'],
                15,
                5
            );

        /*
         * Matching:
         *
         * 5 puntos objetivos
         * +
         * 5 puntos IA.
         */
        $matchingObjective =
            self::normalize(
                (float)$objective['matchability_score'],
                20,
                5
            );

        /*
         * ================================
         * COMPONENTES IA
         * ================================
         */

        $title =
            self::aiWeightedScore(
                $scores['title'] ?? null,
                10
            );

        $description =
            self::aiWeightedScore(
                $scores['description'] ?? null,
                15
            );

        $mediaAI =
            self::aiWeightedScore(
                $scores['images'] ?? null,
                10
            );

        $consistency =
            self::aiWeightedScore(
                $scores['consistency'] ?? null,
                10
            );

        $professionalism =
            self::aiWeightedScore(
                $scores['professionalism'] ?? null,
                10
            );

        $matchingAI =
            self::aiWeightedScore(
                $scores['matchability'] ?? null,
                5
            );

        /*
         * Todos estos scores son obligatorios
         * para considerar completo el índice V2.
         */
        if (
            $title === null ||
            $description === null ||
            $mediaAI === null ||
            $consistency === null ||
            $professionalism === null ||
            $matchingAI === null
        ) {
            return [
                'status' => 'waiting_ai',

                'score' => null,

                'quality_level' => null,

                'objective_progress' =>
                self::calculateObjectiveProgress(
                    $objective
                ),

                'version' =>
                self::VERSION,
            ];
        }

        /*
         * ================================
         * SCORE HÍBRIDO
         * ================================
         */

        $images =
            $mediaObjective +
            $mediaAI;

        $matchability =
            $matchingObjective +
            $matchingAI;

        $rawScore =
            $structure +
            $location +
            $features +
            $title +
            $description +
            $images +
            $consistency +
            $professionalism +
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
 * ================================
 * FLAGS CRÍTICOS
 * ================================
 *
 * Los flags pueden surgir de:
 *
 * 1. reglas objetivas verificables;
 * 2. análisis semántico IA.
 *
 * No interpretamos textos de sugerencias
 * para decidir caps.
 */
        $flags = [];

        /*
 * Sin imágenes.
 *
 * Una publicación sin ninguna imagen
 * no puede alcanzar un nivel de calidad
 * normal aunque el resto esté completo.
 *
 * media_score = 0 implica que no hay
 * imágenes válidas cargadas.
 */
        if (
            (float)($objective['media_score'] ?? 0) <= 0
        ) {
            $flags[] = [
                'code' =>
                'no_images',

                'severity' =>
                'critical',

                'message' =>
                'La publicación no tiene imágenes cargadas.',

                'max_score' =>
                49,
            ];
        }

        /*
 * Flags semánticos detectados por IA.
 */
        $aiFlags =
            is_array($ai['quality_flags'] ?? null)
            ? $ai['quality_flags']
            : [];

        $aiCaps = [
            'critical_contradiction' => 69,
            'junk_content' => 69,
            'severe_unprofessional_content' => 69,
            'unusable_matchability' => 69,
        ];

        foreach ($aiFlags as $flag) {
            if (!is_array($flag)) {
                continue;
            }

            $code =
                trim(
                    (string)($flag['code'] ?? '')
                );

            if (
                $code === '' ||
                !array_key_exists(
                    $code,
                    $aiCaps
                )
            ) {
                continue;
            }

            $flags[] = [
                'code' =>
                $code,

                'severity' =>
                trim(
                    (string)(
                        $flag['severity']
                        ?? 'high'
                    )
                ),

                'message' =>
                trim(
                    (string)(
                        $flag['message']
                        ?? ''
                    )
                ),

                'max_score' =>
                $aiCaps[$code],
            ];
        }

        /*
 * Partimos del score calculado normalmente.
 */
        $score = $rawScore;

        /*
 * Si existen varios flags, se aplica
 * siempre el límite más restrictivo.
 */
        foreach ($flags as $flag) {
            $maxScore =
                (float)($flag['max_score'] ?? 100);

            $score =
                min(
                    $score,
                    $maxScore
                );
        }

        $score =
            round(
                self::clamp(
                    $score,
                    0,
                    100
                ),
                2
            );

        $qualityLevel =
            self::resolveQualityLevel(
                $score
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
                'structure' => [
                    'score' =>
                    round($structure, 2),

                    'max_score' =>
                    10,
                ],

                'location' => [
                    'score' =>
                    round($location, 2),

                    'max_score' =>
                    10,
                ],

                'features' => [
                    'score' =>
                    round($features, 2),

                    'max_score' =>
                    10,
                ],

                'title' => [
                    'score' =>
                    round($title, 2),

                    'max_score' =>
                    10,
                ],

                'description' => [
                    'score' =>
                    round($description, 2),

                    'max_score' =>
                    15,
                ],

                'images' => [
                    'score' =>
                    round($images, 2),

                    'max_score' =>
                    15,

                    'objective_score' =>
                    round(
                        $mediaObjective,
                        2
                    ),

                    'ai_score' =>
                    round(
                        $mediaAI,
                        2
                    ),
                ],

                'consistency' => [
                    'score' =>
                    round(
                        $consistency,
                        2
                    ),

                    'max_score' =>
                    10,
                ],

                'professionalism' => [
                    'score' =>
                    round(
                        $professionalism,
                        2
                    ),

                    'max_score' =>
                    10,
                ],

                'matchability' => [
                    'score' =>
                    round(
                        $matchability,
                        2
                    ),

                    'max_score' =>
                    10,

                    'objective_score' =>
                    round(
                        $matchingObjective,
                        2
                    ),

                    'ai_score' =>
                    round(
                        $matchingAI,
                        2
                    ),
                ],
            ],

            'sources' => [
                'objective_algorithm_version' =>
                $objective['algorithm_version'] ?? null,

                'ai_analysis_id' =>
                $ai['id'] ?? null,

                'ai_prompt_version' =>
                $ai['prompt_version'] ?? null,
            ],

            'version' =>
            self::VERSION,
        ];
    }
    public static function recalculateAndPersistPropertyScore(
        int $propertyId
    ): ?array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        $result =
            self::getPropertyScore(
                $propertyId
            );

        if (
            $result === null ||
            ($result['status'] ?? null) !== 'completed'
        ) {
            return $result;
        }

        $sources =
            $result['sources'] ?? [];
        try {
            $flagsJson =
                json_encode(
                    $result['flags'] ?? [],
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                );
        } catch (\JsonException $e) {
            throw new Exception(
                'No se pudieron serializar los flags oficiales de calidad.',
                0,
                $e
            );
        }
        $pdo = self::db();

        $st = $pdo->prepare("
        UPDATE publication_quality_scores

        SET
            official_score =
                :official_score,

            official_quality_level =
                :official_quality_level,

            official_score_version =
                :official_score_version,

            official_ai_analysis_id =
                :official_ai_analysis_id,
official_ai_prompt_version =
    :official_ai_prompt_version,

official_flags_json =
    :official_flags_json,

official_calculated_at =
    NOW()
        WHERE entity_type = 'property'
          AND entity_id = :entity_id

        LIMIT 1
    ");

        $st->execute([
            'official_score' =>
            $result['score'],

            'official_quality_level' =>
            $result['quality_level'],

            'official_score_version' =>
            self::VERSION,

            'official_ai_analysis_id' =>
            $sources['ai_analysis_id'] ?? null,

            'official_ai_prompt_version' =>
            $sources['ai_prompt_version'] ?? null,
            'official_flags_json' =>
            $flagsJson,
            'entity_id' =>
            $propertyId,
        ]);

        return $result;
    }
    private static function getObjectiveQuality(
        int $propertyId
    ): ?array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT
                score,
                basic_score,
                structure_score_v2,
                location_score,
                features_score,
                media_score,
                matchability_score,
                algorithm_version,
                analyzed_at
            FROM publication_quality_scores
            WHERE entity_type = 'property'
              AND entity_id = :entity_id
            LIMIT 1
        ");

        $st->execute([
            'entity_id' =>
            $propertyId,
        ]);

        $row =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private static function normalize(
        float $score,
        float $currentMax,
        float $targetMax
    ): float {
        if ($currentMax <= 0) {
            return 0.0;
        }

        $percentage =
            self::clamp(
                $score / $currentMax,
                0,
                1
            );

        return
            $percentage *
            $targetMax;
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

    private static function calculateObjectiveProgress(
        array $objective
    ): float {
        /*
         * Parte objetiva del índice:
         *
         * structure     10
         * location      10
         * features      10
         * media          5
         * matchability   5
         *
         * Total:        40
         */

        $score =
            self::clamp(
                (float)(
                    $objective['structure_score_v2'] ?? 0
                ),
                0,
                10
            );

        $score +=
            self::normalize(
                (float)(
                    $objective['location_score'] ?? 0
                ),
                20,
                10
            );

        $score +=
            self::normalize(
                (float)(
                    $objective['features_score'] ?? 0
                ),
                20,
                10
            );

        $score +=
            self::normalize(
                (float)(
                    $objective['media_score'] ?? 0
                ),
                15,
                5
            );

        $score +=
            self::normalize(
                (float)(
                    $objective['matchability_score'] ?? 0
                ),
                20,
                5
            );

        return round(
            ($score / 40) * 100,
            2
        );
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

    private static function clamp(
        float $value,
        float $min,
        float $max
    ): float {
        return max(
            $min,
            min(
                $max,
                $value
            )
        );
    }
}
