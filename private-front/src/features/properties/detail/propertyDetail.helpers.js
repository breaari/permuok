import { getApiBaseUrl } from "../../../api/http";

export function formatMoney(value, currency = "USD") {
  const amount = Number(value || 0);

  if (!Number.isFinite(amount) || amount <= 0) return "—";

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(amount);
}

export function formatDate(value) {
  if (!value) return "—";

  try {
    return new Date(value).toLocaleDateString("es-AR", {
      day: "2-digit",
      month: "2-digit",
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
      label: "Publicada",
      badgeClass: "bg-emerald-100 text-emerald-700 border-emerald-200",
    },
    paused: {
      label: "Pausada",
      badgeClass: "bg-amber-100 text-amber-700 border-amber-200",
    },
    archived: {
      label: "Archivada",
      badgeClass: "bg-slate-200 text-slate-700 border-slate-300",
    },
    closed: {
      label: "Cerrada",
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

export function propertyTypeLabel(value) {
  const map = {
    house: "Casa",
    apartment: "Departamento",
    land: "Lote",
    commercial: "Local",
    office: "Oficina",
    warehouse: "Depósito",
    country_house: "Casa quinta",
    ph: "PH",
    garage: "Cochera",
    hotel: "Hotel",
    development: "Desarrollo",
    other: "Otro",
  };

  return map[value] || value || "—";
}

export function exchangeModeLabel(requirements) {
  if (!requirements) return "Sin criterios cargados";
  if (requirements.criteria_mode === "criteria") return "Busco con criterios";
  return "Escucho propuestas";
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

export function normalizeRequirementType(type) {
  if (typeof type === "string") return type;

  if (type && typeof type === "object") {
    return type.property_type || type.value || type.code || type.name || "";
  }

  return "";
}