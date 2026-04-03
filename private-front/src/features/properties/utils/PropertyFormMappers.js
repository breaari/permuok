import { emptyRequirements, resolveCountryCode } from "./PropertyFormHelpers";

export function normalizeCountryCode(country) {
  const direct = resolveCountryCode(country);
  if (direct) return direct;

  const value = String(country || "")
    .trim()
    .toLowerCase();

  if (value === "united states" || value === "usa" || value === "us") {
    return "US";
  }

  if (value === "italy") {
    return "IT";
  }

  if (value === "argentina") {
    return "AR";
  }

  return "";
}

export function mapPropertyToForm(property) {
  return {
    title: property?.title || "",
    description: property?.description || "",
    property_type: property?.property_type || "",
    price: property?.price || "",
    currency: property?.currency || "USD",
    total_area: property?.total_area || "",
    covered_area: property?.covered_area || "",
    bedrooms: property?.bedrooms || "",
    bathrooms: property?.bathrooms || "",
    garages: property?.garages || "",
    antiquity: property?.antiquity || "",
    country: property?.country || "Argentina",
    province: property?.province || "",
    city: property?.city || "",
    zone: property?.zone || "",
    address: property?.address || "",
    formatted_address: property?.formatted_address || "",
    place_id: property?.place_id || "",
    latitude: property?.latitude || "",
    longitude: property?.longitude || "",
    status: property?.status || "draft",
  };
}

export function mapRequirementsToState(requirementsData, types, locations) {
  return {
    ...emptyRequirements(),
    criteria_mode: requirementsData?.criteria_mode || "open",
    accepts_total_swap: !!Number(requirementsData?.accepts_total_swap || 0),
    accepts_swap_plus_cash: !!Number(
      requirementsData?.accepts_swap_plus_cash || 0,
    ),
    accepts_multiple_swap: !!Number(
      requirementsData?.accepts_multiple_swap || 0,
    ),
    accepts_open_proposals: !!Number(
      requirementsData?.accepts_open_proposals || 0,
    ),
    accepts_cash_only: !!Number(requirementsData?.accepts_cash_only || 0),
    cash_difference_direction:
      requirementsData?.cash_difference_direction || "",
    cash_difference_min: requirementsData?.cash_difference_min || "",
    cash_difference_max: requirementsData?.cash_difference_max || "",
    cash_difference_currency:
      requirementsData?.cash_difference_currency || "USD",
    price_min: requirementsData?.price_min || "",
    price_max: requirementsData?.price_max || "",
    price_currency: requirementsData?.price_currency || "USD",
    min_total_area: requirementsData?.min_total_area || "",
    max_total_area: requirementsData?.max_total_area || "",
    min_covered_area: requirementsData?.min_covered_area || "",
    max_covered_area: requirementsData?.max_covered_area || "",
    min_bedrooms: requirementsData?.min_bedrooms || "",
    min_bathrooms: requirementsData?.min_bathrooms || "",
    min_garages: requirementsData?.min_garages || "",
    max_antiquity: requirementsData?.max_antiquity || "",
    open_to_other_zones: !!Number(requirementsData?.open_to_other_zones || 0),
    notes: requirementsData?.notes || "",
    property_types: Array.isArray(types) ? types : [],
    locations: Array.isArray(locations) ? locations : [],
  };
}

export function buildPropertyPayload(form) {
  return {
    title: form.title.trim(),
    description: form.description.trim(),
    property_type: form.property_type,
    price: form.price,
    currency: form.currency || "USD",

    country_code: normalizeCountryCode(form.country),
    country: form.country?.trim() || "",
    province: form.province?.trim() || "",
    city: form.city?.trim() || "",
    zone: form.zone?.trim() || "",

    address: form.address?.trim() || "",
    formatted_address: form.formatted_address?.trim() || "",
    postal_code: "",
    place_id: form.place_id || "",
    latitude: form.latitude,
    longitude: form.longitude,

    total_area: form.total_area || null,
    covered_area: form.covered_area || null,
    bedrooms: form.bedrooms || null,
    bathrooms: form.bathrooms || null,
    garages: form.garages || null,
    antiquity: form.antiquity || null,
  };
}

export function buildRequirementsPayload(requirements) {
  const criteriaMode = requirements.criteria_mode || "open";
  const rawLocations = Array.isArray(requirements.locations)
    ? requirements.locations
    : [];

  const normalizedLocations =
    criteriaMode === "criteria"
      ? rawLocations.map((loc) => ({
          country_code: normalizeCountryCode(loc.country),
          country: String(loc.country || "").trim(),
          province: String(loc.province || "").trim(),
          city: String(loc.city || "").trim(),
          zone: String(loc.zone || "").trim(),
        }))
      : [];

  const normalizedPropertyTypes =
    criteriaMode === "criteria"
      ? Array.isArray(requirements.property_types)
        ? requirements.property_types
        : []
      : [];

  return {
    criteria_mode: criteriaMode,

    accepts_total_swap: !!requirements.accepts_total_swap,
    accepts_swap_plus_cash: !!requirements.accepts_swap_plus_cash,
    accepts_multiple_swap: !!requirements.accepts_multiple_swap,
    accepts_open_proposals: !!requirements.accepts_open_proposals,
    accepts_cash_only: !!requirements.accepts_cash_only,

    cash_difference_direction: requirements.cash_difference_direction || "",
    cash_difference_min: requirements.cash_difference_min || null,
    cash_difference_max: requirements.cash_difference_max || null,
    cash_difference_currency: requirements.cash_difference_currency || "USD",

    price_min: requirements.price_min || null,
    price_max: requirements.price_max || null,
    price_currency: requirements.price_currency || "USD",

    min_total_area: requirements.min_total_area || null,
    max_total_area: requirements.max_total_area || null,
    min_covered_area: requirements.min_covered_area || null,
    max_covered_area: requirements.max_covered_area || null,

    min_bedrooms: requirements.min_bedrooms || null,
    min_bathrooms: requirements.min_bathrooms || null,
    min_garages: requirements.min_garages || null,
    max_antiquity: requirements.max_antiquity || null,

    open_to_other_zones: !!requirements.open_to_other_zones,
    preferred_zones: null,
    notes: requirements.notes?.trim() || "",

    property_types: normalizedPropertyTypes,
    locations: normalizedLocations,
  };
}

export function validatePropertyForSubmit({
  googleMapsLoaded,
  isLocationValid,
  form,
  requirements,
  isEditMode,
  images,
  existingImages,
}) {
  if (!googleMapsLoaded) {
    throw new Error("Google Maps todavía se está cargando.");
  }

  if (!isLocationValid) {
    throw new Error("Seleccioná una dirección válida desde Google Maps.");
  }

  if (!form.title.trim()) {
    throw new Error("Completá el título de la publicación.");
  }

  if (!form.description.trim()) {
    throw new Error("Completá la descripción de la propiedad.");
  }

  if (!form.property_type) {
    throw new Error("Seleccioná el tipo de propiedad.");
  }

  if (!form.price || Number(form.price) <= 0) {
    throw new Error("Completá un precio válido.");
  }

  if (!normalizeCountryCode(form.country)) {
    throw new Error("No se pudo determinar el código de país de la propiedad.");
  }

  if (!form.country?.trim() || !form.province?.trim() || !form.city?.trim()) {
    throw new Error("Completá país, provincia y ciudad de la propiedad.");
  }

  if (!isEditMode && !images.length) {
    throw new Error("Debés cargar al menos una imagen.");
  }

  if (isEditMode && !existingImages.length && !images.length) {
    throw new Error("Debés tener al menos una imagen cargada.");
  }

  const hasAnyExchangeMode =
    !!requirements.accepts_total_swap ||
    !!requirements.accepts_swap_plus_cash ||
    !!requirements.accepts_multiple_swap ||
    !!requirements.accepts_open_proposals ||
    !!requirements.accepts_cash_only;

  if (!hasAnyExchangeMode) {
    throw new Error("Debés definir al menos una modalidad de intercambio.");
  }

  if (requirements.criteria_mode === "criteria") {
    const locs = Array.isArray(requirements.locations)
      ? requirements.locations
      : [];

    if (!locs.length) {
      throw new Error("Agregá al menos una ubicación deseada.");
    }

    for (const loc of locs) {
      const country = String(loc.country || "").trim();
      const province = String(loc.province || "").trim();
      const countryCode = normalizeCountryCode(country);

      if (!country || !province || !countryCode) {
        throw new Error(
          "Cada ubicación deseada debe tener país y provincia válidos.",
        );
      }
    }
  }
}
