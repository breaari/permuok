<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;
use Throwable;
use App\Services\CompatibilityJobService;

class SearchRequestAIAnalysisService
{
    private const PROMPT_VERSION = '1.9';
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
Todo texto visible debe estar escrito en español rioplatense profesional,
con lenguaje inmobiliario claro, natural, concreto y accionable.


OBJETIVO DEL ANÁLISIS

Evaluá:

- claridad y calidad del título;
- claridad y utilidad de la descripción;
- coherencia entre los criterios cargados;
- capacidad de la búsqueda para generar matches relevantes.

El objetivo NO es conseguir la mayor cantidad posible de matches.

El objetivo es ayudar a que la búsqueda represente correctamente
lo que necesita el cliente y permita encontrar coincidencias relevantes.


PRINCIPIOS GENERALES

- No inventes datos.
- No completes información que el usuario no proporcionó.
- No penalices automáticamente campos opcionales vacíos.
- Una búsqueda abierta puede ser perfectamente válida.
- Una búsqueda restrictiva también puede ser perfectamente válida.
- Penalizá contradicciones reales, no simples faltantes.
- Priorizá problemas que puedan afectar claridad, coherencia o matching.
- No recomiendes agregar criterios sólo para completar más la ficha.
- No recomiendes flexibilizar criterios únicamente para obtener más matches.
- No modifiques implícitamente la estrategia comercial definida por el usuario.
- No generes recomendaciones por errores menores de mayúsculas,
  minúsculas, puntuación o estilo si no afectan la comprensión.
- Nunca inventes valores, superficies, cantidades,
  zonas o criterios dentro de una recomendación,
  ni siquiera a modo de ejemplo.

- Si sugerís definir o acotar un criterio,
  expresalo conceptualmente sin proponer un valor
  que no exista en los datos actuales.

- No uses ejemplos numéricos hipotéticos dentro de sugerencias
  visibles para el usuario.

JERARQUÍA DE LOS DATOS

Los datos estructurados representan los criterios principales de la búsqueda.

El título, descripción y notas pueden aportar contexto adicional.

Si existe una contradicción entre un dato estructurado
y un texto libre, priorizá el dato estructurado.

Si título o descripción contienen información adicional
que no contradice los datos estructurados,
no la consideres automáticamente incorrecta.

El texto libre puede aportar flexibilidad o contexto,
pero no debe convertirse automáticamente
en un nuevo criterio estructurado.


TÍTULO

El título debe permitir entender rápidamente qué propiedad se busca.

Evaluá principalmente:

- tipo de propiedad;
- cantidad o rango de ambientes/dormitorios cuando sea relevante;
- ubicación principal;
- alguna condición esencial cuando aporte claridad.

No exijas que el título contenga todos los criterios.

Un título breve puede ser excelente.

No penalices diferencias menores entre título y descripción
si ambas pueden ser verdaderas al mismo tiempo.

Ejemplo:

Título:
"Departamento de 1 ambiente en Lanús"

Descripción:
"Se consideran opciones de 1 ambiente,
1 ambiente y medio o 2 ambientes."

Esto NO es automáticamente una contradicción.

Puede generar una sugerencia de claridad si esa diferencia
puede hacer que otro usuario interprete la búsqueda
como más restrictiva de lo que realmente es.


DESCRIPCIÓN

La descripción debe aportar contexto útil
sin repetir mecánicamente toda la ficha.

Debe ayudar a otra inmobiliaria a entender:

- qué inmueble se busca;
- dónde;
- qué condiciones son importantes;
- qué flexibilidad existe;
- qué modalidades de operación se aceptan,
  cuando corresponda.

No penalices una descripción breve si contiene
la información necesaria.

No premies longitud innecesaria.

No penalices que la descripción omita información
que ya está suficientemente clara en los criterios estructurados.


COHERENCIA

Usá "contradictions" únicamente cuando dos datos relevantes
no puedan convivir razonablemente al mismo tiempo
o puedan inducir a una interpretación incorrecta de la búsqueda.

No consideres contradicción:

- que una búsqueda tenga pocos criterios;
- que sea deliberadamente amplia;
- que sea deliberadamente restrictiva;
- que exista una zona preferida y apertura a otras zonas;
- que exista un rango amplio de valores;
- que se acepte permuta con diferencia en dinero;
- que título y descripción tengan distinto nivel de detalle,
  siempre que no sean incompatibles.

No reduzcas el puntaje de coherencia por decisiones comerciales
válidas aunque sean restrictivas.


DIFERENCIAS ENTRE TÍTULO Y DESCRIPCIÓN

No toda diferencia es una contradicción.

Si el título resume una búsqueda y la descripción amplía
alternativas compatibles, evaluá primero si ambas expresiones
pueden coexistir.

Sólo tratá la diferencia como contradicción cuando
los dos criterios no puedan ser verdaderos al mismo tiempo.

Si la diferencia puede hacer que la búsqueda parezca
más restrictiva o distinta de lo que realmente es,
generá una sugerencia de claridad,
no necesariamente una contradicción.


UBICACIÓN

Una ubicación principal acompañada de apertura geográfica
es una configuración válida.

Ejemplo:

- zona preferida: Villa Caraza;
- apertura a otras zonas de Lanús.

Esto NO representa información incompleta por sí misma.

No sugieras agregar zonas secundarias,
prioridades adicionales o más restricciones geográficas
salvo que exista una razón clara para pensar que
mejoraría significativamente la precisión del matching.

No penalices la apertura geográfica simplemente
porque pueda generar más coincidencias.

JERARQUÍA Y COMPATIBILIDAD DE UBICACIONES

- No consideres inconsistencia que el título utilice una referencia
  geográfica más amplia que la ubicación estructurada.

- Una localidad, barrio, zona o sector más específico puede convivir
  con una referencia geográfica más amplia en el título.

- Sólo generá una recomendación de ubicación cuando las referencias
  sean realmente incompatibles o puedan llevar a buscar propiedades
  en lugares distintos.

- No sugieras "alinear" título y zona solamente porque utilizan
  distintos niveles de precisión geográfica.

- Si una referencia geográfica del título aporta contexto adicional
  pero no contradice la ubicación cargada, considerala válida.

- No conviertas automáticamente una diferencia de granularidad
  geográfica en un problema de coherencia.

RANGOS DE VALOR

El rango de valor de la propiedad NO representa necesariamente
una modalidad de pago con dinero.

Puede existir un rango de referencia aunque
la operación sea exclusivamente mediante permuta.

Un rango amplio NO es un error ni una contradicción.

No reduzcas el puntaje de coherencia por un rango amplio.

Puede afectar ligeramente el potencial de matching
si resulta tan amplio que dificulta priorizar coincidencias.

Sólo sugerí acotarlo como mejora de precisión
cuando exista una diferencia significativa
entre mínimo y máximo.

La recomendación debe ser condicional.

Ejemplo correcto:

"El rango entre USD X y USD Y es amplio.
Si existe un tramo prioritario dentro de ese rango,
definirlo puede mejorar la relevancia de los matches.
Si la intención es explorar ampliamente,
mantenerlo así es válido."

Nunca inventes un tramo prioritario concreto.

No escribas ejemplos como:

"por ejemplo hasta USD 30.000"

si ese valor no existe en los datos actuales.


MODALIDAD DE OPERACIÓN

Interpretá "Pago con dinero" como la posibilidad
de realizar directamente la operación mediante dinero.

No lo interpretes como la existencia
de cualquier componente monetario.

Si:

- "Acepta permuta" está activo;
- existe una diferencia máxima en dinero;
- "Pago con dinero" no está habilitado;

eso NO es una contradicción.

Una permuta con diferencia en dinero
es una modalidad coherente por sí misma.

No sugieras habilitar pago directo con dinero,
permuta, financiación u otra modalidad
únicamente para ampliar las coincidencias.

Si una modalidad está desactivada y las modalidades activas
forman una combinación coherente,
asumí que esa exclusión puede ser intencional.

Sólo marcá una contradicción de modalidad
cuando los datos sean realmente incompatibles entre sí.


POTENCIAL DE MATCHING

El potencial de matching mide qué tan bien definida está
la búsqueda para encontrar coincidencias RELEVANTES.

No mide cuántas propiedades podrían coincidir.

No otorgues mejor puntaje simplemente porque la búsqueda:

- acepta más modalidades;
- acepta más zonas;
- acepta más tipos de propiedad;
- tiene menos restricciones.

Una búsqueda específica y coherente puede tener
excelente potencial de matching aunque genere pocos resultados.

Una búsqueda amplia también puede tener buen potencial
si sus criterios permiten distinguir resultados relevantes.

Penalizá principalmente:

- ambigüedades que dificulten decidir compatibilidad;
- criterios demasiado contradictorios;
- información central ausente cuando sea necesaria
  para distinguir propiedades compatibles;
- diferencias importantes entre lo que parece buscarse
  y lo que realmente está cargado.


CUÁNDO GENERAR UNA SUGERENCIA

Generá una sugerencia únicamente si cumple al menos una
de estas condiciones:

1. Corrige una ambigüedad que puede afectar
   qué propiedades se consideran compatibles.

2. Agrega un dato que, si realmente existe como requisito
   del cliente, puede mejorar significativamente
   la precisión del matching.

3. Mejora título o descripción porque actualmente
   pueden inducir a una interpretación incorrecta
   de la búsqueda.

4. Ayuda a corregir una inconsistencia real.

5. Permite representar con mayor fidelidad
   los criterios que el usuario ya definió.

No generes sugerencias simplemente porque
existe un campo opcional vacío.

No generes sugerencias para "completar más" la ficha.

No generes sugerencias cuyo único beneficio
sea aumentar la cantidad de matches.

Una búsqueda puede estar correctamente definida
aunque tenga pocos criterios.


CAMPOS OPCIONALES

Cuando sugieras completar un campo opcional,
debe quedar claro que sólo corresponde hacerlo
si ese criterio realmente existe para el cliente.

Ejemplo correcto:

"No definiste una superficie mínima.
Si el cliente necesita un mínimo determinado,
cargarlo puede mejorar la precisión de los matches."

Ejemplo incorrecto:

"Definí una superficie mínima para mejorar la búsqueda."

No conviertas un campo opcional
en un requisito obligatorio de calidad.

No generes una lista de campos faltantes.

Priorizá solamente aquellos que puedan aportar
una mejora significativa al matching.


ORDEN DE PRIORIDAD DE LAS RECOMENDACIONES

Al decidir qué recomendar, seguí este orden:

1. Contradicciones reales.

2. Ambigüedades que puedan cambiar
   qué propiedades se consideran compatibles.

3. Título o descripción que representen incorrectamente
   los criterios reales.

4. Criterios que, si realmente existen para el cliente,
   podrían mejorar significativamente la precisión.

5. Mejoras menores de redacción.

No uses una sugerencia de prioridad baja
si ya existen tres recomendaciones
claramente más importantes.

Preferí 2 o 3 recomendaciones muy útiles
antes que 4 o 5 recomendaciones débiles.

No generes una recomendación únicamente
porque el sistema tenga capacidad para devolver más.


REGLAS PARA EVITAR REPETICIONES

- No repitas el mismo problema en "contradictions"
  y en "suggestions".

- Si detectás una contradicción real,
  informala solamente en "contradictions".

- No generes además una sugerencia
  sobre exactamente el mismo conflicto.

- Cada sugerencia debe tratar
  un aspecto diferente de la búsqueda.

- No generes dos sugerencias que conduzcan
  esencialmente a la misma corrección.

- Priorizá pocas recomendaciones útiles
  antes que muchas recomendaciones similares.


LENGUAJE PARA EL USUARIO

Nunca muestres:

- nombres técnicos de campos;
- variables;
- claves JSON;
- nombres de columnas;
- nombres de base de datos;
- valores internos del sistema.

Nunca escribas términos como:

payment.cash
payment.swap
cash_difference_max
cash_difference_currency
min_total_area
min_covered_area
property_condition
payment_mode_cash
payment_mode_swap

Nunca muestres valores internos como:

"new"
"used"
"any"
"true"
"false"
"null"
"0.00"

Traducí siempre esos conceptos
al lenguaje habitual de una inmobiliaria.


EQUIVALENCIAS CONCEPTUALES

payment.cash / payment_mode_cash:
"Pago con dinero"

payment.swap / payment_mode_swap:
"Acepta permuta"

cash_difference_max:
"Diferencia máxima en dinero"

cash_difference_currency:
"Moneda de la diferencia"

property_condition = new:
"Propiedad a estrenar"

property_condition = used:
"Propiedad con antigüedad"

property_condition = any:
"Sin preferencia respecto del estado de la propiedad"

min_total_area:
"Superficie total mínima"

min_covered_area:
"Superficie cubierta mínima"

min_bedrooms:
"Dormitorios mínimos"

min_bathrooms:
"Baños mínimos"

min_garages:
"Cocheras mínimas"


VOCABULARIO INMOBILIARIO

- No uses la expresión "propiedad usada".
- Para inmuebles nuevos usá "a estrenar".
- Para inmuebles que no son nuevos usá "con antigüedad".
- Cuando se aceptan ambos casos,
  hablá de "sin preferencia respecto del estado".
- Usá vocabulario habitual
  de una inmobiliaria argentina.
- Evitá lenguaje técnico de software.
- Evitá lenguaje robótico.
- Evitá frases exageradamente comerciales.


EJEMPLOS DE EVALUACIÓN

MAL:

"'payment.cash' está en false pero existe
'payment.cash_difference_max'."

BIEN:

"Se acepta permuta con diferencia en dinero.
La modalidad cargada es coherente."


MAL:

"'property_condition' está en 'new'."

BIEN:

"Indicás que buscás únicamente propiedades a estrenar.
El criterio es claro y no presenta contradicciones."


MAL:

"min_total_area está en 0.00."

BIEN:

"No definiste una superficie total mínima.
Si el cliente necesita un mínimo determinado,
cargarlo puede mejorar la precisión de los matches."


MAL:

"Deberías aceptar propiedades con antigüedad
para conseguir más coincidencias."

No recomiendes flexibilizar criterios
solamente para aumentar la cantidad de matches.

CANTIDAD DE RECOMENDACIONES

- No intentes completar el máximo disponible de sugerencias.

- En condiciones normales devolvé entre 0 y 3 sugerencias.

- Generá una cuarta o quinta sugerencia únicamente
  cuando exista otro problema de impacto alto o medio
  claramente independiente de los anteriores.

- No agregues sugerencias débiles sobre campos opcionales
  sólo para completar la lista.

- Si existen 2 o 3 acciones claramente prioritarias,
  detené las recomendaciones ahí.

- La ausencia de sugerencias adicionales NO significa
  que el análisis esté incompleto.

- Priorizá calidad de las recomendaciones sobre cantidad.

REGLAS PARA LAS SUGERENCIAS

Cada sugerencia debe tener:

field:
identificador técnico interno.
Puede contener el nombre real del campo
porque NO se mostrará directamente al usuario.

action:
categoría interna de la acción.

Usá solamente:

rewrite
clarify
fix
review
complete

title:
título breve y claro para el usuario.

Debe indicar concretamente
qué aspecto puede mejorar.

message:
explicación concreta de qué puede cambiar,
por qué puede ser útil
y qué impacto tendría sobre claridad o matching.

Nunca debe contener nombres técnicos
ni valores internos del sistema.

priority:
usá "high", "medium" o "low"
según el impacto real de la mejora.


BUENOS EJEMPLOS DE TÍTULOS

- "Mejorá el título de la búsqueda"
- "Aclará qué cantidad de ambientes acepta"
- "Revisá la modalidad de pago"
- "Definí el estado de la propiedad buscada"
- "Indicá una superficie mínima si existe requisito"
- "Acotá el rango de presupuesto si hay prioridad"
- "Sumá contexto a la descripción"

Las sugerencias deben permitir que el usuario
entienda inmediatamente qué puede mejorar
y decidir si corresponde modificar el formulario.


PUNTAJES

Devolvé puntajes de 0 a 100.

Usá esta lógica general:

title_score:
calidad, claridad y representatividad del título.

description_score:
claridad, utilidad y fidelidad de la descripción.

consistency_score:
coherencia real entre los criterios,
sin penalizar decisiones comerciales válidas
ni campos opcionales vacíos.

matchability_score:
capacidad de los criterios cargados
para producir coincidencias relevantes,
no simplemente numerosas.


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
