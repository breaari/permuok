<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\DevelopmentImageService;
use App\Services\MembershipGuard;

class DevelopmentImageController
{
    public static function upload(): void
    {
        $auth = AuthHelper::requireUser();

        MembershipGuard::requireActiveMembership((int)$auth['id']);

        $developmentId = (int)($_GET['id'] ?? 0);

        $result = DevelopmentImageService::upload(
            (int)$auth['id'],
            $developmentId,
            $_FILES['images'] ?? []
        );

        ResponseHelper::ok($result, 201);
    }
    public static function delete(): void
    {
        $auth = AuthHelper::requireUser();
        $imageId = (int)($_GET['image_id'] ?? 0);

        $result = DevelopmentImageService::delete((int)$auth['id'], $imageId);
        ResponseHelper::ok($result);
    }

    public static function reorder(): void
    {
        $auth = AuthHelper::requireUser();

        MembershipGuard::requireActiveMembership((int)$auth['id']);

        $developmentId = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = DevelopmentImageService::reorder(
            (int)$auth['id'],
            $developmentId,
            $data['images'] ?? []
        );

        ResponseHelper::ok($result);
    }
}
