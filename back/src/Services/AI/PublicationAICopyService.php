<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class PublicationAICopyService
{
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private const TITLE_PROMPT_VERSION = '1.0';
    private const DESCRIPTION_PROMPT_VERSION = '1.1';

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    public static function generatePropertyTitle(
        int $propertyId,
        ?int $userId = null
    ): array {
        return self::generatePropertyCopy(
            $propertyId,
            'title',
            $userId
        );
    }

    public static function generatePropertyDescription(
        int $propertyId,
        ?int $userId = null
    ): array {
        return self::generatePropertyCopy(
            $propertyId,
            'description',
            $userId
        );
    }

    private static function generatePropertyCopy(
        int $propertyId,
        string $copyType,
        ?int $userId
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        if (!in_array(
            $copyType,
            ['title', 'description'],
            true
        )) {
            throw new Exception(
                'Tipo de contenido IA inválido.'
            );
        }

        /*
         * Reutilizamos exactamente la misma
         * preparación estructurada que usa
         * el análisis profundo.
         */
        $input =
            PublicationAIAnalysisService::preparePropertyInput(
                $propertyId
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
         * Las últimas opciones generadas se incluyen
         * en el prompt para que "Generar otra opción"
         * no tienda a devolver exactamente lo mismo.
         */
        $previousOptions =
            self::getPreviousOptions(
                $propertyId,
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
                'property',
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
                $propertyId,

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
        int $propertyId,
        string $copyType,
        string $inputHash
    ): array {
        $pdo = self::db();

        $st = $pdo->prepare("
            SELECT content

            FROM publication_ai_copy_generations

            WHERE entity_type = 'property'
              AND entity_id = :entity_id
              AND copy_type = :copy_type
              AND input_hash = :input_hash

            ORDER BY id DESC

            LIMIT 3
        ");

        $st->execute([
            'entity_id' =>
                $propertyId,

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
    $propertyJson =
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
en publicaciones inmobiliarias argentinas.

Generá UN título para esta propiedad.

El resultado se utilizará directamente como título
de una publicación en PermuOK.

REGLAS:

- Devolver solamente el título.
- No explicar tu respuesta.
- No devolver análisis.
- No incluir precio.
- No incluir moneda.
- No incluir metros cuadrados salvo que sean esenciales
  para identificar el tipo de inmueble.
- No incluir dirección exacta.
- No usar mayúsculas sostenidas.
- No utilizar expresiones vacías como:
  "imperdible", "oportunidad única", "soñado",
  "espectacular" o similares.
- Debe sonar como un título inmobiliario profesional real.
- Priorizar:
  tipo de propiedad,
  ambientes,
  atributo diferencial confirmado,
  zona o barrio.
- No inventar atributos.

AMBIENTES:

Para casas y departamentos, cuando existe una cantidad
confirmada de dormitorios y no hay información que lo contradiga,
podés expresar:

1 dormitorio = 2 ambientes
2 dormitorios = 3 ambientes
3 dormitorios = 4 ambientes

No uses esta conversión si existe incertidumbre.

EJEMPLOS CORRECTOS:

Departamento 2 ambientes en Lanús Oeste
Casa 4 ambientes con jardín en Martínez
Local comercial en Palermo Soho
Terreno en barrio privado de Pilar

NO GENERAR:

Departamento en Lanús Oeste - 1 dormitorio · 40 m² · USD 65.489
DEPARTAMENTO IMPERDIBLE
Departamento USD 65.489

OPCIONES GENERADAS ANTERIORMENTE:

{$previousJson}

Si existen opciones anteriores, generá una alternativa
naturalmente diferente sin perder calidad.

PROPIEDAD:

{$propertyJson}
PROMPT;
}
private static function buildDescriptionPrompt(
    array $input,
    array $previousOptions
): string {
    $propertyJson =
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
Sos un redactor inmobiliario profesional argentino.

Redactá una descripción comercial lista para publicarse
para la siguiente propiedad.

Tu respuesta se insertará directamente dentro del campo
"Descripción" de PermuOK.

REGLAS CRÍTICAS:

- Devolver solamente la descripción.
- No explicar tu respuesta.
- No realizar análisis.
- No mencionar PermuOK.
- No decir "datos confirmados".
- No decir "para optimizar".
- No recomendar completar información.
- No mencionar campos faltantes.
- No formular preguntas.
- No comentar la calidad de las imágenes.
- No explicar cómo mejorar la publicación.
- No inferir público objetivo ni uso ideal.
- No escribir frases como "ideal para inversión",
  "ideal para primera vivienda" o similares.
- No inferir funcionalidad, comodidad, amplitud,
  luminosidad o calidad si no están confirmadas.
- No agregar frases genéricas de cierre como:
  "contactanos",
  "coordiná una visita",
  "consultanos",
  "no dejes pasar esta oportunidad".
- No incluir la dirección exacta salvo que sea
  explícitamente relevante para la descripción.
- Preferir zona, barrio o ciudad.
- No convertir "acepta propuestas abiertas" en
  "abierto a ofertas" si eso no está confirmado.
- Si la propiedad acepta propuestas abiertas de permuta,
  expresarlo únicamente como condición de permuta.

  NO GENERAR:

"Ideal como inversión o primera vivienda por su tamaño
y funcionalidad. Contacto para consultas y coordinación
de visitas."

USAR SOLAMENTE INFORMACIÓN CONFIRMADA.

Nunca inventar:

- luminosidad;
- orientación;
- vistas;
- estado;
- calidad constructiva;
- materiales;
- distribución;
- amenities;
- balcones;
- terrazas;
- cocheras;
- antigüedad.

La descripción debe:

- sonar como una publicación inmobiliaria real;
- ser profesional y natural;
- utilizar español rioplatense;
- priorizar tipo de inmueble y ubicación;
- integrar naturalmente las características relevantes;
- mencionar condiciones de permuta cuando realmente correspondan;
- evitar repetir mecánicamente toda la ficha;
- no incluir precio salvo que resulte imprescindible;
- utilizar párrafos breves;
- tener aproximadamente entre 300 y 700 caracteres
  cuando haya suficiente información.

OPCIONES GENERADAS ANTERIORMENTE:

{$previousJson}

Si existen opciones anteriores, generá otra redacción,
sin inventar características.



PROPIEDAD:

{$propertyJson}
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

    if ($model === '') {
        $model =
            self::DEFAULT_MODEL;
    }

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

        'input' =>
            $prompt,
    ];

    $jsonBody =
        json_encode(
            $body,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

    $ch =
        curl_init(
            'https://api.openai.com/v1/responses'
        );

    if ($ch === false) {
        throw new Exception(
            'No se pudo inicializar la conexión con OpenAI.'
        );
    }

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
                $jsonBody,

            CURLOPT_CONNECTTIMEOUT =>
                15,

            CURLOPT_TIMEOUT =>
                60,
        ]
    );

    $response =
        curl_exec(
            $ch
        );

    if ($response === false) {
        $error =
            curl_error(
                $ch
            );

        curl_close(
            $ch
        );

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

    curl_close(
        $ch
    );

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

    $content =
        self::extractOutputText(
            $decoded
        );

    if ($content === '') {
        throw new Exception(
            'OpenAI no generó contenido.'
        );
    }

    return [
        'content' =>
            trim($content),

        'model' =>
            (string)(
                $decoded['model']
                ?? $model
            ),
    ];
}
private static function extractOutputText(
    array $response
): string {
    foreach (
        $response['output'] ?? []
        as $output
    ) {
        foreach (
            $output['content'] ?? []
            as $content
        ) {
            if (
                ($content['type'] ?? null)
                === 'output_text'
            ) {
                return trim(
                    (string)(
                        $content['text']
                        ?? ''
                    )
                );
            }
        }
    }

    return '';
}
}