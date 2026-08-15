import { Icon } from "../../../../ui/icons/Index";
import DetailSection from "./DetailSection";

function joinLocation(parts) {
  return parts.filter(Boolean).join(", ");
}

function getMapQuery({
  latitude,
  longitude,
  formattedAddress,
  address,
  zone,
  city,
  province,
  country,
}) {
  if (latitude && longitude) {
    return `${latitude},${longitude}`;
  }

  return (
    formattedAddress ||
    joinLocation([address, zone, city, province, country])
  );
}

function getMapEmbedUrl(location) {
  const query = getMapQuery(location);
  if (!query) return "";

  return `https://maps.google.com/maps?q=${encodeURIComponent(
    query
  )}&z=15&output=embed`;
}

function getMapOpenUrl(location) {
  const query = getMapQuery(location);
  if (!query) return "";

  const base = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
    query
  )}`;

  if (location.placeId) {
    return `${base}&query_place_id=${encodeURIComponent(location.placeId)}`;
  }

  return base;
}

export default function DetailMapSection({
  title = "Ubicación",
  location = {},
  showOpenButton = true,
}) {
  const mapUrl = getMapEmbedUrl(location);
  const openUrl = getMapOpenUrl(location);

  return (
    <DetailSection title={title}>
      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
        {mapUrl ? (
          <iframe
            title={title}
            src={mapUrl}
            className="h-80 w-full border-0"
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
          />
        ) : (
          <div className="flex h-80 items-center justify-center px-5 text-center text-sm font-semibold text-slate-400">
            No hay ubicación suficiente para mostrar el mapa.
          </div>
        )}
      </div>

      {showOpenButton && openUrl ? (
        <div className="mt-4">
          <a
            href={openUrl}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:opacity-90"
          >
            <Icon name="mapPin" size={14} />
            Abrir en Google Maps
          </a>
        </div>
      ) : null}
    </DetailSection>
  );
}