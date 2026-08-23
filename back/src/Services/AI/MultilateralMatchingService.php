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
                            $cycleEdges
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
pr.accepts_multiple_swap,
pr.accepts_open_proposals,
pr.accepts_cash_only,

pr.cash_difference_direction
    AS owner_difference_direction,

pr.cash_difference_min
    AS owner_difference_min,

pr.cash_difference_max
    AS owner_difference_max,

pr.cash_difference_currency
    AS owner_difference_currency

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
                    $currentEdge['offered_property_id'] ?? 0
                );

            $previousTargetPropertyId =
                (int)(
                    $previousEdge['property_id'] ?? 0
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
         * El participante que busca debe aceptar
         * resolver la operación mediante permuta.
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
                            $edge['offered_property_currency'] ?? ''
                        )
                    )
                );

            $targetCurrency =
                strtoupper(
                    trim(
                        (string)(
                            $edge['target_property_currency'] ?? ''
                        )
                    )
                );

            /*
         * Regla de producto:
         * una operación multilateral se calcula
         * solamente en una misma moneda.
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
                    $edge['accepts_swap_plus_cash'] ?? 0
                ) === 1;

            $acceptsOpenProposals =
                (int)(
                    $edge['accepts_open_proposals'] ?? 0
                ) === 1;

            if (
                !$acceptsTotalSwap &&
                !$acceptsSwapPlusCash &&
                !$acceptsOpenProposals
            ) {
                return false;
            }

            /*
         * Diferencia firmada desde el punto de
         * vista del dueño de la propiedad objetivo.
         *
         * > 0 = recibe dinero (a favor).
         * < 0 = debe entregar dinero (en contra).
         * = 0 = permuta pareja.
         */
            $signedDifference =
                round(
                    $targetPrice -
                        $offeredPrice,
                    2
                );

            $differenceAmount =
                abs($signedDifference);

            if ($differenceAmount <= 0) {
                if (
                    !$acceptsTotalSwap &&
                    !$acceptsOpenProposals
                ) {
                    return false;
                }

                continue;
            }

            if (
                !$acceptsSwapPlusCash &&
                !$acceptsOpenProposals
            ) {
                return false;
            }

            $actualDirection =
                $signedDifference > 0
                ? 'a_favor'
                : 'en_contra';

            $ownerDirection =
                trim(
                    (string)(
                        $edge['owner_difference_direction'] ?? ''
                    )
                );

            if (
                $ownerDirection !== '' &&
                $ownerDirection !== 'indistinto' &&
                $ownerDirection !==
                $actualDirection
            ) {
                return false;
            }

            $ownerCurrency =
                strtoupper(
                    trim(
                        (string)(
                            $edge['owner_difference_currency'] ?? ''
                        )
                    )
                );

            if (
                $ownerCurrency !== '' &&
                $ownerCurrency !==
                $targetCurrency
            ) {
                return false;
            }

            $ownerMin =
                self::nullablePositiveFloat(
                    $edge['owner_difference_min'] ?? null
                );

            $ownerMax =
                self::nullablePositiveFloat(
                    $edge['owner_difference_max'] ?? null
                );

            if (
                $ownerMin !== null &&
                $differenceAmount < $ownerMin
            ) {
                return false;
            }

            if (
                $ownerMax !== null &&
                $differenceAmount > $ownerMax
            ) {
                return false;
            }

            /*
         * Si la diferencia es a favor del dueño
         * objetivo, quien busca debe aportar dinero.
         */
            if ($actualDirection === 'a_favor') {
                if (
                    (int)(
                        $edge['payment_mode_cash'] ?? 0
                    ) !== 1
                ) {
                    return false;
                }

                $availableCash =
                    self::nullablePositiveFloat(
                        $edge['cash_difference_max'] ?? null
                    );

                if (
                    $availableCash === null ||
                    $availableCash <
                    $differenceAmount
                ) {
                    return false;
                }

                $availableCurrency =
                    strtoupper(
                        trim(
                            (string)(
                                $edge['cash_difference_currency'] ?? ''
                            )
                        )
                    );

                if (
                    $availableCurrency !==
                    $targetCurrency
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function nullablePositiveFloat(
        mixed $value
    ): ?float {
        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        $number = (float)$value;

        return $number >= 0
            ? $number
            : null;
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

        $averageScore =
            $scores !== []
            ? array_sum($scores) /
            count($scores)
            : 0.0;

        $minimumScore =
            $scores !== []
            ? min($scores)
            : 0.0;

        /*
 * En una operación circular importa mucho
 * el eslabón más débil.
 *
 * 60% peor compatibilidad
 * 40% promedio general.
 */
        $score = round(
            ($minimumScore * 0.60) +
                ($averageScore * 0.40),
            2
        );

        return [
            'participants_count' =>
            count($participants),

            'real_estate_ids' =>
            array_values($participants),

            'score' =>
            $score,
            'minimum_edge_score' =>
            round($minimumScore, 2),

            'average_edge_score' =>
            round($averageScore, 2),
            'compatibilities' =>
            array_map(
                static function (
                    array $edge
                ): array {
                    $offeredPrice =
                        (float)(
                            $edge['offered_property_price'] ?? 0
                        );

                    $targetPrice =
                        (float)(
                            $edge['target_property_price'] ?? 0
                        );

                    return [
                        'compatibility_id' =>
                        (int)$edge['id'],

                        'property_id' =>
                        (int)$edge['property_id'],

                        'search_request_id' =>
                        (int)$edge['search_request_id'],

                        'source_real_estate_id' =>
                        (int)$edge['source_real_estate_id'],

                        'target_real_estate_id' =>
                        (int)$edge['target_real_estate_id'],

                        'exchange_offer_id' =>
                        (int)$edge['exchange_offer_id'],

                        'offered_property_id' =>
                        (int)$edge['offered_property_id'],

                        'offered_value' =>
                        $offeredPrice,

                        'target_value' =>
                        $targetPrice,

                        'currency' =>
                        (string)$edge['target_property_currency'],

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
        array $edges
    ): string {
        $tokens = array_map(
            static fn(array $edge): string =>
            (int)$edge['id'] . ':' .
                (int)$edge['property_id'] . ':' .
                (int)$edge['offered_property_id'],
            $edges
        );

        $variants = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $variant = array_merge(
                array_slice($tokens, $i),
                array_slice($tokens, 0, $i)
            );

            $variants[] =
                implode('|', $variant);
        }

        sort(
            $variants,
            SORT_STRING
        );

        return $variants[0];
    }
}
