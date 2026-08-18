<?php

namespace App\Services\AI;

use PDO;
use Exception;
use JsonException;

class PublicationQualityService
{
    private const ALGORITHM_VERSION = '1.0';

    /*
     * Puntajes máximos.
     */
    private const BASIC_MAX = 25.0;
    private const LOCATION_MAX = 20.0;
    private const FEATURES_MAX = 20.0;
    private const MEDIA_MAX = 15.0;
    private const MATCHABILITY_MAX = 20.0;
    private const STRUCTURE_V2_MAX = 10.0;

    private static function db(): PDO
    {
        require_once __DIR__ . '/../../../db.php';

        return pdo();
    }

    /**
     * Analiza una propiedad y guarda su calidad actual.
     */
    public static function analyzeProperty(
        int $propertyId
    ): array {
        if ($propertyId <= 0) {
            throw new Exception(
                'El ID de la propiedad no es válido.'
            );
        }

        $pdo = self::db();

        $property =
            self::getProperty(
                $pdo,
                $propertyId
            );

        if (!$property) {
            throw new Exception(
                'Propiedad no encontrada.'
            );
        }

        $images =
            self::getImages(
                $pdo,
                $propertyId
            );

        $amenities =
            self::getAmenities(
                $pdo,
                $propertyId
            );

        $requirements =
            self::getRequirements(
                $pdo,
                $propertyId
            );

        $requirementPropertyTypes = [];
        $requirementLocations = [];

        if (
            $requirements !== null &&
            !empty($requirements['id'])
        ) {
            $requirementId =
                (int)$requirements['id'];

            $requirementPropertyTypes =
                self::getRequirementPropertyTypes(
                    $pdo,
                    $requirementId
                );

            $requirementLocations =
                self::getRequirementLocations(
                    $pdo,
                    $requirementId
                );
        }

        /*
         * Cada sección devuelve:
         *
         * score
         * issues
         * suggestions
         */
        $basic =
            self::evaluateBasicData(
                $property
            );
        $structureV2 =
            self::evaluateStructureV2(
                $property
            );
        $location =
            self::evaluateLocation(
                $property
            );

        $features =
            self::evaluateFeatures(
                $property
            );

        $media =
            self::evaluateMedia(
                $images
            );

        $matchability =
            self::evaluateMatchability(
                $property,
                $amenities,
                $requirements,
                $requirementPropertyTypes,
                $requirementLocations
            );

        $score = round(
            $basic['score'] +
                $location['score'] +
                $features['score'] +
                $media['score'] +
                $matchability['score'],
            2
        );

        /*
         * Protección por cualquier eventual error
         * de redondeo futuro.
         */
        $score = max(
            0.0,
            min(100.0, $score)
        );

        $qualityLevel =
            self::resolveQualityLevel(
                $score
            );

        /*
 * Algunos faltantes críticos impiden considerar
 * una publicación como "good" o "excellent",
 * aunque el resto de la ficha esté muy completa.
 */
        $qualityLevel =
            self::applyQualityLevelCaps(
                $qualityLevel,
                $property,
                $images
            );

        $issues = array_merge(
            $basic['issues'],
            $location['issues'],
            $features['issues'],
            $media['issues'],
            $matchability['issues']
        );

        $suggestions = array_merge(
            $basic['suggestions'],
            $location['suggestions'],
            $features['suggestions'],
            $media['suggestions'],
            $matchability['suggestions']
        );

        $result = [
            'entity_type' =>
            'property',

            'entity_id' =>
            $propertyId,

            'score' =>
            $score,

            'quality_level' =>
            $qualityLevel,

            'sections' => [
                'basic' => [
                    'score' =>
                    $basic['score'],

                    'max_score' =>
                    self::BASIC_MAX,
                ],
                'structure_v2' => [
                    'score' =>
                    $structureV2['score'],

                    'max_score' =>
                    self::STRUCTURE_V2_MAX,
                ],
                'location' => [
                    'score' =>
                    $location['score'],

                    'max_score' =>
                    self::LOCATION_MAX,
                ],

                'features' => [
                    'score' =>
                    $features['score'],

                    'max_score' =>
                    self::FEATURES_MAX,
                ],

                'media' => [
                    'score' =>
                    $media['score'],

                    'max_score' =>
                    self::MEDIA_MAX,
                ],

                'matchability' => [
                    'score' =>
                    $matchability['score'],

                    'max_score' =>
                    self::MATCHABILITY_MAX,
                ],
            ],

            'issues' =>
            $issues,

            'suggestions' =>
            $suggestions,

            'algorithm_version' =>
            self::ALGORITHM_VERSION,
        ];

        self::saveResult(
            $pdo,
            $result
        );

        return $result;
    }

    private static function applyQualityLevelCaps(
        string $qualityLevel,
        array $property,
        array $images
    ): string {
        /*
     * Sin imágenes una publicación no puede
     * considerarse buena o excelente.
     */
        if (count($images) === 0) {
            if (
                in_array(
                    $qualityLevel,
                    ['good', 'excellent'],
                    true
                )
            ) {
                return 'basic';
            }
        }

        /*
     * Sin una descripción mínimamente desarrollada,
     * tampoco permitimos nivel excellent.
     */
        $description = trim(
            (string)(
                $property['description'] ?? ''
            )
        );

        $descriptionLength =
            self::textLength(
                $description
            );

        if (
            $descriptionLength < 150 &&
            $qualityLevel === 'excellent'
        ) {
            return 'good';
        }

        return $qualityLevel;
    }
    private static function evaluateStructureV2(
        array $property
    ): array {
        $score = 0.0;
        $issues = [];
        $suggestions = [];

        /*
     * Esta evaluación NO juzga título ni descripción.
     *
     * Su único objetivo es determinar qué tan completa
     * está la ficha estructurada de la propiedad.
     */

        /*
     * Tipo de propiedad: 3 puntos.
     */
        if (
            self::hasValue(
                $property['property_type'] ?? null
            )
        ) {
            $score += 3.0;
        } else {
            self::addIssue(
                $issues,
                'property_type_missing',
                'property_type',
                'high',
                'Falta indicar el tipo de propiedad.'
            );
        }

        /*
     * Precio + moneda: 4 puntos.
     */
        $price =
            self::nullableFloat(
                $property['price'] ?? null
            );

        $currency = trim(
            (string)(
                $property['currency'] ?? ''
            )
        );

        if (
            $price !== null &&
            $price > 0 &&
            $currency !== ''
        ) {
            $score += 4.0;
        } else {
            self::addIssue(
                $issues,
                'price_incomplete',
                'price',
                'high',
                'El precio o la moneda no están correctamente informados.'
            );
        }

        /*
     * Información estructural complementaria:
     * superficie total, superficie cubierta y antigüedad.
     *
     * En conjunto aportan 3 puntos.
     */
        $additionalFields = [
            'total_area',
            'covered_area',
            'antiquity',
        ];

        $completed = 0;

        foreach (
            $additionalFields
            as $field
        ) {
            if (
                self::positiveNumber(
                    $property[$field] ?? null
                )
            ) {
                $completed++;
            }
        }

        $score +=
            ($completed / count($additionalFields))
            * 3.0;

        return [
            'score' =>
            round(
                min(
                    self::STRUCTURE_V2_MAX,
                    $score
                ),
                2
            ),

            'issues' =>
            $issues,

            'suggestions' =>
            $suggestions,
        ];
    }
    private static function evaluateBasicData(
        array $property
    ): array {
        $score = 0.0;
        $issues = [];
        $suggestions = [];

        /*
         * Título: 5 puntos.
         */
        $title = trim(
            (string)($property['title'] ?? '')
        );

        $titleLength =
            self::textLength($title);

        if ($titleLength >= 25) {
            $score += 5;
        } elseif ($titleLength >= 12) {
            $score += 3;

            self::addSuggestion(
                $suggestions,
                'title',
                'medium',
                'El título podría ser más descriptivo.',
                'Incluí tipo de propiedad, característica diferencial y ubicación.'
            );
        } elseif ($titleLength > 0) {
            $score += 1;

            self::addIssue(
                $issues,
                'title_too_short',
                'title',
                'medium',
                'El título es demasiado breve.'
            );

            self::addSuggestion(
                $suggestions,
                'title',
                'high',
                'Mejorá el título de la publicación.',
                'Un título más específico ayuda a entender rápidamente qué hace atractiva a la propiedad.'
            );
        } else {
            self::addIssue(
                $issues,
                'title_missing',
                'title',
                'high',
                'La publicación no tiene título.'
            );
        }

        /*
         * Descripción: 8 puntos.
         */
        $description = trim(
            (string)(
                $property['description'] ?? ''
            )
        );

        $descriptionLength =
            self::textLength(
                $description
            );

        if ($descriptionLength >= 300) {
            $score += 8;
        } elseif ($descriptionLength >= 150) {
            $score += 6;

            self::addSuggestion(
                $suggestions,
                'description',
                'low',
                'La descripción puede desarrollarse un poco más.',
                'Podés destacar distribución, estado, luminosidad, entorno y características diferenciales.'
            );
        } elseif ($descriptionLength >= 60) {
            $score += 3;

            self::addIssue(
                $issues,
                'description_short',
                'description',
                'medium',
                'La descripción contiene poca información.'
            );

            self::addSuggestion(
                $suggestions,
                'description',
                'high',
                'Ampliá la descripción.',
                'Una descripción completa permite interpretar mejor la propiedad y también le dará más contexto al optimizador IA.'
            );
        } elseif ($descriptionLength > 0) {
            $score += 1;

            self::addIssue(
                $issues,
                'description_too_short',
                'description',
                'high',
                'La descripción es demasiado breve.'
            );
        } else {
            self::addIssue(
                $issues,
                'description_missing',
                'description',
                'high',
                'La publicación no tiene descripción.'
            );
        }

        /*
         * Tipo de propiedad: 4 puntos.
         */
        if (
            self::hasValue(
                $property['property_type'] ?? null
            )
        ) {
            $score += 4;
        } else {
            self::addIssue(
                $issues,
                'property_type_missing',
                'property_type',
                'high',
                'Falta indicar el tipo de propiedad.'
            );
        }

        /*
         * Precio: 5 puntos.
         */
        $price =
            self::nullableFloat(
                $property['price'] ?? null
            );

        $currency = trim(
            (string)(
                $property['currency'] ?? ''
            )
        );

        if (
            $price !== null &&
            $price > 0 &&
            $currency !== ''
        ) {
            $score += 5;
        } else {
            self::addIssue(
                $issues,
                'price_incomplete',
                'price',
                'high',
                'El precio o la moneda no están correctamente informados.'
            );
        }

        /*
         * Datos generales adicionales: 3 puntos.
         *
         * Premia que exista información útil
         * sin convertirla todavía en criterio semántico.
         */
        $additionalFields = [
            'antiquity',
            'total_area',
            'covered_area',
        ];

        $completed = 0;

        foreach (
            $additionalFields
            as $field
        ) {
            if (
                self::positiveNumber(
                    $property[$field] ?? null
                )
            ) {
                $completed++;
            }
        }

        $score +=
            ($completed / count($additionalFields))
            * 3;

        return [
            'score' =>
            round(
                min(
                    self::BASIC_MAX,
                    $score
                ),
                2
            ),

            'issues' =>
            $issues,

            'suggestions' =>
            $suggestions,
        ];
    }

    private static function evaluateLocation(
        array $property
    ): array {
        $score = 0.0;
        $issues = [];
        $suggestions = [];

        /*
         * País + provincia + ciudad: 8 puntos.
         */
        $locationFields = [
            'country',
            'province',
            'city',
        ];

        $completed = 0;

        foreach (
            $locationFields
            as $field
        ) {
            if (
                self::hasValue(
                    $property[$field] ?? null
                )
            ) {
                $completed++;
            }
        }

        $score +=
            ($completed / 3) * 8;

        if ($completed < 3) {
            self::addIssue(
                $issues,
                'location_incomplete',
                'location',
                'high',
                'La ubicación principal está incompleta.'
            );
        }

        /*
         * Zona: 5 puntos.
         */
        if (
            self::hasValue(
                $property['zone'] ?? null
            )
        ) {
            $score += 5;
        } else {
            self::addSuggestion(
                $suggestions,
                'zone',
                'medium',
                'Agregá el barrio o zona.',
                'La zona mejora considerablemente la precisión de los matches.'
            );
        }

        /*
         * Dirección: 3 puntos.
         */
        if (
            self::hasValue(
                $property['formatted_address'] ?? null
            ) ||
            self::hasValue(
                $property['address'] ?? null
            )
        ) {
            $score += 3;
        } else {
            self::addSuggestion(
                $suggestions,
                'address',
                'low',
                'Completá la dirección.',
                'La dirección puede mantenerse protegida frente a otros usuarios, pero mejora la calidad interna de la ficha.'
            );
        }

        /*
         * Georreferenciación: 4 puntos.
         */
        $hasCoordinates =
            self::hasCoordinate(
                $property['latitude'] ?? null
            )
            &&
            self::hasCoordinate(
                $property['longitude'] ?? null
            );

        $hasPlaceId =
            self::hasValue(
                $property['place_id'] ?? null
            );

        if ($hasCoordinates) {
            $score += 4;
        } elseif ($hasPlaceId) {
            $score += 3;
        } else {
            self::addSuggestion(
                $suggestions,
                'geolocation',
                'medium',
                'Precisá la ubicación de la propiedad.',
                'Una geolocalización completa permitirá mejorar los matches por cercanía en versiones futuras.'
            );
        }

        return [
            'score' =>
            round(
                min(
                    self::LOCATION_MAX,
                    $score
                ),
                2
            ),

            'issues' =>
            $issues,

            'suggestions' =>
            $suggestions,
        ];
    }

    private static function evaluateFeatures(
        array $property
    ): array {
        $score = 0.0;
        $issues = [];
        $suggestions = [];

        $propertyType = trim(
            (string)(
                $property['property_type'] ?? ''
            )
        );

        /*
         * Superficie total.
         * Es útil para prácticamente todos los tipos.
         */
        if (
            self::positiveNumber(
                $property['total_area'] ?? null
            )
        ) {
            $score += 6;
        } else {
            self::addIssue(
                $issues,
                'total_area_missing',
                'total_area',
                'high',
                'No informaste la superficie total.'
            );
        }

        /*
         * Terrenos se evalúan distinto.
         * No tendría sentido penalizarlos por no tener
         * dormitorios, baños o superficie cubierta.
         */
        if ($propertyType === 'land') {
            if (
                self::hasValue(
                    $property['zone'] ?? null
                )
            ) {
                $score += 5;
            }

            if (
                self::hasCoordinate(
                    $property['latitude'] ?? null
                ) &&
                self::hasCoordinate(
                    $property['longitude'] ?? null
                )
            ) {
                $score += 5;
            }

            if (
                self::hasValue(
                    $property['description'] ?? null
                )
            ) {
                $score += 4;
            }

            return [
                'score' =>
                round(
                    min(
                        self::FEATURES_MAX,
                        $score
                    ),
                    2
                ),

                'issues' =>
                $issues,

                'suggestions' =>
                $suggestions,
            ];
        }

        /*
         * Para propiedades construidas.
         */
        if (
            self::positiveNumber(
                $property['covered_area'] ?? null
            )
        ) {
            $score += 4;
        } else {
            self::addSuggestion(
                $suggestions,
                'covered_area',
                'high',
                'Completá la superficie cubierta.',
                'Es un dato importante para comparar propiedades similares.'
            );
        }

        if (
            self::nonNegativeNumber(
                $property['bedrooms'] ?? null
            )
        ) {
            $score += 4;
        } else {
            self::addSuggestion(
                $suggestions,
                'bedrooms',
                'high',
                'Indicá la cantidad de dormitorios.',
                'Este dato participa directamente en los matches.'
            );
        }

        if (
            self::positiveNumber(
                $property['bathrooms'] ?? null
            )
        ) {
            $score += 3;
        } else {
            self::addSuggestion(
                $suggestions,
                'bathrooms',
                'medium',
                'Indicá la cantidad de baños.',
                'Este dato ayuda a mejorar la precisión de las compatibilidades.'
            );
        }

        if (
            self::nonNegativeNumber(
                $property['garages'] ?? null
            )
        ) {
            $score += 2;
        } else {
            self::addSuggestion(
                $suggestions,
                'garages',
                'low',
                'Indicá si tiene cochera.',
                'Incluso indicar 0 permite distinguir un dato faltante de una propiedad sin cochera.'
            );
        }

        if (
            self::nonNegativeNumber(
                $property['antiquity'] ?? null
            )
        ) {
            $score += 1;
        } else {
            self::addSuggestion(
                $suggestions,
                'antiquity',
                'low',
                'Completá la antigüedad.',
                'Ayuda a describir mejor la propiedad y permitirá comparaciones más precisas.'
            );
        }

        return [
            'score' =>
            round(
                min(
                    self::FEATURES_MAX,
                    $score
                ),
                2
            ),

            'issues' =>
            $issues,

            'suggestions' =>
            $suggestions,
        ];
    }

    private static function evaluateMedia(
        array $images
    ): array {
        $issues = [];
        $suggestions = [];

        $count =
            count($images);

        /*
         * Actualmente PermuOK admite hasta 5 imágenes.
         */
        if ($count >= 5) {
            $score = 15.0;
        } elseif ($count === 4) {
            $score = 13.0;
        } elseif ($count === 3) {
            $score = 10.0;
        } elseif ($count === 2) {
            $score = 7.0;
        } elseif ($count === 1) {
            $score = 4.0;
        } else {
            $score = 0.0;
        }

        if ($count === 0) {
            self::addIssue(
                $issues,
                'images_missing',
                'images',
                'high',
                'La publicación no tiene imágenes.'
            );

            self::addSuggestion(
                $suggestions,
                'images',
                'high',
                'Agregá imágenes de la propiedad.',
                'Las fotografías también permitirán que la IA detecte oportunidades de mejora y genere mejores textos.'
            );
        } elseif ($count < 3) {
            self::addSuggestion(
                $suggestions,
                'images',
                'high',
                'Agregá más fotografías.',
                'Intentá mostrar distintos ambientes y atributos de la propiedad.'
            );
        } elseif ($count < 5) {
            self::addSuggestion(
                $suggestions,
                'images',
                'medium',
                'Podés completar la galería.',
                'Una mayor variedad de imágenes dará más contexto al análisis IA.'
            );
        }

        $hasCover = false;

        foreach ($images as $image) {
            if (
                (int)(
                    $image['is_cover'] ?? 0
                ) === 1
            ) {
                $hasCover = true;
                break;
            }
        }

        if (
            $count > 0 &&
            !$hasCover
        ) {
            self::addSuggestion(
                $suggestions,
                'cover_image',
                'medium',
                'Definí una imagen principal.',
                'La portada debería mostrar la propiedad de la forma más clara y atractiva posible.'
            );
        }

        return [
            'score' =>
            $score,

            'issues' =>
            $issues,

            'suggestions' =>
            $suggestions,
        ];
    }

    private static function evaluateMatchability(
        array $property,
        array $amenities,
        ?array $requirements,
        array $requirementPropertyTypes,
        array $requirementLocations
    ): array {
        $score = 0.0;
        $issues = [];
        $suggestions = [];

        /*
         * Amenities: 4 puntos.
         */
        $amenityCount =
            count($amenities);

        if ($amenityCount >= 4) {
            $score += 4;
        } elseif ($amenityCount >= 2) {
            $score += 3;
        } elseif ($amenityCount === 1) {
            $score += 1.5;
        } else {
            self::addSuggestion(
                $suggestions,
                'amenities',
                'medium',
                'Revisá los amenities de la propiedad.',
                'Informarlos permite detectar coincidencias más precisas.'
            );
        }

        /*
         * Datos estructurados directamente utilizados
         * por CompatibilityEngine: 4 puntos.
         */
        $matchingFields = [
            'total_area',
            'bedrooms',
            'bathrooms',
            'garages',
        ];

        $matchingCompleted = 0;

        foreach (
            $matchingFields
            as $field
        ) {
            if (
                self::nonNegativeNumber(
                    $property[$field] ?? null
                )
            ) {
                $matchingCompleted++;
            }
        }

        $score +=
            ($matchingCompleted /
                count($matchingFields))
            * 4;

        /*
         * Requerimientos de la propiedad: 12 puntos.
         */
        if ($requirements === null) {
            self::addIssue(
                $issues,
                'requirements_missing',
                'requirements',
                'medium',
                'No hay criterios definidos sobre qué acepta el propietario.'
            );

            self::addSuggestion(
                $suggestions,
                'requirements',
                'high',
                'Definí qué propuestas acepta la propiedad.',
                'Cuanto más claro sea qué acepta el propietario, mejores compatibilidades podrá generar PermuOK.'
            );

            return [
                'score' =>
                round($score, 2),

                'issues' =>
                $issues,

                'suggestions' =>
                $suggestions,
            ];
        }

        /*
         * Tener configurado el requerimiento base.
         */
        $score += 3;

        $criteriaMode = trim(
            (string)(
                $requirements['criteria_mode'] ?? ''
            )
        );

        /*
         * Modalidades aceptadas.
         */
        $acceptedModes = [
            'accepts_total_swap',
            'accepts_swap_plus_cash',
            'accepts_multiple_swap',
            'accepts_open_proposals',
            'accepts_cash_only',
        ];

        $hasAcceptedMode = false;

        foreach (
            $acceptedModes
            as $field
        ) {
            if (
                (int)(
                    $requirements[$field] ?? 0
                ) === 1
            ) {
                $hasAcceptedMode = true;
                break;
            }
        }

        if ($hasAcceptedMode) {
            $score += 3;
        } else {
            self::addIssue(
                $issues,
                'accepted_modes_missing',
                'requirements',
                'high',
                'No está claro qué modalidad de operación acepta el propietario.'
            );
        }

        /*
         * Si está abierto a propuestas, no exigimos
         * criterios extremadamente específicos.
         */
        if (
            $criteriaMode === 'open' ||
            (int)(
                $requirements['accepts_open_proposals'] ?? 0
            ) === 1
        ) {
            $score += 6;

            return [
                'score' =>
                round(
                    min(
                        self::MATCHABILITY_MAX,
                        $score
                    ),
                    2
                ),

                'issues' =>
                $issues,

                'suggestions' =>
                $suggestions,
            ];
        }

        /*
         * Cuando eligió criterios concretos,
         * premiamos que realmente los detalle.
         */
        $criteriaScore = 0.0;

        if (
            count(
                $requirementPropertyTypes
            ) > 0
        ) {
            $criteriaScore += 2;
        } else {
            self::addSuggestion(
                $suggestions,
                'requirement_property_types',
                'high',
                'Indicá qué tipos de propiedad podría aceptar.',
                'Esto aumenta mucho la precisión del matching de permutas.'
            );
        }

        if (
            count(
                $requirementLocations
            ) > 0
        ) {
            $criteriaScore += 2;
        } else {
            self::addSuggestion(
                $suggestions,
                'requirement_locations',
                'high',
                'Indicá las ubicaciones de interés.',
                'Las zonas deseadas ayudan a reducir propuestas poco relevantes.'
            );
        }

        $hasValueRange =
            self::positiveNumber(
                $requirements['min_value'] ?? null
            )
            ||
            self::positiveNumber(
                $requirements['max_value'] ?? null
            );

        if ($hasValueRange) {
            $criteriaScore += 1;
        } else {
            self::addSuggestion(
                $suggestions,
                'requirement_value',
                'medium',
                'Podés definir un rango de valor aceptable.',
                'Esto ayuda a priorizar propuestas económicamente viables.'
            );
        }

        $hasPhysicalCriteria =
            self::positiveNumber(
                $requirements['min_surface'] ?? null
            )
            ||
            self::positiveNumber(
                $requirements['max_surface'] ?? null
            )
            ||
            self::positiveNumber(
                $requirements['rooms'] ?? null
            );

        if ($hasPhysicalCriteria) {
            $criteriaScore += 1;
        }

        $score +=
            $criteriaScore;

        return [
            'score' =>
            round(
                min(
                    self::MATCHABILITY_MAX,
                    $score
                ),
                2
            ),

            'issues' =>
            $issues,

            'suggestions' =>
            $suggestions,
        ];
    }

    private static function getProperty(
        PDO $pdo,
        int $propertyId
    ): ?array {
        $st = $pdo->prepare("
            SELECT *
            FROM properties
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $st->execute([
            'id' => $propertyId,
        ]);

        $row =
            $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function getImages(
        PDO $pdo,
        int $propertyId
    ): array {
        $st = $pdo->prepare("
            SELECT
                id,
                sort_order,
                is_cover
            FROM property_images
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY
                sort_order ASC,
                id ASC
        ");

        $st->execute([
            'property_id' =>
            $propertyId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function getAmenities(
        PDO $pdo,
        int $propertyId
    ): array {
        $st = $pdo->prepare("
            SELECT amenity_code
            FROM property_amenities
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY id ASC
        ");

        $st->execute([
            'property_id' =>
            $propertyId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];
    }

    private static function getRequirements(
        PDO $pdo,
        int $propertyId
    ): ?array {
        $st = $pdo->prepare("
            SELECT *
            FROM property_requirements
            WHERE property_id = :property_id
              AND deleted_at IS NULL
            ORDER BY id ASC
            LIMIT 1
        ");

        $st->execute([
            'property_id' =>
            $propertyId,
        ]);

        $row =
            $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function getRequirementPropertyTypes(
        PDO $pdo,
        int $requirementId
    ): array {
        $st = $pdo->prepare("
            SELECT property_type
            FROM property_requirement_property_types
            WHERE property_requirement_id =
                :property_requirement_id
            ORDER BY id ASC
        ");

        $st->execute([
            'property_requirement_id' =>
            $requirementId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];
    }

    private static function getRequirementLocations(
        PDO $pdo,
        int $requirementId
    ): array {
        $st = $pdo->prepare("
            SELECT
                country_code,
                country,
                province,
                city,
                zone
            FROM property_requirement_locations
            WHERE property_requirement_id =
                :property_requirement_id
            ORDER BY id ASC
        ");

        $st->execute([
            'property_requirement_id' =>
            $requirementId,
        ]);

        return $st->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }

    private static function saveResult(
        PDO $pdo,
        array $result
    ): void {
        try {
            $issuesJson = json_encode(
                $result['issues'],
                JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_UNICODE
            );

            $suggestionsJson = json_encode(
                $result['suggestions'],
                JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $e) {
            throw new Exception(
                'No se pudo serializar el resultado de calidad.',
                0,
                $e
            );
        }

        $sections =
            $result['sections'];

        $st = $pdo->prepare("
            INSERT INTO publication_quality_scores (
                entity_type,
                entity_id,
                score,
                quality_level,
                basic_score,
structure_score_v2,
location_score,
features_score,
media_score,
matchability_score,
                issues_json,
                suggestions_json,
                algorithm_version,
                analyzed_at
            )
            VALUES (
                :entity_type,
                :entity_id,
                :score,
                :quality_level,
                :basic_score,
                :structure_score_v2,
                :location_score,
                :features_score,
                :media_score,
                :matchability_score,
                :issues_json,
                :suggestions_json,
                :algorithm_version,
                NOW()
            )

            ON DUPLICATE KEY UPDATE
                score =
                    VALUES(score),

                quality_level =
                    VALUES(quality_level),

                basic_score =
                    VALUES(basic_score),
structure_score_v2 =
    VALUES(structure_score_v2),
                location_score =
                    VALUES(location_score),

                features_score =
                    VALUES(features_score),

                media_score =
                    VALUES(media_score),

                matchability_score =
                    VALUES(matchability_score),

                issues_json =
                    VALUES(issues_json),

                suggestions_json =
                    VALUES(suggestions_json),

                algorithm_version =
                    VALUES(algorithm_version),

                analyzed_at =
                    NOW()
        ");

        $st->execute([
            'entity_type' =>
            'property',

            'entity_id' =>
            $result['entity_id'],

            'score' =>
            $result['score'],

            'quality_level' =>
            $result['quality_level'],

            'basic_score' =>
            $sections['basic']['score'],
            'structure_score_v2' =>
            $sections['structure_v2']['score'],
            'location_score' =>
            $sections['location']['score'],

            'features_score' =>
            $sections['features']['score'],

            'media_score' =>
            $sections['media']['score'],

            'matchability_score' =>
            $sections['matchability']['score'],

            'issues_json' =>
            $issuesJson,

            'suggestions_json' =>
            $suggestionsJson,

            'algorithm_version' =>
            self::ALGORITHM_VERSION,
        ]);
    }

    private static function resolveQualityLevel(
        float $score
    ): string {
        if ($score >= 85) {
            return 'excellent';
        }

        if ($score >= 60) {
            return 'good';
        }

        if ($score >= 40) {
            return 'basic';
        }

        return 'poor';
    }

    private static function addIssue(
        array &$issues,
        string $code,
        string $field,
        string $priority,
        string $message
    ): void {
        $issues[] = [
            'code' =>
            $code,

            'field' =>
            $field,

            'priority' =>
            $priority,

            'message' =>
            $message,
        ];
    }

    private static function addSuggestion(
        array &$suggestions,
        string $field,
        string $priority,
        string $title,
        string $message
    ): void {
        $suggestions[] = [
            'field' =>
            $field,

            'priority' =>
            $priority,

            'title' =>
            $title,

            'message' =>
            $message,
        ];
    }

    private static function hasValue(
        mixed $value
    ): bool {
        return trim(
            (string)($value ?? '')
        ) !== '';
    }

    private static function positiveNumber(
        mixed $value
    ): bool {
        return
            is_numeric($value) &&
            (float)$value > 0;
    }

    private static function nonNegativeNumber(
        mixed $value
    ): bool {
        return
            $value !== null &&
            $value !== '' &&
            is_numeric($value) &&
            (float)$value >= 0;
    }

    private static function nullableFloat(
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

    private static function hasCoordinate(
        mixed $value
    ): bool {
        if (
            $value === null ||
            $value === '' ||
            !is_numeric($value)
        ) {
            return false;
        }

        return true;
    }

    private static function textLength(
        string $text
    ): int {
        if (
            function_exists(
                'mb_strlen'
            )
        ) {
            return mb_strlen(
                $text,
                'UTF-8'
            );
        }

        return strlen($text);
    }
}
