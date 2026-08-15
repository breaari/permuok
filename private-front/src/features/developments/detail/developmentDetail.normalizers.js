import { resolveImageUrl } from "./developmentDetail.helpers";
import { normalizeAmenities } from "../../shared/helpers/amenities";

export function extractDevelopment(detail) {
  if (!detail || typeof detail !== "object") return null;

  if (detail.development && typeof detail.development === "object") {
    return detail.development;
  }

  if (detail.item && typeof detail.item === "object") {
    return detail.item;
  }

  if (detail.id || detail.title || detail.description) {
    return detail;
  }

  return null;
}

export function extractDevelopmentImages(detail, development) {
  const candidates = [
    detail?.images,
    detail?.development_images,
    development?.images,
    development?.development_images,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

export function extractDevelopmentUnitTypes(detail, development) {
  const candidates = [
    detail?.unit_types,
    detail?.development_unit_types,
    development?.unit_types,
    development?.development_unit_types,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

export function extractDevelopmentAmenities(detail, development) {
  const candidates = [
    detail?.amenities,
    detail?.development_amenities,
    development?.amenities,
    development?.development_amenities,
  ];

  for (const candidate of candidates) {
    const normalized = normalizeAmenities(candidate);

    if (normalized.length) {
      return normalized;
    }
  }

  return [];
}
export function getDevelopmentImages(images) {
  return (Array.isArray(images) ? images : [])
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
