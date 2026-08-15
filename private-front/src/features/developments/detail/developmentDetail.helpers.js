import { getApiBaseUrl } from "../../../api/http";
import { getAmenityLabel } from "../../shared/helpers/amenities";

export function formatMoney(value, currency = "USD") {
  const amount = Number(value || 0);
  if (!Number.isFinite(amount) || amount <= 0) return "—";

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(amount);
}

export function formatPriceRange(development) {
  const from = formatMoney(
    development?.price_from,
    development?.currency || "USD",
  );
  const to = formatMoney(development?.price_to, development?.currency || "USD");

  if (from !== "—" && to !== "—") return `${from} - ${to}`;
  if (from !== "—") return `Desde ${from}`;
  if (to !== "—") return `Hasta ${to}`;
  return "Consultar";
}

export function formatDate(value) {
  if (!value) return "—";

  try {
    return new Date(`${value}T00:00:00`).toLocaleDateString("es-AR", {
      month: "long",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

export function statusMeta(status) {
  const map = {
    draft: {
      label: "En borrador",
      badgeClass: "bg-slate-100 text-slate-700 border-slate-200",
    },
    published: {
      label: "Publicado",
      badgeClass: "bg-emerald-100 text-emerald-700 border-emerald-200",
    },
    paused: {
      label: "Pausado",
      badgeClass: "bg-amber-100 text-amber-700 border-amber-200",
    },
    archived: {
      label: "Archivado",
      badgeClass: "bg-slate-200 text-slate-700 border-slate-300",
    },
    closed: {
      label: "Cerrado",
      badgeClass: "bg-rose-100 text-rose-700 border-rose-200",
    },
  };

  return (
    map[status] || {
      label: status || "Sin estado",
      badgeClass: "bg-slate-100 text-slate-700 border-slate-200",
    }
  );
}

export function formatStage(value) {
  const map = {
    land: "Lote / tierra",
    prelaunch: "Prelanzamiento",
    launch: "Lanzamiento",
    presale: "Preventa",
    under_construction: "En construcción",
    finished: "Finalizado",
  };

  return map[value] || value || "—";
}

export function unitTypeLabel(value) {
  const map = {
    apartment: "Departamento",
    house: "Casa",
    land: "Lote",
    commercial: "Local",
    office: "Oficina",
    warehouse: "Depósito",
    garage: "Cochera",
    other: "Otro",
  };

  return map[value] || value || "—";
}

export function amenityLabel(value) {
  return getAmenityLabel(value);
}

export function joinLocation(parts) {
  return parts.filter(Boolean).join(", ");
}

export function resolveImageUrl(rawUrl) {
  if (!rawUrl) return null;

  const value = String(rawUrl).trim();
  if (!value) return null;

  if (/^https?:\/\//i.test(value)) return value;

  const base = getApiBaseUrl().replace(/\/+$/, "");

  if (value.startsWith("/")) return `${base}${value}`;

  return `${base}/${value}`;
}

export function formatAreaRange(item) {
  const from =
    Number(item?.area_from || 0) > 0 ? `${Number(item.area_from)} m²` : null;
  const to =
    Number(item?.area_to || 0) > 0 ? `${Number(item.area_to)} m²` : null;

  if (from && to) return `${from} - ${to}`;
  if (from) return `Desde ${from}`;
  if (to) return `Hasta ${to}`;
  return "—";
}

export function formatUnitPrice(item) {
  const from = formatMoney(item?.price_from, item?.currency || "USD");
  const to = formatMoney(item?.price_to, item?.currency || "USD");

  if (from !== "—" && to !== "—") return `${from} - ${to}`;
  if (from !== "—") return `Desde ${from}`;
  if (to !== "—") return `Hasta ${to}`;
  return "—";
}

export function formatUnits(development) {
  const total = Number(development?.total_units || 0);
  const available = Number(development?.available_units || 0);

  if (!total && !available) return "—";
  if (!total) return `${available} disponibles`;

  const safeAvailable = Math.max(0, Math.min(available, total));
  const sold = Math.max(0, total - safeAvailable);
  const soldPercent = Math.round((sold / total) * 100);

  return `${safeAvailable} / ${total} disponibles · ${soldPercent}% vendido`;
}

export function getUnitsProgress(development) {
  const total = Number(development?.total_units || 0);
  const available = Number(development?.available_units || 0);

  if (!total) return 0;

  const sold = Math.max(0, total - available);
  return Math.max(0, Math.min(100, Math.round((sold / total) * 100)));
}
