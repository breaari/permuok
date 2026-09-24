<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\QueryParamHelper;
use App\Services\CompatibilityService;
use Throwable;
use App\Services\MultilateralOperationReadService;
use App\Services\MultilateralOperationResponseService;
use App\Services\MembershipGuard;

class CompatibilityController
{
    private static function readJsonObject(
        array $allowedFields
    ): array {
        $raw =
            file_get_contents(
                'php://input'
            );

        if (
            $raw === false ||
            trim($raw) === ''
        ) {
            throw new \Exception(
                'El cuerpo de la solicitud está vacío.',
                422
            );
        }

        if (strlen($raw) > 65536) {
            throw new \Exception(
                'El cuerpo de la solicitud es demasiado extenso.',
                413
            );
        }

        $raw =
            trim($raw);

        if ($raw[0] !== '{') {
            throw new \Exception(
                'El cuerpo JSON debe ser un objeto.',
                422
            );
        }

        try {
            $body =
                json_decode(
                    $raw,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (\JsonException $e) {
            throw new \Exception(
                'El cuerpo JSON es inválido.',
                422
            );
        }

        if (!is_array($body)) {
            throw new \Exception(
                'El cuerpo JSON es inválido.',
                422
            );
        }

        $unknownFields =
            array_diff(
                array_keys($body),
                $allowedFields
            );

        if ($unknownFields !== []) {
            throw new \Exception(
                'La solicitud contiene campos no permitidos.',
                422
            );
        }

        return $body;
    }

    public static function recommendations(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'page',
                'limit',
                'view',
                'match_level',
                'min_score',
                'pending',
            ]);

            $filters = [
                'page' =>
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                ),
                'limit' =>
                QueryParamHelper::positiveInt(
                    'limit',
                    12,
                    50
                ),
                'view' =>
                QueryParamHelper::enum(
                    'view',
                    [
                        'active',
                        'history',
                        'all',
                    ],
                    'active'
                ),
                'match_level' =>
                QueryParamHelper::enum(
                    'match_level',
                    [
                        'low',
                        'medium',
                        'high',
                        'total',
                    ],
                    ''
                ),
                'min_score' =>
                QueryParamHelper::optionalNumber(
                    'min_score',
                    0,
                    100
                ),
                'pending' =>
                QueryParamHelper::boolean(
                    'pending',
                    false
                ),
            ];

            $result =
                CompatibilityService::listRecommendations(
                    (int)$user['id'],
                    $filters
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function detail(int $compatibilityId): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result =
                CompatibilityService::getRecommendationDetail(
                    (int)$user['id'],
                    $compatibilityId
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function respond(
        int $compatibilityId
    ): void {
        try {
            if ($compatibilityId <= 0) {
                throw new \Exception(
                    'Compatibilidad inválida.',
                    422
                );
            }

            $user =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$user['id']
            );
            $body =
                self::readJsonObject([
                    'response',
                ]);

            if (
                !array_key_exists(
                    'response',
                    $body
                ) ||
                !is_string(
                    $body['response']
                )
            ) {
                throw new \Exception(
                    'Respuesta requerida.',
                    422
                );
            }

            $response =
                trim($body['response']);

            if (
                !in_array(
                    $response,
                    [
                        'pending',
                        'interested',
                        'dismissed',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'Respuesta inválida.',
                    422
                );
            }

            $result =
                CompatibilityService::respond(
                    (int)$user['id'],
                    $compatibilityId,
                    $response
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function feedback(
        int $compatibilityId
    ): void {
        try {
            if ($compatibilityId <= 0) {
                throw new \Exception(
                    'Compatibilidad inválida.',
                    422
                );
            }

            $user =
                AuthHelper::requireUser();

            $body =
                self::readJsonObject([
                    'useful',
                    'rating',
                    'comment',
                ]);

            $normalized = [
                'useful' => null,
                'rating' => null,
                'comment' => '',
            ];

            /*
         * useful es opcional, pero si llega debe
         * ser un booleano o 0/1 exacto.
         */
            if (
                array_key_exists(
                    'useful',
                    $body
                ) &&
                $body['useful'] !== null
            ) {
                $rawUseful =
                    $body['useful'];

                if (
                    in_array(
                        $rawUseful,
                        [true, 1, '1'],
                        true
                    )
                ) {
                    $normalized['useful'] = true;
                } elseif (
                    in_array(
                        $rawUseful,
                        [false, 0, '0'],
                        true
                    )
                ) {
                    $normalized['useful'] = false;
                } else {
                    throw new \Exception(
                        'Valor useful inválido.',
                        422
                    );
                }
            }

            /*
         * rating es opcional, pero debe ser
         * un entero exacto entre 1 y 5.
         */
            if (
                array_key_exists(
                    'rating',
                    $body
                ) &&
                $body['rating'] !== null &&
                $body['rating'] !== ''
            ) {
                $rating = filter_var(
                    $body['rating'],
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 1,
                            'max_range' => 5,
                        ],
                    ]
                );

                if ($rating === false) {
                    throw new \Exception(
                        'La calificación debe estar entre 1 y 5.',
                        422
                    );
                }

                $normalized['rating'] =
                    (int)$rating;
            }

            if (
                array_key_exists(
                    'comment',
                    $body
                ) &&
                $body['comment'] !== null
            ) {
                if (
                    !is_string(
                        $body['comment']
                    )
                ) {
                    throw new \Exception(
                        'El comentario es inválido.',
                        422
                    );
                }

                $normalized['comment'] =
                    trim(
                        $body['comment']
                    );

                if (
                    mb_strlen(
                        $normalized['comment']
                    ) > 2000
                ) {
                    throw new \Exception(
                        'El comentario es demasiado largo.',
                        422
                    );
                }
            }

            /*
         * Evitamos crear registros de feedback
         * completamente vacíos.
         */
            if (
                $normalized['useful'] === null &&
                $normalized['rating'] === null &&
                $normalized['comment'] === ''
            ) {
                throw new \Exception(
                    'Debés completar al menos un dato del feedback.',
                    422
                );
            }

            $result =
                CompatibilityService::saveFeedback(
                    (int)$user['id'],
                    $compatibilityId,
                    $normalized
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function seen(
        int $compatibilityId
    ): void {
        try {
            $user = AuthHelper::requireUser();

            $result =
                CompatibilityService::markAsSeen(
                    (int)$user['id'],
                    $compatibilityId
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function multilateral(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'page',
                'limit',
                'view',
            ]);

            $filters = [
                'page' =>
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                ),
                'limit' =>
                QueryParamHelper::positiveInt(
                    'limit',
                    12,
                    50
                ),
                'view' =>
                QueryParamHelper::enum(
                    'view',
                    [
                        'active',
                        'history',
                        'all',
                    ],
                    'active'
                ),
            ];

            $result =
                MultilateralOperationReadService::listForUser(
                    (int)$user['id'],
                    $filters
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function multilateralDetail(
        int $operationId
    ): void {
        try {
            $user =
                AuthHelper::requireUser();

            $result =
                MultilateralOperationReadService::detailForUser(
                    (int)$user['id'],
                    $operationId
                );

            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function multilateralRespond(
        int $operationId
    ): void {
        try {
            if ($operationId <= 0) {
                throw new \Exception(
                    'Operación multilateral inválida.',
                    422
                );
            }

            $user =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$user['id']
            );

            $body =
                self::readJsonObject([
                    'response',
                ]);

            if (
                !array_key_exists(
                    'response',
                    $body
                ) ||
                !is_string(
                    $body['response']
                )
            ) {
                throw new \Exception(
                    'Respuesta requerida.',
                    422
                );
            }

            $response =
                trim($body['response']);

            if (
                !in_array(
                    $response,
                    [
                        'interested',
                        'declined',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'Respuesta inválida.',
                    422
                );
            }

            $result =
                MultilateralOperationResponseService::respond(
                    (int)$user['id'],
                    $operationId,
                    $response
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    private static function error(
        Throwable $e
    ): void {
        $status = (int)$e->getCode();

        if ($status < 400 || $status > 499) {
            error_log(
                '[CompatibilityController] ' .
                    $e->getMessage()
            );

            ResponseHelper::fail(
                'No se pudo procesar la solicitud.',
                500
            );

            return;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo procesar la solicitud.',
            $status
        );
    }
}
