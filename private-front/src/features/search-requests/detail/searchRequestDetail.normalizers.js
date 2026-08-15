import { normalizeAmenities } from "../../shared/helpers/amenities";

export function normalizePropertyTypes(raw) {
  if (!raw) return [];

  if (Array.isArray(raw)) {
    return raw
      .map((item) => {
        if (typeof item === "string") return item;
        if (typeof item === "object" && item !== null) {
          return (
            item.property_type || item.value || item.code || item.name || ""
          );
        }
        return "";
      })
      .filter(Boolean);
  }

  if (typeof raw === "string") {
    return raw
      .split(",")
      .map((v) => v.trim())
      .filter(Boolean);
  }

  return [];
}

export function extractRequest(detail) {
  if (!detail || typeof detail !== "object") return {};

  if (detail.search_request && typeof detail.search_request === "object") {
    return detail.search_request;
  }

  if (detail.request && typeof detail.request === "object") {
    return detail.request;
  }

  if (detail.item && typeof detail.item === "object") {
    return detail.item;
  }

  if (
    detail.id ||
    detail.title ||
    detail.description ||
    detail.status ||
    detail.city ||
    detail.zone ||
    detail.province
  ) {
    return detail;
  }

  return {};
}

export function extractPropertyTypes(detail, request) {
  const candidates = [
    detail?.property_types,
    detail?.search_request_property_types,
    detail?.types,
    request?.property_types,
  ];

  for (const candidate of candidates) {
    const normalized = normalizePropertyTypes(candidate);
    if (normalized.length) return normalized;
  }

  return [];
}

export function extractAmenities(detail, request) {
  const candidates = [
    detail?.amenities,
    detail?.search_request_amenities,
    request?.amenities,
  ];

  for (const candidate of candidates) {
    const normalized = normalizeAmenities(candidate);

    if (normalized.length) {
      return normalized;
    }
  }

  return [];
}
