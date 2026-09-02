<?php

namespace App\Services\AI;

use Exception;
use JsonException;
use Throwable;

require_once __DIR__ . '/AiProviderInterface.php';
require_once __DIR__ . '/AiPromptService.php';

class OpenAIProvider implements AiProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/responses';

    private string $apiKey;
    private string $model;
    private int $timeoutSeconds;
    private int $connectTimeoutSeconds;
    private int $maxRetries;

    public function __construct(
        string $apiKey,
        string $model,
        int $timeoutSeconds = 90,
        int $connectTimeoutSeconds = 15,
        int $maxRetries = 2
    ) {
        $this->apiKey = trim($apiKey);
        $this->model = trim($model);

        $this->timeoutSeconds = max(10, $timeoutSeconds);
        $this->connectTimeoutSeconds = max(
            5,
            $connectTimeoutSeconds
        );
        $this->maxRetries = max(0, $maxRetries);

        if ($this->apiKey === '') {
            throw new Exception(
                'No se configuró la clave de OpenAI'
            );
        }

        if ($this->model === '') {
            throw new Exception(
                'No se configuró el modelo de OpenAI'
            );
        }

        if (!function_exists('curl_init')) {
            throw new Exception(
                'La extensión cURL de PHP no está disponible'
            );
        }
    }

    /**
     * Analiza una entidad y devuelve el contrato esperado por
     * AiEnrichmentService.
     */
    public function analyzeEntity(
        string $entityType,
        array $context,
        string $promptVersion
    ): array {
        $payload = $this->buildPayload(
            $entityType,
            $context,
            $promptVersion
        );

        $response = $this->sendWithRetries($payload);

        $data = $this->extractStructuredData($response);

        $usage =
            $this->extractUsage(
                $response
            );

        return [
            'data' =>
            $data,

            'model' =>
            (string)(
                $response['model'] ??
                $this->model
            ),

            /*
     * Lo mantenemos por compatibilidad con
     * AiEnrichmentService y registros existentes.
     */
            'tokens_used' =>
            $usage['total_tokens'],

            /*
     * Uso detallado para métricas y costos.
     */
            'usage' =>
            $usage,
        ];
    }

    /**
     * Construye la solicitud para Responses API.
     */
    private function buildPayload(
        string $entityType,
        array $context,
        string $promptVersion
    ): array {
        $schemaConfig =
            AiPromptService::getEntityEnrichmentSchema();

        return [
            'model' => $this->model,

            'instructions' =>
            AiPromptService::buildInstructions(
                $entityType
            ),

            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' =>
                            AiPromptService::buildInput(
                                $entityType,
                                [
                                    'prompt_version' =>
                                    $promptVersion,

                                    'entity' => $context,
                                ]
                            ),
                        ],
                    ],
                ],
            ],

            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaConfig['name'],
                    'strict' => $schemaConfig['strict'],
                    'schema' => $schemaConfig['schema'],
                ],
            ],

            /*
             * Para extracción estructurada no necesitamos que el modelo
             * haga razonamiento extenso.
             */
            'reasoning' => [
                'effort' => 'minimal',
            ],

            /*
             * Evita almacenar la respuesta en OpenAI.
             */
            'store' => false,
        ];
    }

    /**
     * Ejecuta la llamada y reintenta errores temporales.
     */
    private function sendWithRetries(array $payload): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $this->sendRequest($payload);
            } catch (OpenAIRetryableException $e) {
                $lastException = $e;

                if ($attempt >= $this->maxRetries) {
                    break;
                }

                $retryAfter = $e->getRetryAfterSeconds();

                if ($retryAfter !== null) {
                    sleep($retryAfter);
                } else {
                    $this->sleepBeforeRetry($attempt);
                }
                $attempt++;
            }
        }

        throw new Exception(
            'OpenAI no respondió correctamente después de ' .
                ($this->maxRetries + 1) .
                ' intento(s): ' .
                ($lastException?->getMessage() ?? 'error desconocido'),
            0,
            $lastException
        );
    }

    /**
     * Envía una solicitud HTTP a OpenAI.
     */
    private function sendRequest(array $payload): array
    {
        try {
            $jsonPayload = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION |
                    JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo serializar la solicitud para OpenAI: ' .
                    $e->getMessage(),
                0,
                $e
            );
        }

        $curl = curl_init(self::API_URL);

        if ($curl === false) {
            throw new Exception(
                'No se pudo iniciar la conexión con OpenAI'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,

            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],

            CURLOPT_POSTFIELDS => $jsonPayload,

            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT =>
            $this->connectTimeoutSeconds,

            CURLOPT_ENCODING => '',
        ]);

        $rawResponse = curl_exec($curl);

        if ($rawResponse === false) {
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);

            curl_close($curl);

            throw new OpenAIRetryableException(
                'Error de conexión con OpenAI (' .
                    $curlErrno .
                    '): ' .
                    $curlError
            );
        }

        $httpStatus = (int)curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        $headerSize = (int)curl_getinfo(
            $curl,
            CURLINFO_HEADER_SIZE
        );

        $rawHeaders = substr(
            $rawResponse,
            0,
            $headerSize
        );

        $rawBody = substr(
            $rawResponse,
            $headerSize
        );

        curl_close($curl);

        try {
            $decoded = json_decode(
                $rawBody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new Exception(
                'OpenAI devolvió una respuesta JSON inválida. ' .
                    'HTTP ' .
                    $httpStatus,
                0,
                $e
            );
        }

        if (!is_array($decoded)) {
            throw new Exception(
                'OpenAI devolvió una respuesta inesperada'
            );
        }

        if ($httpStatus >= 200 && $httpStatus < 300) {
            return $decoded;
        }

        $message = $this->extractApiErrorMessage(
            $decoded,
            $httpStatus
        );

        if ($this->isRetryableStatus($httpStatus)) {
            $retryAfter = $this->extractRetryAfter(
                $rawHeaders
            );

            throw new OpenAIRetryableException(
                $message,
                $retryAfter
            );
        }

        throw new Exception($message);
    }

    /**
     * Obtiene el JSON generado dentro de output.
     */
    private function extractStructuredData(
        array $response
    ): array {
        $status = (string)($response['status'] ?? '');

        if ($status === 'incomplete') {
            $reason = (string)(
                $response['incomplete_details']['reason']
                ?? 'desconocido'
            );

            throw new Exception(
                'La respuesta de OpenAI quedó incompleta: ' .
                    $reason
            );
        }

        $output = $response['output'] ?? null;

        if (!is_array($output)) {
            throw new Exception(
                'OpenAI no devolvió el campo output'
            );
        }

        foreach ($output as $outputItem) {
            if (!is_array($outputItem)) {
                continue;
            }

            /*
             * Un rechazo puede venir como elemento de contenido.
             */
            if (($outputItem['type'] ?? null) === 'refusal') {
                throw new Exception(
                    'OpenAI rechazó el análisis: ' .
                        (string)(
                            $outputItem['refusal']
                            ?? 'sin detalle'
                        )
                );
            }

            $content = $outputItem['content'] ?? null;

            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }

                $type = (string)(
                    $contentItem['type'] ?? ''
                );

                if ($type === 'refusal') {
                    throw new Exception(
                        'OpenAI rechazó el análisis: ' .
                            (string)(
                                $contentItem['refusal']
                                ?? 'sin detalle'
                            )
                    );
                }

                if ($type !== 'output_text') {
                    continue;
                }

                $text = trim(
                    (string)($contentItem['text'] ?? '')
                );

                if ($text === '') {
                    continue;
                }

                try {
                    $decoded = json_decode(
                        $text,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (JsonException $e) {
                    throw new Exception(
                        'El contenido estructurado no es JSON válido: ' .
                            $e->getMessage(),
                        0,
                        $e
                    );
                }

                if (!is_array($decoded)) {
                    throw new Exception(
                        'El resultado estructurado no es un objeto JSON'
                    );
                }

                return $decoded;
            }
        }

        throw new Exception(
            'OpenAI no devolvió contenido estructurado'
        );
    }

    private function extractUsage(
        array $response
    ): array {
        $usage =
            $response['usage'] ?? [];

        if (!is_array($usage)) {
            $usage = [];
        }

        $inputTokens =
            max(
                0,
                (int)(
                    $usage['input_tokens'] ?? 0
                )
            );

        $outputTokens =
            max(
                0,
                (int)(
                    $usage['output_tokens'] ?? 0
                )
            );

        $cachedInputTokens =
            max(
                0,
                (int)(
                    $usage['input_tokens_details']['cached_tokens'] ?? 0
                )
            );

        $totalTokens =
            isset($usage['total_tokens'])
            ? max(
                0,
                (int)$usage['total_tokens']
            )
            : (
                $inputTokens +
                $outputTokens
            );

        return [
            'input_tokens' =>
            $inputTokens,

            'cached_input_tokens' =>
            $cachedInputTokens,

            'output_tokens' =>
            $outputTokens,

            'total_tokens' =>
            $totalTokens,
        ];
    }

    private function extractApiErrorMessage(
        array $response,
        int $httpStatus
    ): string {
        $error = $response['error'] ?? null;

        if (is_array($error)) {
            $message = trim(
                (string)($error['message'] ?? '')
            );

            $type = trim(
                (string)($error['type'] ?? '')
            );

            $code = trim(
                (string)($error['code'] ?? '')
            );

            $parts = [];

            if ($message !== '') {
                $parts[] = $message;
            }

            if ($type !== '') {
                $parts[] = 'tipo: ' . $type;
            }

            if ($code !== '') {
                $parts[] = 'código: ' . $code;
            }

            if ($parts !== []) {
                return 'Error de OpenAI HTTP ' .
                    $httpStatus .
                    ': ' .
                    implode(' | ', $parts);
            }
        }

        return 'Error de OpenAI HTTP ' . $httpStatus;
    }

    private function isRetryableStatus(int $status): bool
    {
        return in_array(
            $status,
            [408, 409, 429, 500, 502, 503, 504],
            true
        );
    }

    private function extractRetryAfter(
        string $headers
    ): ?int {
        if (
            preg_match(
                '/^retry-after:\s*(\d+)/mi',
                $headers,
                $matches
            ) === 1
        ) {
            return max(1, (int)$matches[1]);
        }

        return null;
    }

    private function sleepBeforeRetry(int $attempt): void
    {
        /*
         * 1 segundo, 2 segundos, 4 segundos...
         * más una pequeña variación aleatoria.
         */
        $baseMilliseconds =
            (2 ** $attempt) * 1000;

        $jitterMilliseconds =
            random_int(100, 500);

        usleep(
            ($baseMilliseconds + $jitterMilliseconds)
                * 1000
        );
    }
}

/**
 * Excepción interna para errores que permiten reintentar.
 */
class OpenAIRetryableException extends Exception
{
    private ?int $retryAfterSeconds;

    public function __construct(
        string $message,
        ?int $retryAfterSeconds = null
    ) {
        parent::__construct($message);

        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
