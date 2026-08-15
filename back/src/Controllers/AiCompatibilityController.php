<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\AI\CompatibilityEngine;
use Throwable;

class AiCompatibilityController
{
    public static function calculateForSearchRequest(
        int $searchRequestId
    ): void {
        try {
            AuthHelper::requireUser();

            if ($searchRequestId <= 0) {
                throw new \Exception(
                    'Búsqueda inválida.',
                    422
                );
            }

            $result =
                CompatibilityEngine::calculateForSearchRequest(
                    $searchRequestId
                );

            ResponseHelper::ok([
                'calculation' => $result,
            ]);
        } catch (Throwable $e) {
            $code = (int)$e->getCode();

            if ($code < 400 || $code > 599) {
                $code = 500;
            }

            ResponseHelper::fail(
                $e->getMessage() ?:
                    'No se pudieron calcular las compatibilidades.',
                $code
            );
        }
    }
}