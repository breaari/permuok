<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\ProvinceService;

class ProvinceController
{
    public static function list(): void
    {
        try {
            $items = ProvinceService::listActive();
            ResponseHelper::ok(['items' => $items]);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }
}