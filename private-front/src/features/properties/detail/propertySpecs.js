export function buildPropertySpecs(property) {
  return [
    {
      label: "Sup. total",
      value: property?.total_area ? `${property.total_area} m²` : "—",
      type: "total_area",
    },
    {
      label: "Sup. cubierta",
      value: property?.covered_area ? `${property.covered_area} m²` : "—",
      type: "covered_area",
    },
    {
      label: "Dormitorios",
      value: property?.bedrooms || "—",
      type: "bedrooms",
    },
    {
      label: "Baños",
      value: property?.bathrooms || "—",
      type: "bathrooms",
    },
    {
      label: "Garage",
      value: property?.garages || "—",
      type: "garages",
    },
    {
      label: "Antigüedad",
      value: property?.antiquity ? `${property.antiquity} años` : "—",
      type: "antiquity",
    },
  ];
}

export function buildPropertyLocationData(property) {
  return {
    latitude: property?.latitude,
    longitude: property?.longitude,
    placeId: property?.place_id,
    formattedAddress: property?.formatted_address,
    address: property?.address,
    zone: property?.zone,
    city: property?.city,
    province: property?.province,
    country: property?.country,
  };
}