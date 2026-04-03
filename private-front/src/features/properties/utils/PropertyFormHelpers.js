export const PROPERTY_TYPES = [
  { value: "house", label: "Casa" },
  { value: "apartment", label: "Departamento" },
  { value: "land", label: "Terreno" },
  { value: "commercial", label: "Local comercial" },
  { value: "office", label: "Oficina" },
  { value: "warehouse", label: "Galpón" },
  { value: "other", label: "Otro" },
];

export const CURRENCIES = [
  { value: "USD", label: "USD" },
  { value: "ARS", label: "ARS" },
];

export const ALLOWED_COUNTRIES = [
  { value: "Argentina", label: "Argentina" },
  { value: "Estados Unidos", label: "Estados Unidos" },
  { value: "Italia", label: "Italia" },
];

export const CASH_DIFFERENCE_DIRECTIONS = [
  { value: "a_favor", label: "Dinero a favor" },
  { value: "en_contra", label: "Puedo poner diferencia" },
  { value: "indistinto", label: "Indistinto" },
];

export const PROPERTY_CONDITIONS = [
  { value: "nuevo", label: "Nuevo" },
  { value: "bueno", label: "Bueno" },
  { value: "regular", label: "Regular" },
  { value: "a_refaccionar", label: "A refaccionar" },
];

export function resolveCountryCode(country) {
  const normalized = String(country || "").trim().toLowerCase();

  if (
    normalized === "argentina" ||
    normalized === "arg" ||
    normalized.includes("argentina")
  ) {
    return "AR";
  }

  if (
    normalized === "estados unidos" ||
    normalized === "usa" ||
    normalized === "us" ||
    normalized === "eeuu" ||
    normalized.includes("estados unidos")
  ) {
    return "US";
  }

  if (
    normalized === "italia" ||
    normalized === "italy" ||
    normalized.includes("italia")
  ) {
    return "IT";
  }

  return "";
}

export function emptyLocation(country = "Argentina") {
  return {
    country_code: resolveCountryCode(country),
    country,
    province: "",
    city: "",
    zone: "",
  };
}

export function emptyRequirements() {
  return {
    criteria_mode: "open",

    accepts_total_swap: false,
    accepts_swap_plus_cash: false,
    accepts_multiple_swap: false,
    accepts_open_proposals: true,
    accepts_cash_only: false,

    cash_difference_direction: "",
    cash_difference_min: "",
    cash_difference_max: "",
    cash_difference_currency: "USD",

    price_min: "",
    price_max: "",
    price_currency: "USD",

    min_total_area: "",
    max_total_area: "",
    min_covered_area: "",
    max_covered_area: "",

    min_bedrooms: "",
    min_bathrooms: "",
    min_garages: "",
    max_antiquity: "",

    property_condition: "",
    open_to_other_zones: false,
    notes: "",

    property_types: [],
    locations: [],
  };
}

export function nullableNumber(value) {
  if (value === "" || value === null || value === undefined) return null;
  const parsed = Number(value);
  return Number.isNaN(parsed) ? null : parsed;
}
