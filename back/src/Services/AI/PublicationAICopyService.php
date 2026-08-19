<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class PublicationAICopyService
{
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private const TITLE_PROMPT_VERSION = '1.2';
    private const DESCRIPTION_PROMPT_VERSION = '2.5';

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    public static function generatePropertyTitle(
        int $propertyId,
        ?int $userId = null,
        array $draft = []
    ): array {
        return self::generatePropertyCopy(
            $propertyId,
            'title',
            $userId,
            $draft
        );
    }

    public static function generatePropertyDescription(
        int $propertyId,
        ?int $userId = null,
        array $draft = []
    ): array {
        return self::generatePropertyCopy(
            $propertyId,
            'description',
            $userId,
            $draft
        );
    }

    private static function generatePropertyCopy(
        int $propertyId,
        string $copyType,
        ?int $userId,
        array $draft = []
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
        /*
 * La base de datos funciona como fallback.
 *
 * Si el frontend envió un snapshot del formulario,
 * sus valores tienen prioridad aunque todavía
 * no hayan sido guardados.
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
    private static function applyDraftOverrides(
        array $input,
        array $draft
    ): array {
        $draftProperty =
            is_array($draft['property'] ?? null)
            ? $draft['property']
            : [];

        if ($draftProperty !== []) {
            if (!isset($input['property'])) {
                $input['property'] = [];
            }

            /*
         * Datos principales.
         */
            foreach (
                [
                    'title',
                    'description',
                    'property_type',
                    'price',
                    'currency',
                ]
                as $field
            ) {
                if (
                    array_key_exists(
                        $field,
                        $draftProperty
                    )
                ) {
                    $input['property'][$field] =
                        $draftProperty[$field];
                }
            }

            /*
         * Ubicación.
         */
            if (
                !isset($input['property']['location']) ||
                !is_array($input['property']['location'])
            ) {
                $input['property']['location'] = [];
            }

            foreach (
                [
                    'country_code',
                    'country',
                    'province',
                    'city',
                    'zone',
                    'address',
                    'formatted_address',
                    'postal_code',
                    'latitude',
                    'longitude',
                ]
                as $field
            ) {
                if (
                    array_key_exists(
                        $field,
                        $draftProperty
                    )
                ) {
                    $input['property']['location'][$field] =
                        $draftProperty[$field];
                }
            }

            /*
         * Características.
         */
            if (
                !isset($input['property']['features']) ||
                !is_array($input['property']['features'])
            ) {
                $input['property']['features'] = [];
            }

            foreach (
                [
                    'total_area',
                    'covered_area',
                    'bedrooms',
                    'bathrooms',
                    'garages',
                    'antiquity',
                    'amenities',
                ]
                as $field
            ) {
                if (
                    array_key_exists(
                        $field,
                        $draftProperty
                    )
                ) {
                    $input['property']['features'][$field] =
                        $draftProperty[$field];
                }
            }
        }

        /*
     * Requisitos actuales del formulario.
     *
     * También tienen prioridad sobre la versión
     * guardada para que la IA pueda trabajar con
     * cambios todavía no persistidos.
     */
        $draftRequirements =
            is_array($draft['requirements'] ?? null)
            ? $draft['requirements']
            : null;

        if ($draftRequirements !== null) {
            $input['requirements'] = [
                'criteria_mode' =>
                $draftRequirements['criteria_mode']
                    ?? null,

                'accepts_total_swap' =>
                (bool)(
                    $draftRequirements['accepts_total_swap']
                    ?? false
                ),

                'accepts_swap_plus_cash' =>
                (bool)(
                    $draftRequirements['accepts_swap_plus_cash']
                    ?? false
                ),

                'accepts_multiple_swap' =>
                (bool)(
                    $draftRequirements['accepts_multiple_swap']
                    ?? false
                ),

                'accepts_open_proposals' =>
                (bool)(
                    $draftRequirements['accepts_open_proposals']
                    ?? false
                ),

                'accepts_cash_only' =>
                (bool)(
                    $draftRequirements['accepts_cash_only']
                    ?? false
                ),

                'cash_difference' => [
                    'direction' =>
                    $draftRequirements['cash_difference_direction'] ?? null,

                    'min' =>
                    $draftRequirements['cash_difference_min'] ?? null,

                    'max' =>
                    $draftRequirements['cash_difference_max'] ?? null,

                    'currency' =>
                    $draftRequirements['cash_difference_currency'] ?? null,
                ],

                'price' => [
                    'min' =>
                    $draftRequirements['price_min']
                        ?? null,

                    'max' =>
                    $draftRequirements['price_max']
                        ?? null,

                    'currency' =>
                    $draftRequirements['price_currency']
                        ?? null,
                ],

                'features' => [
                    'min_total_area' =>
                    $draftRequirements['min_total_area']
                        ?? null,

                    'max_total_area' =>
                    $draftRequirements['max_total_area']
                        ?? null,

                    'min_covered_area' =>
                    $draftRequirements['min_covered_area']
                        ?? null,

                    'max_covered_area' =>
                    $draftRequirements['max_covered_area']
                        ?? null,

                    'min_bedrooms' =>
                    $draftRequirements['min_bedrooms']
                        ?? null,

                    'min_bathrooms' =>
                    $draftRequirements['min_bathrooms']
                        ?? null,

                    'min_garages' =>
                    $draftRequirements['min_garages']
                        ?? null,

                    'max_antiquity' =>
                    $draftRequirements['max_antiquity']
                        ?? null,
                ],

                'property_types' =>
                is_array(
                    $draftRequirements['property_types']
                        ?? null
                )
                    ? $draftRequirements['property_types']
                    : [],

                'locations' =>
                is_array(
                    $draftRequirements['locations']
                        ?? null
                )
                    ? $draftRequirements['locations']
                    : [],

                'open_to_other_zones' =>
                (bool)(
                    $draftRequirements['open_to_other_zones']
                    ?? false
                ),

                'property_condition' =>
                $draftRequirements['property_condition']
                    ?? null,

                'notes' =>
                $draftRequirements['notes']
                    ?? null,
            ];
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
- No mencionar características estándar que no diferencian
  comercialmente la propiedad.
- En casas y departamentos, NO mencionar baños en el título
  salvo que exista una característica realmente excepcional
  relacionada con ellos.
- Si ya expresaste la cantidad de ambientes, no repetir la cantidad
  de dormitorios en el título.
- No mencionar superficies solamente para generar variedad.
- No mencionar valores cero ni características ausentes.
- Un buen título puede ser breve. No agregar datos irrelevantes
  solamente para hacerlo más largo.
  - La permuta puede mencionarse en el título únicamente cuando
  no haya otro atributo diferencial más relevante.
- Si se menciona, usar una formulación natural como:
  "acepta permuta".
- NO usar expresiones como:
  "con permuta aceptada",
  "permuta disponible",
  "permuta habilitada",
  "permuta admitida".
- No inferir beneficios, perfil de comprador ni cualidades subjetivas
  a partir de datos estructurados.

- No utilizar frases como:
  "ideal para",
  "perfecto para",
  "pensado para",
  "una excelente opción",
  "aporta comodidad",
  "aporta amplitud",
  "aporta luminosidad",
  "aporta una distribución clara",
  salvo que esa cualidad haya sido expresamente indicada por el usuario.

- No describir una zona como:
  "consolidada",
  "residencial",
  "tranquila",
  "comercial",
  "estratégica",
  "privilegiada"
  salvo que esa información haya sido expresamente confirmada.

- Cuando un dato objetivo no permita afirmar una consecuencia,
  limitarse a mencionar el dato sin interpretar su beneficio.
EJEMPLOS DE DATOS QUE NORMALMENTE NO SON DIFERENCIALES:

- 1 baño en un departamento.
- 1 dormitorio cuando ya se indicó "2 ambientes".
- superficie total o cubierta.
- ausencia de cochera.
- moneda.
- antigüedad, salvo que sea comercialmente relevante.

ATRIBUTOS DIFERENCIALES VÁLIDOS, SI ESTÁN CONFIRMADOS:

- cochera;
- balcón;
- terraza;
- patio;
- jardín;
- pileta;
- parrilla;
- vista;
- frente al mar;
- apto profesional;
- apto crédito;
- amenities relevantes;
- ubicación especialmente específica o reconocible.


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
Departamento 2 ambientes en Lanús Oeste - acepta permuta

NO GENERAR:

Departamento en Lanús Oeste - 1 dormitorio · 40 m² · USD 65.489
DEPARTAMENTO IMPERDIBLE
Departamento USD 65.489
Departamento 2 ambientes en Lanús Oeste con permuta aceptada

OPCIONES GENERADAS ANTERIORMENTE:

{$previousJson}

Si existen opciones anteriores, generá una alternativa
REALMENTE diferente.

No alcanza con:
- cambiar "con baño" por "1 baño";
- invertir dos palabras;
- agregar una coma;
- reemplazar "ubicado en" por "en".

Buscá otro enfoque comercial utilizando únicamente atributos
relevantes y confirmados.

IMPORTANTE:
si la propiedad tiene pocos atributos diferenciales, mantené
un título breve y profesional. No agregues baños, dormitorios,
superficies u otros datos triviales solamente para diferenciar
la nueva opción.

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
Actuás como redactor de una inmobiliaria profesional argentina.

Tu tarea es escribir la descripción comercial de una propiedad
lista para ser publicada.

IMPORTANTE:

No estás redactando una ficha técnica.
No estás resumiendo una base de datos.
No estás analizando la publicación.
Estás escribiendo el texto comercial que leería una persona
interesada en la propiedad.

ESTILO DE REDACCIÓN:

- Escribí como una inmobiliaria profesional argentina.
- Usá lenguaje natural, claro y comercial.
- La descripción debe tener ritmo y continuidad.
- Evitá enumerar campos de una base de datos.
- Seleccioná la información que aporte valor comercial o ayude al matching.
- No omitas datos confirmados relevantes solamente para mantener el texto breve.
- La descripción debe aprovechar especialmente los datos confirmados que
  permitan comprender mejor la propiedad y comparar compatibilidades.
- Preferí explicar cómo está compuesta la propiedad antes que
  enumerar números sin contexto.
- Podés dividir el texto en 2 o 3 párrafos breves.
- No uses encabezados como "Descripción", "Características",
  "Superficie", "Permuta" u "Operación".
- No uses lenguaje grandilocuente ni exageraciones.
- Evitá frases propias de una ficha técnica como
  "se compone de", "dispone de X m²", "cuenta con una superficie de"
  cuando puedan expresarse de una manera más natural.
- La descripción debe tener una introducción comercial y luego
  desarrollar los datos disponibles con continuidad.
- Variá la construcción de las frases para que el texto no parezca
  generado desde campos estructurados.
- Si hay poca información disponible, priorizá calidad sobre longitud.
  Una descripción breve y natural es mejor que una descripción larga
  rellenada artificialmente.

ESTRUCTURA ORIENTATIVA:

1. Presentación breve:
   tipo de propiedad + ambientes + zona.

2. Desarrollo:
   contar naturalmente cómo está compuesta la propiedad usando
   únicamente información confirmada.

3. Información adicional relevante:
   superficies, cochera, patio, amenities, antigüedad,
   condiciones de permuta u otros datos confirmados que realmente
   aporten valor.

No es obligatorio utilizar las tres partes.
Si hay poca información confirmada, redactá un texto más breve
en lugar de rellenarlo artificialmente.

AMBIENTES:

En departamentos y casas, cuando existe una cantidad confirmada
de dormitorios y no hay información que lo contradiga, podés utilizar:

1 dormitorio = 2 ambientes
2 dormitorios = 3 ambientes
3 dormitorios = 4 ambientes

Esto es solamente una forma comercial de redactar el texto.
No modifica el dato estructurado.

INFORMACIÓN PRIORITARIA PARA MATCHING:

Cuando estos datos estén confirmados y sean relevantes para la propiedad,
intentá incorporarlos naturalmente en la descripción:

- cantidad de ambientes;
- dormitorios;
- baños;
- superficie total y cubierta;
- cochera;
- amenities relevantes;
- características diferenciales;
- antigüedad cuando aporte contexto;
- condiciones de permuta;
- ubicación a nivel zona o barrio.

Si existieran datos confirmados sobre:
- distribución de ambientes;
- orientación;
- estado de conservación;
- balcón;
- lavadero;
- tipo de cochera;
- ascensor;
- expensas;
- servicios;

también priorizalos porque mejoran la calidad comercial y el matching.

IMPORTANTE:
si alguno de esos datos NO está confirmado en la ficha, no lo inventes ni
lo deduzcas. El generador debe producir la mejor descripción posible con
la información disponible.

TRATAMIENTO DE LA DESCRIPCIÓN ACTUAL:

La descripción actual escrita por el usuario es una fuente de información
confirmada sobre la propiedad.

Al optimizarla:

- Detectá los hechos inmobiliarios concretos que el usuario escribió.
- Conservá los datos útiles aunque no exista un campo estructurado específico.
- Podés reorganizarlos y redactarlos mejor, pero no eliminarlos sin motivo.
- Esto incluye, cuando hayan sido expresamente indicados:
  distribución,
  orientación,
  estado,
  luminosidad,
  expensas,
  calefacción,
  servicios,
  lavadero,
  balcón,
  terraza,
  patio,
  jardín,
  vistas,
  reformas,
  materiales
  y otros diferenciales comerciales.
- Si la descripción contradice un dato estructurado actual, priorizá
  el dato estructurado.
- No consideres como hechos las exageraciones, opiniones, slogans
  ni lenguaje puramente publicitario.
- No conserves texto basura, pruebas ni repeticiones.

REGLAS DE VERACIDAD:

Usá solamente información confirmada en los datos estructurados actuales
o expresamente indicada por el usuario en la descripción actual.

No inventes ni deduzcas:
- luminosidad;
- amplitud;
- comodidad;
- funcionalidad;
- distribución específica;
- estado de conservación;
- orientación;
- vistas;
- materiales;
- calidad constructiva;
- público objetivo;
- usos posibles;
- amenities;
- ambientes que no estén confirmados.
- No inferir accesibilidad, conectividad, tranquilidad, seguridad,
  cercanía a servicios, transporte, comercios o puntos de interés
  si esos datos no están explícitamente confirmados.
- Evitar frases genéricas como:
  "zona de fácil acceso",
  "excelente ubicación",
  "zona tranquila",
  "cerca de todo",
  salvo que exista información concreta que las respalde.
- No inferir ni mencionar para qué tipo de persona, familia,
  inversor o comprador sería adecuada la propiedad.
- No utilizar frases como:
  "pensado para quienes buscan",
  "ideal para quienes buscan",
  "una opción práctica",
  "una alternativa funcional",
  "perfecto para",
  salvo que esa afirmación provenga explícitamente de información
  confirmada y sea objetivamente verificable.

No afirmar algo solamente porque aparezca en una imagen.

REGLAS DE CONTENIDO:

- No incluir precio.
- No incluir moneda.
- No repetir la dirección exacta.
- Utilizar preferentemente barrio o zona.
- No mencionar país, provincia o partido si resulta redundante.
- No mencionar PermuOK.
- No hablar de la publicación, ficha o datos cargados.
- No decir "se publica", "se informa", "datos confirmados",
  "tipología", "operación en moneda" ni expresiones similares.
- No agregar llamadas a la acción.
- No decir "contactanos", "coordiná una visita" ni similares.
- No mencionar información faltante.
- No recomendar mejoras.
- No mencionar características ausentes solamente porque estén
  informadas con valor 0.
- Por ejemplo, si cocheras = 0, NO escribir
  "no posee cochera" o "no dispone de cochera".
- Las ausencias sólo deben mencionarse cuando sean realmente
  relevantes para comprender la propiedad.
- No convertir la descripción en una lista exhaustiva de datos.
- No convertir la descripción en una enumeración mecánica.
- Incluir los datos relevantes disponibles, integrándolos de forma natural.
- Evitar omitir información útil para matching solamente para acortar el texto.
- Si existen pocos datos confirmados, no rellenar con información inventada.

PERMUTA:

Si existen condiciones de permuta confirmadas, incorporarlas
naturalmente al final.

Por ejemplo:

"La propiedad acepta propuestas abiertas de permuta."

No usar etiquetas como:

"Permuta:"
"Operación:"
"Condiciones:"

EJEMPLO DEL ESTILO BUSCADO:

"En Lanús Oeste se encuentra este departamento de 2 ambientes,
con una superficie total de 40 m² y 35 m² cubiertos.

La unidad cuenta con 1 dormitorio y 1 baño. La propiedad acepta
propuestas abiertas de permuta."

Este ejemplo muestra el TONO y la FORMA DE REDACTAR.
No copies información del ejemplo que no esté presente en la propiedad.

EJEMPLOS DE ESTILO NO DESEADO:

"Departamento en Lanús Oeste, Lanús, Provincia de Buenos Aires.
Superficie total 40 m², superficie cubierta 35 m².
Operación en moneda USD."

"Se publica con detalle de superficies y tipología del inmueble."

"Propiedad ideal para inversión por su excelente funcionalidad."

"Permuta: acepta propuestas abiertas."
"No dispone de cochera."

"No posee balcón."

"No cuenta con patio."

OPCIONES GENERADAS ANTERIORMENTE:

{$previousJson}

Si existen opciones anteriores, redactá una alternativa diferente
manteniendo el mismo nivel profesional.

La descripción actual escrita por el usuario también puede contener
atributos confirmados que no tengan un campo estructurado específico.

Podés utilizar esos atributos como diferenciales en el título cuando sean
hechos concretos, no contradigan los datos estructurados y sean
comercialmente relevantes.

No utilizar opiniones, exageraciones ni frases promocionales de la
descripción como si fueran características objetivas.

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
