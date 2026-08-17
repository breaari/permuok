<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\PropertyImageService;

class PropertyImageController
{
    public static function upload(): void
    {
        $auth = AuthHelper::requireUser();

        $propertyId = (int)($_GET['id'] ?? 0);

        $result = PropertyImageService::upload(
            (int)$auth['id'],
            $propertyId,
            $_FILES['images'] ?? []
        );

        ResponseHelper::ok($result, 201);
    }
    public static function delete(): void
    {
        $auth = AuthHelper::requireUser();
        $imageId = (int)($_GET['image_id'] ?? 0);

        $result = PropertyImageService::delete((int)$auth['id'], $imageId);
        ResponseHelper::ok($result);
    }

    public static function reorder(): void
    {
        $auth = AuthHelper::requireUser();

        $propertyId = (int)($_GET['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $result = PropertyImageService::reorder(
            (int)$auth['id'],
            $propertyId,
            $data['images'] ?? []
        );

        ResponseHelper::ok($result);
    }
}
