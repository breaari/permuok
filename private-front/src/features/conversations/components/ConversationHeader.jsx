import { Icon } from "../../../ui/icons/Index";

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

export default function ConversationHeader({
  conversation,
  directionLabel,
  shareStatusLabel,
  polling = false,
  onBack,
}) {
  return (
    <header className="border-b border-slate-200 bg-white p-4 sm:p-5">
      <button
        type="button"
        onClick={onBack}
        className="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-emerald-700"
      >
        <Icon name="arrowLeft" size={16} />
        Volver
      </button>

      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-[10px] sm:text-[11px] font-black uppercase tracking-[0.18em] text-emerald-600">
              {getOpportunityLabel(conversation?.opportunity_type)}
            </p>

            {polling ? (
              <span className="text-[10px] sm:text-[11px] font-semibold text-slate-400">
                Actualizando...
              </span>
            ) : null}
          </div>

          <h1 className="mt-2 break-words text-lg font-black leading-tight text-slate-900 sm:text-xl">
            {conversation?.opportunity_title ||
              conversation?.subject ||
              "Consulta"}
          </h1>

          <p className="mt-2 text-sm leading-relaxed text-slate-500">
            {directionLabel} · Conversación protegida dentro de Permuok.
          </p>
        </div>

        {shareStatusLabel ? (
          <div className="sm:shrink-0">
            <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600">
              {shareStatusLabel}
            </span>
          </div>
        ) : null}
      </div>
    </header>
  );
}
