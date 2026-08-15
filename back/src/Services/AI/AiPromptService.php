<?php

namespace App\Services\AI;

use Exception;

class AiPromptService
{
    public const PROPERTY_PROMPT_VERSION = 'property-enrichment-v3';
    public const SEARCH_REQUEST_PROMPT_VERSION =
    'search-request-enrichment-v7';

    /**
     * Instrucciones generales para interpretar una publicación.
     */
    public static function buildInstructions(string $entityType): string
    {
        if (!in_array(
            $entityType,
            ['property', 'search_request', 'development'],
            true
        )) {
            throw new Exception(
                'Tipo de entidad no soportado para análisis IA'
            );
        }

        return <<<PROMPT
Sos un analista inmobiliario senior de PermuOK, una plataforma B2B para inmobiliarias.

Tu trabajo es interpretar publicaciones inmobiliarias con precisión comercial y detectar información útil para:

- encontrar compradores, vendedores e inversores;
- detectar permutas y operaciones encadenadas;
- completar datos que no fueron cargados en campos estructurados;
- identificar requisitos de compra, venta o intercambio;
- detectar financiación, entrega de propiedades y diferencias monetarias;
- encontrar contradicciones entre campos y descripción;
- generar preguntas inteligentes para mejorar la publicación;
- producir información útil para un motor posterior de compatibilidades.

TIPO DE ENTIDAD ANALIZADA:
{$entityType}

REGLAS OBLIGATORIAS:

1. No inventes datos.
2. Diferenciá siempre entre información explícita e inferida.
3. Si algo no puede determinarse, usá null o una lista vacía.
4. Los campos estructurados tienen prioridad sobre inferencias del texto.
5. Si el texto contradice un campo estructurado, no lo reemplaces: registrá la contradicción.
6. Conservá evidencia textual breve para cada dato detectado en texto libre.
7. Interpretá sinónimos y expresiones inmobiliarias argentinas.
8. Prestá especial atención a:
   - "acepta menor valor";
   - "toma propiedad";
   - "permuta";
   - "escucha oferta";
   - "financia";
   - "anticipo y cuotas";
   - "apto crédito";
   - "entrada para auto";
   - "a reciclar";
   - "ideal inversor";
   - "ideal constructor";
   - "con renta";
   - "desocupado";
   - "ocupado";
   - "parte de pago";
   - "diferencia a convenir".
9. No concluyas que existe cochera únicamente porque hay espacio exterior.
10. No concluyas que acepta permuta salvo que exista evidencia explícita o una inferencia suficientemente fundada.
11. Las preguntas inteligentes deben ayudar a conseguir más compatibilidades.
12. El resumen debe describir la oportunidad comercial, no funcionar como publicidad.
13. La confianza general debe estar entre 0 y 1.
14. Respondé únicamente según el esquema JSON solicitado.
15. "Acepta propuestas abiertas" o "escucha ofertas" no significa automáticamente que acepta una propiedad de menor valor.

16. Solo marcá accepts_lower_value como true si existe evidencia expresa como:
   - acepta menor valor;
   - toma propiedad de menor valor;
   - recibe departamento más diferencia;
   - acepta inmueble inferior más efectivo.

17. accepts_open_proposals indica flexibilidad comercial, pero no define por sí solo el sentido económico de una permuta.

18. Si los campos estructurados contradicen una nota libre, registrá la contradicción y no elijas silenciosamente uno de los dos datos.
19. El estado "published" solamente indica visibilidad. No lo uses como evidencia de venta, alquiler, permuta o inversión.

20. Determiná operation_type y primary_intent usando campos específicos, requisitos, título, descripción y contexto comercial.
21. No determines que una propiedad está en venta únicamente porque tiene precio o porque está publicada.

22. Si no existe un campo o texto que indique explícitamente venta, alquiler, permuta, inversión u otra modalidad, operation_type y primary_intent deben ser "unknown" o null.

23. "published" significa únicamente que el contenido está visible en la plataforma.

24. Un precio por sí solo no permite distinguir entre venta, alquiler, anticipo, cuota, reserva o valor de referencia.
25. Nunca corrijas, completes ni reemplaces un precio declarado basándote en valores de mercado o expectativas propias.

26. Si un precio parece atípico, conservá exactamente el valor estructurado y generá una advertencia para verificarlo.

27. No afirmes que “falta un cero” ni propongas otro importe. Podés indicar solamente que el valor podría requerir confirmación.

28. No utilices conocimiento externo del mercado para modificar o reinterpretar datos de la ficha.
29. La ausencia de una afirmación no equivale a false.

30. Usá false únicamente cuando un campo estructurado o el texto indiquen expresamente que una condición no se acepta.

31. Si no existe información suficiente para confirmar o negar una condición, devolvé null con mode "unknown".

32. En particular, no marques accepts_lower_value, accepts_higher_value, financing_available o credit_eligible como false solo porque no fueron mencionados.
33. Dar prioridad al campo estructurado no implica ignorar contradicciones encontradas en título, descripción o notas.

34. Revisá siempre título, descripción y notas contra los campos estructurados.

35. Toda contradicción explícita debe incluirse en contradictions, aunque el valor principal conserve el dato estructurado.

36. Ejemplo: si property_type es "apartment" y el título dice "casa", mantené "apartment" como valor principal y registrá la contradicción.
37. Nunca afirmes que una frase aparece en la publicación si esa frase no existe literalmente en el contexto recibido.

38. Cada afirmación sobre texto libre debe poder respaldarse con evidence textual exacta o una paráfrasis claramente identificable.

39. No uses ejemplos de las instrucciones como si fueran contenido de la publicación analizada.
40. Si accepts_open_proposals es verdadero, budget_flexibility puede ser "open_to_offers".

41. "open_to_offers" expresa flexibilidad para escuchar propuestas, pero no implica aceptar menor valor, permuta ni financiación.
42. Cuando exista una contradicción entre flags estructurados y una nota libre, describí ambas fuentes de manera neutral. No afirmes que una condición está definitivamente rechazada hasta que la contradicción sea resuelta.
43. En una search_request, los campos min_* representan mínimos requeridos, no características existentes.

44. min_value y max_value representan el rango presupuestario buscado. No los interpretes como precio de una propiedad ofrecida.

45. payment_mode_cash indica disponibilidad para operar con efectivo.

46. payment_mode_swap indica interés en una operación con intercambio o permuta.

47. open_to_other_zones indica flexibilidad geográfica, pero no permite asumir cualquier ciudad o provincia.

48. Los amenities de una búsqueda representan características deseadas. No afirmes que el usuario ya posee esos amenities.

49. La urgencia pertenece a la búsqueda y debe considerarse como prioridad comercial.

50. Si existen tipos de propiedad o amenities estructurados, conservá sus códigos y analizalos como requisitos deseados.
51. No conviertas cantidad de ambientes en cantidad de dormitorios.

52. “Monoambiente” o “1 ambiente” normalmente implica cero dormitorios separados, salvo que la publicación indique otra cosa.

53. Si el texto menciona ambientes pero el sistema solo tiene min_bedrooms, registrá la información textual sin asignarla automáticamente a min_bedrooms.
54. payment_mode_cash = false no implica financiación.

55. payment_mode_cash = false puede significar que la modalidad no fue seleccionada, que solo considera permuta o que falta información.

56. Si no hay evidencia explícita sobre financiación, mantené financing_available en null.
57. Aceptar permuta o intercambio no implica flexibilidad presupuestaria.

58. Solo marcá budget_flexibility como open_to_offers cuando exista evidencia explícita de que escucha ofertas, acepta otros valores o tiene margen de negociación.
59. En campos mínimos de una búsqueda, un valor igual a 0 debe interpretarse como “sin requisito mínimo”, no como una superficie buscada de cero.

60. Para min_total_area, min_covered_area, min_bedrooms, min_bathrooms y min_garages, devolvé null cuando el valor sea cero y represente ausencia de restricción.
61. La frase "tengo algo en permuta" solamente indica que existe un bien para ofrecer en intercambio.

62. No uses esa frase como evidencia de budget_flexibility.

63. budget_flexibility solo puede ser "open_to_offers" si existe evidencia explícita como:
- escucha ofertas;
- presupuesto flexible;
- puede ampliar presupuesto;
- valor negociable;
- considera propuestas por otros importes.

64. Si no existe esa evidencia, budget_flexibility debe ser null con mode "unknown".

65. payment_mode_cash = false no significa que rechace el efectivo.

66. Interpretalo como "modalidad en efectivo no seleccionada o no informada", salvo que el texto indique expresamente que no acepta efectivo.

67. No uses payment_mode_cash = false para afirmar que una operación exclusivamente en efectivo está descartada.

68. Para search_request, usá desired_property_types como campo principal de tipos buscados.

69. En search_request, property_type debe ser null salvo que represente una entidad ofrecida y no una preferencia.

70. Para search_request, usá desired_amenities como campo principal. amenities debe ser null o lista vacía para evitar duplicar requisitos deseados.

71. En una search_request, si el título o la descripción indican de forma clara "busco", "necesito", "quiero comprar" o una expresión equivalente de adquisición, primary_intent debe ser "buy".

72. Para una search_request, operation_type puede ser "buy" cuando la intención de adquisición sea clara. No hace falta que exista una columna específica llamada operation_type.

73. Si payment_mode_swap es verdadero o existe evidencia explícita de una permuta, agregá "exchange" dentro de secondary_intents.

74. No generes advertencias sobre el formato, magnitud o posible truncamiento de min_value o max_value si no existe una contradicción concreta en los datos.

75. Un rango presupuestario válido no debe generar por sí solo advertencias sobre moneda, truncamiento o errores de carga.

76. No generes preguntas genéricas sobre financiación si la publicación no menciona financiación y esa información no resulta prioritaria para la operación detectada.

77. Priorizá preguntas que permitan concretar la operación, especialmente:
- qué inmueble ofrece en permuta;
- si ese inmueble ya está publicado en PermuOK;
- ubicación, tipo y valor aproximado del inmueble ofrecido;
- diferencia máxima disponible;
- requisitos excluyentes de la búsqueda.

78. Si el texto o los campos indican que existe un inmueble para ofrecer en permuta, detectá una sugerencia de flujo para cargar o vincular esa propiedad.

79. Las workflow_suggestions deben representar acciones concretas que la plataforma pueda ofrecer al usuario.

80. No incluyas workflow_suggestions genéricas. Cada sugerencia debe estar respaldada por los datos de la publicación.

81. Si el usuario menciona que tiene algo para permutar pero exchange_offers está vacío, agregá una workflow_suggestion de tipo "create_exchange_offer" con prioridad alta.

82. Si ya existe una propiedad publicada que podría utilizarse como oferta, la acción sugerida puede ser "link_existing_property".

83. Para una search_request, no preguntes automáticamente si acepta propiedades de menor valor. Esa pregunta solo corresponde cuando la estructura concreta de la permuta lo vuelve relevante.

84. Diferenciá entre:
- la propiedad buscada;
- la propiedad ofrecida en permuta;
- el efectivo disponible para completar la diferencia.

85. No interpretes el presupuesto total de búsqueda como diferencia disponible para una permuta salvo que el usuario lo indique expresamente.
86. En una search_request con payment_mode_swap, no preguntes si el usuario acepta recibir una propiedad de menor valor salvo que la búsqueda implique que también está ofreciendo recibir otro bien distinto al buscado.

87. Priorizá preguntar:
- valor aproximado de la propiedad ofrecida;
- diferencia máxima que puede aportar;
- si acepta entregar su propiedad y completar con efectivo;
- si la propiedad ofrecida ya está publicada.

88. En search_request, accepts_lower_value suele referirse a condiciones de la propiedad ofrecida o de una cadena de intercambio. No lo uses automáticamente como pregunta principal.

89. Si payment_mode_swap es verdadero y se menciona una propiedad ofrecida, pero exchange_offers está vacío, reducí information_score y matchability_score de forma significativa.

90. Una búsqueda con permuta no debe superar 60 puntos de matchability mientras no tenga identificado al menos el tipo, ubicación y valor aproximado del bien ofrecido.
91. En una search_request, exchange_offers representa los inmuebles que el interesado ofrece para entregar, permutar o utilizar como parte de pago.

92. No confundas exchange_offers con las propiedades buscadas.

93. desired_property_types, desired_locations y desired_amenities describen lo que el usuario busca recibir.

94. exchange_offers describe lo que el usuario puede entregar en la operación.

95. Si exchange_offers contiene al menos una oferta con tipo, ubicación y valor aproximado, no indiques que falta completamente el inmueble ofrecido.

96. Si existe una oferta parcial, mencioná únicamente los datos específicos que falten.

97. Si exchange_offers no está vacío, no generes una workflow_suggestion de tipo create_exchange_offer.

98. Si existe una oferta cargada, podés generar una workflow_suggestion de tipo complete_property_requirements cuando falten campos importantes de esa oferta.

99. El valor estimated_price de una exchange_offer corresponde al inmueble ofrecido, no al presupuesto de compra ni a la diferencia en efectivo.

100. cash_difference_max representa el efectivo máximo disponible para completar la operación, cuando está informado.

101. No sumes automáticamente estimated_price y cash_difference_max para determinar capacidad total, salvo que la modalidad de la operación lo permita expresamente.

102. Al analizar una permuta, diferenciá siempre:
- valor estimado del bien ofrecido;
- presupuesto de búsqueda;
- diferencia máxima en efectivo;
- valor de la propiedad buscada.

103. Si una exchange_offer tiene imágenes, usá images_count y has_cover únicamente para evaluar calidad de información. No inventes características visuales de las imágenes.
104. Si exchange_offers.estimated_price ya tiene valor y moneda, no preguntes nuevamente cuál es el valor aproximado.

105. Podés sugerir confirmar o actualizar la valuación únicamente si existe evidencia de que podría estar desactualizada, incompleta o contradictoria.

106. No incluyas como missing_information un dato que ya esté presente en exchange_offers.

107. Si exchange_offers ya contiene una fila asociada a la búsqueda, no sugieras create_exchange_offer.

108. No sugieras link_existing_property únicamente porque existe una exchange_offer.

109. Usá link_existing_property solo cuando exista evidencia de que el inmueble ofrecido también está publicado en la tabla general de propiedades y todavía no está vinculado.

110. Si la exchange_offer existe pero está incompleta, usá workflow_suggestion type "complete_exchange_offer".
111. En una search_request donde el usuario entrega una exchange_offer para adquirir la propiedad buscada, no preguntes si acepta recibir un inmueble de menor valor, salvo que exista evidencia de una operación inversa o una cadena donde también reciba otro activo.

112. Priorizá cash_difference_max, valor del bien ofrecido y condiciones de aceptación del propietario objetivo.

PROMPT;
    }

    /**
     * Devuelve el JSON Schema que OpenAI debe respetar.
     */
    public static function getEntityEnrichmentSchema(): array
    {
        $detectedValueSchema = [
            'type' => 'object',
            'additionalProperties' => false,

            'properties' => [
                'value' => [
                    'anyOf' => [
                        [
                            'type' => 'string',
                        ],
                        [
                            'type' => 'number',
                        ],
                        [
                            'type' => 'integer',
                        ],
                        [
                            'type' => 'boolean',
                        ],
                        [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        [
                            'type' => 'null',
                        ],
                    ],
                ],

                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],

                'mode' => [
                    'type' => 'string',
                    'enum' => [
                        'structured',
                        'explicit',
                        'inferred',
                        'unknown',
                    ],
                ],

                'source' => [
                    'type' => [
                        'string',
                        'null',
                    ],
                ],

                'evidence' => [
                    'type' => [
                        'string',
                        'null',
                    ],
                ],
            ],

            'required' => [
                'value',
                'confidence',
                'mode',
                'source',
                'evidence',
            ],
        ];
        return [
            'name' => 'real_estate_entity_enrichment',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'summary' => [
                        'type' => 'string',
                    ],

                    'tags' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],

                    'entities' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'property_type' => $detectedValueSchema,
                            'operation_type' => $detectedValueSchema,
                            'condition' => $detectedValueSchema,
                            'occupancy_status' => $detectedValueSchema,
                            'commercial_use' => $detectedValueSchema,
                            'investment_profile' => $detectedValueSchema,
                            'urgency' => $detectedValueSchema,
                            'accepts_exchange' => $detectedValueSchema,
                            'accepts_lower_value' => $detectedValueSchema,
                            'accepts_higher_value' => $detectedValueSchema,
                            'financing_available' => $detectedValueSchema,
                            'credit_eligible' => $detectedValueSchema,
                            'desired_property_types' => $detectedValueSchema,
                            'desired_locations' => $detectedValueSchema,
                            'desired_amenities' => $detectedValueSchema,
                            'budget_flexibility' => $detectedValueSchema,
                            'price_difference' => $detectedValueSchema,
                            'amenities' => $detectedValueSchema,
                            'bedrooms' => $detectedValueSchema,
                            'bathrooms' => $detectedValueSchema,
                            'garages' => $detectedValueSchema,
                            'total_area' => $detectedValueSchema,
                            'covered_area' => $detectedValueSchema,
                        ],
                        'required' => [
                            'property_type',
                            'operation_type',
                            'condition',
                            'occupancy_status',
                            'commercial_use',
                            'investment_profile',
                            'urgency',
                            'accepts_exchange',
                            'accepts_lower_value',
                            'accepts_higher_value',
                            'financing_available',
                            'credit_eligible',
                            'desired_property_types',
                            'desired_locations',
                            'desired_amenities',
                            'budget_flexibility',
                            'price_difference',
                            'amenities',
                            'bedrooms',
                            'bathrooms',
                            'garages',
                            'total_area',
                            'covered_area',
                        ],
                    ],

                    'intent' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'primary_intent' => [
                                'type' => [
                                    'string',
                                    'null',
                                ],
                                'enum' => [
                                    'sell',
                                    'buy',
                                    'exchange',
                                    'rent',
                                    'invest',
                                    'finance',
                                    'unknown',
                                    null,
                                ],
                            ],
                            'secondary_intents' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],
                            'commercial_strategy' => [
                                'type' => [
                                    'string',
                                    'null',
                                ],
                            ],
                        ],
                        'required' => [
                            'primary_intent',
                            'secondary_intents',
                            'commercial_strategy',
                        ],
                    ],

                    'contradictions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'field' => [
                                    'type' => 'string',
                                ],
                                'structured_value' => [
                                    'anyOf' => [
                                        [
                                            'type' => 'string',
                                        ],
                                        [
                                            'type' => 'number',
                                        ],
                                        [
                                            'type' => 'integer',
                                        ],
                                        [
                                            'type' => 'boolean',
                                        ],
                                        [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        [
                                            'type' => 'null',
                                        ],
                                    ],
                                ],
                                'detected_value' => [
                                    'anyOf' => [
                                        [
                                            'type' => 'string',
                                        ],
                                        [
                                            'type' => 'number',
                                        ],
                                        [
                                            'type' => 'integer',
                                        ],
                                        [
                                            'type' => 'boolean',
                                        ],
                                        [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        [
                                            'type' => 'null',
                                        ],
                                    ],
                                ],
                                'evidence' => [
                                    'type' => 'string',
                                ],
                                'severity' => [
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
                                'structured_value',
                                'detected_value',
                                'evidence',
                                'severity',
                            ],
                        ],
                    ],

                    'missing_information' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],

                    'warnings' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'code' => [
                                    'type' => 'string',
                                ],
                                'message' => [
                                    'type' => 'string',
                                ],
                                'severity' => [
                                    'type' => 'string',
                                    'enum' => [
                                        'low',
                                        'medium',
                                        'high',
                                    ],
                                ],
                            ],
                            'required' => [
                                'code',
                                'message',
                                'severity',
                            ],
                        ],
                    ],

                    'smart_questions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'question' => [
                                    'type' => 'string',
                                ],
                                'field' => [
                                    'type' => [
                                        'string',
                                        'null',
                                    ],
                                ],
                                'reason' => [
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
                                'question',
                                'field',
                                'reason',
                                'priority',
                            ],
                        ],
                    ],
                    'workflow_suggestions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'enum' => [
                                        'create_exchange_offer',
                                        'complete_exchange_offer',
                                        'link_existing_property',
                                        'complete_budget_information',
                                        'complete_location_information',
                                        'complete_property_requirements',
                                        'complete_payment_information',
                                        'resolve_contradiction',
                                        'improve_description',
                                    ],
                                ],

                                'priority' => [
                                    'type' => 'string',
                                    'enum' => [
                                        'low',
                                        'medium',
                                        'high',
                                    ],
                                ],

                                'reason' => [
                                    'type' => 'string',
                                ],

                                'related_field' => [
                                    'type' => [
                                        'string',
                                        'null',
                                    ],
                                ],

                                'action_label' => [
                                    'type' => 'string',
                                ],
                            ],

                            'required' => [
                                'type',
                                'priority',
                                'reason',
                                'related_field',
                                'action_label',
                            ],
                        ],
                    ],
                    'publication_analysis' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'information_score' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                            'commercial_score' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                            'matchability_score' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                            'overall_score' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                            'strengths' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],
                            'recommendations' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                        'required' => [
                            'information_score',
                            'commercial_score',
                            'matchability_score',
                            'overall_score',
                            'strengths',
                            'recommendations',
                        ],
                    ],

                    'confidence' => [
                        'type' => 'number',
                        'minimum' => 0,
                        'maximum' => 1,
                    ],
                ],

                'required' => [
                    'summary',
                    'tags',
                    'entities',
                    'intent',
                    'contradictions',
                    'missing_information',
                    'warnings',
                    'smart_questions',
                    'publication_analysis',
                    'confidence',
                    'workflow_suggestions',
                ],
            ],
        ];
    }

    /**
     * Convierte el contexto completo en el contenido enviado al modelo.
     */
    public static function buildInput(
        string $entityType,
        array $context
    ): string {
        return json_encode(
            [
                'task' => 'enrich_real_estate_entity',
                'entity_type' => $entityType,
                'context' => $context,
            ],
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRESERVE_ZERO_FRACTION |
                JSON_THROW_ON_ERROR
        );
    }
}
