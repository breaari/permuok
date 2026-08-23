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

                    if (
                        !isset($seen[$key]) &&
                        self::isStructurallyContinuous(
                            $cycleEdges
                        ) &&
                        self::isEconomicallyViable(
                            $cycleEdges
                        )
                    ) {
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
                c.id,
                c.property_id,
                c.search_request_id,

                c.source_real_estate_id,
                c.target_real_estate_id,

                c.score,
                c.match_level,

                seo.id AS exchange_offer_id,
                seo.property_id AS offered_property_id,

                offered_property.price
                    AS offered_property_price,

                offered_property.currency
                    AS offered_property_currency,

                target_property.price
                    AS target_property_price,

                target_property.currency
                    AS target_property_currency,

                sr.payment_mode_cash,
                sr.payment_mode_swap,
                sr.cash_difference_max,
                sr.cash_difference_currency,

                pr.accepts_total_swap,
                pr.accepts_swap_plus_cash,
                pr.accepts_open_proposals

            FROM compatibilities c

            INNER JOIN search_requests sr
                ON sr.id = c.search_request_id

                AND sr.deleted_at IS NULL

                AND sr.status = 'published'

                AND sr.is_visible = 1

            INNER JOIN search_request_exchange_offers seo
                ON seo.search_request_id =
                    c.search_request_id

                AND seo.deleted_at IS NULL

                AND seo.property_id IS NOT NULL

            INNER JOIN properties offered_property
                ON offered_property.id =
                    seo.property_id

                AND offered_property.real_estate_id =
                    c.source_real_estate_id

                AND offered_property.deleted_at IS NULL

            INNER JOIN properties target_property
                ON target_property.id =
                    c.property_id

                AND target_property.real_estate_id =
                    c.target_real_estate_id

                AND target_property.deleted_at IS NULL

                AND target_property.status =
                    'published'

                AND target_property.is_visible = 1

            LEFT JOIN property_requirements pr
                ON pr.property_id =
                    target_property.id

                AND pr.deleted_at IS NULL

            WHERE
                c.compatibility_type =
                    'property_search_request'

                AND c.deleted_at IS NULL

                AND c.status IN (
                    'detected',
                    'one_side_interested',
                    'mutual_interest',
                    'chat_enabled'
                )

                AND c.score >= :min_score

                AND c.source_real_estate_id
                    <> c.target_real_estate_id

            ORDER BY
                c.source_real_estate_id ASC,
                c.score DESC,
                c.id ASC
        ");

        $st->execute([
            'min_score' =>
                self::MIN_EDGE_SCORE,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function isStructurallyContinuous(
        array $edges
    ): bool {
        $count = count($edges);

        if ($count < 3) {
            return false;
        }

        for ($i = 0; $i < $count; $i++) {
            $currentEdge = $edges[$i];

            $previousIndex =
                ($i - 1 + $count) % $count;

            $previousEdge =
                $edges[$previousIndex];

            $offeredPropertyId =
                (int)(
                    $currentEdge[
                        'offered_property_id'
                    ] ?? 0
                );

            $previousTargetPropertyId =
                (int)(
                    $previousEdge[
                        'property_id'
                    ] ?? 0
                );

            if (
                $offeredPropertyId <= 0 ||
                $previousTargetPropertyId <= 0 ||
                $offeredPropertyId !==
                    $previousTargetPropertyId
            ) {
                return false;
            }
        }

        return true;
    }

    private static function isEconomicallyViable(
        array $edges
    ): bool {
        foreach ($edges as $edge) {
            /*
             * Una operación multilateral necesita
             * que la búsqueda acepte permuta.
             */
            if (
                (int)($edge['payment_mode_swap'] ?? 0)
                !== 1
            ) {
                return false;
            }

            $offeredPrice =
                (float)(
                    $edge['offered_property_price']
                    ?? 0
                );

            $targetPrice =
                (float)(
                    $edge['target_property_price']
                    ?? 0
                );

            if (
                $offeredPrice <= 0 ||
                $targetPrice <= 0
            ) {
                return false;
            }

            $offeredCurrency =
                strtoupper(
                    trim(
                        (string)(
                            $edge[
                                'offered_property_currency'
                            ] ?? ''
                        )
                    )
                );

            $targetCurrency =
                strtoupper(
                    trim(
                        (string)(
                            $edge[
                                'target_property_currency'
                            ] ?? ''
                        )
                    )
                );

            /*
             * Por ahora no hacemos conversión
             * automática entre monedas.
             */
            if (
                $offeredCurrency === '' ||
                $targetCurrency === '' ||
                $offeredCurrency !==
                    $targetCurrency
            ) {
                return false;
            }

            $acceptsTotalSwap =
                (int)(
                    $edge['accepts_total_swap']
                    ?? 0
                ) === 1;

            $acceptsSwapPlusCash =
                (int)(
                    $edge[
                        'accepts_swap_plus_cash'
                    ] ?? 0
                ) === 1;

            $acceptsOpenProposals =
                (int)(
                    $edge[
                        'accepts_open_proposals'
                    ] ?? 0
                ) === 1;

            if (
                !$acceptsTotalSwap &&
                !$acceptsSwapPlusCash &&
                !$acceptsOpenProposals
            ) {
                return false;
            }

            /*
             * Diferencia que debe agregar quien
             * está buscando la propiedad.
             */
            $requiredCash =
                max(
                    0,
                    $targetPrice - $offeredPrice
                );

            /*
             * Si no necesita agregar dinero,
             * puede resolverse como permuta total
             * o propuesta abierta.
             */
            if ($requiredCash <= 0) {
                if (
                    !$acceptsTotalSwap &&
                    !$acceptsOpenProposals
                ) {
                    return false;
                }

                continue;
            }

            /*
             * Si necesita agregar dinero,
             * debe admitirse permuta + efectivo.
             */
            if (
                !$acceptsSwapPlusCash &&
                !$acceptsOpenProposals
            ) {
                return false;
            }

            if (
                (int)($edge['payment_mode_cash'] ?? 0)
                !== 1
            ) {
                return false;
            }

            $cashDifferenceMax =
                (float)(
                    $edge['cash_difference_max']
                    ?? 0
                );

            if (
                $cashDifferenceMax <= 0 ||
                $requiredCash >
                    $cashDifferenceMax
            ) {
                return false;
            }

            $cashCurrency =
                strtoupper(
                    trim(
                        (string)(
                            $edge[
                                'cash_difference_currency'
                            ] ?? ''
                        )
                    )
                );

            if (
                $cashCurrency !== '' &&
                $cashCurrency !==
                    $targetCurrency
            ) {
                return false;
            }
        }

        return true;
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
                    static function (
                        array $edge
                    ): array {
                        $offeredPrice =
                            (float)(
                                $edge[
                                    'offered_property_price'
                                ] ?? 0
                            );

                        $targetPrice =
                            (float)(
                                $edge[
                                    'target_property_price'
                                ] ?? 0
                            );

                        return [
                            'compatibility_id' =>
                                (int)$edge['id'],

                            'property_id' =>
                                (int)$edge[
                                    'property_id'
                                ],

                            'search_request_id' =>
                                (int)$edge[
                                    'search_request_id'
                                ],

                            'source_real_estate_id' =>
                                (int)$edge[
                                    'source_real_estate_id'
                                ],

                            'target_real_estate_id' =>
                                (int)$edge[
                                    'target_real_estate_id'
                                ],

                            'exchange_offer_id' =>
                                (int)$edge[
                                    'exchange_offer_id'
                                ],

                            'offered_property_id' =>
                                (int)$edge[
                                    'offered_property_id'
                                ],

                            'offered_value' =>
                                $offeredPrice,

                            'target_value' =>
                                $targetPrice,

                            'currency' =>
                                (string)$edge[
                                    'target_property_currency'
                                ],

                            'cash_difference' =>
                                round(
                                    max(
                                        0,
                                        $targetPrice -
                                        $offeredPrice
                                    ),
                                    2
                                ),

                            'score' =>
                                (float)$edge['score'],
                        ];
                    },
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