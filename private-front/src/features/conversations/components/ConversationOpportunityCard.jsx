import { Icon } from "../../../ui/icons/Index";

const CONVERSATION_STATUSES = [
  { value: "open", label: "Abierta" },
  { value: "negotiating", label: "En negociación" },
  { value: "visit_scheduled", label: "Visita coordinada" },
  { value: "closed", label: "Cerrada" },
  { value: "discarded", label: "Descartada" },
];

function getOpportunityLabel(type) {
  switch (type) {
    case "property":
      return "Publicación";
    case "search_request":
      return "Búsqueda";
    case "development":
      return "Desarrollo";
    default:
      return "Oportunidad";
  }
}

function getOpportunityPath(conversation) {
  if (conversation?.opportunity_url) return conversation.opportunity_url;

  const type = conversation?.opportunity_type;
  const id = conversation?.opportunity_id;

  if (!type || !id) return null;

  if (type === "property") return `/explore/properties/${id}`;
  if (type === "search_request") return `/explore/search-requests/${id}`;
  if (type === "development") return `/explore/developments/${id}`;

  return null;
}

function getImageUrl(conversation) {
  const url = conversation?.opportunity_image_url;
  if (!url) return "";

  if (/^https?:\/\//i.test(url)) return url;

  const apiBase =
    import.meta.env.VITE_API_BASE_URL || "http://localhost/permuok/public";

  const cleanBase = apiBase.replace(/\/$/, "");
  const cleanUrl = url.startsWith("/") ? url : `/${url}`;

  return `${cleanBase}${cleanUrl}`;
}

function formatMoney(value, currency = "USD") {
  if (value === undefined || value === null || value === "") return null;

  const number = Number(value);
  if (Number.isNaN(number)) return null;

  try {
    return new Intl.NumberFormat("es-AR", {
      style: "currency",
      currency: currency || "USD",
      maximumFractionDigits: 0,
    }).format(number);
  } catch {
    return `${currency || "USD"} ${number.toLocaleString("es-AR")}`;
  }
}

function formatPrice(conversation) {
  const from = formatMoney(
    conversation?.opportunity_price,
    conversation?.opportunity_currency || "USD",
  );

  const to = formatMoney(
    conversation?.opportunity_price_to,
    conversation?.opportunity_currency || "USD",
  );

  if (from && to && from !== to) return `${from} - ${to}`;

  return from || to || null;
}

function formatStatus(value) {
  const map = {
    open: "Abierta",
    active: "Activa",
    published: "Publicada",
    draft: "Borrador",
    paused: "Pausada",
    archived: "Archivada",
    closed: "Cerrada",
    negotiating: "En negociación",
    visit_scheduled: "Visita coordinada",
    discarded: "Descartada",
  };

  return map[value] || value || "—";
}

export default function ConversationOpportunityCard({
  conversation,
  directionLabel,
  onNavigate,
  statusLoading = false,
  onStatusChange,
}) {
  const opportunityPath = getOpportunityPath(conversation);
  const imageUrl = getImageUrl(conversation);
  const price = formatPrice(conversation);

  const location =
    conversation?.opportunity_location ||
    conversation?.opportunity_address ||
    "";

  const conversationStatus = conversation?.status || "open";

  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:rounded-3xl">
      {imageUrl ? (
        <button
          type="button"
          onClick={() => opportunityPath && onNavigate(opportunityPath)}
          disabled={!opportunityPath}
          className="relative block h-36 w-full bg-slate-200 text-left disabled:cursor-default sm:h-40"
        >
          <img
            src={imageUrl}
            alt={conversation?.opportunity_title || "Oportunidad"}
            className="h-full w-full object-cover"
            loading="lazy"
          />

          <div className="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/10 to-transparent" />

          <span className="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[9px] font-black uppercase tracking-[0.12em] text-slate-800 shadow-sm sm:left-4 sm:top-4 sm:text-[10px]">
            {getOpportunityLabel(conversation?.opportunity_type)}
          </span>
        </button>
      ) : null}

      <div className="bg-slate-900 p-4 text-white sm:p-5">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
          <p className="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-300 sm:text-[11px] sm:tracking-[0.18em]">
            Oportunidad
          </p>

          {!imageUrl ? (
            <span className="w-fit rounded-full bg-white/10 px-3 py-1 text-[9px] font-black uppercase tracking-[0.12em] text-white/70 sm:text-[10px]">
              {getOpportunityLabel(conversation?.opportunity_type)}
            </span>
          ) : null}
        </div>

        <button
          type="button"
          onClick={() => opportunityPath && onNavigate(opportunityPath)}
          disabled={!opportunityPath}
          className="mt-3 block text-left text-base font-black leading-tight transition hover:text-emerald-300 disabled:cursor-default disabled:hover:text-white sm:text-lg"
        >
          {conversation?.opportunity_title ||
            conversation?.subject ||
            "Consulta"}
        </button>

        {location ? (
          <div className="mt-3 flex items-start gap-2 text-sm font-semibold leading-relaxed text-white/70">
            <Icon name="mapPin" size={15} className="mt-0.5 shrink-0" />
            <span className="break-words">{location}</span>
          </div>
        ) : null}

        {price ? (
          <div className="mt-4 rounded-2xl border border-white/10 bg-white/10 p-3 sm:p-4">
            <p className="text-[9px] font-black uppercase tracking-[0.14em] text-white/50 sm:text-[10px] sm:tracking-[0.16em]">
              Valor de referencia
            </p>
            <p className="mt-1 break-words text-xl font-black tracking-tight text-white sm:text-2xl">
              {price}
            </p>
          </div>
        ) : null}
      </div>

      <div className="space-y-3 p-4 text-sm sm:p-5">
        <InfoRow
          label="Referencia"
          value={`#${conversation?.opportunity_id || "—"}`}
        />

        <InfoRow label="Conversación" value={directionLabel} />

        <InfoRow
          label="Estado publicación"
          value={formatStatus(conversation?.opportunity_status)}
        />

        <div className="border-b border-slate-100 pb-3 last:border-b-0">
          <label className="mb-2 block text-sm font-semibold text-slate-500">
            Estado conversación
          </label>

          <select
            value={conversationStatus}
            disabled={statusLoading || !onStatusChange}
            onChange={(e) => onStatusChange?.(e.target.value)}
            className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 disabled:opacity-60 sm:py-2"
          >
            {CONVERSATION_STATUSES.map((item) => (
              <option key={item.value} value={item.value}>
                {item.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {opportunityPath ? (
        <div className="border-t border-slate-100 p-4 pt-0 sm:p-5 sm:pt-0">
          <button
            type="button"
            onClick={() => onNavigate(opportunityPath)}
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:opacity-90"
          >
            Ver oportunidad
            <Icon name="arrowRight" size={16} />
          </button>
        </div>
      ) : null}
    </section>
  );
}

function InfoRow({ label, value }) {
  return (
    <div className="flex flex-col gap-1 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
      <span className="font-semibold text-slate-500">{label}</span>
      <span className="break-words font-black text-slate-900 sm:text-right">
        {value || "—"}
      </span>
    </div>
  );
}