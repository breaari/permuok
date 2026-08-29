<?php

namespace App\Services\AI;

use PDO;
use Throwable;

class AiUsageService
{
    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function log(array $data): ?int
    {
        try {
            $pdo = self::db();

            $inputTokens = max(
                0,
                (int)($data['input_tokens'] ?? 0)
            );

            $cachedInputTokens = max(
                0,
                (int)($data['cached_input_tokens'] ?? 0)
            );

            $outputTokens = max(
                0,
                (int)($data['output_tokens'] ?? 0)
            );

            $totalTokens = max(
                0,
                (int)(
                    $data['total_tokens']
                    ?? ($inputTokens + $outputTokens)
                )
            );

            $stmt = $pdo->prepare("
                INSERT INTO ai_usage_logs (
                    provider,
                    model_name,
                    operation,
                    entity_type,
                    entity_id,
                    real_estate_id,
                    user_id,
                    input_tokens,
                    cached_input_tokens,
                    output_tokens,
                    total_tokens,
                    estimated_cost_usd,
                    status,
                    duration_ms,
                    error_message
                ) VALUES (
                    :provider,
                    :model_name,
                    :operation,
                    :entity_type,
                    :entity_id,
                    :real_estate_id,
                    :user_id,
                    :input_tokens,
                    :cached_input_tokens,
                    :output_tokens,
                    :total_tokens,
                    :estimated_cost_usd,
                    :status,
                    :duration_ms,
                    :error_message
                )
            ");

            $stmt->execute([
                'provider' =>
                    trim((string)($data['provider'] ?? 'openai')),

                'model_name' =>
                    self::nullableString(
                        $data['model_name'] ?? null
                    ),

                'operation' =>
                    trim((string)($data['operation'] ?? 'unknown')),

                'entity_type' =>
                    self::nullableString(
                        $data['entity_type'] ?? null
                    ),

                'entity_id' =>
                    self::nullablePositiveInt(
                        $data['entity_id'] ?? null
                    ),

                'real_estate_id' =>
                    self::nullablePositiveInt(
                        $data['real_estate_id'] ?? null
                    ),

                'user_id' =>
                    self::nullablePositiveInt(
                        $data['user_id'] ?? null
                    ),

                'input_tokens' =>
                    $inputTokens,

                'cached_input_tokens' =>
                    $cachedInputTokens,

                'output_tokens' =>
                    $outputTokens,

                'total_tokens' =>
                    $totalTokens,

                'estimated_cost_usd' =>
                    max(
                        0,
                        (float)(
                            $data['estimated_cost_usd'] ?? 0
                        )
                    ),

                'status' =>
                    trim((string)($data['status'] ?? 'success')),

                'duration_ms' =>
                    isset($data['duration_ms'])
                        ? max(0, (int)$data['duration_ms'])
                        : null,

                'error_message' =>
                    self::nullableString(
                        $data['error_message'] ?? null
                    ),
            ]);

            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            /*
             * El registro estadístico nunca debe romper
             * la funcionalidad principal de IA.
             */
            error_log(
                '[AiUsageService] ' . $e->getMessage()
            );

            return null;
        }
    }

    private static function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== ''
            ? $value
            : null;
    }

    private static function nullablePositiveInt(
        mixed $value
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int)$value;

        return $value > 0
            ? $value
            : null;
    }
}