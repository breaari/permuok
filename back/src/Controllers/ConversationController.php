<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Services\ConversationService;
use App\Services\MembershipGuard;
use Throwable;

class ConversationController
{
    public static function start(): void
    {
        try {
            $user = AuthHelper::requireUser();

            MembershipGuard::requireActiveMembership((int)$user['id']);
            $input = self::getJsonInput();

            $result = ConversationService::startConversation(
                (int) $user['id'],
                $input
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function index(): void
    {
        try {
            $user = AuthHelper::requireUser();

            $result = ConversationService::listConversations(
                (int) $user['id'],
                $_GET
            );

            self::success($result);
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

    public static function sendMessage(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$user['id']);
            $input = self::getJsonInput();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::sendMessage(
                (int) $user['id'],
                $conversationId,
                $input
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    public static function updateStatus(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            $input = self::getJsonInput();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::updateStatus(
                (int) $user['id'],
                $conversationId,
                $input
            );

            self::success($result);
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

    public static function respondContactShare(int $conversationId): void
    {
        try {
            $user = AuthHelper::requireUser();
            MembershipGuard::requireActiveMembership((int)$user['id']);
            $input = self::getJsonInput();

            if ($conversationId <= 0) {
                throw new \Exception('Conversación inválida.', 422);
            }

            $result = ConversationService::respondContactShare(
                (int) $user['id'],
                $conversationId,
                $input
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }

    private static function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        return is_array($data) ? $data : [];
    }

    private static function success(array $data = [], int $status = 200): void
    {
        ResponseHelper::ok($data, $status);
    }

    private static function error(Throwable $e): void
    {
        $status = (int) ($e->getCode() ?: 400);

        if ($status < 100 || $status > 599) {
            $status = 400;
        }

        ResponseHelper::fail($e->getMessage(), $status);
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
            $user = AuthHelper::requireUser();

            $result = ConversationService::unreadCount(
                (int)$user['id']
            );

            self::success($result);
        } catch (Throwable $e) {
            self::error($e);
        }
    }
}
