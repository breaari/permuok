<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\BillingCycleService;

class BillingCycleController
{
    public static function process(): void
    {
        try {
            $result = BillingCycleService::processDueMemberships();
            ResponseHelper::ok($result);
        } catch (\Throwable $e) {
            ResponseHelper::fail($e->getMessage(), 500);
        }
    }
}