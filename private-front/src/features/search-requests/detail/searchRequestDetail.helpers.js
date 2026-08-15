export function isTrue(value) {
  return value === true || value === 1 || value === "1";
}

export function formatMoneyRange(item) {
  const currency = item?.currency || "USD";
  const min = item?.min_value;
  const max = item?.max_value;

  const format = (value) =>
    new Intl.NumberFormat("es-AR", {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    }).format(Number(value || 0));

  if (min && max) return `${format(min)} - ${format(max)}`;
  if (min) return `Desde ${format(min)}`;
  if (max) return `Hasta ${format(max)}`;
  return "Sin referencia definida";
}

export function formatPaymentLabel(item) {
  const cash = isTrue(item?.payment_mode_cash);
  const swap = isTrue(item?.payment_mode_swap);

  if (cash && swap) return "Dinero + permuta";
  if (cash) return "Solo dinero";
  if (swap) return "Solo permuta";
  return "Sin modalidad definida";
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

export function joinLocation(parts) {
  return parts.filter(Boolean).join(", ");
}

export function boolText(value) {
  return isTrue(value) ? "Sí" : "No";
}

export function getStatusMeta(status) {
  const map = {
    draft: {
      label: "En borrador",
      className: "bg-slate-100 text-slate-700 border-slate-200",
    },
    published: {
      label: "Publicada",
      className: "bg-emerald-100 text-emerald-700 border-emerald-200",
    },
    paused: {
      label: "Pausada",
      className: "bg-amber-100 text-amber-700 border-amber-200",
    },
    archived: {
      label: "Archivada",
      className: "bg-slate-200 text-slate-700 border-slate-300",
    },
    closed: {
      label: "Cerrada",
      className: "bg-rose-100 text-rose-700 border-rose-200",
    },
    deleted: {
      label: "Eliminada",
      className: "bg-rose-100 text-rose-700 border-rose-200",
    },
  };

  return (
    map[status] || {
      label: status || "Sin estado",
      className: "bg-slate-100 text-slate-600 border-slate-200",
    }
  );
}

export function urgencyMeta(value) {
  const urgency = String(value || "").toLowerCase();

  const map = {
    low: {
      label: "Baja",
      className: "bg-slate-100 text-slate-700 border-slate-200",
    },
    medium: {
      label: "Media",
      className: "bg-amber-50 text-amber-700 border-amber-200",
    },
    high: {
      label: "Alta",
      className: "bg-rose-50 text-rose-700 border-rose-200",
    },
  };

  return (
    map[urgency] || {
      label: value || "—",
      className: "bg-slate-100 text-slate-700 border-slate-200",
    }
  );
}

export function propertyConditionLabel(value) {
  const map = {
    any: "Cualquiera",
    new: "Nuevo",
    used: "Usado",
    to_renovate: "A refaccionar",
  };

  return map[value] || value || "—";
}