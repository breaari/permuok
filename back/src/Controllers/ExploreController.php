<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\ExploreService;
use Exception;

class ExploreController
{
    public static function index(): void
    {
        try {
            $auth = AuthHelper::requireUser();

            $filters = [
                'q' => $_GET['q'] ?? null,
                'opportunity_type' => $_GET['opportunity_type'] ?? 'all',

                'country' => $_GET['country'] ?? null,
                'province' => $_GET['province'] ?? null,
                'city' => $_GET['city'] ?? null,
                'zone' => $_GET['zone'] ?? null,
                'place_id' => $_GET['place_id'] ?? null,
                'lat' => $_GET['lat'] ?? null,
                'lng' => $_GET['lng'] ?? null,

                'property_type' => $_GET['property_type'] ?? null,

                'currency' => $_GET['currency'] ?? null,
                'value_min' => $_GET['value_min'] ?? null,
                'value_max' => $_GET['value_max'] ?? null,

                'bedrooms_min' => $_GET['bedrooms_min'] ?? null,
                'bathrooms_min' => $_GET['bathrooms_min'] ?? null,
                'garages_min' => $_GET['garages_min'] ?? null,
                'area_min' => $_GET['area_min'] ?? null,

                'exchange_modes' => $_GET['exchange_modes'] ?? [],
                'amenities' => $_GET['amenities'] ?? [],
                'development_stage' => $_GET['development_stage'] ?? null,

                'sort' => $_GET['sort'] ?? 'recent',
                'page' => $_GET['page'] ?? 1,
                'limit' => $_GET['limit'] ?? 12,
            ];

            $result = ExploreService::search((int)$auth['id'], $filters);

            ResponseHelper::ok($result);
        } catch (Exception $e) {
            ResponseHelper::fail($e->getMessage(), 400);
        }
    }
}