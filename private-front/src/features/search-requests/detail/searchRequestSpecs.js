import {
  boolText,
  formatMoneyRange,
  formatPaymentLabel,
  joinLocation,
  propertyConditionLabel,
  propertyTypeLabel,
  urgencyMeta,
} from "./searchRequestDetail.helpers";

export function buildSearchRequestSummary(request, propertyTypes) {
  const locationLabel = joinLocation([
    request?.city,
    request?.zone,
    request?.province,
  ]);

  return [
    {
      label: "Ubicación",
      value: locationLabel || "Sin ubicación",
      type: "location",
    },
    {
      label: "Tipo buscado",
      value: propertyTypes.length
        ? propertyTypes.map((item) => propertyTypeLabel(item)).join(", ")
        : "Sin definir",
      type: "property_type",
    },
    {
      label: "Modalidad",
      value: formatPaymentLabel(request),
      type: "payment",
    },
    {
      label: "Rango de valor",
      value: formatMoneyRange(request),
      type: "price",
    },
  ];
}

function hasRealValue(value) {
  if (value === undefined || value === null || value === "") return false;
  if (value === "0") return false;
  if (value === 0) return false;
  return true;
}

function isTrue(value) {
  return value === true || value === 1 || value === "1";
}

function isValidNumber(value) {
  return value !== null && value !== undefined && Number(value) > 0;
}

export function buildSearchRequestConditions(request) {
  return [
    // estado SOLO si no es "any"
    request?.property_condition &&
    request.property_condition !== "any"
      ? {
          label: "Estado de la propiedad",
          value: propertyConditionLabel(request.property_condition),
          type: "condition",
        }
      : null,

    // 🔥 IMPORTANTE: solo > 0
    isValidNumber(request?.min_total_area)
      ? {
          label: "Superficie total mínima",
          value: `${Number(request.min_total_area)} m²`,
          type: "total_area",
        }
      : null,

    isValidNumber(request?.min_covered_area)
      ? {
          label: "Superficie cubierta mínima",
          value: `${Number(request.min_covered_area)} m²`,
          type: "covered_area",
        }
      : null,

    isValidNumber(request?.max_antiquity)
      ? {
          label: "Antigüedad máxima",
          value: `${request.max_antiquity} años`,
          type: "antiquity",
        }
      : null,

    isValidNumber(request?.min_bedrooms)
      ? {
          label: "Dormitorios mínimos",
          value: request.min_bedrooms,
          type: "bedrooms",
        }
      : null,

    isValidNumber(request?.min_bathrooms)
      ? {
          label: "Baños mínimos",
          value: request.min_bathrooms,
          type: "bathrooms",
        }
      : null,

    isValidNumber(request?.min_garages)
      ? {
          label: "Cocheras mínimas",
          value: request.min_garages,
          type: "garages",
        }
      : null,

    // 🔥 SOLO si es true
    isTrue(request?.open_to_other_zones)
      ? {
          label: "Abierto a otras zonas",
          value: "Sí",
          type: "location",
        }
      : null,
  ].filter(Boolean);
}

export function buildSearchRequestQuickFacts(request, propertyTypes) {
  return [
    {
      label: "Presupuesto",
      value:
        request?.min_value || request?.max_value
          ? formatMoneyRange(request)
          : null,
    },
    {
      label: "Tipo",
      value: propertyTypes.length
        ? propertyTypes.map((item) => propertyTypeLabel(item)).join(", ")
        : null,
    },
    {
      label: "Localidad",
      value: request?.city || request?.zone || request?.province || null,
    },
    {
      label: "Urgencia",
      value: urgencyMeta(request?.urgency)?.label || null,
      accent: "amber",
    },
    {
      label: "Permuta",
      value:
        request?.payment_mode_swap !== undefined &&
        request?.payment_mode_swap !== null
          ? Number(request?.payment_mode_swap) === 1 ||
            request?.payment_mode_swap === "1"
            ? "Acepta"
            : "No"
          : null,
      accent:
        Number(request?.payment_mode_swap) === 1 ||
        request?.payment_mode_swap === "1"
          ? "green"
          : null,
    },
  ].filter((item) => !!item.value);
}

export function buildSearchRequestLocationData(request) {
  return {
    formattedAddress: request?.formatted_address,
    address: request?.address,
    zone: request?.zone,
    city: request?.city,
    province: request?.province,
    country: request?.country,
  };
}

