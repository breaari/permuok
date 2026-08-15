import { http, unwrap } from "../../../api/http";

export async function getExplore(filters = {}) {
  const params = {
    q: filters.q || undefined,
    opportunity_type: filters.type || filters.opportunity_type || "all",

    country: filters.country || undefined,
    province: filters.province || undefined,
    city: filters.city || undefined,
    zone: filters.zone || undefined,
    place_id: filters.place_id || undefined,
    lat: filters.lat || undefined,
    lng: filters.lng || undefined,

    property_type: filters.property_type || undefined,

    currency: filters.currency || undefined,
    value_min: filters.value_min || filters.min_price || undefined,
    value_max: filters.value_max || filters.max_price || undefined,

    bedrooms_min: filters.bedrooms_min || undefined,
    bathrooms_min: filters.bathrooms_min || undefined,
    garages_min: filters.garages_min || undefined,
    area_min: filters.area_min || undefined,

    exchange_modes: Array.isArray(filters.exchange_modes)
      ? filters.exchange_modes.join(",")
      : filters.exchange_modes || undefined,

    amenities: Array.isArray(filters.amenities)
      ? filters.amenities.join(",")
      : filters.amenities || undefined,

    development_stage: filters.development_stage || undefined,

    sort: filters.sort || "recent",
    page: filters.page || 1,
    limit: filters.limit || 12,
  };

  const res = await http.get("/explore", { params });
  return unwrap(res);
}