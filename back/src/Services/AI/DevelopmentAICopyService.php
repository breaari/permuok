<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class DevelopmentAICopyService
{
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private const TITLE_PROMPT_VERSION = '1.0';
    private const DESCRIPTION_PROMPT_VERSION = '1.0';

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    public static function generateTitle(
        int $developmentId,
        ?int $userId = null,
        array $draft = []
    ): array {
        return self::generateCopy(
            $developmentId,
            'title',
            $userId,
            $draft
        );
    }

    public static function generateDescription(
        int $developmentId,
        ?int $userId = null,
        array $draft = []
    ): array {
        return self::generateCopy(
            $developmentId,
            'description',
            $userId,
            $draft
        );
    }

    private static function generateCopy(
        int $developmentId,
        string $copyType,
        ?int $userId,
        array $draft = []
    ): array {
        if ($developmentId <= 0) {
            throw new Exception(
                'El ID del desarrollo no es válido.'
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
         * Partimos de la misma representación
         * que usa el análisis de calidad IA.
         */
        $input =
            DevelopmentAIAnalysisService::prepareInput(
                $developmentId
            );

        /*
         * Los cambios actuales del formulario,
         * aunque todavía no estén guardados,
         * tienen prioridad.
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
         * Recuperamos propuestas anteriores para
         * evitar devolver siempre el mismo texto.
         */
        $previousOptions =
            self::getPreviousOptions(
                $developmentId,
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
                'development',
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
                $developmentId,

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
        $draftDevelopment =
            is_array(
                $draft['development']
                    ?? null
            )
                ? $draft['development']
                : [];

        if (
            !isset($input['development']) ||
            !is_array($input['development'])
        ) {
            $input['development'] = [];
        }

        $development =
            &$input['development'];

        /*
         * Datos generales.
         */
        foreach (
            [
                'title',
                'short_description',
                'description',
                'developer_name',
                'construction_company',
                'stage',
                'delivery_date_estimated',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftDevelopment
                )
            ) {
                $development[$field] =
                    $draftDevelopment[$field];
            }
        }

        /*
         * Ubicación.
         */
        if (
            !isset($development['location']) ||
            !is_array($development['location'])
        ) {
            $development['location'] = [];
        }

        $draftLocation =
            is_array(
                $draftDevelopment['location']
                    ?? null
            )
                ? $draftDevelopment['location']
                : [];

        foreach (
            [
                'country',
                'province',
                'city',
                'zone',
                'address',
                'formatted_address',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftLocation
                )
            ) {
                $development['location'][$field] =
                    $draftLocation[$field];
            }
        }

        /*
         * Comercialización.
         */
        if (
            !isset($development['commercial']) ||
            !is_array($development['commercial'])
        ) {
            $development['commercial'] = [];
        }

        $draftCommercial =
            is_array(
                $draftDevelopment['commercial']
                    ?? null
            )
                ? $draftDevelopment['commercial']
                : [];

        foreach (
            [
                'currency',
                'price_from',
                'price_to',
                'total_units',
                'available_units',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $draftCommercial
                )
            ) {
                $development['commercial'][$field] =
                    $draftCommercial[$field];
            }
        }

        /*
         * Tipologías.
         *
         * También podemos recibirlas desde el estado
         * actual del frontend.
         */
        if (
            array_key_exists(
                'unit_types',
                $draftDevelopment
            )
        ) {
            $development['unit_types'] =
                is_array(
                    $draftDevelopment['unit_types']
                )
                    ? $draftDevelopment['unit_types']
                    : [];
        }

        /*
         * Amenities.
         */
        if (
            array_key_exists(
                'amenities',
                $draftDevelopment
            )
        ) {
            $development['amenities'] =
                is_array(
                    $draftDevelopment['amenities']
                )
                    ? $draftDevelopment['amenities']
                    : [];
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
        int $developmentId,
        string $copyType,
        string $inputHash
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT content
            FROM publication_ai_copy_generations
            WHERE entity_type = 'development'
              AND entity_id = :entity_id
              AND copy_type = :copy_type
              AND input_hash = :input_hash
            ORDER BY id DESC
            LIMIT 3
        ");

        $st->execute([
            'entity_id' =>
                $developmentId,

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
en desarrollos inmobiliarios B2B en Argentina.

Generá UN título profesional para publicar este desarrollo
en PermuOK.

El título debe permitir que otra inmobiliaria identifique
rápidamente el proyecto.

REGLAS:

- Devolvé solamente el título.
- No agregues comillas.
- No inventes información.
- No inventes un nombre comercial.
- Si existe un nombre real del proyecto, podés conservarlo
  o mejorarlo sin modificar su identidad.
- Si el título actual es texto de prueba, genérico o inútil,
  construí uno descriptivo usando únicamente datos reales.
- Priorizá, cuando aporten claridad:
  nombre real del proyecto,
  tipo o concepto identificable,
  ciudad o zona,
  etapa comercial.
- No es obligatorio incluir todos esos elementos.
- No incluyas precios.
- No incluyas cantidad de unidades salvo que sea
  indispensable para identificar el proyecto.
- No uses frases promocionales como:
  "oportunidad única",
  "imperdible",
  "el mejor desarrollo",
  "inversión asegurada".
- No uses emojis.
- No uses mayúsculas sostenidas.
- No escribas "desarrollo inmobiliario" solamente
  para rellenar.
- No agregues datos que no estén presentes.
- Máximo aproximado: 90 caracteres.
- Español natural y profesional.

Los datos estructurados tienen prioridad sobre el
título y las descripciones actuales si existe una
contradicción.

DATOS ACTUALES:

{$inputJson}

TÍTULOS GENERADOS ANTERIORMENTE PARA ESTOS MISMOS DATOS:

{$previousJson}

Si existen opciones anteriores, generá una alternativa
realmente diferente pero igualmente fiel a los datos.

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
en desarrollos inmobiliarios B2B en Argentina.

Redactá UNA descripción profesional para este desarrollo.

El texto será publicado en PermuOK y será leído
principalmente por otras inmobiliarias y profesionales
del sector.

OBJETIVO:

La descripción debe permitir comprender rápidamente
qué es el proyecto, dónde está, en qué etapa se encuentra,
qué tipo de unidades ofrece y cuáles son sus principales
características comerciales.

PRINCIPIO FUNDAMENTAL:

Usá solamente información realmente presente en los datos.

Los datos estructurados actuales tienen prioridad sobre
el título, la descripción corta y la descripción existente.

Si el texto libre contradice datos estructurados,
NO reproduzcas la contradicción.

Podés:

- ordenar la información;
- mejorar la redacción;
- resumir;
- eliminar repeticiones;
- transformar datos estructurados en lenguaje natural;
- seleccionar los datos comercialmente más relevantes.

NO podés:

- inventar;
- completar información faltante;
- asumir calidad constructiva;
- asumir vistas;
- asumir luminosidad;
- asumir orientación;
- asumir financiación;
- asumir rentabilidad;
- asumir fecha de entrega;
- asumir amenities;
- asumir características de las unidades;
- corregir silenciosamente datos contradictorios inventando
  cuál debería ser el valor correcto.

CONTENIDO:

Cuando la información exista y sea útil, podés incluir:

- nombre o concepto del proyecto;
- desarrolladora o constructora;
- ubicación;
- etapa del desarrollo;
- fecha estimada de entrega;
- principales tipologías;
- ambientes o dormitorios;
- superficies;
- rango de precios;
- cantidad de unidades;
- unidades disponibles;
- amenities relevantes.

No es obligatorio mencionar todos los campos.

No conviertas la descripción en una transcripción
mecánica del formulario.

TIPOLOGÍAS:

Resumí las tipologías de forma natural.

Ejemplo de estilo:

"El proyecto ofrece unidades de 1, 2 y 3 ambientes,
con superficies desde X m²."

Usá ese formato solamente si esos datos existen.

Si existe una contradicción evidente entre ambientes,
dormitorios y superficies, evitá presentarla como si
fuera información confiable.

No inventes una corrección.

VALORES:

Si existen valores válidos, podés expresarlos naturalmente.

Ejemplos:

"Valores desde USD 120.000."

"Rango de valores entre USD 120.000 y USD 180.000."

No llames "precio final" a un valor si eso no está indicado.

No agregues financiación ni condiciones comerciales
que no estén cargadas.

ETAPA Y ENTREGA:

Traducí las etapas técnicas a lenguaje natural:

land = terreno / etapa inicial según contexto
prelaunch = pre lanzamiento
launch = lanzamiento
presale = preventa
under_construction = en construcción
finished = finalizado

No muestres los códigos internos.

Si no existe fecha estimada de entrega y la etapa
no la requiere, no la menciones.

AMENITIES:

Mencioná solamente amenities realmente cargadas.

No describas un amenity con características que no conocemos.

ESTILO:

- Español rioplatense natural.
- Profesional.
- Claro.
- Informativo.
- B2B inmobiliario.
- Entre 1 y 3 párrafos según la cantidad de información.
- Sin títulos internos.
- Sin listas.
- Sin emojis.
- Sin llamados a la acción.
- Sin frases exageradamente publicitarias.
- Sin referencias al formulario, sistema o IA.
- Sin frases como:
  "según los datos cargados",
  "según la ficha",
  "la información indica",
  "se detecta",
  "faltaría completar".

La descripción debe parecer redactada directamente
por una inmobiliaria.

Ante la duda, OMITÍ antes que inventar.

DATOS ACTUALES:

{$inputJson}

DESCRIPCIONES GENERADAS ANTERIORMENTE PARA ESTOS MISMOS DATOS:

{$previousJson}

Si existen opciones anteriores, generá una alternativa
de redacción diferente sin modificar los datos reales.

Devolvé solamente la descripción final.
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

        try {
            $decoded =
                json_decode(
                    $response,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (JsonException $e) {
            throw new Exception(
                'OpenAI devolvió una respuesta inválida.',
                0,
                $e
            );
        }

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