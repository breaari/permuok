<?php

namespace App\Services;

use PDO;
use Exception;
use Throwable;

class MultilateralOperationResponseService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../db.php';

        return pdo();
    }

    public static function respond(
        int $userId,
        int $operationId,
        string $response
    ): array {
        if ($operationId <= 0) {
            throw new Exception(
                'Operación multilateral inválida.',
                422
            );
        }

        if (
            !in_array(
                $response,
                ['interested', 'declined'],
                true
            )
        ) {
            throw new Exception(
                'Respuesta inválida.',
                422
            );
        }

        $pdo = self::db();

        $pdo->beginTransaction();

        try {
            $stUser = $pdo->prepare("
                SELECT
                    id,
                    real_estate_id
                FROM users
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND is_active = 1
                LIMIT 1
            ");

            $stUser->execute([
                'id' => $userId,
            ]);

            $user = $stUser->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$user) {
                throw new Exception(
                    'Usuario no encontrado.',
                    404
                );
            }

            $realEstateId =
                (int)($user['real_estate_id'] ?? 0);

            if ($realEstateId <= 0) {
                throw new Exception(
                    'El usuario no está vinculado a una inmobiliaria.',
                    422
                );
            }

            /*
             * Bloqueamos la operación durante
             * el cálculo del nuevo estado.
             */
            $stOperation = $pdo->prepare("
                SELECT
                    id,
                    status,
                    commercial_status,
                    participants_count
                FROM multilateral_operations
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");

            $stOperation->execute([
                'id' => $operationId,
            ]);

            $operation = $stOperation->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$operation) {
                throw new Exception(
                    'Oportunidad multilateral no encontrada.',
                    404
                );
            }

            if (
                $operation['status'] !== 'detected'
            ) {
                throw new Exception(
                    'Esta oportunidad ya no se encuentra disponible.',
                    409
                );
            }

            if (
                $operation['commercial_status'] !== 'open'
            ) {
                throw new Exception(
                    'Esta oportunidad ya fue resuelta.',
                    409
                );
            }

            /*
             * Para responder la inmobiliaria
             * debe ser participante del ciclo.
             */
            $stParticipant = $pdo->prepare("
                SELECT 1
                FROM multilateral_operation_legs
                WHERE operation_id = :operation_id
                  AND source_real_estate_id =
                      :real_estate_id
                LIMIT 1
            ");

            $stParticipant->execute([
                'operation_id' =>
                    $operationId,

                'real_estate_id' =>
                    $realEstateId,
            ]);

            if (!$stParticipant->fetchColumn()) {
                throw new Exception(
                    'No participás de esta oportunidad.',
                    403
                );
            }

            /*
             * Una inmobiliaria tiene una sola
             * respuesta por operación.
             */
            $stResponse = $pdo->prepare("
                INSERT INTO multilateral_operation_responses (
                    operation_id,
                    real_estate_id,
                    response,
                    responded_by_user_id,
                    responded_at
                ) VALUES (
                    :operation_id,
                    :real_estate_id,
                    :response,
                    :user_id,
                    NOW()
                )

                ON DUPLICATE KEY UPDATE
                    response = VALUES(response),
                    responded_by_user_id =
                        VALUES(responded_by_user_id),
                    responded_at = NOW()
            ");

            $stResponse->execute([
                'operation_id' =>
                    $operationId,

                'real_estate_id' =>
                    $realEstateId,

                'response' =>
                    $response,

                'user_id' =>
                    $userId,
            ]);

            /*
             * Si alguien rechaza, la oportunidad
             * comercial termina inmediatamente.
             */
            $stDeclined = $pdo->prepare("
                SELECT COUNT(*)
                FROM multilateral_operation_responses
                WHERE operation_id = :operation_id
                  AND response = 'declined'
            ");

            $stDeclined->execute([
                'operation_id' =>
                    $operationId,
            ]);

            $declinedCount =
                (int)$stDeclined->fetchColumn();

            if ($declinedCount > 0) {
                $stUpdate = $pdo->prepare("
                    UPDATE multilateral_operations
                    SET
                        commercial_status = 'declined',
                        declined_at = NOW(),
                        confirmed_at = NULL
                    WHERE id = :id
                ");

                $stUpdate->execute([
                    'id' => $operationId,
                ]);
            } else {
                /*
                 * Sólo contamos respuestas positivas.
                 * No exponemos qué inmobiliarias faltan.
                 */
                $stInterested = $pdo->prepare("
                    SELECT COUNT(DISTINCT real_estate_id)
                    FROM multilateral_operation_responses
                    WHERE operation_id = :operation_id
                      AND response = 'interested'
                ");

                $stInterested->execute([
                    'operation_id' =>
                        $operationId,
                ]);

                $interestedCount =
                    (int)$stInterested->fetchColumn();

                $participantsCount =
                    (int)$operation['participants_count'];

                if (
                    $participantsCount > 0 &&
                    $interestedCount >= $participantsCount
                ) {
                    $stUpdate = $pdo->prepare("
                        UPDATE multilateral_operations
                        SET
                            commercial_status = 'confirmed',
                            confirmed_at = NOW(),
                            declined_at = NULL
                        WHERE id = :id
                    ");

                    $stUpdate->execute([
                        'id' => $operationId,
                    ]);
                }
            }

            $stFinal = $pdo->prepare("
                SELECT
                    commercial_status,
                    confirmed_at,
                    declined_at
                FROM multilateral_operations
                WHERE id = :id
                LIMIT 1
            ");

            $stFinal->execute([
                'id' => $operationId,
            ]);

            $final =
                $stFinal->fetch(PDO::FETCH_ASSOC);

            $pdo->commit();

            return [
                'operation_id' =>
                    $operationId,

                'response' =>
                    $response,

                'commercial_status' =>
                    $final['commercial_status']
                        ?? 'open',

                'confirmed_at' =>
                    $final['confirmed_at']
                        ?? null,

                'declined_at' =>
                    $final['declined_at']
                        ?? null,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}