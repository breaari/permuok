import {
  AMENITIES,
  getAmenityLabel,
  normalizeAmenities,
} from "../../shared/helpers/amenities";

export const SEARCH_REQUEST_STATUS_OPTIONS = [
  { key: "", label: "Todas" },
  { key: "draft", label: "Borradores" },
  { key: "published", label: "Publicadas" },
  { key: "paused", label: "Pausadas" },
  { key: "archived", label: "Archivadas" },
  { key: "closed", label: "Cerradas" },
];

export const SEARCH_REQUEST_PROPERTY_TYPES = [
  { value: "house", label: "Casa" },
  { value: "apartment", label: "Departamento" },
  { value: "land", label: "Lote / Terreno" },
  { value: "commercial", label: "Local / Comercial" },
  { value: "office", label: "Oficina" },
  { value: "warehouse", label: "Galpón / Depósito" },
  { value: "country_house", label: "Casaquinta / Quinta" },
  { value: "farm", label: "Campo / Chacra" },
  { value: "garage", label: "Cochera" },
  { value: "other", label: "Otro" },
];

export const SEARCH_REQUEST_AMENITIES = AMENITIES;

export const SEARCH_REQUEST_CONDITION_OPTIONS = [
  { value: "any", label: "Cualquiera" },
  { value: "new", label: "Nueva" },
  { value: "used", label: "Usada" },
  { value: "to_renovate", label: "A refaccionar" },
];

export const SEARCH_REQUEST_URGENCY_OPTIONS = [
  { value: "low", label: "Baja" },
  { value: "medium", label: "Media" },
  { value: "high", label: "Alta" },
];

export const SEARCH_REQUEST_CURRENCIES = [
  { value: "USD", label: "USD" },
  { value: "ARS", label: "ARS" },
];

export const ALLOWED_COUNTRIES = [
  { value: "Argentina", label: "Argentina", code: "AR" },
  { value: "Estados Unidos", label: "Estados Unidos", code: "US" },
  { value: "Italia", label: "Italia", code: "IT" },
];

export function resolveCountryCode(country) {
  const found = ALLOWED_COUNTRIES.find(
    (item) => item.value === country || item.label === country,
  );
  return found?.code || "";
}

export function emptySearchRequestForm() {
  return {
    title: "",
    description: "",
    country: "Argentina",
    country_code: "AR",
    province: "",
    city: "",
    zone: "",
    exchange_offers: [],
    property_condition: "any",
    currency: "USD",
    min_value: "",
    max_value: "",
    min_total_area: "",
    min_covered_area: "",
    min_bedrooms: "",
    min_bathrooms: "",
    min_garages: "",
    max_antiquity: "",
    urgency: "medium",
    payment_mode_cash: true,
    payment_mode_swap: false,
    cash_difference_max: "",
    cash_difference_currency: "USD",
    open_to_other_zones: false,
    notes: "",
    property_types: [],
    amenities: [],
    status: "draft",
  };
}

export function mapSearchRequestToForm(detail) {
  const request = detail?.search_request || {};

  return {
    title: request.title || "",
    description: request.description || "",
    country: request.country || "Argentina",
    country_code:
      request.country_code || resolveCountryCode(request.country) || "AR",
    province: request.province || "",
    city: request.city || "",
    zone: request.zone || "",
    property_condition: request.property_condition || "any",
    currency: request.currency || "USD",
    min_value: request.min_value || "",
    max_value: request.max_value || "",
    exchange_offers: Array.isArray(detail?.exchange_offers)
      ? detail.exchange_offers.map((offer) => ({
          offer_type: offer.offer_type || "property",
          property_id: offer.property_id || "",
          title: offer.title || "",
          description: offer.description || "",
          property_type: offer.property_type || "",
          vehicle_type: offer.vehicle_type || "",
          vehicle_brand: offer.vehicle_brand || "",
          vehicle_model: offer.vehicle_model || "",
          vehicle_year: offer.vehicle_year || "",
          estimated_price: offer.estimated_price || "",
          currency: offer.currency || "USD",
          country_code: offer.country_code || "",
          country: offer.country || "",
          province: offer.province || "",
          city: offer.city || "",
          zone: offer.zone || "",
          total_area: offer.total_area || "",
          covered_area: offer.covered_area || "",
          bedrooms: offer.bedrooms || "",
          bathrooms: offer.bathrooms || "",
          garages: offer.garages || "",
          antiquity: offer.antiquity || "",
        }))
      : [],
    min_total_area: request.min_total_area || "",
    min_covered_area: request.min_covered_area || "",
    min_bedrooms: request.min_bedrooms || "",
    min_bathrooms: request.min_bathrooms || "",
    min_garages: request.min_garages || "",
    max_antiquity: request.max_antiquity || "",
    urgency: request.urgency || "medium",
    payment_mode_cash:
      request.payment_mode_cash === true ||
      request.payment_mode_cash === 1 ||
      request.payment_mode_cash === "1",
    payment_mode_swap:
      request.payment_mode_swap === true ||
      request.payment_mode_swap === 1 ||
      request.payment_mode_swap === "1",
    cash_difference_max: request.cash_difference_max || "",
    cash_difference_currency: request.cash_difference_currency || "USD",
    open_to_other_zones:
      request.open_to_other_zones === true ||
      request.open_to_other_zones === 1 ||
      request.open_to_other_zones === "1",
    notes: request.notes || "",
    property_types: Array.isArray(detail?.property_types)
      ? detail.property_types
      : [],
    amenities: normalizeAmenities(detail?.amenities),
    status: request.status || "draft",
  };
}

export function buildSearchRequestPayload(form) {
  return {
    title: String(form.title || "").trim(),
    description: String(form.description || "").trim(),
    country_code: form.country_code || resolveCountryCode(form.country),
    country: String(form.country || "").trim(),
    province: String(form.province || "").trim(),
    city: String(form.city || "").trim() || null,
    zone: String(form.zone || "").trim() || null,
    property_condition: form.property_condition || "any",
    currency: form.currency || "USD",
    min_value: form.min_value || null,
    max_value: form.max_value || null,
    min_total_area: form.min_total_area || null,
    min_covered_area: form.min_covered_area || null,
    min_bedrooms: form.min_bedrooms || null,
    min_bathrooms: form.min_bathrooms || null,
    min_garages: form.min_garages || null,
    max_antiquity: form.max_antiquity || null,
    urgency: form.urgency || "medium",
    payment_mode_cash: !!form.payment_mode_cash,
    payment_mode_swap: !!form.payment_mode_swap,
    exchange_offers:
      form.payment_mode_swap && Array.isArray(form.exchange_offers)
        ? form.exchange_offers.map((offer) => ({
            ...offer,
            property_id: offer.property_id ? Number(offer.property_id) : null,
            vehicle_year: offer.vehicle_year
              ? Number(offer.vehicle_year)
              : null,
            estimated_price: offer.estimated_price || null,
            total_area: offer.total_area || null,
            covered_area: offer.covered_area || null,
            bedrooms: offer.bedrooms || null,
            bathrooms: offer.bathrooms || null,
            garages: offer.garages || null,
            antiquity: offer.antiquity || null,
          }))
        : [],
    cash_difference_max: form.cash_difference_max || null,
    cash_difference_currency: form.cash_difference_currency || "USD",
    open_to_other_zones: !!form.open_to_other_zones,
    notes: String(form.notes || "").trim() || null,
    property_types: Array.isArray(form.property_types)
      ? form.property_types
      : [],
    amenities: normalizeAmenities(form.amenities),
  };
}

export function buildSearchRequestAIDraft(form) {
  return {
    search_request: {
      title: String(form.title || "").trim(),

      description: String(form.description || "").trim(),

      property_condition: form.property_condition || "any",

      urgency: form.urgency || "medium",

      notes: String(form.notes || "").trim() || null,

      property_types: Array.isArray(form.property_types)
        ? form.property_types
        : [],

      location: {
        country: String(form.country || "").trim(),

        province: String(form.province || "").trim(),

        city: String(form.city || "").trim() || null,

        zone: String(form.zone || "").trim() || null,

        open_to_other_zones: !!form.open_to_other_zones,
      },

      budget: {
        currency: form.currency || "USD",

        min: form.min_value || null,

        max: form.max_value || null,
      },

      criteria: {
        min_total_area: form.min_total_area || null,

        min_covered_area: form.min_covered_area || null,

        min_bedrooms: form.min_bedrooms || null,

        min_bathrooms: form.min_bathrooms || null,

        min_garages: form.min_garages || null,

        max_antiquity: form.max_antiquity || null,

        amenities: normalizeAmenities(form.amenities),
      },

      payment: {
        cash: !!form.payment_mode_cash,

        swap: !!form.payment_mode_swap,

        cash_difference_max: form.cash_difference_max || null,

        cash_difference_currency: form.cash_difference_currency || "USD",
      },
    },
  };
}

export function validateSearchRequestForm(form, { requireFull = true } = {}) {
  if (!String(form.title || "").trim()) {
    throw new Error("Completá el título de la búsqueda.");
  }

  if (!String(form.description || "").trim()) {
    throw new Error("Completá la descripción.");
  }

  if (!String(form.country_code || resolveCountryCode(form.country)).trim()) {
    throw new Error("Seleccioná un país válido.");
  }

  if (!String(form.province || "").trim()) {
    throw new Error("Completá la provincia.");
  }

  if (!form.payment_mode_cash && !form.payment_mode_swap) {
    throw new Error("Seleccioná al menos una forma de pago.");
  }

  if (
    requireFull &&
    (!Array.isArray(form.property_types) || !form.property_types.length)
  ) {
    throw new Error("Seleccioná al menos un tipo de propiedad buscada.");
  }

  if (
    form.min_value !== "" &&
    form.max_value !== "" &&
    Number(form.min_value) > Number(form.max_value)
  ) {
    throw new Error("El valor mínimo no puede ser mayor al máximo.");
  }

  if (form.cash_difference_max !== "" && Number(form.cash_difference_max) < 0) {
    throw new Error("La diferencia máxima en efectivo no puede ser negativa.");
  }
}

export function formatSearchRequestPayment(formOrItem) {
  const cash =
    formOrItem?.payment_mode_cash === true ||
    formOrItem?.payment_mode_cash === 1 ||
    formOrItem?.payment_mode_cash === "1";

  const swap =
    formOrItem?.payment_mode_swap === true ||
    formOrItem?.payment_mode_swap === 1 ||
    formOrItem?.payment_mode_swap === "1";

  if (cash && swap) return "Permuta + dinero";
  if (swap) return "Permuta";
  if (cash) return "Solo dinero";
  return "Sin definir";
}

export function getSearchRequestPropertyTypeLabel(value) {
  return (
    SEARCH_REQUEST_PROPERTY_TYPES.find((item) => item.value === value)?.label ||
    value
  );
}
