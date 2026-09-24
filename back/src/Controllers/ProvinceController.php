<?php

namespace App\Controllers;

use App\Helpers\QueryParamHelper;
use App\Helpers\ResponseHelper;
use App\Services\ProvinceService;

class ProvinceController
{
    public static function list(): void
    {
        try {
            QueryParamHelper::rejectUnknown([]);

            $items =
                ProvinceService::listActive();

            ResponseHelper::ok([
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            ResponseHelper::fromThrowable(
                $e
            );
        }
    }
}
