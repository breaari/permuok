import { resolveImageUrl } from "../../properties/detail/propertyDetail.helpers";
import { normalizeAmenities } from "../../shared/helpers/amenities";

function normalizeArray(value) {
  return Array.isArray(value) ? value : [];
}

export function extractProperty(detail) {
  if (!detail || typeof detail !== "object") return null;

  if (detail.property && typeof detail.property === "object") {
    return detail.property;
  }

  if (detail.item && typeof detail.item === "object") {
    return detail.item;
  }

  if (
    detail.id ||
    detail.title ||
    detail.description ||
    detail.property_type ||
    detail.city ||
    detail.province ||
    detail.country
  ) {
    return detail;
  }

  return null;
}

export function extractImages(detail, property) {
  const candidates = [
    detail?.images,
    detail?.property_images,
    property?.images,
    property?.property_images,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

export function extractRequirements(detail, property) {
  if (detail?.requirements && typeof detail.requirements === "object") {
    return detail.requirements;
  }

  if (
    detail?.criteria_mode ||
    detail?.accepts_total_swap !== undefined ||
    detail?.accepts_swap_plus_cash !== undefined ||
    detail?.accepts_multiple_swap !== undefined ||
    detail?.accepts_open_proposals !== undefined ||
    detail?.accepts_cash_only !== undefined
  ) {
    return detail;
  }

  if (property?.requirements && typeof property.requirements === "object") {
    return property.requirements;
  }

  return null;
}

export function extractRequirementTypes(detail, requirements) {
  const candidates = [
    detail?.requirement_property_types,
    detail?.property_requirement_property_types,
    requirements?.property_types,
    requirements?.requirement_property_types,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

export function extractRequirementLocations(detail, requirements) {
  const candidates = [
    detail?.requirement_locations,
    detail?.property_requirement_locations,
    requirements?.locations,
    requirements?.requirement_locations,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

export function extractAmenities(detail, property) {
  const candidates = [
    detail?.amenities,
    detail?.property_amenities,
    property?.amenities,
    property?.property_amenities,
  ];

  for (const candidate of candidates) {
    const normalized = normalizeAmenities(candidate);

    if (normalized.length) {
      return normalized;
    }
  }

  return [];
}

export function getPropertyImages(images) {
  return normalizeArray(images)
    .map((image) => {
      const rawUrl =
        image?.view_url ||
        image?.image_url ||
        image?.web_path ||
        image?.url ||
        image?.path ||
        image?.file_path ||
        image?.archive_path ||
        null;

      return {
        ...image,
        url: resolveImageUrl(rawUrl),
      };
    })
    .filter((image) => !!image.url);
}
