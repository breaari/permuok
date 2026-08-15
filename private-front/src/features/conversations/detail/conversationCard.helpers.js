export function formatConversationDate(value) {
  if (!value) return "";

  try {
    const date = new Date(value);
    const now = new Date();

    const sameDay =
      date.getDate() === now.getDate() &&
      date.getMonth() === now.getMonth() &&
      date.getFullYear() === now.getFullYear();

    if (sameDay) {
      return date.toLocaleTimeString("es-AR", {
        hour: "2-digit",
        minute: "2-digit",
      });
    }

    return date.toLocaleDateString("es-AR", {
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