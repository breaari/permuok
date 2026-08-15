// utils/normalizePropertyDetail.js

export function normalizePropertyDetail(data) {
  const property = extractProperty(data);
  const images = extractImages(data, property);
  const amenities = extractAmenities(data, property);
  const requirements = extractRequirements(data, property);

  return {
    property,
    images,
    amenities,
    requirements,
  };
}