<?php

namespace App\Services\AI;

use PDO;
use Exception;

class DevelopmentQualityService
{
    private const VERSION = '1.0';

    private const VALID_STAGES = [
        'land',
        'prelaunch',
        'launch',
        'presale',
        'under_construction',
        'finished',
    ];

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    public static function analyze(
        int $developmentId
    ): array {
        if ($developmentId <= 0) {
            throw new Exception(
                'El ID del desarrollo no es válido.'
            );
        }

        $pdo = self::db();

        $development =
            self::getDevelopment(
                $pdo,
                $developmentId
            );

        if (!$development) {
            throw new Exception(
                'Desarrollo no encontrado.'
            );
        }

        $unitTypes =
            self::getUnitTypes(
                $pdo,
                $developmentId
            );

        $amenities =
            self::getAmenities(
                $pdo,
                $developmentId
            );

        $images =
            self::getImages(
                $pdo,
                $developmentId
            );

        $project =
            self::evaluateProject(
                $development
            );

        $location =
            self::evaluateLocation(
                $development
            );

        $commercial =
            self::evaluateCommercial(
                $development
            );

        $unitTypesQuality =
            self::evaluateUnitTypes(
                $unitTypes
            );

        $amenitiesQuality =
            self::evaluateAmenities(
                $amenities
            );

        $imagesQuality =
            self::evaluateImages(
                $images
            );

        return [
            'status' => 'completed',

            'score' => round(
                $project['score'] +
                    $location['score'] +
                    $commercial['score'] +
                    $unitTypesQuality['score'] +
                    $amenitiesQuality['score'] +
                    $imagesQuality['score'],
                2
            ),

            'sections' => [
                'project' => [
                    'score' =>
                        $project['score'],

                    'max_score' =>
                        10,
                ],

                'location' => [
                    'score' =>
                        $location['score'],

                    'max_score' =>
                        10,
                ],

                'commercial' => [
                    'score' =>
                        $commercial['score'],

                    'max_score' =>
                        15,
                ],

                'unit_types' => [
                    'score' =>
                        $unitTypesQuality['score'],

                    'max_score' =>
                        15,
                ],

                'amenities' => [
                    'score' =>
                        $amenitiesQuality['score'],

                    'max_score' =>
                        5,
                ],

                'images' => [
                    'score' =>
                        $imagesQuality['score'],

                    'max_score' =>
                        5,
                ],
            ],

            'suggestions' => array_merge(
                $project['suggestions'],
                $location['suggestions'],
                $commercial['suggestions'],
                $unitTypesQuality['suggestions'],
                $amenitiesQuality['suggestions'],
                $imagesQuality['suggestions']
            ),

            'version' =>
                self::VERSION,
        ];
    }

    private static function evaluateProject(
        array $development
    ): array {
        $score = 0.0;
        $suggestions = [];

        /*
         * 1. Etapa del proyecto.
         * Es uno de los datos estructurales
         * principales del desarrollo.
         */
        $stage =
            trim(
                (string)(
                    $development[
                        'development_stage'
                    ] ?? ''
                )
            );

        if (
            $stage !== '' &&
            in_array(
                $stage,
                self::VALID_STAGES,
                true
            )
        ) {
            $score += 4;
        } else {
            $suggestions[] = [
                'field' =>
                    'development_stage',

                'priority' =>
                    'high',

                'title' =>
                    'Definí la etapa del desarrollo.',

                'message' =>
                    'Indicá en qué etapa se encuentra el proyecto para que otros usuarios puedan evaluar correctamente su situación.',
            ];
        }

        /*
         * 2. Desarrolladora / constructora.
         *
         * No exigimos ambas.
         * Con identificar al menos uno de los
         * actores principales consideramos
         * suficientemente contextualizado
         * este aspecto.
         */
        $developerName =
            trim(
                (string)(
                    $development[
                        'developer_name'
                    ] ?? ''
                )
            );

        $constructionCompany =
            trim(
                (string)(
                    $development[
                        'construction_company'
                    ] ?? ''
                )
            );

        if (
            $developerName !== '' ||
            $constructionCompany !== ''
        ) {
            $score += 3;
        }

        /*
         * 3. Entrega estimada.
         *
         * No corresponde penalizar del mismo
         * modo a un proyecto terminado o a
         * un lote/tierra.
         */
        if (
            in_array(
                $stage,
                ['finished', 'land'],
                true
            )
        ) {
            $score += 3;
        } elseif (
            !empty(
                $development[
                    'delivery_date_estimated'
                ]
            )
        ) {
            $score += 3;
        } elseif ($stage !== '') {
            $score += 1;

            $suggestions[] = [
                'field' =>
                    'delivery_date_estimated',

                'priority' =>
                    'medium',

                'title' =>
                    'Indicá la entrega estimada si está definida.',

                'message' =>
                    'Si el proyecto ya tiene una fecha estimada de entrega, cargarla aporta contexto comercial importante.',
            ];
        }

        return [
            'score' =>
                min(10, $score),

            'suggestions' =>
                $suggestions,
        ];
    }

    private static function evaluateLocation(
        array $development
    ): array {
        $score = 0.0;
        $suggestions = [];

        /*
         * País + provincia.
         */
        if (
            !empty($development['country']) &&
            !empty($development['province'])
        ) {
            $score += 3;
        }

        /*
         * Ciudad.
         */
        if (
            !empty(
                $development['city']
            )
        ) {
            $score += 2;
        }

        /*
         * Zona.
         */
        if (
            !empty(
                $development['zone']
            )
        ) {
            $score += 1;
        }

        /*
         * Dirección/geolocalización.
         *
         * Para un desarrollo es especialmente
         * importante que exista una ubicación
         * concreta.
         */
        $hasCoordinates =
            self::number(
                $development['latitude']
                    ?? null
            ) !== null &&
            self::number(
                $development['longitude']
                    ?? null
            ) !== null;

        $hasResolvedAddress =
            !empty(
                $development[
                    'formatted_address'
                ]
            ) &&
            !empty(
                $development[
                    'place_id'
                ]
            );

        if (
            $hasCoordinates &&
            $hasResolvedAddress
        ) {
            $score += 4;
        } elseif (
            !empty(
                $development['address']
            ) ||
            !empty(
                $development[
                    'formatted_address'
                ]
            )
        ) {
            $score += 2;

            $suggestions[] = [
                'field' =>
                    'location',

                'priority' =>
                    'medium',

                'title' =>
                    'Completá la ubicación exacta.',

                'message' =>
                    'Seleccionar correctamente la dirección del desarrollo mejora su identificación y la precisión de las búsquedas.',
            ];
        } else {
            $suggestions[] = [
                'field' =>
                    'location',

                'priority' =>
                    'high',

                'title' =>
                    'Indicá la ubicación del desarrollo.',

                'message' =>
                    'La ubicación concreta es un dato central para publicar y encontrar correctamente el proyecto.',
            ];
        }

        return [
            'score' =>
                min(10, $score),

            'suggestions' =>
                $suggestions,
        ];
    }

    private static function evaluateCommercial(
        array $development
    ): array {
        $score = 0.0;
        $suggestions = [];

        $priceFrom =
            self::number(
                $development[
                    'price_from'
                ] ?? null
            );

        $priceTo =
            self::number(
                $development[
                    'price_to'
                ] ?? null
            );

        $currency =
            trim(
                (string)(
                    $development[
                        'currency'
                    ] ?? ''
                )
            );

        /*
         * 1. Precio desde + moneda.
         */
        if (
            $priceFrom !== null &&
            $priceFrom > 0 &&
            $currency !== ''
        ) {
            $score += 7;
        } else {
            $suggestions[] = [
                'field' =>
                    'price_from',

                'priority' =>
                    'high',

                'title' =>
                    'Indicá un valor de referencia.',

                'message' =>
                    'El precio desde es uno de los datos comerciales más útiles para evaluar rápidamente el desarrollo.',
            ];
        }

        /*
         * 2. Precio hasta.
         *
         * No es obligatorio tener un máximo.
         * Un proyecto comercializado "desde"
         * puede estar perfectamente definido.
         */
        if ($priceTo === null) {
            $score += 2;
        } elseif (
            $priceTo > 0 &&
            (
                $priceFrom === null ||
                $priceTo >= $priceFrom
            )
        ) {
            $score += 2;
        } else {
            $suggestions[] = [
                'field' =>
                    'price_to',

                'priority' =>
                    'high',

                'title' =>
                    'Revisá el rango de precios.',

                'message' =>
                    'El valor máximo debe ser coherente con el precio desde del desarrollo.',
            ];
        }

        /*
         * 3. Unidades totales/disponibles.
         */
        $totalUnits =
            self::number(
                $development[
                    'total_units'
                ] ?? null
            );

        $availableUnits =
            self::number(
                $development[
                    'available_units'
                ] ?? null
            );

        if (
            $totalUnits !== null &&
            $totalUnits > 0 &&
            $availableUnits !== null &&
            $availableUnits >= 0 &&
            $availableUnits <= $totalUnits
        ) {
            $score += 3;
        } elseif (
            $totalUnits !== null &&
            $totalUnits > 0
        ) {
            $score += 2;
        } elseif (
            $availableUnits !== null &&
            $availableUnits >= 0
        ) {
            $score += 1;
        }

        /*
         * 4. Recursos comerciales.
         *
         * No exigimos WhatsApp + brochure + video.
         * Premiamos que exista material de apoyo.
         */
        $commercialResources = 0;

        foreach (
            [
                'whatsapp_url',
                'brochure_url',
                'video_url',
            ]
            as $field
        ) {
            if (
                trim(
                    (string)(
                        $development[$field]
                        ?? ''
                    )
                ) !== ''
            ) {
                $commercialResources++;
            }
        }

        if ($commercialResources >= 2) {
            $score += 3;
        } elseif ($commercialResources === 1) {
            $score += 2;
        }

        return [
            'score' =>
                min(15, $score),

            'suggestions' =>
                $suggestions,
        ];
    }

    private static function evaluateUnitTypes(
        array $unitTypes
    ): array {
        $suggestions = [];

        if (!$unitTypes) {
            return [
                'score' => 0,

                'suggestions' => [
                    [
                        'field' =>
                            'unit_types',

                        'priority' =>
                            'high',

                        'title' =>
                            'Cargá al menos una tipología.',

                        'message' =>
                            'Las tipologías permiten entender qué unidades ofrece el desarrollo y son fundamentales para generar coincidencias relevantes.',
                    ],
                ],
            ];
        }

        /*
         * Tener al menos una tipología
         * ya representa una parte importante
         * del bloque.
         */
        $score = 6.0;

        $totalCompleteness = 0.0;

        foreach (
            $unitTypes
            as $unitType
        ) {
            $signals = 0;

            /*
             * 1. Tipo de unidad.
             */
            if (
                trim(
                    (string)(
                        $unitType[
                            'unit_type'
                        ] ?? ''
                    )
                ) !== ''
            ) {
                $signals++;
            }

            /*
             * 2. Superficie.
             */
            $hasArea =
                self::positive(
                    $unitType[
                        'area_from'
                    ] ?? null
                ) ||
                self::positive(
                    $unitType[
                        'area_to'
                    ] ?? null
                );

            if ($hasArea) {
                $signals++;
            }

            /*
             * 3. Precio.
             */
            $hasPrice =
                (
                    self::positive(
                        $unitType[
                            'price_from'
                        ] ?? null
                    ) ||
                    self::positive(
                        $unitType[
                            'price_to'
                        ] ?? null
                    )
                ) &&
                trim(
                    (string)(
                        $unitType[
                            'currency'
                        ] ?? ''
                    )
                ) !== '';

            if ($hasPrice) {
                $signals++;
            }

            /*
             * 4. Disponibilidad.
             */
            if (
                self::nonNegative(
                    $unitType[
                        'available_units'
                    ] ?? null
                )
            ) {
                $signals++;
            }

            /*
             * 5. Configuración.
             *
             * Para departamentos, casas,
             * oficinas, etc., ambientes,
             * dormitorios, baños o cocheras
             * aportan definición.
             *
             * Para tipologías donde esos datos
             * no son necesariamente relevantes,
             * la superficie puede cumplir esta
             * función descriptiva.
             */
            $type =
                trim(
                    (string)(
                        $unitType[
                            'unit_type'
                        ] ?? ''
                    )
                );

            $hasConfiguration =
                self::positive(
                    $unitType['rooms']
                        ?? null
                ) ||
                self::positive(
                    $unitType['bedrooms']
                        ?? null
                ) ||
                self::positive(
                    $unitType['bathrooms']
                        ?? null
                ) ||
                self::positive(
                    $unitType['garages']
                        ?? null
                );

            if (
                in_array(
                    $type,
                    [
                        'land',
                        'warehouse',
                        'garage',
                        'other',
                    ],
                    true
                )
            ) {
                $hasConfiguration =
                    $hasConfiguration ||
                    $hasArea;
            }

            if ($hasConfiguration) {
                $signals++;
            }

            /*
             * Cada tipología puede tener
             * 5 señales de calidad.
             */
            $totalCompleteness +=
                $signals / 5;
        }

        /*
         * Promedio de completitud:
         * hasta 9 puntos adicionales.
         *
         * Esto permite que UNA sola tipología
         * bien cargada pueda obtener 15/15.
         */
        $averageCompleteness =
            $totalCompleteness /
            count($unitTypes);

        $score +=
            round(
                9 * $averageCompleteness,
                2
            );

        if ($averageCompleteness < 0.6) {
            $suggestions[] = [
                'field' =>
                    'unit_types',

                'priority' =>
                    'medium',

                'title' =>
                    'Completá mejor las tipologías.',

                'message' =>
                    'Sumá los datos que realmente correspondan, como superficies, valores, disponibilidad o distribución, para que las unidades puedan compararse con mayor precisión.',
            ];
        }

        return [
            'score' =>
                min(
                    15,
                    round($score, 2)
                ),

            'suggestions' =>
                $suggestions,
        ];
    }

    private static function evaluateAmenities(
        array $amenities
    ): array {
        if ($amenities) {
            return [
                'score' =>
                    5,

                'suggestions' =>
                    [],
            ];
        }

        return [
            'score' =>
                0,

            'suggestions' => [
                [
                    'field' =>
                        'amenities',

                    'priority' =>
                        'medium',

                    'title' =>
                        'Indicá los amenities del desarrollo.',

                    'message' =>
                        'Cargar los servicios y espacios comunes relevantes mejora la descripción y la compatibilidad con búsquedas.',
                ],
            ],
        ];
    }

    private static function evaluateImages(
        array $images
    ): array {
        $count =
            count($images);

        if ($count <= 0) {
            return [
                'score' =>
                    0,

                'suggestions' => [
                    [
                        'field' =>
                            'images',

                        'priority' =>
                            'high',

                        'title' =>
                            'Agregá imágenes del desarrollo.',

                        'message' =>
                            'Las imágenes son fundamentales para presentar correctamente el proyecto y evaluar su calidad comercial.',
                    ],
                ],
            ];
        }

        if ($count === 1) {
            return [
                'score' =>
                    2,

                'suggestions' => [
                    [
                        'field' =>
                            'images',

                        'priority' =>
                            'medium',

                        'title' =>
                            'Sumá más imágenes del desarrollo.',

                        'message' =>
                            'Una sola imagen aporta poca información visual. Si tenés más material, agregarlo mejora la presentación del proyecto.',
                    ],
                ],
            ];
        }

        if ($count === 2) {
            return [
                'score' =>
                    3,

                'suggestions' =>
                    [],
            ];
        }

        if ($count === 3) {
            return [
                'score' =>
                    4,

                'suggestions' =>
                    [],
            ];
        }

        return [
            'score' =>
                5,

            'suggestions' =>
                [],
        ];
    }

    private static function getDevelopment(
        PDO $pdo,
        int $developmentId
    ): ?array {
        $st = $pdo->prepare("
            SELECT *
            FROM developments
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' =>
                $developmentId,
        ]);

        $row =
            $st->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private static function getUnitTypes(
        PDO $pdo,
        int $developmentId
    ): array {
        $st = $pdo->prepare("
            SELECT *
            FROM development_unit_types
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");

        $st->execute([
            'development_id' =>
                $developmentId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function getAmenities(
        PDO $pdo,
        int $developmentId
    ): array {
        $st = $pdo->prepare("
            SELECT amenity_code
            FROM development_amenities
            WHERE development_id = :development_id
            ORDER BY id ASC
        ");

        $st->execute([
            'development_id' =>
                $developmentId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];
    }

    private static function getImages(
        PDO $pdo,
        int $developmentId
    ): array {
        $st = $pdo->prepare("
            SELECT id, is_cover, sort_order
            FROM development_images
            WHERE development_id = :development_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, id ASC
        ");

        $st->execute([
            'development_id' =>
                $developmentId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function number(
        mixed $value
    ): ?float {
        if (
            $value === null ||
            $value === '' ||
            !is_numeric($value)
        ) {
            return null;
        }

        return (float)$value;
    }

    private static function positive(
        mixed $value
    ): bool {
        $value =
            self::number($value);

        return
            $value !== null &&
            $value > 0;
    }

    private static function nonNegative(
        mixed $value
    ): bool {
        $value =
            self::number($value);

        return
            $value !== null &&
            $value >= 0;
    }
}