<?php

namespace App\Controllers;

use Throwable;
use PDOException;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentService;
use App\Services\MembershipGuard;
use App\Services\AI\DevelopmentAIAnalysisService;
use App\Services\AI\DevelopmentQualityScoreService;
use App\Services\AI\DevelopmentAICopyService;
use App\Services\SecurityRateLimitService;

class DevelopmentController
{
    private static function fail(
        Throwable $e,
        int $status = 400
    ): void {
        if (
            $e instanceof \PDOException ||
            !($e instanceof \Exception)
        ) {
            ResponseHelper::fromThrowable(
                $e,
                'No se pudo completar la operación.',
                'DevelopmentController'
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
        Throwable $e
    ): void {
        $status =
            (int)$e->getCode();

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
            'DevelopmentController::AI'
        );
    }

    private static function readJsonBody(
        bool $allowEmpty = false
    ): array {
        $rawBody =
            file_get_contents(
                'php://input'
            );

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
            $data =
                json_decode(
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
            $auth = AuthHelper::requireUser();

            $filters = [
                'status' => $_GET['status'] ?? null,
                'q' => $_GET['q'] ?? null,
                'limit' => $_GET['limit'] ?? 20,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = DevelopmentService::listMyDevelopments((int)$auth['id'], $filters);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function explore(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            $filters = [
                'q' => $_GET['q'] ?? null,
                'development_stage' => $_GET['development_stage'] ?? null,
                'limit' => $_GET['limit'] ?? 20,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = DevelopmentService::listExploreDevelopments((int)$auth['id'], $filters);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function create(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $data =
                self::readJsonBody();

            $result =
                DevelopmentService::createDraft(
                    (int)$auth['id'],
                    $data
                );

            ResponseHelper::ok(
                $result,
                201
            );
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function detail(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = DevelopmentService::getDetail((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function update(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id =
                (int)($_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new \Exception(
                    'El ID del desarrollo no es válido.',
                    422
                );
            }

            $data =
                self::readJsonBody();

            $result =
                DevelopmentService::updateDraft(
                    (int)$auth['id'],
                    $id,
                    $data
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function publish(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$auth['id']);
            $id = (int)($_GET['id'] ?? 0);

            $result = DevelopmentService::publish((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function pause(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = DevelopmentService::pause((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function archive(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = DevelopmentService::archive((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function delete(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = DevelopmentService::delete((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function close(): void
    {
        try {
            $auth = AuthHelper::requireUser();
            $id = (int)($_GET['id'] ?? 0);

            $result = DevelopmentService::close((int)$auth['id'], $id);
            ResponseHelper::ok($result);
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function getQuality(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            $id =
                (int)($_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new \Exception(
                    'El ID del desarrollo no es válido.'
                );
            }

            /*
         * Verifica propiedad del desarrollo.
         */
            DevelopmentService::assertOwnedDevelopment(
                (int)$auth['id'],
                $id
            );
            $result =
                DevelopmentQualityScoreService::getScore(
                    $id
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
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

            if ($id <= 0) {
                throw new \Exception(
                    'El ID del desarrollo no es válido.'
                );
            }

            /*
         * Primero verificamos pertenencia.
         * Un desarrollo ajeno no consume cupo.
         */
            DevelopmentService::assertOwnedDevelopment(
                (int)$auth['id'],
                $id
            );

            self::consumeAIAnalysisLimits(
                (int)$auth['id'],
                (int)$auth['real_estate_id']
            );

            $result =
                DevelopmentAIAnalysisService::requestAnalysis(
                    $id
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::failAI($e);
        }
    }

    public static function generateAITitle(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id =
                (int)($_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new \Exception(
                    'El ID del desarrollo no es válido.',
                    422
                );
            }

            /*
         * Primero verificamos pertenencia.
         * Un desarrollo ajeno no consume cupo.
         */
            DevelopmentService::assertOwnedDevelopment(
                (int)$auth['id'],
                $id
            );

            /*
         * Después validamos la solicitud.
         * Un JSON roto tampoco consume cupo.
         */
            $data =
                self::readJsonBody(
                    true
                );

            if (
                array_key_exists(
                    'draft',
                    $data
                ) &&
                !is_array($data['draft'])
            ) {
                throw new \Exception(
                    'El borrador de IA tiene un formato inválido',
                    422
                );
            }

            $draft =
                $data['draft']
                ?? [];

            self::consumeAICopyLimits(
                (int)$auth['id'],
                (int)$auth['real_estate_id']
            );

            $result =
                DevelopmentAICopyService::generateTitle(
                    $id,
                    (int)$auth['id'],
                    $draft
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::failAI($e);
        }
    }

    public static function generateAIDescription(): void
    {
        try {
            $auth =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$auth['id']
            );

            $id =
                (int)($_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new \Exception(
                    'El ID del desarrollo no es válido.',
                    422
                );
            }

            /*
         * Primero verificamos pertenencia.
         * Un desarrollo ajeno no consume cupo.
         */
            DevelopmentService::assertOwnedDevelopment(
                (int)$auth['id'],
                $id
            );

            /*
         * Después validamos la solicitud.
         * Un JSON roto tampoco consume cupo.
         */
            $data =
                self::readJsonBody(
                    true
                );

            if (
                array_key_exists(
                    'draft',
                    $data
                ) &&
                !is_array($data['draft'])
            ) {
                throw new \Exception(
                    'El borrador de IA tiene un formato inválido',
                    422
                );
            }

            $draft =
                $data['draft']
                ?? [];

            self::consumeAICopyLimits(
                (int)$auth['id'],
                (int)$auth['real_estate_id']
            );

            $result =
                DevelopmentAICopyService::generateDescription(
                    $id,
                    (int)$auth['id'],
                    $draft
                );

            ResponseHelper::ok(
                $result
            );
        } catch (Throwable $e) {
            self::failAI($e);
        }
    }

    private static function consumeAICopyLimits(
        int $userId,
        int $realEstateId
    ): void {
        SecurityRateLimitService::consume(
            'ai_copy_user',
            (string)$userId,
            30,
            5 * 60
        );

        SecurityRateLimitService::consume(
            'ai_daily_real_estate',
            (string)$realEstateId,
            1000,
            24 * 60 * 60
        );
    }

    private static function consumeAIAnalysisLimits(
        int $userId,
        int $realEstateId
    ): void {
        SecurityRateLimitService::consume(
            'ai_analysis_user',
            (string)$userId,
            20,
            60 * 60
        );

        SecurityRateLimitService::consume(
            'ai_daily_real_estate',
            (string)$realEstateId,
            1000,
            24 * 60 * 60
        );
    }
}
