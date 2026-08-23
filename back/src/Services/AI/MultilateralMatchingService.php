<?php

namespace App\Services\AI;

use PDO;

class MultilateralMatchingService
{
    private const MIN_EDGE_SCORE = 75.0;
    private const MAX_PARTICIPANTS = 4;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function findCycles(): array
    {
        $pdo = self::db();

        $edges = self::getEligibleEdges($pdo);

        if ($edges === []) {
            return [];
        }

        $graph = [];

        foreach ($edges as $edge) {
            $source =
                (int)$edge['source_real_estate_id'];

            $target =
                (int)$edge['target_real_estate_id'];

            $graph[$source][] = $edge;
        }

        $cycles = [];
        $seen = [];

        foreach (array_keys($graph) as $start) {
            self::walk(
                $graph,
                (int)$start,
                (int)$start,
                [],
                [],
                $cycles,
                $seen
            );
        }

        usort(
            $cycles,
            static fn(array $a, array $b): int =>
                $b['score'] <=> $a['score']
        );

        return $cycles;
    }

    private static function walk(
        array $graph,
        int $start,
        int $current,
        array $participants,
        array $edges,
        array &$cycles,
        array &$seen
    ): void {
        $participants[] = $current;

        if (
            count($participants) >
            self::MAX_PARTICIPANTS
        ) {
            return;
        }

        foreach ($graph[$current] ?? [] as $edge) {
            $next =
                (int)$edge['target_real_estate_id'];

            /*
             * Cerramos un ciclo solamente con
             * 3 o 4 inmobiliarias.
             */
            if ($next === $start) {
                if (
                    count($participants) >= 3 &&
                    count($participants) <=
                        self::MAX_PARTICIPANTS
                ) {
                    $cycleEdges =
                        array_merge(
                            $edges,
                            [$edge]
                        );

                    $key =
                        self::buildCycleKey(
                            $participants
                        );

                    if (!isset($seen[$key])) {
                        $seen[$key] = true;

                        $cycles[] =
                            self::buildCycleResult(
                                $participants,
                                $cycleEdges
                            );
                    }
                }

                continue;
            }

            /*
             * Una inmobiliaria no puede aparecer
             * dos veces dentro de la misma operación.
             */
            if (
                in_array(
                    $next,
                    $participants,
                    true
                )
            ) {
                continue;
            }

            if (
                count($participants) >=
                self::MAX_PARTICIPANTS
            ) {
                continue;
            }

            self::walk(
                $graph,
                $start,
                $next,
                $participants,
                array_merge(
                    $edges,
                    [$edge]
                ),
                $cycles,
                $seen
            );
        }
    }

    private static function getEligibleEdges(
        PDO $pdo
    ): array {
        $st = $pdo->prepare("
            SELECT
                id,
                property_id,
                search_request_id,

                source_real_estate_id,
                target_real_estate_id,

                score,
                match_level

            FROM compatibilities

            WHERE
                compatibility_type =
                    'property_search_request'

                AND deleted_at IS NULL

                AND status IN (
                    'detected',
                    'one_side_interested',
                    'mutual_interest',
                    'chat_enabled'
                )

                AND score >= :min_score

                AND source_real_estate_id
                    <> target_real_estate_id

            ORDER BY
                source_real_estate_id ASC,
                score DESC,
                id ASC
        ");

        $st->execute([
            'min_score' =>
                self::MIN_EDGE_SCORE,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function buildCycleResult(
        array $participants,
        array $edges
    ): array {
        $scores = array_map(
            static fn(array $edge): float =>
                (float)$edge['score'],
            $edges
        );

        /*
         * Usamos el promedio de las aristas
         * como primer score global.
         *
         * Después agregaremos viabilidad económica.
         */
        $score = $scores !== []
            ? round(
                array_sum($scores) /
                count($scores),
                2
            )
            : 0.0;

        return [
            'participants_count' =>
                count($participants),

            'real_estate_ids' =>
                array_values($participants),

            'score' =>
                $score,

            'compatibilities' =>
                array_map(
                    static fn(array $edge): array => [
                        'compatibility_id' =>
                            (int)$edge['id'],

                        'property_id' =>
                            (int)$edge['property_id'],

                        'search_request_id' =>
                            (int)$edge['search_request_id'],

                        'source_real_estate_id' =>
                            (int)$edge[
                                'source_real_estate_id'
                            ],

                        'target_real_estate_id' =>
                            (int)$edge[
                                'target_real_estate_id'
                            ],

                        'score' =>
                            (float)$edge['score'],
                    ],
                    $edges
                ),
        ];
    }

    private static function buildCycleKey(
        array $participants
    ): string {
        $ids = array_map(
            'intval',
            $participants
        );

        $variants = [];

        $count = count($ids);

        for ($i = 0; $i < $count; $i++) {
            $variant = array_merge(
                array_slice($ids, $i),
                array_slice($ids, 0, $i)
            );

            $variants[] =
                implode('-', $variant);
        }

        sort(
            $variants,
            SORT_STRING
        );

        return $variants[0];
    }
}