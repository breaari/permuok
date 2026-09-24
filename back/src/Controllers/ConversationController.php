<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\QueryParamHelper;
use App\Services\ConversationService;
use App\Services\MembershipGuard;
use Throwable;

class ConversationController
{
    public static function start(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            /*
         * Solamente la inmobiliaria y sus agentes
         * pueden iniciar una conversación.
         * Los inversores pueden participar cuando
         * sean incorporados a una existente.
         */
            if (
                !in_array(
                    (int)$user['role'],
                    [2, 3],
                    true
                )
            ) {
                throw new \Exception(
                    'No tenés permisos para iniciar conversaciones.',
                    403
                );
            }

            MembershipGuard::requireActiveMembership(
                (int)$user['id']
            );

            $input =
                self::getJsonInput();

            if (
                !isset($input['opportunity_type']) ||
                !is_string(
                    $input['opportunity_type']
                )
            ) {
                throw new \Exception(
                    'El tipo de oportunidad es obligatorio.',
                    422
                );
            }

            $opportunityType =
                trim(
                    $input['opportunity_type']
                );

            if (
                !in_array(
                    $opportunityType,
                    [
                        'property',
                        'search_request',
                        'development',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'Tipo de oportunidad inválido.',
                    422
                );
            }

            $opportunityId = filter_var(
                $input['opportunity_id'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($opportunityId === false) {
                throw new \Exception(
                    'La oportunidad es obligatoria.',
                    422
                );
            }

            if (
                !isset($input['message']) ||
                !is_string(
                    $input['message']
                )
            ) {
                throw new \Exception(
                    'El mensaje inicial es obligatorio.',
                    422
                );
            }

            $message =
                trim($input['message']);

            if ($message === '') {
                throw new \Exception(
                    'El mensaje inicial es obligatorio.',
                    422
                );
            }

            if (
                self::textLength($message) >
                2000
            ) {
                throw new \Exception(
                    'El mensaje no puede superar los 2000 caracteres.',
                    422
                );
            }

            $result =
                ConversationService::startConversation(
                    (int)$user['id'],
                    [
                        'opportunity_type' =>
                        $opportunityType,

                        'opportunity_id' =>
                        (int)$opportunityId,

                        'message' =>
                        $message,
                    ]
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function index(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'page',
                'limit',
                'archived',
            ]);

            $filters = [
                'page' =>
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                ),
                'limit' =>
                QueryParamHelper::positiveInt(
                    'limit',
                    20,
                    50
                ),
                'archived' =>
                QueryParamHelper::boolean(
                    'archived',
                    false
                ),
            ];

            $result =
                ConversationService::listConversations(
                    (int)$user['id'],
                    $filters
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function inbox(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'tab',
                'status',
                'search',
                'page',
                'limit',
                'archived',
            ]);

            $filters = [
                'tab' =>
                QueryParamHelper::enum(
                    'tab',
                    [
                        'own',
                        'external',
                        'matches',
                    ],
                    'own'
                ),
                'status' =>
                QueryParamHelper::enum(
                    'status',
                    [
                        'all',
                        'open',
                        'negotiating',
                        'visit_scheduled',
                        'closed',
                        'discarded',
                    ],
                    'all'
                ),
                'search' =>
                QueryParamHelper::optionalString(
                    'search',
                    150
                ),
                'page' =>
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                ),
                'limit' =>
                QueryParamHelper::positiveInt(
                    'limit',
                    20,
                    50
                ),
                'archived' =>
                QueryParamHelper::boolean(
                    'archived',
                    false
                ),
            ];

            $result =
                ConversationService::getInbox(
                    (int)$user['id'],
                    $filters
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function show(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::getConversationDetail(
                (int) $user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function sendMessage(
        int $conversationId
    ): void {
        try {
            if ($conversationId <= 0) {
                throw new \Exception(
                    'Conversación inválida.',
                    422
                );
            }

            $user =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$user['id']
            );

            $input =
                self::getJsonInput();

            if (
                !isset($input['body']) ||
                !is_string(
                    $input['body']
                )
            ) {
                throw new \Exception(
                    'El mensaje no puede estar vacío.',
                    422
                );
            }

            $body =
                trim($input['body']);

            if ($body === '') {
                throw new \Exception(
                    'El mensaje no puede estar vacío.',
                    422
                );
            }

            if (
                self::textLength($body) >
                2000
            ) {
                throw new \Exception(
                    'El mensaje no puede superar los 2000 caracteres.',
                    422
                );
            }

            $result =
                ConversationService::sendMessage(
                    (int)$user['id'],
                    $conversationId,
                    [
                        'body' => $body,
                    ]
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function updateStatus(
        int $conversationId
    ): void {
        try {
            if ($conversationId <= 0) {
                throw new \Exception(
                    'Conversación inválida.',
                    422
                );
            }

            $user =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$user['id']
            );

            $input =
                self::getJsonInput();

            if (
                !isset($input['status']) ||
                !is_string(
                    $input['status']
                )
            ) {
                throw new \Exception(
                    'Estado de conversación inválido.',
                    422
                );
            }

            $status =
                trim($input['status']);

            if (
                !in_array(
                    $status,
                    [
                        'open',
                        'negotiating',
                        'visit_scheduled',
                        'closed',
                        'discarded',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'Estado de conversación inválido.',
                    422
                );
            }

            $result =
                ConversationService::updateStatus(
                    (int)$user['id'],
                    $conversationId,
                    [
                        'status' => $status,
                    ]
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function requestContactShare(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$user['id']);


            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::requestContactShare(
                (int) $user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function respondContactShare(
        int $conversationId
    ): void {
        try {
            if ($conversationId <= 0) {
                throw new \Exception(
                    'Conversación inválida.',
                    422
                );
            }

            $user =
                AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership(
                (int)$user['id']
            );

            $input =
                self::getJsonInput();

            if (
                !isset($input['decision']) ||
                !is_string(
                    $input['decision']
                )
            ) {
                throw new \Exception(
                    'La respuesta debe ser accepted o rejected.',
                    422
                );
            }

            $decision =
                trim($input['decision']);

            if (
                !in_array(
                    $decision,
                    [
                        'accepted',
                        'rejected',
                    ],
                    true
                )
            ) {
                throw new \Exception(
                    'La respuesta debe ser accepted o rejected.',
                    422
                );
            }

            $reason = '';

            if (
                array_key_exists(
                    'reason',
                    $input
                ) &&
                $input['reason'] !== null
            ) {
                if (
                    !is_string(
                        $input['reason']
                    )
                ) {
                    throw new \Exception(
                        'El motivo de rechazo es inválido.',
                        422
                    );
                }

                $reason =
                    trim($input['reason']);

                if (
                    self::textLength($reason) >
                    1000
                ) {
                    throw new \Exception(
                        'El motivo de rechazo es demasiado extenso.',
                        422
                    );
                }
            }

            /*
         * Si se acepta, descartamos cualquier motivo
         * enviado accidentalmente por el cliente.
         */
            if ($decision === 'accepted') {
                $reason = '';
            }

            $result =
                ConversationService::respondContactShare(
                    (int)$user['id'],
                    $conversationId,
                    [
                        'decision' =>
                        $decision,

                        'reason' =>
                        $reason,
                    ]
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    private static function getJsonInput(): array
    {
        $raw =
            file_get_contents(
                'php://input'
            );

        if (
            $raw === false ||
            trim($raw) === ''
        ) {
            throw new \Exception(
                'El cuerpo de la solicitud está vacío.',
                422
            );
        }

        $raw = trim($raw);

        if ($raw[0] !== '{') {
            throw new \Exception(
                'El cuerpo JSON debe ser un objeto.',
                422
            );
        }

        try {
            $data = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \Exception(
                'El cuerpo JSON es inválido.',
                422
            );
        }

        if (!is_array($data)) {
            throw new \Exception(
                'El cuerpo JSON es inválido.',
                422
            );
        }

        return $data;
    }

    private static function textLength(
        string $text
    ): int {
        return function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);
    }

    private static function success(array $data = [], int $status = 200): void
    {
        ResponseHelper::ok($data, $status);
    }

    private static function error(Throwable $e): void
    {
        ResponseHelper::fromThrowable(
            $e,
            'No se pudo completar la operación con la conversación.',
            'ConversationController'
        );
    }

    public static function archive(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::archiveConversation(
                (int)$user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function unarchive(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::unarchiveConversation(
                (int)$user['id'],
                $conversationId
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function unreadCount(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([]);

            $result =
                ConversationService::unreadCount(
                    (int)$user['id']
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function inboxGroup(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'tab',
                'opportunity_type',
                'opportunity_id',
                'page',
                'limit',
                'archived',
            ]);

            $filters = [
                'tab' =>
                QueryParamHelper::enum(
                    'tab',
                    [
                        'own',
                        'external',
                    ],
                    'own'
                ),
                'opportunity_type' =>
                QueryParamHelper::enum(
                    'opportunity_type',
                    [
                        'property',
                        'search_request',
                        'development',
                    ],
                    ''
                ),
                'opportunity_id' =>
                QueryParamHelper::requiredPositiveInt(
                    'opportunity_id'
                ),
                'page' =>
                QueryParamHelper::positiveInt(
                    'page',
                    1,
                    1000000
                ),
                'limit' =>
                QueryParamHelper::positiveInt(
                    'limit',
                    20,
                    50
                ),
                'archived' =>
                QueryParamHelper::boolean(
                    'archived',
                    false
                ),
            ];

            if ($filters['opportunity_type'] === '') {
                throw new \Exception(
                    'Tipo de publicación inválido.',
                    422
                );
            }

            $result =
                ConversationService::getInboxGroup(
                    (int)$user['id'],
                    $filters
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function existing(): void
    {
        try {
            $user =
                AuthHelper::requireUser();

            QueryParamHelper::rejectUnknown([
                'opportunity_type',
                'opportunity_id',
            ]);

            $opportunityType =
                QueryParamHelper::enum(
                    'opportunity_type',
                    [
                        'property',
                        'search_request',
                        'development',
                    ],
                    ''
                );

            if ($opportunityType === '') {
                throw new \Exception(
                    'Tipo de oportunidad inválido.',
                    422
                );
            }

            $opportunityId =
                QueryParamHelper::requiredPositiveInt(
                    'opportunity_id'
                );

            $result =
                ConversationService::findExistingForOpportunity(
                    (int)$user['id'],
                    [
                        'opportunity_type' =>
                        $opportunityType,

                        'opportunity_id' =>
                        $opportunityId,
                    ]
                );

            self::success(
                $result
            );
        } catch (Throwable $e) {
            self::error($e);
        }
    }
}
