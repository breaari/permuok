<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\AI\CompatibilityEngine;
use App\Services\MembershipGuard;
use App\Services\SearchRequestService;
use Throwable;

class AiCompatibilityController
{
    public static function calculateForSearchRequest(
        int $searchRequestId
    ): void {
        try {
            $user = AuthHelper::requireUser();

            if ($searchRequestId <= 0) {
                throw new \Exception(
                    'Búsqueda inválida.',
                    422
                );
            }

            /*
             * El recálculo consume recursos y modifica
             * compatibilidades. Solo puede ejecutarlo
             * una inmobiliaria con membresía activa.
             */
            MembershipGuard::requireActiveMembership(
                (int)$user['id']
            );

            /*
             * getDetail() verifica que la búsqueda
             * pertenezca a la inmobiliaria del usuario.
             * No alcanza con recibir un ID válido.
             */
            SearchRequestService::getDetail(
                (int)$user['id'],
                $searchRequestId
            );

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
                $e->getMessage()
                    ?: 'No se pudieron calcular las compatibilidades.',
                $code
            );
        }
    }
}
