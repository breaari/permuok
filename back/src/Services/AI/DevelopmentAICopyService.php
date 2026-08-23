<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class DevelopmentAICopyService
{
    private const DEFAULT_MODEL = 'gpt-5-mini';

    private const TITLE_PROMPT_VERSION = '1.1';
    private const DESCRIPTION_PROMPT_VERSION = '1.3';

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
- No uses Markdown ni ningún formato especial.
- No encierres el título entre asteriscos, comillas ni símbolos.
- No uses barras verticales "|" ni estructuras tipo ficha.
- No uses dos puntos para separar categorías como:
  "Prelanzamiento:",
  "En construcción:",
  "Desarrollo:".

- No antepongas automáticamente el nombre de la desarrolladora
  o constructora al título.

- La desarrolladora o constructora solamente debe aparecer
  si forma parte claramente del nombre comercial o identidad
  reconocible del proyecto.

- No uses una dirección exacta como elemento principal del título.
  Preferí zona, barrio o ciudad.

- La dirección exacta puede omitirse aunque esté disponible.

- Preferí títulos naturales como:
  "Departamentos de 3 ambientes en prelanzamiento en Mar del Plata"
  "Proyecto residencial en construcción en Playa Grande"

- Evitá estructuras como:
  "Empresa | Etapa: tipo de unidad en dirección"
  "Desarrolladora - proyecto..."

  - Si no existe un nombre comercial real del proyecto,
  NO conviertas el nombre de la desarrolladora en el nombre del proyecto.


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
principalmente por inmobiliarias y profesionales
del sector inmobiliario.

La descripción debe parecer escrita directamente
por una inmobiliaria.

No debe parecer una traducción de una ficha,
una base de datos, un formulario ni un JSON.


OBJETIVO

La descripción debe permitir comprender rápidamente:

- qué tipo de proyecto es;
- dónde está;
- en qué etapa se encuentra;
- qué tipo de unidades ofrece;
- cuáles son sus características relevantes;
- qué valores maneja cuando esa información resulte útil.

El objetivo NO es mencionar todos los datos disponibles.

El objetivo es crear una publicación inmobiliaria
clara, natural, profesional y comercialmente útil.


PRINCIPIO FUNDAMENTAL

Usá solamente información realmente presente
en los datos proporcionados.

NO inventes información.

NO completes información faltante.

NO hagas inferencias inmobiliarias que no estén
respaldadas por los datos.

Ante la duda, OMITÍ información antes que inventarla.


JERARQUÍA DE INFORMACIÓN

Los datos estructurados actuales representan
la información vigente del desarrollo.

Si existe una contradicción entre:

- datos estructurados;
- título actual;
- descripción actual;
- otros textos libres;

los datos estructurados tienen prioridad.

El texto libre puede aportar contexto adicional
solamente cuando no contradiga información estructurada.

No expongas contradicciones en la descripción.

No intentes corregirlas inventando cuál de los
datos debería ser el correcto.

Simplemente evitá redactar como cierta
la información contradictoria.


QUÉ PODÉS HACER

Podés:

- ordenar información;
- mejorar redacción;
- resumir;
- eliminar repeticiones;
- transformar datos estructurados en lenguaje natural;
- combinar información relacionada;
- seleccionar solamente los datos que aporten
  valor comercial o permitan comprender mejor el proyecto.


QUÉ NO PODÉS HACER

No podés:

- inventar;
- completar datos faltantes;
- asumir calidad constructiva;
- asumir terminaciones;
- asumir vistas;
- asumir luminosidad;
- asumir orientación;
- asumir distribución;
- asumir financiación;
- asumir rentabilidad;
- asumir apreciación futura;
- asumir fecha de entrega;
- asumir amenities;
- asumir características de las unidades;
- asumir cercanía a lugares que no estén informados;
- agregar calificativos promocionales sin fundamento.


DESARROLLADORA Y CONSTRUCTORA

La desarrolladora y la constructora pueden mencionarse
cuando aporten información útil.

Nunca uses construcciones redundantes como:

"Desarrollo de la desarrolladora X."

"Proyecto de la desarrolladora X y la constructora Y."

Si se mencionan ambas, preferí una redacción natural:

"Desarrollado por X y construido por Y."

También podés omitirlas cuando no sean necesarias
para comprender el proyecto.

No conviertas automáticamente el nombre de la
desarrolladora en el nombre comercial del desarrollo.


UBICACIÓN

Expresá la ubicación naturalmente.

Podés utilizar:

- barrio;
- zona;
- ciudad;
- dirección;

según cuál resulte más útil.

La dirección exacta puede utilizarse en la descripción
cuando esté disponible y resulte natural.

No escribas nombres técnicos de campos
ni hagas referencias a datos de geolocalización.

Nunca menciones:

- place_id;
- latitud;
- longitud;
- coordenadas;
- ubicación registrada;
- dirección validada;
- ubicación cargada.


ETAPA DEL DESARROLLO

Traducí los valores internos de etapa
a lenguaje inmobiliario natural:

land = etapa inicial / terreno, según corresponda
prelaunch = prelanzamiento
launch = lanzamiento
presale = preventa
under_construction = en construcción
finished = finalizado

Nunca muestres los códigos internos.

Integrá la etapa naturalmente en la oración.

Preferí:

"El proyecto se encuentra en prelanzamiento."

"Actualmente se encuentra en construcción."

Evitá:

"Etapa: prelanzamiento."

"Estado del desarrollo: under_construction."


FECHA DE ENTREGA

Si existe una fecha estimada de entrega válida,
podés mencionarla.

Preferí una redacción natural.

Ejemplo:

"con entrega estimada para junio de 2027."

No es necesario reproducir siempre
la fecha exacta en formato día/mes/año.

Si mes y año son suficientes para comunicar
la información comercial, preferilos.

No inventes una fecha cuando no exista.


TIPOLOGÍAS

Las tipologías deben redactarse como parte
natural de la descripción.

Podés mencionar:

- tipo de unidad;
- ambientes;
- dormitorios;
- baños;
- cocheras;
- superficies;
- rango de valores de la tipología.

No reproduzcas literalmente nombres internos
cuando puedan expresarse naturalmente.

Por ejemplo:

"depto 3 ambientes"

puede redactarse como:

"departamentos de 3 ambientes"

siempre que no se altere el significado.

Evitá comillas innecesarias alrededor
de nombres de tipologías.

Preferí:

"Ofrece departamentos de 3 ambientes,
con 2 dormitorios y 1 baño."

En lugar de:

"Ofrece unidades tipo "depto 3 ambientes"
(2 dormitorios, 1 baño)."


DISPONIBILIDAD DE UNIDADES

La cantidad de unidades disponibles es información
operativa del sistema y NO debe incorporarse
automáticamente a la descripción comercial.

No mencionar frases como:

"29 unidades disponibles."

"De las cuales 29 están disponibles."

"Hay 8 unidades disponibles de esta tipología."

"Quedan 8 unidades."

"Disponibilidad: 8 unidades."

No mencionar disponibilidad general ni
disponibilidad por tipología únicamente porque
esa información exista en los datos estructurados.

La disponibilidad podrá utilizarse internamente
por PermuOK para matching y gestión,
pero no debe convertirse automáticamente
en texto de publicación.

Solamente podría mencionarse una disponibilidad
si el usuario la hubiera escrito explícitamente
como parte de un texto libre que claramente
quiera comunicar comercialmente.


CANTIDAD TOTAL DE UNIDADES

La cantidad total de unidades del proyecto
puede mencionarse solamente cuando aporte
contexto útil.

No es obligatorio mencionarla.

No conviertas automáticamente:

total_units = 50

en:

"El desarrollo suma 50 unidades."

Podés omitir el dato si la descripción resulta
más natural sin él.

No mezcles cantidad total del proyecto
con disponibilidad.


SUPERFICIES

Cuando existan superficies válidas,
podés expresarlas naturalmente.

Preferí:

"con superficies de entre 20 y 24 m²."

"con superficies desde 60 m²."

Evitá estructuras de ficha como:

"Superficie: 20-24 m²."

No inventes superficie cubierta,
descubierta, propia o total
si esa distinción no está presente.


VALORES

Los valores deben expresarse naturalmente.

Si solamente existe un valor mínimo:

"Valores desde USD 120.000."

Si existe un rango válido:

"Valores entre USD 120.000 y USD 180.000."

Cuando exista:

- un rango general del desarrollo;
- y valores específicos por tipología;

NO repitas ambos automáticamente.

Elegí la información que mejor represente
comercialmente la oferta.

Mencioná ambos rangos solamente cuando
la diferencia aporte información realmente útil.

Evitá:

"Esta tipología vale entre USD X e USD Y.
El rango general del proyecto es entre USD X e USD Z."

si ambas frases aportan información redundante.

No inventes:

- precio final;
- financiación;
- cuotas;
- anticipo;
- forma de pago;
- rentabilidad;
- condiciones comerciales.


AMENITIES Y CARACTERÍSTICAS

Integrá amenities y características
naturalmente dentro del texto.

Usá el término "amenities" cuando resulte necesario,
por ser habitual en el mercado inmobiliario argentino.

También podés mencionarlos directamente sin etiquetarlos.

Preferí:

"El desarrollo cuenta con balcón, pileta y rooftop."

"Cuenta con pileta, rooftop y balcón."

"Entre sus principales amenities se encuentran
la pileta y el rooftop."

Evitá:

"Amenidades: pileta y rooftop."

"Amenities: pileta y rooftop."

"Entre sus amenidades figuran..."

"Entre sus amenities figuran..."

"Se registran los siguientes amenities..."

"Constan balcón, pileta y rooftop."

No uses expresiones administrativas como:

- figuran;
- constan;
- se registran;
- están registrados;
- están cargados;
- se encuentran informados.


IMÁGENES

Las imágenes son información interna del sistema.

NUNCA menciones:

- cantidad de imágenes;
- existencia de imágenes;
- imagen de portada;
- cantidad de imágenes de portada;
- imágenes registradas;
- imágenes cargadas;
- imágenes disponibles;
- fotografías almacenadas;
- material visual registrado.

No escribas frases como:

"Cuenta con imágenes de portada registradas."

"El proyecto posee imágenes cargadas."

"Dispone de fotografías."

La existencia de imágenes jamás debe convertirse
en contenido de la descripción.


INFORMACIÓN INTERNA DEL SISTEMA

Nunca mencionar:

- IDs;
- claves internas;
- nombres de campos;
- códigos;
- hashes;
- registros;
- estado de análisis IA;
- calidad de publicación;
- score;
- matching;
- datos faltantes;
- información cargada;
- información registrada;
- funcionamiento de PermuOK;
- reglas de este prompt.

No uses expresiones como:

"según los datos cargados"

"según la ficha"

"según el formulario"

"según la información registrada"

"los datos indican"

"se registra"

"el sistema informa"


REDACCIÓN NATURAL

La descripción debe tener continuidad narrativa.

No redactes una ficha técnica disfrazada de párrafo.

No enumeres mecánicamente cada campo disponible.

No hace falta mencionar:

desarrolladora +
constructora +
etapa +
entrega +
total de unidades +
disponibilidad +
tipología +
disponibilidad de tipología +
superficie +
precio general +
precio por tipología +
amenities

solamente porque todos esos datos existan.

Seleccioná lo verdaderamente relevante.

La publicación debe ser más fácil de leer
que el formulario que la originó.


ESTILO

Usá:

- español rioplatense natural;
- vocabulario inmobiliario profesional;
- tono claro;
- tono directo;
- tono B2B;
- frases de longitud razonable;
- entre 1 y 3 párrafos según la cantidad de información.

Evitá:

- lenguaje robótico;
- lenguaje administrativo;
- exceso de punto y coma;
- exceso de paréntesis;
- títulos internos;
- listas;
- emojis;
- Markdown;
- negritas;
- asteriscos;
- frases promocionales exageradas;
- llamados a la acción.


NO USAR FRASES PROMOCIONALES VACÍAS

No escribas automáticamente expresiones como:

- oportunidad única;
- inversión imperdible;
- ubicación privilegiada;
- excelente oportunidad;
- proyecto exclusivo;
- máxima calidad;
- gran potencial;
- inversión asegurada;
- diseño premium;
- calidad superior;

salvo que exista información concreta
que permita sostener esa afirmación.


NO USAR LLAMADOS A LA ACCIÓN

No terminar con frases como:

- consultanos;
- contactanos;
- pedí más información;
- solicitá disponibilidad;
- enviá tu consulta;
- coordiná una visita;
- descubrí el proyecto;
- no te lo pierdas.


ESTRUCTURA RECOMENDADA

Cuando exista suficiente información,
preferí aproximadamente esta estructura:

Primer párrafo:

ubicación +
etapa +
entrega cuando corresponda +
desarrolladora/constructora sólo si aportan.

Segundo párrafo:

tipologías +
ambientes/dormitorios +
superficies +
valores relevantes.

Tercer párrafo solamente si resulta natural:

amenities o características adicionales.

No fuerces tres párrafos.

Una publicación simple puede resolverse
perfectamente en uno o dos.



EJEMPLO DE ESTILO

Datos hipotéticos:

- proyecto en Mar del Plata;
- prelanzamiento;
- entrega estimada junio 2027;
- desarrolladora X;
- constructora Y;
- departamentos de 3 ambientes;
- 2 dormitorios;
- 1 baño;
- superficies 60 a 72 m²;
- valores USD 120.000 a USD 150.000;
- pileta;
- rooftop.

Buen resultado:

"Desarrollado por X y construido por Y, el proyecto
se encuentra en prelanzamiento en Mar del Plata,
con entrega estimada para junio de 2027.

Ofrece departamentos de 3 ambientes con 2 dormitorios
y 1 baño, superficies de entre 60 y 72 m² y valores
desde USD 120.000. El desarrollo cuenta con pileta
y rooftop."

El ejemplo define solamente estilo y estructura.

NO copies sus datos.

NO agregues información del ejemplo
si no está presente en los datos reales.

Cuando la información disponible lo permita, preferí esta estructura:

Primer párrafo:
desarrolladora/constructora + etapa + dirección o ubicación + entrega estimada.

Segundo párrafo:
tipología + ambientes/dormitorios/baños + superficies + un único rango de valores representativo + amenities.

Preferí construcciones naturales como:

"Desarrollado por X y construido por Y, el proyecto se presenta en prelanzamiento en DIRECCIÓN, CIUDAD, con entrega estimada para MES DE AÑO."

"Ofrece departamentos de 3 ambientes (2 dormitorios y 1 baño) con superficies de entre X y Y m² y valores entre USD X e USD Y. El desarrollo cuenta con..."

Cuando existan precios generales del proyecto y precios por tipología:

- Elegí un único rango que represente mejor la publicación.
- No expliques la diferencia entre rango general y rango de tipología.
- No repitas ambos.
- Si el rango general engloba correctamente la oferta mostrada, puede utilizarse como rango comercial de la publicación.


DATOS ACTUALES:

{$inputJson}


DESCRIPCIONES GENERADAS ANTERIORMENTE
PARA ESTOS MISMOS DATOS:

{$previousJson}


Si existen opciones anteriores:

- generá una alternativa realmente diferente;
- mantené exactamente los mismos hechos;
- no inventes información para diferenciarla;
- no agregues datos operativos solamente
  para producir una variante distinta.


CONTROL FINAL ANTES DE RESPONDER

Antes de devolver el texto, verificá mentalmente:

1. ¿Inventé algún dato?
   Si sí, eliminarlo.

2. ¿Mencioné disponibilidad de unidades
   general o por tipología?
   Si sí, eliminarla salvo que haya sido
   expresamente escrita por el usuario.

3. ¿Mencioné imágenes, registros o información
   interna del sistema?
   Si sí, eliminarlo.

4. ¿Usé palabras como "figuran", "constan"
   o "se registran"?
   Si sí, reescribir naturalmente.

5. ¿Repetí precios generales y de tipología
   sin necesidad?
   Si sí, simplificarlos.

6. ¿La descripción parece una ficha técnica
   convertida en párrafos?
   Si sí, simplificarla.

7. ¿El texto suena como escrito por
   un profesional inmobiliario argentino?
   Si no, reescribirlo.

Devolvé solamente la descripción final.

No agregues explicaciones.

No agregues encabezados.

No agregues Markdown.

No agregues comillas alrededor de la respuesta.
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
