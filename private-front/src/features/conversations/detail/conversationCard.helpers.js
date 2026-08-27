function parseUtcDate(value) {
  if (!value) return null;

  try {
    const raw = String(value).trim();

    // MySQL DATETIME sin zona horaria:
    // 2026-08-27 01:13:22
    //
    // El backend trabaja en UTC, por eso agregamos Z.
    const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)
      ? `${raw.replace(" ", "T")}Z`
      : raw;

    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? null : date;
  } catch {
    return null;
  }
}

export function formatConversationDate(value) {
  const date = parseUtcDate(value);

  if (!date) return "";

  try {
    const timeZone = "America/Argentina/Buenos_Aires";

    const dateKey = new Intl.DateTimeFormat("en-CA", {
      timeZone,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).format(date);

    const nowKey = new Intl.DateTimeFormat("en-CA", {
      timeZone,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).format(new Date());

    if (dateKey === nowKey) {
      return date.toLocaleTimeString("es-AR", {
        timeZone,
        hour: "2-digit",
        minute: "2-digit",
      });
    }

    return date.toLocaleDateString("es-AR", {
      timeZone,
      day: "2-digit",
      month: "2-digit",
    });
  } catch {
    return "";
  }
}

export function getOpportunityLabel(type) {
  switch (type) {
    case "property":
      return "Publicación";
    case "search_request":
      return "Búsqueda";
    case "development":
      return "Desarrollo";
    default:
      return "Conversación";
  }
}

export function getConversationStatusMeta(status) {
  const map = {
    open: ["Abierta", "bg-slate-100 text-slate-700"],
    negotiating: ["En negociación", "bg-blue-100 text-blue-700"],
    visit_scheduled: ["Visita coordinada", "bg-amber-100 text-amber-700"],
    closed: ["Cerrada", "bg-emerald-100 text-emerald-700"],
    discarded: ["Descartada", "bg-rose-100 text-rose-700"],
  };

  const [label, className] = map[status] || map.open;
  return { label, className };
}

export function buildConversationPreview(item) {
  return (
    item?.last_message_sanitized_body ||
    item?.last_message_body ||
    "Todavía no hay mensajes."
  );
}
