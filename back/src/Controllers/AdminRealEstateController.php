<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\AuthMiddleware;
use App\Services\AdminRealEstateService;

class AdminRealEstateController
{
    private static function requireAdmin(): array
    {
        $ctx = AuthMiddleware::handle();
        if ((int)($ctx['role'] ?? 0) !== 1) {
            ResponseHelper::fail('No autorizado', 403);
        }
        return $ctx;
    }

    private static function handleError(
        \Throwable $e
    ): void {
        $code = (int)$e->getCode();

        if (
            !($e instanceof \PDOException) &&
            $code >= 400 &&
            $code <= 499
        ) {
            ResponseHelper::fail(
                $e->getMessage(),
                $code
            );
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación.',
            'AdminRealEstateController'
        );
    }


    private static function readJsonObject(): array
    {
        $raw =
            file_get_contents(
                'php://input'
            );

        if (
            $raw === false ||
            trim($raw) === ''
        ) {
            throw new \Exception(
                'El cuerpo de la solicitud está vacío',
                422
            );
        }

        $raw = trim($raw);

        if ($raw[0] !== '{') {
            throw new \Exception(
                'El cuerpo JSON debe ser un objeto',
                422
            );
        }

        try {
            $payload = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \Exception(
                'El cuerpo JSON es inválido',
                422
            );
        }

        if (!is_array($payload)) {
            throw new \Exception(
                'El cuerpo JSON es inválido',
                422
            );
        }

        return $payload;
    }

    public static function counts(): void
    {
        try {
            self::requireAdmin();

            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;

            $counts = AdminRealEstateService::counts($q);
            ResponseHelper::ok(['counts' => $counts]);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function list(): void
    {
        try {
            self::requireAdmin();

            $status = (string)($_GET['status'] ?? 'pending');
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 10);
            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;

            $data = AdminRealEstateService::list($status, $page, $perPage, $q);
            ResponseHelper::ok($data);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }



    public static function validate(): void
    {
        try {
            $ctx =
                self::requireAdmin();

            $payload =
                self::readJsonObject();

            $realEstateId = filter_var(
                $payload['real_estate_id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($realEstateId === false) {
                throw new \Exception(
                    'real_estate_id inválido',
                    422
                );
            }

            if (
                !isset($payload['action']) ||
                !is_string($payload['action'])
            ) {
                throw new \Exception(
                    'action requerida',
                    422
                );
            }

            $action =
                strtolower(
                    trim($payload['action'])
                );

            if (
                !in_array(
                    $action,
                    [
                        'approve',
                        'reject',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'action inválida',
                    422
                );
            }

            $validationNote = null;

            if (
                array_key_exists(
                    'validation_note',
                    $payload
                ) &&
                $payload['validation_note'] !== null
            ) {
                if (
                    !is_string(
                        $payload['validation_note']
                    )
                ) {
                    throw new \Exception(
                        'La nota de validación es inválida',
                        422
                    );
                }

                $validationNote =
                    trim(
                        $payload['validation_note']
                    );

                if (
                    mb_strlen(
                        $validationNote
                    ) > 1000
                ) {
                    throw new \Exception(
                        'La nota de validación es demasiado extensa',
                        422
                    );
                }
            }

            if (
                $action === 'reject' &&
                (
                    $validationNote === null ||
                    $validationNote === ''
                )
            ) {
                throw new \Exception(
                    'El motivo de rechazo es requerido',
                    422
                );
            }

            if (
                $action === 'approve' &&
                $validationNote === ''
            ) {
                $validationNote = null;
            }

            $result =
                AdminRealEstateService::validate(
                    (int)$ctx['id'],
                    (int)$realEstateId,
                    $action,
                    $validationNote
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function pending(): void
    {
        try {
            self::requireAdmin();
            $data = AdminRealEstateService::list('pending', 1, 200, null);
            ResponseHelper::ok(['items' => $data['items']]);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function approved(): void
    {
        try {
            self::requireAdmin();
            $data = AdminRealEstateService::list('approved', 1, 200, null);
            ResponseHelper::ok(['items' => $data['items']]);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function rejected(): void
    {
        try {
            self::requireAdmin();
            $data = AdminRealEstateService::list('rejected', 1, 200, null);
            ResponseHelper::ok(['items' => $data['items']]);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function approve(): void
    {
        self::validate();
    }

    public static function detail(): void
    {
        try {
            self::requireAdmin();

            $id = filter_var(
                $_GET['id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($id === false) {
                throw new \Exception(
                    'Identificador de inmobiliaria inválido',
                    422
                );
            }

            $data =
                AdminRealEstateService::getDetail(
                    (int)$id
                );

            ResponseHelper::ok(
                $data
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function operationalStatus(): void
    {
        try {
            self::requireAdmin();

            $payload =
                self::readJsonObject();

            $realEstateId = filter_var(
                $payload['real_estate_id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($realEstateId === false) {
                throw new \Exception(
                    'real_estate_id inválido',
                    422
                );
            }

            if (
                !array_key_exists(
                    'is_active',
                    $payload
                )
            ) {
                throw new \Exception(
                    'is_active requerido',
                    422
                );
            }

            $rawIsActive =
                $payload['is_active'];

            if (
                in_array(
                    $rawIsActive,
                    [true, 1, '1'],
                    true
                )
            ) {
                $isActive = true;
            } elseif (
                in_array(
                    $rawIsActive,
                    [false, 0, '0'],
                    true
                )
            ) {
                $isActive = false;
            } else {
                throw new \Exception(
                    'is_active inválido',
                    422
                );
            }

            $result =
                AdminRealEstateService::setOperationalStatus(
                    (int)$realEstateId,
                    $isActive
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function operationalCounts(): void
    {
        try {
            self::requireAdmin();

            $q =
                isset($_GET['q'])
                ? (string)$_GET['q']
                : null;

            $counts =
                AdminRealEstateService::operationalCounts(
                    $q
                );

            ResponseHelper::ok([
                'counts' =>
                $counts,
            ]);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    public static function operationalList(): void
    {
        try {
            self::requireAdmin();

            $status =
                (string)(
                    $_GET['status']
                    ?? 'all'
                );

            $page =
                (int)(
                    $_GET['page']
                    ?? 1
                );

            $perPage =
                (int)(
                    $_GET['per_page']
                    ?? 10
                );

            $q =
                isset($_GET['q'])
                ? (string)$_GET['q']
                : null;

            $data =
                AdminRealEstateService::operationalList(
                    $status,
                    $page,
                    $perPage,
                    $q
                );

            ResponseHelper::ok(
                $data
            );
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }
}
