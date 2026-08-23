<?php

namespace App\Controllers;

use Throwable;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentService;
use App\Services\MembershipGuard;
use App\Services\AI\DevelopmentAIAnalysisService;
use App\Services\AI\DevelopmentQualityScoreService;

class DevelopmentController
{
    private static function fail(Throwable $e, int $status = 400): void
    {
        ResponseHelper::error($e->getMessage(), $status);
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
            $auth = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$auth['id']);

            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = DevelopmentService::createDraft((int)$auth['id'], $data);
            ResponseHelper::ok($result, 201);
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
            $auth = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$auth['id']);
            $id = (int)($_GET['id'] ?? 0);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = DevelopmentService::updateDraft((int)$auth['id'], $id, $data);
            ResponseHelper::ok($result);
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
         * Verifica que el desarrollo pertenezca
         * a la inmobiliaria del usuario.
         */
            DevelopmentService::assertOwnedDevelopment(
                (int)$auth['id'],
                $id
            );

            $result =
                DevelopmentAIAnalysisService::requestAnalysis(
                    $id
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            ResponseHelper::error(
                $e->getMessage(),
                400
            );
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
        } catch (\Throwable $e) {
            ResponseHelper::error(
                $e->getMessage(),
                400
            );
        }
    }
}
