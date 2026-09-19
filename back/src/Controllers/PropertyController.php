<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
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

    public static function list(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            $filters = [
                'status' => $_GET['status'] ?? null,
                'q' => $_GET['q'] ?? null,
                'limit' => $_GET['limit'] ?? 5,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = PropertyService::listMyProperties((int)$auth['id'], $filters);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function explore(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            $filters = [
                'q' => $_GET['q'] ?? null,
                'property_type' => $_GET['property_type'] ?? null,
                'limit' => $_GET['limit'] ?? 6,
                'page' => $_GET['page'] ?? 1,
            ];

            $result = PropertyService::listExploreProperties((int)$auth['id'], $filters);
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function create(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership((int)$auth['id']);

            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = PropertyService::createDraft((int)$auth['id'], $data);
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
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = PropertyService::updateDraft((int)$auth['id'], $id, $data);
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
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $result = PropertyService::replaceRequirements((int)$auth['id'], $id, $data);
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
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $closingType = trim((string)($data['closing_type'] ?? ''));
            $result = PropertyService::close((int)$auth['id'], $id, $closingType);
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
            self::fail($e);
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
            self::fail($e);
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
            $data =
                json_decode(
                    file_get_contents('php://input'),
                    true
                ) ?? [];

            $draft =
                is_array($data['draft'] ?? null)
                ? $data['draft']
                : [];
            $result =
                PropertyService::generateAITitle(
                    (int)$auth['id'],
                    $id,
                    $draft
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::fail($e);
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
            $data =
                json_decode(
                    file_get_contents('php://input'),
                    true
                ) ?? [];

            $draft =
                is_array($data['draft'] ?? null)
                ? $data['draft']
                : [];
            $result =
                PropertyService::generateAIDescription(
                    (int)$auth['id'],
                    $id,
                    $draft
                );

            ResponseHelper::ok(
                $result
            );
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }
}
