<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\QueryParamHelper;
use App\Services\PropertyService;
use App\Services\MembershipGuard;

class PropertyController
{

    private static function fail(
        \Throwable $e,
        int $status = 400
    ): void {
        if (
            $e instanceof \PDOException ||
            !($e instanceof \Exception)
        ) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación.',
                'PropertyController'
            );

            return;
        }

        $exceptionStatus =
            (int)$e->getCode();

        if (
            $exceptionStatus >= 400 &&
            $exceptionStatus <= 499
        ) {
            $status =
                $exceptionStatus;
        }

        ResponseHelper::fail(
            $e->getMessage()
                ?: 'No se pudo completar la operación.',
            $status
        );
    }

    private static function failAI(
        \Throwable $e
    ): void {
        $status = (int)$e->getCode();

        if (
            $status >= 400 &&
            $status <= 499
        ) {
            ResponseHelper::fail(
                $e->getMessage(),
                $status
            );

            return;
        }

        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación con IA. Intentá nuevamente.',
            'PropertyController::AI'
        );
    }

    private static function readJsonBody(
        bool $allowEmpty = false
    ): array {
        $rawBody = file_get_contents('php://input');

        if (
            $rawBody === false ||
            trim($rawBody) === ''
        ) {
            if ($allowEmpty) {
                return [];
            }

            throw new \Exception(
                'El cuerpo de la solicitud está vacío',
                422
            );
        }

        try {
            $data = json_decode(
                $rawBody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \Exception(
                'El cuerpo de la solicitud no contiene un JSON válido',
                422
            );
        }

        if (!is_array($data)) {
            throw new \Exception(
                'El cuerpo de la solicitud debe ser un objeto JSON',
                422
            );
        }

        return $data;
    }

    public static function list(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'status',
                'q',
                'limit',
                'page',
            ]);

            $filters = [
                'status' =>
                QueryParamHelper::enum(
                    'status',
                    [
                        'draft',
                        'published',
                        'paused',
                        'archived',
                        'closed',
                    ],
                    ''
                ),
                'q' =>
                QueryParamHelper::optionalString(
                    'q',
                    150
                ),
                /*
             * Se permite recibir 100 porque actualmente
             * getMyPublishedProperties() lo solicita.
             * PropertyService mantiene el límite efectivo en 50.
             */
                'limit' =>
                QueryParamHelper::positiveInt(
                    'limit',
                    5,
                    100
                ),
                'page' =>
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                ),
            ];

            $result =
                PropertyService::listMyProperties(
                    (int)$auth['id'],
                    $filters
                );

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function explore(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'q',
                'property_type',
                'limit',
                'page',
            ]);

            $filters = [
                'q' =>
                QueryParamHelper::optionalString(
                    'q',
                    150
                ),
                'property_type' =>
                QueryParamHelper::enum(
                    'property_type',
                    [
                        'house',
                        'apartment',
                        'land',
                        'commercial',
                        'office',
                        'warehouse',
                        'other',
                    ],
                    ''
                ),
                'limit' =>
                QueryParamHelper::positiveInt(
                    'limit',
                    6,
                    50
                ),
                'page' =>
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                ),
            ];

            $result =
                PropertyService::listExploreProperties(
                    (int)$auth['id'],
                    $filters
                );

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function create(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $data = self::readJsonBody();

            $result = PropertyService::createDraft(
                (int)$auth['id'],
                $data
            );

            ResponseHelper::ok($result, 201);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function detail(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::getDetail((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail(
                $e,
                404
            );
        }
    }

    public static function exploreDetail(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::getExploreDetail((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail(
                $e,
                404
            );
        }
    }

    public static function update(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id = (int)($_GET['id'] ?? 0);
            $data = self::readJsonBody();

            $result = PropertyService::updateDraft(
                (int)$auth['id'],
                $id,
                $data
            );

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function saveRequirements(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id = (int)($_GET['id'] ?? 0);
            $data = self::readJsonBody();

            $result = PropertyService::replaceRequirements(
                (int)$auth['id'],
                $id,
                $data
            );

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function publish(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership((int)$auth['id']);

            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::publish((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function pause(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::pause((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function archive(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::archive((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function close(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $data = self::readJsonBody(true);

            $closingTypeValue =
                $data['closing_type'] ?? '';

            if (!is_string($closingTypeValue)) {
                throw new \Exception(
                    'El tipo de cierre tiene un formato inválido',
                    422
                );
            }

            $closingType = trim($closingTypeValue);

            $result = PropertyService::close(
                (int)$auth['id'],
                $id,
                $closingType
            );

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function delete(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = PropertyService::delete((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function requestAIAnalysis(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id =
                (int)($_GET['id'] ?? 0);

            $result =
                PropertyService::requestAIAnalysis(
                    (int)$auth['id'],
                    $id
                );

            ResponseHelper::ok(
                $result,
                202
            );
        } catch (\Throwable $e) {
            self::failAI($e);
        }
    }

    public static function getAIAnalysis(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            $id =
                (int)($_GET['id'] ?? 0);

            $result =
                PropertyService::getAIAnalysis(
                    (int)$auth['id'],
                    $id
                );

            ResponseHelper::ok([
                'analysis' => $result,
            ]);
        } catch (\Throwable $e) {
            self::failAI($e);
        }
    }

    public static function generateAITitle(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id = (int)($_GET['id'] ?? 0);
            $data = self::readJsonBody(true);

            if (
                array_key_exists('draft', $data) &&
                !is_array($data['draft'])
            ) {
                throw new \Exception(
                    'El borrador de IA tiene un formato inválido',
                    422
                );
            }

            $draft = $data['draft'] ?? [];

            $result = PropertyService::generateAITitle(
                (int)$auth['id'],
                $id,
                $draft
            );

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::failAI($e);
        }
    }

    public static function generateAIDescription(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id = (int)($_GET['id'] ?? 0);
            $data = self::readJsonBody(true);

            if (
                array_key_exists('draft', $data) &&
                !is_array($data['draft'])
            ) {
                throw new \Exception(
                    'El borrador de IA tiene un formato inválido',
                    422
                );
            }

            $draft = $data['draft'] ?? [];

            $result = PropertyService::generateAIDescription(
                (int)$auth['id'],
                $id,
                $draft
            );

            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::failAI($e);
        }
    }
}
