<?php

namespace App\Services\AI;

use PDO;
use Throwable;

class MultilateralOperationService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function recalculate(): array
    {
        $pdo = self::db();

        $cycles =
            MultilateralMatchingService::findCycles();

        $activeKeys = [];

        $created = 0;
        $updated = 0;

        foreach ($cycles as $cycle) {
            $result =
                self::persistCycle(
                    $pdo,
                    $cycle
                );

            $activeKeys[] =
                $result['cycle_key'];

            if ($result['created']) {
                $created++;
            } else {
                $updated++;
            }
        }

        $archived =
            self::archiveMissing(
                $pdo,
                $activeKeys
            );

        return [
            'cycles_detected' =>
                count($cycles),

            'operations_created' =>
                $created,

            'operations_updated' =>
                $updated,

            'operations_archived' =>
                $archived,
        ];
    }

    private static function persistCycle(
        PDO $pdo,
        array $cycle
    ): array {
        $compatibilities =
            $cycle['compatibilities']
            ?? [];

        $cycleKey =
            self::buildCycleHash(
                $compatibilities
            );

        $pdo->beginTransaction();

        try {
            $stExisting =
                $pdo->prepare("
                    SELECT
                        id,
                        score,
                        minimum_edge_score,
                        average_edge_score,
                        status

                    FROM multilateral_operations

                    WHERE cycle_key =
                        :cycle_key

                    LIMIT 1

                    FOR UPDATE
                ");

            $stExisting->execute([
                'cycle_key' =>
                    $cycleKey,
            ]);

            $existing =
                $stExisting->fetch(
                    PDO::FETCH_ASSOC
                );

            $created =
                !$existing;

            if ($created) {
                $st = $pdo->prepare("
                    INSERT INTO multilateral_operations (
                        cycle_key,
                        participants_count,
                        score,
                        minimum_edge_score,
                        average_edge_score,
                        status,
                        detected_at,
                        last_seen_at
                    ) VALUES (
                        :cycle_key,
                        :participants_count,
                        :score,
                        :minimum_edge_score,
                        :average_edge_score,
                        'detected',
                        NOW(),
                        NOW()
                    )
                ");

                $st->execute([
                    'cycle_key' =>
                        $cycleKey,

                    'participants_count' =>
                        (int)$cycle[
                            'participants_count'
                        ],

                    'score' =>
                        (float)$cycle['score'],

                    'minimum_edge_score' =>
                        (float)$cycle[
                            'minimum_edge_score'
                        ],

                    'average_edge_score' =>
                        (float)$cycle[
                            'average_edge_score'
                        ],
                ]);

                $operationId =
                    (int)$pdo->lastInsertId();

                self::createEvent(
                    $pdo,
                    'multilateral_new',
                    $operationId,
                    $cycle
                );
            } else {
                $operationId =
                    (int)$existing['id'];

                $materiallyChanged =
                    abs(
                        (float)$existing['score'] -
                        (float)$cycle['score']
                    ) >= 5.0
                    ||
                    $existing['status'] ===
                        'archived';

                $st = $pdo->prepare("
                    UPDATE multilateral_operations
                    SET
                        participants_count =
                            :participants_count,

                        score =
                            :score,

                        minimum_edge_score =
                            :minimum_edge_score,

                        average_edge_score =
                            :average_edge_score,

                        status =
                            'detected',

                        last_seen_at =
                            NOW(),

                        archived_at =
                            NULL

                    WHERE id =
                        :id
                ");

                $st->execute([
                    'participants_count' =>
                        (int)$cycle[
                            'participants_count'
                        ],

                    'score' =>
                        (float)$cycle['score'],

                    'minimum_edge_score' =>
                        (float)$cycle[
                            'minimum_edge_score'
                        ],

                    'average_edge_score' =>
                        (float)$cycle[
                            'average_edge_score'
                        ],

                    'id' =>
                        $operationId,
                ]);

                if ($materiallyChanged) {
                    self::createEvent(
                        $pdo,
                        'multilateral_changed',
                        $operationId,
                        $cycle
                    );
                }
            }

            /*
             * Las legs son snapshot de la
             * última evaluación válida.
             */
            $pdo->prepare("
                DELETE FROM
                    multilateral_operation_legs

                WHERE operation_id =
                    :operation_id
            ")->execute([
                'operation_id' =>
                    $operationId,
            ]);

            $stLeg =
                $pdo->prepare("
                    INSERT INTO
                    multilateral_operation_legs (
                        operation_id,
                        position,
                        compatibility_id,
                        search_request_id,
                        source_real_estate_id,
                        target_real_estate_id,
                        property_id,
                        offered_property_id,
                        exchange_offer_id,
                        score,
                        offered_value,
                        offered_original_value,
                        offered_original_currency,
                        target_value,
                        comparison_currency,
                        signed_cash_difference,
                        cash_difference,
                        cash_difference_direction
                    ) VALUES (
                        :operation_id,
                        :position,
                        :compatibility_id,
                        :search_request_id,
                        :source_real_estate_id,
                        :target_real_estate_id,
                        :property_id,
                        :offered_property_id,
                        :exchange_offer_id,
                        :score,
                        :offered_value,
                        :offered_original_value,
                        :offered_original_currency,
                        :target_value,
                        :comparison_currency,
                        :signed_cash_difference,
                        :cash_difference,
                        :cash_difference_direction
                    )
                ");

            foreach (
                $compatibilities
                as $index => $leg
            ) {
                $stLeg->execute([
                    'operation_id' =>
                        $operationId,

                    'position' =>
                        $index + 1,

                    'compatibility_id' =>
                        (int)$leg[
                            'compatibility_id'
                        ],

                    'search_request_id' =>
                        (int)$leg[
                            'search_request_id'
                        ],

                    'source_real_estate_id' =>
                        (int)$leg[
                            'source_real_estate_id'
                        ],

                    'target_real_estate_id' =>
                        (int)$leg[
                            'target_real_estate_id'
                        ],

                    'property_id' =>
                        (int)$leg[
                            'property_id'
                        ],

                    'offered_property_id' =>
                        (int)$leg[
                            'offered_property_id'
                        ],

                    'exchange_offer_id' =>
                        (int)$leg[
                            'exchange_offer_id'
                        ],

                    'score' =>
                        (float)$leg['score'],

                    'offered_value' =>
                        (float)$leg[
                            'offered_value'
                        ],

                    'offered_original_value' =>
                        (float)$leg[
                            'offered_original_value'
                        ],

                    'offered_original_currency' =>
                        (string)$leg[
                            'offered_original_currency'
                        ],

                    'target_value' =>
                        (float)$leg[
                            'target_value'
                        ],

                    'comparison_currency' =>
                        (string)$leg[
                            'currency'
                        ],

                    'signed_cash_difference' =>
                        (float)$leg[
                            'signed_cash_difference'
                        ],

                    'cash_difference' =>
                        (float)$leg[
                            'cash_difference'
                        ],

                    'cash_difference_direction' =>
                        (string)$leg[
                            'cash_difference_direction'
                        ],
                ]);
            }

            $pdo->commit();

            return [
                'operation_id' =>
                    $operationId,

                'cycle_key' =>
                    $cycleKey,

                'created' =>
                    $created,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    private static function archiveMissing(
        PDO $pdo,
        array $activeKeys
    ): int {
        $params = [];

        $sql = "
            SELECT id
            FROM multilateral_operations
            WHERE status = 'detected'
        ";

        if ($activeKeys !== []) {
            $placeholders = [];

            foreach (
                $activeKeys
                as $index => $key
            ) {
                $name =
                    'cycle_key_' . $index;

                $placeholders[] =
                    ':' . $name;

                $params[$name] =
                    $key;
            }

            $sql .= "
                AND cycle_key NOT IN (
                    " .
                    implode(
                        ', ',
                        $placeholders
                    ) .
                    "
                )
            ";
        }

        $st =
            $pdo->prepare($sql);

        $st->execute($params);

        $ids =
            $st->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        foreach ($ids as $id) {
            $id = (int)$id;

            $pdo->beginTransaction();

            try {
                $pdo->prepare("
                    UPDATE multilateral_operations
                    SET
                        status = 'archived',
                        archived_at = NOW()

                    WHERE id = :id
                      AND status = 'detected'
                ")->execute([
                    'id' => $id,
                ]);

                self::createEvent(
                    $pdo,
                    'multilateral_lost',
                    $id,
                    null
                );

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        return count($ids);
    }

    private static function createEvent(
        PDO $pdo,
        string $eventType,
        int $operationId,
        ?array $payload
    ): void {
        $st =
            $pdo->prepare("
                INSERT INTO match_events (
                    event_type,
                    entity_type,
                    entity_id,
                    payload_json,
                    occurred_at
                ) VALUES (
                    :event_type,
                    'multilateral_operation',
                    :entity_id,
                    :payload_json,
                    NOW()
                )
            ");

        $st->execute([
            'event_type' =>
                $eventType,

            'entity_id' =>
                $operationId,

            'payload_json' =>
                $payload !== null
                    ? json_encode(
                        $payload,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    )
                    : null,
        ]);
    }

    private static function buildCycleHash(
        array $legs
    ): string {
        $tokens = [];

        foreach ($legs as $leg) {
            $tokens[] =
                (int)$leg['compatibility_id'] .
                ':' .
                (int)$leg['property_id'] .
                ':' .
                (int)$leg['offered_property_id'];
        }

        if ($tokens === []) {
            throw new \RuntimeException(
                'El ciclo multilateral no contiene legs.'
            );
        }

        /*
         * Sólo rotaciones.
         * Invertir el circuito representa
         * otra operación dirigida.
         */
        $variants = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $variants[] =
                implode(
                    '|',
                    array_merge(
                        array_slice(
                            $tokens,
                            $i
                        ),
                        array_slice(
                            $tokens,
                            0,
                            $i
                        )
                    )
                );
        }

        sort(
            $variants,
            SORT_STRING
        );

        return hash(
            'sha256',
            $variants[0]
        );
    }
}