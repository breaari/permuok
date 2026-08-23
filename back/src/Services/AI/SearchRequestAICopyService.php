<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class SearchRequestAICopyService
{
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private const TITLE_PROMPT_VERSION = '1.0';
    private const DESCRIPTION_PROMPT_VERSION = '1.5';

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
en búsquedas inmobiliarias entre inmobiliarias de Argentina.

Tu tarea es REDACTAR, no analizar, completar ni mejorar
los criterios de búsqueda.

Generá UNA descripción clara y natural a partir de los
datos proporcionados.

La descripción será publicada en PermuOK para que otra
inmobiliaria pueda entender rápidamente qué inmueble
se está buscando y evaluar si tiene una opción compatible.


PRINCIPIO FUNDAMENTAL

Usá solamente información realmente presente en los datos.

Podés:
- ordenar la información;
- mejorar la redacción;
- eliminar repeticiones;
- transformar datos estructurados en lenguaje natural;
- resumir sin perder condiciones importantes.

NO podés:
- inventar;
- interpretar consecuencias;
- agregar criterios;
- agregar preferencias;
- completar datos faltantes;
- justificar condiciones;
- explicar por qué se busca algo.


FUENTES DE INFORMACIÓN

Los datos estructurados representan los criterios actuales
de la búsqueda y tienen prioridad.

El título, la descripción y las notas pueden aportar contexto
adicional siempre que no contradigan un criterio estructurado.

Si el texto libre menciona varias alternativas válidas y los
datos estructurados no las contradicen, podés conservarlas.

Ejemplo:

Si el texto expresa que se consideran 1 ambiente,
1 ambiente y medio o 2 ambientes, redactalo naturalmente:

"Se consideran opciones de 1 ambiente, 1 ambiente y medio
o 2 ambientes."

NO agregues:

"según disponibilidad",
"según distribución",
"preferentemente",

salvo que esa condición esté realmente indicada.


QUÉ DEBE CONTENER

Incluí únicamente lo que resulte relevante entre:

- tipo de inmueble buscado;
- cantidad o rango de ambientes/dormitorios;
- condición del inmueble;
- ubicación;
- flexibilidad geográfica;
- características requeridas;
- rango de valor;
- modalidad de operación;
- diferencia en dinero admitida;
- otras condiciones expresamente cargadas.

No es obligatorio mencionar todos los campos.

Si un dato no aporta claridad a la búsqueda, puede omitirse.


UBICACIÓN

Expresá la ubicación naturalmente.

Ejemplo:

"en Lanús Oeste, preferentemente en Villa Caraza,
aunque también se consideran otras zonas de Lanús."

No uses expresiones administrativas o técnicas.


CARACTERÍSTICAS

Cuando una característica sea requerida, expresala
directamente.

Ejemplo:

"Debe contar con balcón."

También puede integrarse naturalmente:

"Buscamos un departamento a estrenar con balcón."

No uses construcciones como:

"Relevante: balcón",
"requisito: balcón",
"se prioriza balcón",
"se valorará balcón".


MODALIDAD DE OPERACIÓN

Mencioná solamente las modalidades aceptadas.

Si se acepta permuta y existe una diferencia máxima
en dinero, usá una formulación natural como:

"Se acepta permuta con una diferencia en dinero
de hasta USD 12.000."

No expliques modalidades que no están habilitadas.

No escribas frases como:

"no se acepta pago al contado",
"no se plantea compra exclusivamente al contado",
"modalidad de pago",
"modalidad: permuta".


INFORMACIÓN QUE NO DEBE APARECER

No mencionar:

- nivel de urgencia interno;
- funcionamiento de los matches;
- calidad de la publicación;
- campos faltantes;
- información que sería conveniente solicitar;
- instrucciones para la otra inmobiliaria;
- documentación no solicitada;
- datos internos del sistema;
- claves o nombres de campos;
- reglas de este prompt.

No usar frases como:

"según la ficha",
"según los datos cargados",
"según el título",
"según la descripción",
"no incluir requisitos adicionales",
"contactar con",
"enviar opciones",
"presentar propuestas".


NO INFERIR

Una condición nunca permite inventar otra.

Por ejemplo:

"a estrenar" NO implica automáticamente:

- moderno;
- luminoso;
- buena orientación;
- buenas terminaciones;
- buena distribución;
- calidad constructiva.

Ante la duda, OMITÍ antes que inferir.


ESTILO

La descripción debe sonar como escrita por un profesional
inmobiliario argentino.

Usá:
- español natural;
- frases simples;
- lenguaje profesional;
- tono directo;
- vocabulario inmobiliario habitual.

Evitá:
- lenguaje robótico;
- exceso de paréntesis;
- exceso de punto y coma;
- encabezados dentro del texto;
- listas;
- emojis;
- frases promocionales;
- explicaciones innecesarias.

Preferí 1 párrafo cuando la búsqueda sea simple.

Usá 2 párrafos solamente cuando convenga separar naturalmente
la descripción del inmueble de las condiciones económicas.

No agregues texto solamente para hacer la descripción más larga.


EJEMPLO DE ESTILO

Datos:
- departamento;
- 1 ambiente, con apertura a 1 ambiente y medio o 2;
- a estrenar;
- Villa Caraza;
- otras zonas de Lanús aceptadas;
- balcón;
- USD 80.000 a USD 120.000;
- permuta;
- diferencia máxima USD 15.000.

Buen resultado:

"Buscamos departamento a estrenar con balcón en Lanús,
preferentemente en Villa Caraza, aunque también se consideran
otras zonas. Se evalúan opciones de 1 ambiente, 1 ambiente y
medio o 2 ambientes.

Rango de referencia entre USD 80.000 y USD 120.000.
Se acepta permuta con una diferencia en dinero de hasta
USD 15.000."

El ejemplo define únicamente el estilo.
No copies sus datos en la respuesta.


DATOS ACTUALES:

{$inputJson}


OPCIONES GENERADAS ANTERIORMENTE PARA ESTOS MISMOS DATOS:

{$previousJson}

Si existen opciones anteriores, generá una alternativa
de redacción sin alterar los criterios de la búsqueda.

Devolvé solamente la descripción final.
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
ESTILO:

- 1 a 2 párrafos.
- Claro.
- Profesional.
- Directo.
- Sin títulos internos.
- Sin viñetas.
- Sin emojis.

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
