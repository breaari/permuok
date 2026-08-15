import {
  formatDate,
  formatStage,
  formatUnits,
} from "./developmentDetail.helpers";

export function buildDevelopmentSpecs(development, unitTypes = []) {
  return [
    {
      label: "Etapa",
      value: formatStage(development?.development_stage),
      type: "condition",
    },
    {
      label: "Entrega",
      value: formatDate(development?.delivery_date_estimated),
      type: "calendar",
    },
    {
      label: "Unidades",
      value: formatUnits(development),
      type: "home",
    },
    {
      label: "Tipologías",
      value: unitTypes.length ? `${unitTypes.length}` : "—",
      type: "layoutGrid",
    },
    {
      label: "Desarrolladora",
      value: development?.developer_name || "—",
      type: "building2",
    },
    {
      label: "Constructora",
      value: development?.construction_company || "—",
      type: "building",
    },
  ];
}

export function buildDevelopmentLocationData(development) {
  return {
    latitude: development?.latitude,
    longitude: development?.longitude,
    placeId: development?.place_id,
    formattedAddress: development?.formatted_address,
    address: development?.address,
    zone: development?.zone,
    city: development?.city,
    province: development?.province,
    country: development?.country,
  };
}