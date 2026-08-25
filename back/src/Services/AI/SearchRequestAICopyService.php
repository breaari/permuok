<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class SearchRequestAICopyService
{
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private const TITLE_PROMPT_VERSION = '1.1';
    private const DESCRIPTION_PROMPT_VERSION = '1.6';

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    public static function generateTitle(
        int $searchRequestId,
        ?int $userId = null,
        array $draft = []
    ): array {
        return self::generateCopy(
            $searchRequestId,
            'title',
            $userId,
            $draft
        );
    }

    public static function generateDescription(
        int $searchRequestId,
        ?int $userId = null,
        array $draft = []
    ): array {
        return self::generateCopy(
            $searchRequestId,
            'description',
            $userId,
            $draft
        );
    }

    private static function generateCopy(
        int $searchRequestId,
        string $copyType,
        ?int $userId,
        array $draft = []
    ): array {
        if ($searchRequestId <= 0) {
            throw new Exception(
                'El ID de la búsqueda no es válido.'
            );
        }

        if (
            !in_array(
                $copyType,
                ['title', 'description'],
                true
            )
        ) {
            throw new Exception(
                'Tipo de contenido IA inválido.'
            );
        }

        /*
         * Partimos exactamente de la misma
         * estructura que usa el análisis IA.
         */
        $input =
            SearchRequestAIAnalysisService::prepareInput(
                $searchRequestId
            );

        /*
         * Si el formulario tiene cambios
         * todavía no guardados, tienen prioridad.
         */
        $input =
            self::applyDraftOverrides(
                $input,
                $draft
            );

        $promptVersion =
            $copyType === 'title'
            ? self::TITLE_PROMPT_VERSION
            : self::DESCRIPTION_PROMPT_VERSION;

        $inputHash =
            self::buildInputHash(
                $input,
                $copyType,
                $promptVersion
            );

        /*
         * Evitamos repetir siempre
         * la misma propuesta.
         */
        $previousOptions =
            self::getPreviousOptions(
                $searchRequestId,
                $copyType,
                $inputHash
            );

        $generated =
            self::callOpenAI(
                $input,
                $copyType,
                $previousOptions
            );

        $pdo = self::db(true);

        $st = $pdo->prepare("
            INSERT INTO publication_ai_copy_generations (
                entity_type,
                entity_id,
                copy_type,
                content,
                input_hash,
                model_name,
                prompt_version,
                created_by_user_id
            ) VALUES (
                'search_request',
                :entity_id,
                :copy_type,
                :content,
                :input_hash,
                :model_name,
                :prompt_version,
                :created_by_user_id
            )
        ");

        $st->execute([
            'entity_id' =>
            $searchRequestId,

            'copy_type' =>
            $copyType,

            'content' =>
            $generated['content'],

            'input_hash' =>
            $inputHash,

            'model_name' =>
            $generated['model'],

            'prompt_version' =>
            $promptVersion,

            'created_by_user_id' =>
            $userId,
        ]);

        return [
            'id' =>
            (int)$pdo->lastInsertId(),

            'type' =>
            $copyType,

            'content' =>
            $generated['content'],

            'input_hash' =>
            $inputHash,

            'prompt_version' =>
            $promptVersion,
        ];
    }

    private static function applyDraftOverrides(
        array $input,
        array $draft
    ): array {
        $draftRequest =
            is_array(
                $draft['search_request']
                    ?? null
            )
            ? $draft['search_request']
            : [];

        if ($draftRequest === []) {
            return $input;
        }

        if (
            !isset(
                $input['search_request']
            ) ||
            !is_array(
                $input['search_request']
            )
        ) {
            $input['search_request'] = [];
        }

        $request =
            &$input['search_request'];

        foreach (
            [
                'title',
                'description',
                'property_condition',
                'urgency',
                'notes',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftRequest
                )
            ) {
                $request[$field] =
                    $draftRequest[$field];
            }
        }

        if (
            array_key_exists(
                'property_types',
                $draftRequest
            )
        ) {
            $request['property_types'] =
                is_array(
                    $draftRequest['property_types']
                )
                ? $draftRequest['property_types']
                : [];
        }

        /*
         * Ubicación.
         */
        if (
            !isset(
                $request['location']
            ) ||
            !is_array(
                $request['location']
            )
        ) {
            $request['location'] = [];
        }

        $draftLocation =
            is_array(
                $draftRequest['location']
                    ?? null
            )
            ? $draftRequest['location']
            : [];

        foreach (
            [
                'country',
                'province',
                'city',
                'zone',
                'open_to_other_zones',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftLocation
                )
            ) {
                $request['location'][$field] =
                    $draftLocation[$field];
            }
        }

        /*
         * Presupuesto.
         */
        if (
            !isset(
                $request['budget']
            ) ||
            !is_array(
                $request['budget']
            )
        ) {
            $request['budget'] = [];
        }

        $draftBudget =
            is_array(
                $draftRequest['budget']
                    ?? null
            )
            ? $draftRequest['budget']
            : [];

        foreach (
            [
                'currency',
                'min',
                'max',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftBudget
                )
            ) {
                $request['budget'][$field] =
                    $draftBudget[$field];
            }
        }

        /*
         * Criterios.
         */
        if (
            !isset(
                $request['criteria']
            ) ||
            !is_array(
                $request['criteria']
            )
        ) {
            $request['criteria'] = [];
        }

        $draftCriteria =
            is_array(
                $draftRequest['criteria']
                    ?? null
            )
            ? $draftRequest['criteria']
            : [];

        foreach (
            [
                'min_total_area',
                'min_covered_area',
                'min_bedrooms',
                'min_bathrooms',
                'min_garages',
                'max_antiquity',
                'amenities',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftCriteria
                )
            ) {
                $request['criteria'][$field] =
                    $draftCriteria[$field];
            }
        }

        /*
         * Modalidad de operación.
         */
        if (
            !isset(
                $request['payment']
            ) ||
            !is_array(
                $request['payment']
            )
        ) {
            $request['payment'] = [];
        }

        $draftPayment =
            is_array(
                $draftRequest['payment']
                    ?? null
            )
            ? $draftRequest['payment']
            : [];

        foreach (
            [
                'cash',
                'swap',
                'cash_difference_max',
                'cash_difference_currency',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftPayment
                )
            ) {
                $request['payment'][$field] =
                    $draftPayment[$field];
            }
        }

        return $input;
    }

    private static function buildInputHash(
        array $input,
        string $copyType,
        string $promptVersion
    ): string {
        try {
            $json = json_encode(
                [
                    'copy_type' =>
                    $copyType,

                    'prompt_version' =>
                    $promptVersion,

                    'input' =>
                    $input,
                ],
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION |
                    JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo preparar el contenido para IA.',
                0,
                $e
            );
        }

        return hash(
            'sha256',
            $json
        );
    }

    private static function getPreviousOptions(
        int $searchRequestId,
        string $copyType,
        string $inputHash
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT content
            FROM publication_ai_copy_generations
            WHERE entity_type = 'search_request'
              AND entity_id = :entity_id
              AND copy_type = :copy_type
              AND input_hash = :input_hash
            ORDER BY id DESC
            LIMIT 3
        ");

        $st->execute([
            'entity_id' =>
            $searchRequestId,

            'copy_type' =>
            $copyType,

            'input_hash' =>
            $inputHash,
        ]);

        return $st->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];
    }

    private static function buildTitlePrompt(
        array $input,
        array $previousOptions
    ): string {
        $inputJson =
            json_encode(
                $input,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            );

        $previousJson =
            json_encode(
                $previousOptions,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            );

        return <<<PROMPT
Sos un redactor inmobiliario profesional especializado
en búsquedas inmobiliarias B2B de Argentina.

Generá UN título breve y claro para esta búsqueda inmobiliaria.

El resultado se utilizará directamente como título
de una búsqueda publicada en PermuOK.

OBJETIVO

El título debe permitir entender rápidamente
qué inmueble se está buscando y, cuando aporte valor,
en qué zona.

REGLAS

- Devolver solamente el título.
- No explicar la respuesta.
- No devolver análisis.
- No escribir una descripción.
- No usar más de una oración.
- Mantener el título breve.
- Idealmente entre 4 y 10 palabras.
- Máximo aproximado: 80 caracteres.
- No usar punto final.
- No usar listas.
- No usar emojis.
- No incluir llamados a la acción.
- No usar mayúsculas sostenidas.
- No inventar información.
- No agregar características que no estén expresamente cargadas.
- No mencionar campos internos del sistema.
- No incluir nivel de urgencia.
- No explicar modalidad de matching.
- No incluir rango de valor salvo que sea indispensable
  para diferenciar claramente la búsqueda.
- No incluir modalidad de pago o permuta salvo que sea
  una condición central y no exista un dato más útil para el título.
- No enumerar todos los criterios cargados.
- No convertir el título en una descripción.

PRIORIZÁ

1. Tipo de inmueble.
2. Cantidad de ambientes o dormitorios, si está definida.
3. Característica diferencial realmente requerida, si existe.
4. Zona, barrio o ciudad relevante.

ESTILO

Debe sonar como un título inmobiliario profesional y natural.

Buenos ejemplos de estilo:

"Busco departamento de 3 ambientes en Centro"

"Busco casa con cochera en Caisamar"

"Departamento a estrenar en zona Güemes"

"Busco local comercial en Mar del Plata"

Malos ejemplos:

"Buscamos un departamento de tres ambientes ubicado en el Centro
de Mar del Plata que cuente con cochera y que se encuentre dentro
de un rango de valor determinado"

"Se busca propiedad que cumpla con los criterios establecidos"

"Busco departamento con múltiples características y condiciones"

Los ejemplos indican solamente el estilo.
No copies datos que no existan en la búsqueda actual.

DATOS ACTUALES:

{$inputJson}

OPCIONES GENERADAS ANTERIORMENTE PARA ESTOS MISMOS DATOS:

{$previousJson}

Si existen opciones anteriores, generá una alternativa diferente,
pero conservando la misma precisión.

Devolvé solamente el título final.
PROMPT;
    }

    private static function buildDescriptionPrompt(
        array $input,
        array $previousOptions
    ): string {
        $inputJson =
            json_encode(
                $input,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            );

        $previousJson =
            json_encode(
                $previousOptions,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
            );

        return <<<PROMPT
Sos un redactor inmobiliario profesional especializado
en búsquedas inmobiliarias B2B en Argentina.

Redactá UNA descripción profesional para esta búsqueda.

El texto será utilizado directamente en PermuOK
por una inmobiliaria para comunicar qué propiedad
está buscando.

OBJETIVO:

La descripción debe aportar contexto útil para que
otra inmobiliaria pueda determinar rápidamente
si tiene una propiedad compatible.

REGLAS:

- Devolver solamente la descripción.
- No explicar la respuesta.
- No inventar información.
- No utilizar nombres técnicos, claves JSON ni valores internos.
- No repetir mecánicamente todos los campos del formulario.
- No usar lenguaje publicitario exagerado.
- No escribir como aviso dirigido al comprador final.
- Escribir como una búsqueda inmobiliaria profesional.
- No incluir llamados a la acción.
- No terminar con frases como:
  "contactar",
  "enviar información",
  "enviar opciones",
  "consultar",
  "comunicarse",
  "presentar propuestas"
  o similares.
- La descripción debe limitarse a explicar qué inmueble se busca
  y qué condiciones son relevantes para evaluar una compatibilidad.
- No pedir documentación ni información adicional que no figure
  expresamente como requisito de la búsqueda.
- No solicitar datos sobre escritura, documentación, antigüedad,
  superficie u otros aspectos solamente porque sería útil conocerlos.
- Usar español rioplatense natural.
- Evitar frases vacías.
- No asumir motivaciones del cliente.
- No inventar urgencias ni condiciones.
- No presentar campos opcionales vacíos como errores.
- No mencionar valores cero.
- No usar expresiones como "propiedad usada".
- Usar "a estrenar" y "con antigüedad" cuando corresponda.
- Si existe flexibilidad, expresarla claramente.
- Si existen restricciones importantes,
  destacarlas de manera natural.
- Si se acepta permuta o diferencia en dinero,
  explicarlo únicamente si es relevante y coherente.
- No exponer contradicciones del formulario como si fueran
  parte normal de la descripción.
- Si los datos estructurados y el texto actual se contradicen,
  priorizar los datos estructurados del formulario.

  - No agregues preferencias, atributos ni criterios que no estén expresamente
  presentes en los datos estructurados o escritos por el usuario.

- No infieras características a partir de otras.
  Por ejemplo, que una propiedad sea "a estrenar" NO permite asumir:
  modernidad, buena orientación, luminosidad, calidad de terminaciones,
  distribución, calidad constructiva ni ningún otro atributo.

- No conviertas características ausentes en preferencias.

- No agregues frases como:
  "se priorizarán unidades...",
  "se valorarán opciones...",
  "se dará prioridad a...",
  salvo que el usuario haya cargado explícitamente esa prioridad.

- El campo de urgencia es información operativa interna.
  No mencionar "prioridad baja", "prioridad media", "prioridad alta",
  "nivel de prioridad" ni expresiones equivalentes en la descripción.

- No describas cómo se determinarán los matches.
  Evitá frases como:
  "se prioriza coincidencia clara",
  "se evaluará compatibilidad",
  "se buscan propuestas que coincidan"
  o similares.

- La descripción debe comunicar solamente:
  qué inmueble se busca,
  dónde,
  qué características son requeridas o flexibles,
  rango de valor cuando aporte información,
  y modalidad de operación cuando corresponda.

- Ante la duda, OMITÍ información antes que inferirla.
- No uses encabezados internos como:
  "Modalidad:",
  "Presupuesto:",
  "Ubicación:",
  "Condiciones:"
  ni similares.

- No traduzcas literalmente los flags de modalidad de pago.

- Si se acepta permuta, expresalo de forma natural dentro del texto.

- Preferí formulaciones como:
  "Se acepta permuta."
  "Se acepta permuta con una diferencia en dinero de hasta USD X."

- No aclares modalidades que NO están habilitadas.
  Por ejemplo, no escribas:
  "no se acepta pago al contado",
  "no se plantea compra exclusivamente al contado",
  "no se acepta dinero",
  salvo que esa exclusión haya sido expresada explícitamente
  por el usuario en un texto libre.

- La ausencia de una modalidad no debe convertirse
  automáticamente en una restricción redactada.

- No expliques la lógica interna de la operación.
  Redactá únicamente las opciones efectivamente aceptadas.
- Nunca repitas ni reformules en la descripción
  las instrucciones de este prompt.

- No escribas frases metadiscursivas como:
  "no incluir requisitos adicionales",
  "no inventar información",
  "según la ficha",
  "según los datos cargados",
  "según el título",
  "según la descripción"
  ni expresiones equivalentes.

- El resultado debe parecer escrito directamente
  por una inmobiliaria, sin referencias al formulario,
  al sistema, a la ficha ni a las reglas de generación.

  FIDELIDAD DE LOS CRITERIOS

- Conservá todas las alternativas expresamente indicadas por el usuario
  siempre que no contradigan los datos estructurados.

- Si el usuario indica que considera 1 ambiente,
  1 ambiente y medio y 2 ambientes, deben mantenerse
  las tres alternativas en la descripción.

- No reemplaces esas alternativas por una categoría equivalente.

- No inventes equivalencias inmobiliarias.
  Por ejemplo:
  "1 ambiente" no debe transformarse en
  "apto monoambiente",
  "tipo monoambiente",
  "monoambiente equivalente"
  ni expresiones similares,
  salvo que el usuario haya utilizado explícitamente ese término.

- No agregues condiciones circunstanciales como:
  "según disponibilidad",
  "según distribución",
  "de acuerdo con la oferta"
  o similares si no fueron expresadas por el usuario.


REDACCIÓN NATURAL

- Integrá las características dentro de las oraciones.

- No uses etiquetas o construcciones de ficha como:
  "Requisito:",
  "Relevante:",
  "Preferencia:",
  "Condición:",
  "Modalidad:"
  ni similares.

- Evitá el punto y coma cuando una oración simple
  o un punto produzcan una redacción más natural.

- Para flexibilidad geográfica, preferí construcciones como:
  "preferentemente en X, aunque también se consideran otras zonas de Y."

- Para una característica obligatoria, preferí:
  "Debe contar con balcón."
  o integrala naturalmente:
  "departamento a estrenar con balcón."


VALORES Y OPERACIÓN

- Cuando exista un valor mínimo y máximo,
  preferí la expresión:
  "Rango de referencia entre USD X y USD Y."

- No presentes automáticamente esos valores como
  "presupuesto" salvo que el usuario los haya descripto así.

- Si se acepta permuta y existe una diferencia máxima,
  preferí exactamente esta estructura conceptual:
  "Se acepta permuta con una diferencia en dinero de hasta USD X."

- No determines a favor de quién es la diferencia.

- No escribas:
  "a favor del propietario",
  "a favor del comprador",
  "saldo a favor",
  "diferencia a entregar"
  ni ninguna dirección de la diferencia,
  salvo que esté expresamente indicada en los datos.


ESTRUCTURA PREFERIDA

- En búsquedas con información suficiente, organizá el texto así:

  Primer párrafo:
  inmueble + ambientes + condición + ubicación +
  flexibilidad geográfica + características relevantes.

  Segundo párrafo:
  rango de referencia + modalidad de operación.

- No mezcles todos los criterios en una única oración larga.

ESTILO:

- 1 a 2 párrafos.
- Claro.
- Profesional.
- Directo.
- Sin títulos internos.
- Sin viñetas.
- Sin emojis.

EJEMPLO DE ESTILO

Buen resultado:

"Buscamos departamento a estrenar con balcón en Lanús Oeste,
preferentemente en Villa Caraza, aunque también se consideran
otras zonas de Lanús. Se evalúan opciones de 1 ambiente,
1 ambiente y medio o 2 ambientes.

Rango de referencia entre USD 80.000 y USD 120.000.
Se acepta permuta con una diferencia en dinero de hasta USD 15.000."

Observá el estilo:
- no inventa equivalencias;
- conserva todas las alternativas;
- no usa etiquetas como "Requisito:" o "Modalidad:";
- no interpreta hacia qué parte va la diferencia;
- separa naturalmente el inmueble de las condiciones económicas.

El ejemplo define solamente el estilo.
Nunca copies sus valores o ubicaciones.

DATOS ACTUALES:

{$inputJson}

OPCIONES YA GENERADAS PARA ESTOS MISMOS DATOS:

{$previousJson}

Si existen opciones anteriores, redactá una alternativa
realmente diferente sin inventar información.
PROMPT;
    }

    private static function callOpenAI(
        array $input,
        string $copyType,
        array $previousOptions
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

        $prompt =
            $copyType === 'title'
            ? self::buildTitlePrompt(
                $input,
                $previousOptions
            )
            : self::buildDescriptionPrompt(
                $input,
                $previousOptions
            );

        $body = [
            'model' =>
            $model,

            'reasoning' => [
                'effort' =>
                'low',
            ],

            'input' =>
            $prompt,

            'text' => [
                'verbosity' =>
                'low',
            ],
        ];

        $ch =
            curl_init(
                'https://api.openai.com/v1/responses'
            );

        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST =>
                true,

                CURLOPT_RETURNTRANSFER =>
                true,

                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' .
                        $apiKey,

                    'Content-Type: application/json',
                ],

                CURLOPT_POSTFIELDS =>
                json_encode(
                    $body,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                ),

                CURLOPT_CONNECTTIMEOUT =>
                15,

                CURLOPT_TIMEOUT =>
                120,
            ]
        );

        $response =
            curl_exec($ch);

        if ($response === false) {
            $error =
                curl_error($ch);

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
                        (string)(
                            $content['text']
                            ?? ''
                        );
                }
            }
        }

        $outputText =
            trim($outputText);

        if ($outputText === '') {
            throw new Exception(
                'OpenAI no devolvió contenido.'
            );
        }

        return [
            'content' =>
            $outputText,

            'model' =>
            $model,
        ];
    }
}
