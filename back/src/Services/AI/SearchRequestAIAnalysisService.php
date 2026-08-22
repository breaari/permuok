<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;
use Throwable;
use App\Services\CompatibilityJobService;

class SearchRequestAIAnalysisService
{
    private const PROMPT_VERSION = '1.3';
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    public static function prepareInput(
        int $searchRequestId
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT *
            FROM search_requests
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $searchRequestId,
        ]);

        $request =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception(
                'Búsqueda no encontrada.'
            );
        }

        $stTypes = $pdo->prepare("
            SELECT property_type
            FROM search_request_property_types
            WHERE search_request_id = :id
            ORDER BY property_type ASC
        ");

        $stTypes->execute([
            'id' => $searchRequestId,
        ]);

        $propertyTypes =
            $stTypes->fetchAll(PDO::FETCH_COLUMN)
            ?: [];

        $stAmenities = $pdo->prepare("
            SELECT amenity_code
            FROM search_request_amenities
            WHERE search_request_id = :id
              AND deleted_at IS NULL
            ORDER BY amenity_code ASC
        ");

        $stAmenities->execute([
            'id' => $searchRequestId,
        ]);

        $amenities =
            $stAmenities->fetchAll(PDO::FETCH_COLUMN)
            ?: [];

        return [
            'entity_type' =>
            'search_request',

            'entity_id' =>
            $searchRequestId,

            'search_request' => [
                'title' =>
                trim((string)$request['title']),

                'description' =>
                trim((string)$request['description']),

                'location' => [
                    'country' =>
                    $request['country'],

                    'province' =>
                    $request['province'],

                    'city' =>
                    $request['city'],

                    'zone' =>
                    $request['zone'],

                    'open_to_other_zones' =>
                    (bool)$request['open_to_other_zones'],
                ],

                'property_types' =>
                $propertyTypes,

                'property_condition' =>
                $request['property_condition'],

                'budget' => [
                    'currency' =>
                    $request['currency'],

                    'min' =>
                    $request['min_value'],

                    'max' =>
                    $request['max_value'],
                ],

                'criteria' => [
                    'min_total_area' =>
                    $request['min_total_area'],

                    'min_covered_area' =>
                    $request['min_covered_area'],

                    'min_bedrooms' =>
                    $request['min_bedrooms'],

                    'min_bathrooms' =>
                    $request['min_bathrooms'],

                    'min_garages' =>
                    $request['min_garages'],

                    'max_antiquity' =>
                    $request['max_antiquity'],

                    'amenities' =>
                    $amenities,
                ],

                'payment' => [
                    'cash' =>
                    (bool)$request['payment_mode_cash'],

                    'swap' =>
                    (bool)$request['payment_mode_swap'],

                    'cash_difference_max' =>
                    $request['cash_difference_max'],

                    'cash_difference_currency' =>
                    $request['cash_difference_currency'],
                ],

                'urgency' =>
                $request['urgency'],

                'notes' =>
                $request['notes'],
            ],
        ];
    }

    public static function buildInputHash(
        int $searchRequestId
    ): string {
        $payload = [
            'prompt_version' =>
            self::PROMPT_VERSION,

            'input' =>
            self::prepareInput(
                $searchRequestId
            ),
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION |
                    JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo preparar el análisis IA.',
                0,
                $e
            );
        }

        return hash(
            'sha256',
            $json
        );
    }

    public static function requestAnalysis(
        int $searchRequestId
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido.'
            );
        }

        $pdo = self::db();

        $inputHash =
            self::buildInputHash(
                $searchRequestId
            );

        $st = $pdo->prepare("
        SELECT *
        FROM publication_ai_analyses
        WHERE entity_type = 'search_request'
          AND entity_id = :entity_id
          AND input_hash = :input_hash
          AND prompt_version = :prompt_version
        ORDER BY id DESC
        LIMIT 1
    ");

        $st->execute([
            'entity_id' => $searchRequestId,
            'input_hash' => $inputHash,
            'prompt_version' => self::PROMPT_VERSION,
        ]);

        $existing =
            $st->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (
            $existing &&
            $existing['status'] === 'completed'
        ) {
            $quality =
                SearchRequestQualityScoreService::recalculateAndPersist(
                    $searchRequestId
                );

            return [
                'analysis_id' =>
                (int)$existing['id'],

                'status' =>
                'completed',

                'reused' =>
                true,

                'queued' =>
                false,

                'quality' =>
                $quality,
            ];
        }

        if (
            $existing &&
            in_array(
                $existing['status'],
                ['pending', 'processing'],
                true
            )
        ) {
            $analysisId =
                (int)$existing['id'];
        } elseif (
            $existing &&
            $existing['status'] === 'failed'
        ) {
            $analysisId =
                (int)$existing['id'];

            $pdo->prepare("
            UPDATE publication_ai_analyses
            SET
                status = 'pending',
                error_message = NULL,
                analyzed_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute([
                'id' => $analysisId,
            ]);
        } else {
            $stInsert = $pdo->prepare("
            INSERT INTO publication_ai_analyses (
                entity_type,
                entity_id,
                status,
                prompt_version,
                input_hash
            ) VALUES (
                'search_request',
                :entity_id,
                'pending',
                :prompt_version,
                :input_hash
            )
        ");

            $stInsert->execute([
                'entity_id' => $searchRequestId,
                'prompt_version' => self::PROMPT_VERSION,
                'input_hash' => $inputHash,
            ]);

            $analysisId =
                (int)$pdo->lastInsertId();
        }

        $job =
            CompatibilityJobService::enqueueSearchRequestAIAnalysis(
                $searchRequestId,
                $analysisId
            );

        return [
            'analysis_id' => $analysisId,
            'status' => 'pending',
            'reused' => $existing !== null,
            'queued' => true,
            'job_id' => (int)($job['id'] ?? 0),
        ];
    }
    public static function processAnalysis(
        int $searchRequestId,
        int $analysisId,
        int $attempt = 1,
        int $maxAttempts = 3
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido.'
            );
        }

        if ($analysisId <= 0) {
            throw new Exception(
                'El analysis_id no es válido.'
            );
        }

        $pdo = self::db();

        $st = $pdo->prepare("
        SELECT *
        FROM publication_ai_analyses
        WHERE id = :id
          AND entity_type = 'search_request'
          AND entity_id = :entity_id
        LIMIT 1
    ");

        $st->execute([
            'id' => $analysisId,
            'entity_id' => $searchRequestId,
        ]);

        $analysis =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$analysis) {
            throw new Exception(
                'No se encontró el análisis IA de la búsqueda.'
            );
        }

        if ($analysis['status'] === 'completed') {
            return [
                'ok' => true,
                'skipped' => true,
                'analysis_id' => $analysisId,
                'reason' => 'El análisis ya estaba completado.',
            ];
        }

        $currentHash =
            self::buildInputHash(
                $searchRequestId
            );

        $requestedHash =
            (string)($analysis['input_hash'] ?? '');

        if (
            $requestedHash === '' ||
            !hash_equals(
                $requestedHash,
                $currentHash
            )
        ) {
            $pdo->prepare("
            UPDATE publication_ai_analyses
            SET
                status = 'failed',
                error_message = :message,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute([
                'message' =>
                'La búsqueda cambió después de solicitar el análisis.',
                'id' =>
                $analysisId,
            ]);

            return [
                'ok' => true,
                'skipped' => true,
                'analysis_id' => $analysisId,
                'reason' =>
                'La búsqueda cambió después de solicitar el análisis.',
            ];
        }

        $pdo->prepare("
        UPDATE publication_ai_analyses
        SET
            status = 'processing',
            error_message = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ")->execute([
            'id' => $analysisId,
        ]);

        try {
            $input =
                self::prepareInput(
                    $searchRequestId
                );

            $result =
                self::callOpenAI(
                    $input
                );

            self::completeAnalysis(
                $analysisId,
                $result
            );

            /*
 * Una vez persistido el análisis IA,
 * recalculamos y guardamos el score
 * oficial híbrido de la búsqueda.
 */
            $quality =
                SearchRequestQualityScoreService::recalculateAndPersist(
                    $searchRequestId
                );

            return [
                'ok' => true,
                'analysis_id' => $analysisId,
                'status' => 'completed',
                'quality' => $quality,
            ];
        } catch (Throwable $e) {
            if ($attempt < $maxAttempts) {
                self::markPending(
                    $analysisId,
                    $e->getMessage()
                );
            } else {
                self::markFailed(
                    $analysisId,
                    $e->getMessage()
                );
            }

            throw $e;
        }
    }

    private static function callOpenAI(
        array $input
    ): array {
        $apiKey =
            trim(
                (string)(
                    $_ENV['OPENAI_API_KEY']
                    ?? getenv('OPENAI_API_KEY')
                    ?: ''
                )
            );

        if ($apiKey === '') {
            throw new Exception(
                'OPENAI_API_KEY no está configurada.'
            );
        }

        $model =
            trim(
                (string)(
                    $_ENV['OPENAI_MODEL']
                    ?? getenv('OPENAI_MODEL')
                    ?: self::DEFAULT_MODEL
                )
            );

        $inputJson =
            json_encode(
                $input,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            );

        $prompt = <<<PROMPT
Sos un especialista en búsquedas inmobiliarias B2B.

Analizá esta búsqueda publicada en PermuOK.

Tu análisis será mostrado directamente a un usuario de una inmobiliaria.
Por eso, todo texto visible debe estar escrito en lenguaje inmobiliario claro,
natural y accionable.

Evaluá:

- claridad y calidad del título;
- claridad y utilidad de la descripción;
- coherencia entre los criterios cargados;
- utilidad de la búsqueda para generar matches.

REGLAS DE EVALUACIÓN:

- No inventes datos.
- No penalices por campos opcionales vacíos.
- Una búsqueda abierta puede ser perfectamente válida.
- Penalizá contradicciones reales, no simples faltantes.
- El título debe permitir entender rápidamente qué se busca.
- La descripción debe aportar contexto útil sin repetir mecánicamente la ficha.
- Priorizá la utilidad para matching inmobiliario.
- Escribí en español rioplatense profesional.

REGLAS DE LENGUAJE PARA EL USUARIO:

- Nunca muestres nombres técnicos de campos, variables, claves JSON ni nombres de base de datos.
- Nunca escribas términos como:
  payment.cash,
  payment.swap,
  cash_difference_max,
  cash_difference_currency,
  min_total_area,
  min_covered_area,
  property_condition,
  payment_mode_cash,
  payment_mode_swap.
- Nunca muestres valores internos como "new", "any", "true", "false", "null" o "0.00".
- Traducí siempre esos conceptos al lenguaje que entiende el usuario.

Usá estas equivalencias conceptuales:

- payment.cash / payment_mode_cash:
  "Pago con dinero".

- payment.swap / payment_mode_swap:
  "Acepta permuta".

- cash_difference_max:
  "Diferencia máxima en dinero".

- cash_difference_currency:
  "Moneda de la diferencia".

- property_condition = new:
  "Propiedad a estrenar".

- property_condition = used:
  "Propiedad con antigüedad".

- property_condition = any:
  "Sin preferencia respecto del estado de la propiedad".

- min_total_area:
  "Superficie total mínima".

- min_covered_area:
  "Superficie cubierta mínima".

- min_bedrooms:
  "Dormitorios mínimos".

- min_bathrooms:
  "Baños mínimos".

- min_garages:
  "Cocheras mínimas".

Las contradicciones deben explicar el problema como lo explicaría
un asesor inmobiliario a otro.

MAL:
"'payment.cash' está en false pero existe 'payment.cash_difference_max'."

BIEN:
"Indicás que la operación no contempla pago con dinero, pero también cargaste
una diferencia máxima en dinero para la permuta. Revisá la modalidad de pago
para que no quede información contradictoria."

MAL:
"'property_condition' está en 'new'."

BIEN:
"Indicás que buscás únicamente propiedades a estrenar. Si también considerarías
propiedades con antigüedad, ampliar este criterio puede generar más coincidencias."

MAL:
"min_total_area está en 0.00."

BIEN:
"No definiste una superficie total mínima. Si el cliente tiene un mínimo
necesario, cargarlo puede mejorar la precisión de los matches."

VOCABULARIO INMOBILIARIO:

- No uses la expresión "propiedad usada".
- Para inmuebles nuevos usá "a estrenar".
- Para inmuebles que no son nuevos usá "con antigüedad".
- Cuando se aceptan ambos casos, hablá de "sin preferencia respecto del estado".
- Usá vocabulario habitual de una inmobiliaria argentina.

REGLAS PARA EVITAR REPETICIONES:

- No repitas el mismo problema en "contradictions" y en "suggestions".
- Si detectás una contradicción, informala solamente en "contradictions".
- No generes además una sugerencia sobre ese mismo conflicto.
- Cada sugerencia debe tratar un aspecto diferente de la búsqueda.
- No generes dos sugerencias que conduzcan esencialmente a la misma corrección.
- Priorizá pocas recomendaciones útiles antes que muchas recomendaciones parecidas.

Ejemplo:

Si el título dice "1 ambiente" pero la descripción habla de "1,5 y 2 ambientes":

CORRECTO:
- incluir la inconsistencia en "contradictions";
- NO agregar además una sugerencia sobre corregir el título por ese mismo motivo;
- sí podés sugerir mejorar la descripción si existe otro problema diferente,
  como falta de contexto relevante.

Si la modalidad de pago es contradictoria:

CORRECTO:
- incluir el conflicto en "contradictions";
- NO agregar también sugerencias sobre modalidad de pago,
  diferencia en dinero o moneda si todas derivan de la misma contradicción.

REGLAS PARA LAS SUGERENCIAS:

Cada sugerencia debe tener:

- field:
  identificador técnico interno. Puede contener el nombre real del campo
  porque NO se mostrará directamente al usuario.

- action:
  categoría interna de la acción. Usá solamente:
  rewrite, clarify, fix, review o complete.

- title:
  título breve y claro para el usuario.
  Debe explicar qué debería mejorar.
  No uses palabras sueltas como "editar", "clarificar", "corregir",
  "revisar" o "completar".

- message:
  explicación concreta de qué debería cambiar y por qué.
  Nunca debe contener nombres técnicos ni valores internos.

Ejemplos de buenos títulos:

- "Mejorá el título de la búsqueda"
- "Aclará qué cantidad de ambientes acepta"
- "Revisá la modalidad de pago"
- "Definí el estado de la propiedad buscada"
- "Indicá una superficie mínima"
- "Acotá el rango de presupuesto"
- "Sumá contexto a la descripción"

Las sugerencias tienen que servir para que el usuario pueda actuar
inmediatamente sobre el formulario.

Devolvé puntajes de 0 a 100.

BÚSQUEDA:

{$inputJson}
PROMPT;

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,

            'properties' => [
                'title_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'description_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'consistency_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'matchability_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],

                'suggestions' => [
                    'type' => 'array',
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,

                        'properties' => [
                            'field' => [
                                'type' => 'string',
                            ],

                            'action' => [
                                'type' => 'string',
                                'enum' => [
                                    'rewrite',
                                    'clarify',
                                    'fix',
                                    'review',
                                    'complete',
                                ],
                            ],

                            'title' => [
                                'type' => 'string',
                            ],

                            'message' => [
                                'type' => 'string',
                            ],

                            'priority' => [
                                'type' => 'string',
                                'enum' => [
                                    'low',
                                    'medium',
                                    'high',
                                ],
                            ],
                        ],

                        'required' => [
                            'field',
                            'action',
                            'title',
                            'message',
                            'priority',
                        ],
                    ],
                ],

                'contradictions' => [
                    'type' => 'array',
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],

            'required' => [
                'title_score',
                'description_score',
                'consistency_score',
                'matchability_score',
                'suggestions',
                'contradictions',
            ],
        ];

        $body = [
            'model' => $model,

            'reasoning' => [
                'effort' => 'low',
            ],

            'input' => $prompt,

            'text' => [
                'verbosity' => 'low',

                'format' => [
                    'type' => 'json_schema',
                    'name' => 'search_request_analysis',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        $ch =
            curl_init(
                'https://api.openai.com/v1/responses'
            );

        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],

                CURLOPT_POSTFIELDS =>
                json_encode(
                    $body,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                ),

                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
            ]
        );

        $response =
            curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception(
                'Error conectando con OpenAI: ' .
                    $error
            );
        }

        $httpCode =
            (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        curl_close($ch);

        $decoded =
            json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            $httpCode < 200 ||
            $httpCode >= 300
        ) {
            throw new Exception(
                'OpenAI API: ' .
                    (
                        $decoded['error']['message']
                        ?? 'Error desconocido.'
                    )
            );
        }

        $outputText = '';

        foreach (
            $decoded['output'] ?? []
            as $output
        ) {
            foreach (
                $output['content'] ?? []
                as $content
            ) {
                if (
                    ($content['type'] ?? '') ===
                    'output_text'
                ) {
                    $outputText .=
                        (string)($content['text'] ?? '');
                }
            }
        }

        if ($outputText === '') {
            throw new Exception(
                'OpenAI no devolvió el análisis esperado.'
            );
        }

        $result =
            json_decode(
                $outputText,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        $result['_model'] =
            $decoded['model']
            ?? $model;

        return $result;
    }

    private static function completeAnalysis(
        int $analysisId,
        array $result
    ): void {
        $pdo = self::db(true);

        $st = $pdo->prepare("
        UPDATE publication_ai_analyses
        SET
            status = 'completed',

            title_score = :title_score,
            description_score = :description_score,
            consistency_score = :consistency_score,
            matchability_score = :matchability_score,

            suggestions_json = :suggestions_json,
            contradictions_json = :contradictions_json,

            model_name = :model_name,
            error_message = NULL,
            analyzed_at = NOW(),
            updated_at = CURRENT_TIMESTAMP

        WHERE id = :id
        LIMIT 1
    ");

        $st->execute([
            'title_score' =>
            $result['title_score'],

            'description_score' =>
            $result['description_score'],

            'consistency_score' =>
            $result['consistency_score'],

            'matchability_score' =>
            $result['matchability_score'],

            'suggestions_json' =>
            json_encode(
                $result['suggestions'] ?? [],
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            ),

            'contradictions_json' =>
            json_encode(
                $result['contradictions'] ?? [],
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            ),

            'model_name' =>
            $result['_model'] ?? null,

            'id' =>
            $analysisId,
        ]);
    }

    private static function markPending(
        int $analysisId,
        string $message
    ): void {
        self::db()->prepare("
        UPDATE publication_ai_analyses
        SET
            status = 'pending',
            error_message = :message,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ")->execute([
            'message' =>
            mb_substr($message, 0, 6000),

            'id' =>
            $analysisId,
        ]);
    }

    private static function markFailed(
        int $analysisId,
        string $message
    ): void {
        self::db()->prepare("
        UPDATE publication_ai_analyses
        SET
            status = 'failed',
            error_message = :message,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ")->execute([
            'message' =>
            mb_substr($message, 0, 6000),

            'id' =>
            $analysisId,
        ]);
    }
}
