<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;
use Throwable;
use App\Services\CompatibilityJobService;

class DevelopmentAIAnalysisService
{
    private const PROMPT_VERSION = '1.0';
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private static function db(
        bool $forceReconnect = false
    ): PDO {
        require_once __DIR__ . '/../../../db.php';

        return pdo($forceReconnect);
    }

    public static function prepareInput(
        int $developmentId
    ): array {
        if ($developmentId <= 0) {
            throw new Exception(
                'El ID del desarrollo no es válido.'
            );
        }

        $pdo = self::db();

        /*
         * Desarrollo principal.
         */
        $st = $pdo->prepare("
            SELECT *
            FROM developments
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $developmentId,
        ]);

        $development =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$development) {
            throw new Exception(
                'Desarrollo no encontrado.'
            );
        }

        /*
         * Tipologías.
         */
        $stUnits = $pdo->prepare("
            SELECT
                unit_type,
                label,
                rooms,
                bedrooms,
                bathrooms,
                garages,
                area_from,
                area_to,
                price_from,
                price_to,
                currency,
                available_units
            FROM development_unit_types
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");

        $stUnits->execute([
            'development_id' =>
                $developmentId,
        ]);

        $unitTypes =
            $stUnits->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        /*
         * Amenities.
         */
        $stAmenities = $pdo->prepare("
            SELECT amenity_code
            FROM development_amenities
            WHERE development_id = :development_id
            ORDER BY amenity_code ASC
        ");

        $stAmenities->execute([
            'development_id' =>
                $developmentId,
        ]);

        $amenities =
            $stAmenities->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        /*
         * Imágenes.
         *
         * Por ahora la IA no analiza visualmente
         * el contenido de las imágenes.
         *
         * Sólo incluimos cantidad y portada para
         * representar el estado actual de la publicación
         * dentro del hash.
         */
        $stImages = $pdo->prepare("
            SELECT
                id,
                is_cover,
                sort_order
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");

        $stImages->execute([
            'development_id' =>
                $developmentId,
        ]);

        $images =
            $stImages->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        return [
            'entity_type' =>
                'development',

            'entity_id' =>
                $developmentId,

            'development' => [
                'title' =>
                    trim(
                        (string)(
                            $development['title']
                            ?? ''
                        )
                    ),

                'short_description' =>
                    trim(
                        (string)(
                            $development['short_description']
                            ?? ''
                        )
                    ),

                'description' =>
                    trim(
                        (string)(
                            $development['description']
                            ?? ''
                        )
                    ),

                'developer_name' =>
                    $development['developer_name']
                    ?? null,

                'construction_company' =>
                    $development['construction_company']
                    ?? null,

                'stage' =>
                    $development['development_stage']
                    ?? null,

                'delivery_date_estimated' =>
                    $development['delivery_date_estimated']
                    ?? null,

                'location' => [
                    'country' =>
                        $development['country']
                        ?? null,

                    'province' =>
                        $development['province']
                        ?? null,

                    'city' =>
                        $development['city']
                        ?? null,

                    'zone' =>
                        $development['zone']
                        ?? null,

                    'address' =>
                        $development['address']
                        ?? null,

                    'formatted_address' =>
                        $development['formatted_address']
                        ?? null,
                ],

                'commercial' => [
                    'currency' =>
                        $development['currency']
                        ?? null,

                    'price_from' =>
                        $development['price_from']
                        ?? null,

                    'price_to' =>
                        $development['price_to']
                        ?? null,

                    'total_units' =>
                        $development['total_units']
                        ?? null,

                    'available_units' =>
                        $development['available_units']
                        ?? null,
                ],

                'commercial_resources' => [
                    'has_whatsapp' =>
                        !empty(
                            $development['whatsapp_url']
                        ),

                    'has_brochure' =>
                        !empty(
                            $development['brochure_url']
                        ),

                    'has_video' =>
                        !empty(
                            $development['video_url']
                        ),
                ],

                'unit_types' =>
                    $unitTypes,

                'amenities' =>
                    $amenities,

                'images' => [
                    'count' =>
                        count($images),

                    'has_cover' =>
                        self::hasCoverImage(
                            $images
                        ),
                ],
            ],
        ];
    }

    public static function buildInputHash(
        int $developmentId
    ): string {
        $payload = [
            'prompt_version' =>
                self::PROMPT_VERSION,

            'input' =>
                self::prepareInput(
                    $developmentId
                ),
        ];

        try {
            $json =
                json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_PRESERVE_ZERO_FRACTION |
                        JSON_THROW_ON_ERROR
                );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo preparar el análisis IA del desarrollo.',
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
        int $developmentId
    ): array {
        if ($developmentId <= 0) {
            throw new Exception(
                'El ID del desarrollo no es válido.'
            );
        }

        $pdo = self::db();

        $inputHash =
            self::buildInputHash(
                $developmentId
            );

        /*
         * Buscamos un análisis exacto para:
         *
         * - development actual;
         * - contenido actual;
         * - versión actual del prompt.
         */
        $st = $pdo->prepare("
            SELECT *
            FROM publication_ai_analyses
            WHERE entity_type = 'development'
              AND entity_id = :entity_id
              AND input_hash = :input_hash
              AND prompt_version = :prompt_version
            ORDER BY id DESC
            LIMIT 1
        ");

        $st->execute([
            'entity_id' =>
                $developmentId,

            'input_hash' =>
                $inputHash,

            'prompt_version' =>
                self::PROMPT_VERSION,
        ]);

        $existing =
            $st->fetch(PDO::FETCH_ASSOC)
            ?: null;

        /*
         * Si ya existe exactamente el mismo
         * análisis completo, reutilizamos.
         */
        if (
            $existing &&
            $existing['status'] === 'completed'
        ) {
            $quality =
                DevelopmentQualityScoreService::recalculateAndPersist(
                    $developmentId
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

        /*
         * Si ya está pendiente/procesando,
         * conservamos el mismo análisis.
         */
        if (
            $existing &&
            in_array(
                $existing['status'],
                [
                    'pending',
                    'processing',
                ],
                true
            )
        ) {
            $analysisId =
                (int)$existing['id'];
        }

        /*
         * Si había fallado, lo reactivamos.
         */
        elseif (
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
                'id' =>
                    $analysisId,
            ]);
        }

        /*
         * Caso nuevo.
         */
        else {
            $stInsert = $pdo->prepare("
                INSERT INTO publication_ai_analyses (
                    entity_type,
                    entity_id,
                    status,
                    prompt_version,
                    input_hash
                )
                VALUES (
                    'development',
                    :entity_id,
                    'pending',
                    :prompt_version,
                    :input_hash
                )
            ");

            $stInsert->execute([
                'entity_id' =>
                    $developmentId,

                'prompt_version' =>
                    self::PROMPT_VERSION,

                'input_hash' =>
                    $inputHash,
            ]);

            $analysisId =
                (int)$pdo->lastInsertId();
        }

        /*
         * Encolamos.
         */
        $job =
            CompatibilityJobService::enqueueDevelopmentAIAnalysis(
                $developmentId,
                $analysisId
            );

        /*
         * Dejamos persistido el objetivo actual
         * mientras esperamos la IA.
         */
        DevelopmentQualityScoreService::recalculateAndPersist(
            $developmentId
        );

        return [
            'analysis_id' =>
                $analysisId,

            'status' =>
                'pending',

            'reused' =>
                $existing !== null,

            'queued' =>
                true,

            'job_id' =>
                (int)(
                    $job['id']
                    ?? 0
                ),
        ];
    }

    public static function processAnalysis(
        int $developmentId,
        int $analysisId,
        int $attempt = 1,
        int $maxAttempts = 3
    ): array {
        if ($developmentId <= 0) {
            throw new Exception(
                'El ID del desarrollo no es válido.'
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
              AND entity_type = 'development'
              AND entity_id = :entity_id
            LIMIT 1
        ");

        $st->execute([
            'id' =>
                $analysisId,

            'entity_id' =>
                $developmentId,
        ]);

        $analysis =
            $st->fetch(PDO::FETCH_ASSOC);

        if (!$analysis) {
            throw new Exception(
                'No se encontró el análisis IA del desarrollo.'
            );
        }

        if (
            $analysis['status'] ===
            'completed'
        ) {
            return [
                'ok' => true,
                'skipped' => true,
                'analysis_id' => $analysisId,
                'reason' =>
                    'El análisis ya estaba completado.',
            ];
        }

        /*
         * Verificamos que el contenido no haya
         * cambiado desde que se pidió el análisis.
         */
        $currentHash =
            self::buildInputHash(
                $developmentId
            );

        $requestedHash =
            (string)(
                $analysis['input_hash']
                ?? ''
            );

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
                    'El desarrollo cambió después de solicitar el análisis.',

                'id' =>
                    $analysisId,
            ]);

            return [
                'ok' =>
                    true,

                'skipped' =>
                    true,

                'analysis_id' =>
                    $analysisId,

                'reason' =>
                    'El desarrollo cambió después de solicitar el análisis.',
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
            'id' =>
                $analysisId,
        ]);

        try {
            $input =
                self::prepareInput(
                    $developmentId
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
             * generamos el score oficial híbrido.
             */
            $quality =
                DevelopmentQualityScoreService::recalculateAndPersist(
                    $developmentId
                );

            return [
                'ok' =>
                    true,

                'analysis_id' =>
                    $analysisId,

                'status' =>
                    'completed',

                'quality' =>
                    $quality,
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

        try {
            $inputJson =
                json_encode(
                    $input,
                    JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR
                );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo serializar el desarrollo para la IA.',
                0,
                $e
            );
        }

        $prompt = <<<PROMPT
Sos un especialista en desarrollos inmobiliarios B2B.

Analizá esta publicación de un desarrollo inmobiliario en PermuOK.

Tu análisis será mostrado directamente a profesionales inmobiliarios.

Todo texto visible debe escribirse en español rioplatense profesional,
con lenguaje inmobiliario natural, claro, preciso y accionable.


OBJETIVO

Evaluá:

- claridad y calidad del título;
- claridad y utilidad de la descripción;
- coherencia global de la información publicada;
- capacidad del desarrollo para generar matches inmobiliarios relevantes.


PRINCIPIOS GENERALES

- No inventes información.
- No completes datos que no fueron proporcionados.
- No penalices automáticamente campos opcionales vacíos.
- No confundas cantidad de información con calidad.
- Una publicación puede ser excelente sin tener todos los campos opcionales completos.
- Penalizá contradicciones reales y ambigüedades importantes.
- Priorizá problemas que puedan afectar interpretación, comercialización o matching.
- No generes recomendaciones por detalles menores de estilo si no afectan comprensión.
- Nunca inventes precios, superficies, fechas, cantidades, ubicaciones, amenities o características, ni siquiera como ejemplo.
- No uses valores hipotéticos dentro de recomendaciones visibles.


JERARQUÍA DE INFORMACIÓN

Los datos estructurados representan la fuente principal de verdad.

Título, descripción corta y descripción extensa pueden aportar contexto adicional.

Si un texto contradice claramente un dato estructurado,
considerá el dato estructurado como referencia principal.

No consideres contradicción que el texto sea más general
o más resumido que la información estructurada,
si ambas expresiones pueden convivir.


TÍTULO

El título debe permitir identificar rápidamente el desarrollo.

Puede incluir:

- nombre comercial;
- tipo de proyecto;
- ubicación;
- atributo diferencial relevante.

No exijas que incluya todos esos elementos.

Un nombre comercial breve puede ser un buen título
si el resto de la publicación permite entender claramente el proyecto.

Penalizá títulos:

- genéricos al punto de no identificar el proyecto;
- confusos;
- contradictorios con la publicación;
- excesivamente promocionales sin aportar identidad.

No penalices un título simplemente por ser corto.


DESCRIPCIÓN

La descripción debe ayudar a otro profesional inmobiliario
a comprender qué ofrece el desarrollo.

Puede aportar:

- concepto general;
- ubicación;
- etapa;
- propuesta del proyecto;
- tipologías;
- superficies;
- amenities;
- características relevantes;
- situación comercial.

No tiene que repetir mecánicamente todos los campos estructurados.

No premies longitud innecesaria.

Una descripción breve puede obtener un puntaje alto
si comunica correctamente la propuesta.

No penalices que falten en la descripción datos
que ya están claramente representados en la ficha estructurada.


DESCRIPCIÓN CORTA Y DESCRIPCIÓN PRINCIPAL

La descripción corta puede funcionar como resumen comercial.

No exijas que contenga el mismo nivel de detalle
que la descripción principal.

Sólo señalá una diferencia entre ambas cuando
pueda inducir a interpretar proyectos distintos
o información comercial incompatible.


ETAPA Y ENTREGA

Interpretá las etapas conceptualmente:

land:
Lote / tierra.

prelaunch:
Prelanzamiento.

launch:
Lanzamiento.

presale:
Preventa.

under_construction:
En construcción.

finished:
Finalizado.

No muestres estos valores internos al usuario.

No consideres automáticamente obligatorio informar
una fecha de entrega en un proyecto finalizado.

Para proyectos en prelanzamiento, lanzamiento, preventa
o construcción, una fecha estimada puede aportar valor,
pero sólo recomendala si realmente es relevante
y no existe una recomendación más importante.

Nunca inventes una fecha de entrega.


UBICACIÓN

Un desarrollo representa un proyecto ubicado físicamente
en un lugar concreto.

La ciudad, zona y dirección pueden tener distintos niveles de precisión.

No consideres contradicción que:

- el título use una ciudad y la ficha una zona más específica;
- la descripción mencione un barrio y la dirección sea más precisa;
- diferentes textos utilicen niveles geográficos compatibles.

Sólo marcá contradicción si las referencias geográficas
apuntan claramente a ubicaciones incompatibles.


PRECIOS

Un desarrollo puede comercializarse correctamente
sólo con un "precio desde".

La ausencia de precio máximo NO es automáticamente un problema.

No recomiendes inventar o completar un precio máximo
si el proyecto legítimamente se comercializa desde un valor inicial.

Si existen precio desde y precio hasta:

- deben ser coherentes entre sí;
- el máximo no puede ser inferior al mínimo;
- la moneda debe interpretarse como parte del mismo contexto comercial.

Nunca inventes un precio sugerido.


UNIDADES Y DISPONIBILIDAD

Las unidades totales y disponibles deben interpretarse
como información comercial del desarrollo.

No consideres obligatorio informar ambas cantidades
para que una publicación sea buena.

Sí considerá una inconsistencia si las unidades disponibles
superan claramente las unidades totales.

Una disponibilidad igual a 0 puede ser un dato válido;
no la interpretes como campo vacío.


TIPOLOGÍAS

Las tipologías son fundamentales para comprender
qué unidades ofrece el desarrollo.

No premies la cantidad de tipologías por sí sola.

Una sola tipología bien definida puede ser suficiente.

Evaluá principalmente si las tipologías existentes
son útiles para entender y comparar las unidades.

Considerá:

- tipo de unidad;
- nombre comercial si existe;
- ambientes;
- dormitorios;
- baños;
- cocheras;
- superficies;
- precios;
- disponibilidad.

No exijas ambientes o dormitorios para tipologías
donde esos campos no sean relevantes,
como terrenos, cocheras o ciertos depósitos.

No conviertas campos opcionales vacíos
en contradicciones.


COHERENCIA ENTRE DESARROLLO Y TIPOLOGÍAS

Revisá posibles incompatibilidades reales.

Ejemplos relevantes:

- precio general incompatible con todos los precios de tipologías;
- descripción que afirma un tipo de unidad inexistente;
- cantidades de ambientes imposibles o claramente contradictorias;
- superficie mínima superior a máxima;
- precio mínimo superior a máximo;
- disponibilidad incoherente.

No marques contradicción simplemente porque
el desarrollo tenga un rango general más amplio
que una tipología individual.

Eso puede ser perfectamente válido.


AMENITIES

No asumas que todos los desarrollos deben tener amenities.

Si existen amenities cargados,
evaluá si la descripción los representa correctamente cuando los menciona.

No penalices fuertemente la ausencia de amenities.

No inventes amenities faltantes.


RECURSOS COMERCIALES

WhatsApp, brochure y video son recursos complementarios.

No exijas que existan todos.

La ausencia de uno o varios recursos comerciales
no debe generar por sí sola una mala evaluación IA.

Sólo generá una recomendación relacionada con estos recursos
si realmente puede mejorar significativamente la utilidad comercial
y no existen problemas más importantes.


IMÁGENES

El input sólo indica cantidad de imágenes y existencia de portada.

NO analices ni describas el contenido visual de las imágenes.

No inventes qué muestran las imágenes.

La evaluación objetiva ya considera la cantidad de imágenes,
por lo que no dupliques esa penalización en los puntajes IA.


COHERENCIA

consistency_score mide coherencia conceptual.

No lo uses como puntuación de completitud.

Un desarrollo con pocos datos opcionales
puede tener 100 de coherencia si los datos existentes
son compatibles entre sí.

Reducilo principalmente por:

- contradicciones reales;
- datos comerciales incompatibles;
- información textual incompatible con la ficha;
- tipologías internamente incoherentes;
- ubicación contradictoria.


POTENCIAL DE MATCHING

matchability_score mide qué tan útil es la publicación
para encontrar búsquedas o intereses compatibles.

No mide la cantidad potencial de matches.

No otorgues mejor puntaje porque el desarrollo:

- tenga muchas tipologías;
- tenga muchos amenities;
- abarque más precios;
- tenga más unidades.

Una publicación específica y bien definida
puede tener excelente potencial de matching.

Evaluá principalmente si otro usuario puede entender
qué ofrece el desarrollo y determinar si resulta compatible
con una necesidad inmobiliaria.


CUÁNDO GENERAR UNA SUGERENCIA

Generá una sugerencia únicamente si:

1. corrige una contradicción real;

2. corrige una ambigüedad que pueda afectar
   interpretación o matching;

3. mejora título o descripción porque actualmente
   representan mal el desarrollo;

4. completa un dato realmente importante
   que está ausente y puede mejorar significativamente
   la utilidad comercial;

5. mejora una tipología insuficientemente definida
   cuando esa falta afecta su interpretación.

No generes sugerencias sólo porque exista
un campo opcional vacío.

No generes sugerencias para "completar más" la publicación.


CAMPOS OPCIONALES

Cuando sugieras completar un campo opcional,
la recomendación debe ser condicional.

Ejemplo correcto:

"Si el proyecto ya tiene una fecha estimada de entrega,
cargarla puede aportar información comercial útil."

Ejemplo incorrecto:

"Completá la fecha de entrega."

No transformes campos opcionales
en requisitos artificiales de calidad.


CANTIDAD DE RECOMENDACIONES

En condiciones normales devolvé entre 0 y 3 sugerencias.

Generá una cuarta o quinta únicamente
si existe otro problema importante e independiente.

No intentes completar el máximo disponible.

Preferí pocas recomendaciones de alto valor.


PRIORIDAD DE RECOMENDACIONES

Orden:

1. Contradicciones reales.
2. Ambigüedades que afecten matching.
3. Información comercial central mal representada.
4. Título o descripción mejorables.
5. Datos opcionales de alto impacto.
6. Mejoras menores.

No muestres recomendaciones débiles
si ya existen tres más importantes.


EVITAR REPETICIONES

- No repitas el mismo problema en "contradictions" y "suggestions".
- Si algo es una contradicción real, informalo en "contradictions".
- No agregues además una sugerencia sobre exactamente el mismo conflicto.
- Cada sugerencia debe tratar un aspecto diferente.
- No generes varias sugerencias que conduzcan a la misma corrección.


LENGUAJE PARA EL USUARIO

Nunca muestres nombres técnicos de:

- campos;
- variables;
- claves JSON;
- columnas;
- tablas;
- valores internos.

No escribas expresiones como:

development_stage
price_from
price_to
total_units
available_units
unit_type
area_from
area_to
under_construction
presale
finished
true
false
null

Traducí todo a lenguaje inmobiliario natural.


VOCABULARIO

Usá expresiones como:

- "precio desde";
- "precio hasta";
- "unidades disponibles";
- "unidades totales";
- "tipología";
- "superficie desde";
- "superficie hasta";
- "entrega estimada";
- "en construcción";
- "preventa";
- "finalizado";
- "desarrolladora";
- "constructora".

Evitá lenguaje técnico de software.


SUGERENCIAS

Cada sugerencia debe contener:

field:
identificador técnico interno.
No será mostrado directamente.

action:
usá sólo:
rewrite
clarify
fix
review
complete

title:
título breve, específico y accionable.

message:
explicación clara de qué puede mejorar
y por qué importa.

priority:
low
medium
high


PUNTAJES

Devolvé cada puntaje entre 0 y 100.

title_score:
claridad, calidad y capacidad del título
para identificar correctamente el desarrollo.

description_score:
claridad, utilidad comercial y fidelidad
de la descripción respecto del proyecto.

consistency_score:
coherencia entre textos, datos comerciales,
ubicación, etapa y tipologías.

matchability_score:
capacidad de la publicación para permitir
matches inmobiliarios relevantes.


DESARROLLO:

{$inputJson}
PROMPT;

        $schema = [
            'type' =>
                'object',

            'additionalProperties' =>
                false,

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
                        'type' =>
                            'object',

                        'additionalProperties' =>
                            false,

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
                    'type' =>
                        'array',

                    'maxItems' =>
                        5,

                    'items' => [
                        'type' =>
                            'string',
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

                'format' => [
                    'type' =>
                        'json_schema',

                    'name' =>
                        'development_analysis',

                    'strict' =>
                        true,

                    'schema' =>
                        $schema,
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

        if ($outputText === '') {
            throw new Exception(
                'OpenAI no devolvió el análisis esperado.'
            );
        }

        try {
            $result =
                json_decode(
                    $outputText,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo interpretar el análisis de OpenAI.',
                0,
                $e
            );
        }

        $result['_model'] =
            $decoded['model']
            ?? $model;

        return $result;
    }

    private static function completeAnalysis(
        int $analysisId,
        array $result
    ): void {
        $pdo =
            self::db(true);

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
                    $result['suggestions']
                    ?? [],
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                ),

            'contradictions_json' =>
                json_encode(
                    $result['contradictions']
                    ?? [],
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                ),

            'model_name' =>
                $result['_model']
                ?? null,

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
                mb_substr(
                    $message,
                    0,
                    6000
                ),

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
                mb_substr(
                    $message,
                    0,
                    6000
                ),

            'id' =>
                $analysisId,
        ]);
    }

    private static function hasCoverImage(
        array $images
    ): bool {
        foreach ($images as $image) {
            if (
                (int)(
                    $image['is_cover']
                    ?? 0
                ) === 1
            ) {
                return true;
            }
        }

        /*
         * El sistema actual también interpreta
         * la primera imagen como portada,
         * aunque no siempre tenga is_cover = 1.
         */
        return count($images) > 0;
    }
}