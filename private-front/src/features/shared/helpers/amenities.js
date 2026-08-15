export const AMENITIES = [
  { value: "balcony", label: "Balcón" },
  { value: "patio", label: "Patio" },
  { value: "terrace", label: "Terraza" },
  { value: "pool", label: "Pileta" },
  { value: "quincho", label: "Quincho" },
  { value: "garden", label: "Jardín" },
  { value: "barbecue", label: "Parrilla" },
  { value: "sum", label: "SUM" },
  { value: "gym", label: "Gimnasio" },
  { value: "security", label: "Seguridad" },
  { value: "doorman", label: "Portería" },
  { value: "laundry", label: "Laundry" },
  { value: "elevator", label: "Ascensor" },
  { value: "garage", label: "Cocheras" },
  { value: "storage", label: "Bauleras" },
  { value: "green_area", label: "Espacios verdes" },
  { value: "cowork", label: "Cowork" },
  { value: "kids_area", label: "Área kids" },
  { value: "pet_friendly", label: "Pet friendly" },
  { value: "rooftop", label: "Rooftop" },
  { value: "jacuzzi", label: "Jacuzzi" },
];

export function getAmenityValue(value) {
  if (value && typeof value === "object") {
    return String(
      value.amenity_code ||
        value.code ||
        value.value ||
        value.name ||
        value.label ||
        "",
    ).trim();
  }

  return String(value || "").trim();
}

export function getAmenityLabel(value) {
  const code = getAmenityValue(value);

  return (
    AMENITIES.find((item) => item.value === code)?.label ||
    code ||
    "Amenity"
  );
}

export function normalizeAmenities(value) {
  if (Array.isArray(value)) {
    return value
      .map(getAmenityValue)
      .filter(Boolean);
  }

  if (typeof value === "string" && value.trim() !== "") {
    return value
      .split(",")
      .map((item) => getAmenityValue(item))
      .filter(Boolean);
  }

  return [];
}