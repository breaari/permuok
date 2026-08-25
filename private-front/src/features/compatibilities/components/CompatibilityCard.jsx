import { useNavigate } from "react-router-dom";

import { Icon } from "../../../ui/icons/Index";

/* =========================================================
   HELPERS
========================================================= */

function formatMoney(value, currency = "USD") {
  const amount = Number(value);

  if (!Number.isFinite(amount)) {
    return "—";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: currency || "USD",
    maximumFractionDigits: 0,
  }).format(amount);
}

function buildLocation(location = {}) {
  return [location?.zone, location?.city, location?.province]
    .filter(Boolean)
    .join(", ");
}

function formatDetectedDate(value) {
  if (!value) {
    return "—";
  }

  const normalized = String(value).replace(" ", "T");

  const date = new Date(normalized);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat("es-AR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function getMatchMeta(level, score) {
  const numericScore = Number(score || 0);

  if (level === "total" || numericScore >= 95) {
    return {
      label: "Match total",
      text: "text-emerald-700",
      badge: "bg-emerald-100 text-emerald-800",
      icon: "bg-emerald-50 text-emerald-700",
    };
  }

  if (level === "high" || numericScore >= 80) {
    return {
      label: "Match alto",
      text: "text-sky-700",
      badge: "bg-sky-100 text-sky-800",
      icon: "bg-sky-50 text-sky-700",
    };
  }

  if (level === "medium" || numericScore >= 60) {
    return {
      label: "Match medio",
      text: "text-amber-700",
      badge: "bg-amber-100 text-amber-800",
      icon: "bg-amber-50 text-amber-700",
    };
  }

  return {
    label: "Match",
    text: "text-slate-600",
    badge: "bg-slate-100 text-slate-700",
    icon: "bg-slate-100 text-slate-600",
  };
}

function getReasonMeta(reason) {
  const map = {
    property_type_match: {
      label: "Tipo compatible",
      priority: 1,
    },

    exact_zone_match: {
      label: "Zona exacta",
      priority: 2,
    },

    city_match: {
      label: "Misma ciudad",
      priority: 2,
    },

    province_match: {
      label: "Misma provincia",
      priority: 2,
    },

    price_in_range: {
      label: "En presupuesto",
      priority: 3,
    },

    amenities_match: {
      label: "Amenities compatibles",
      priority: 4,
    },

    swap_mode_accepted: {
      label: "Acepta permuta",
      priority: 5,
    },

    exchange_offer_value_match: {
      label: "Valor compatible",
      priority: 6,
    },

    cash_difference_capacity: {
      label: "Diferencia cubierta",
      priority: 7,
    },

    owner_difference_conditions: {
      label: "Diferencia aceptada",
      priority: 8,
    },
  };

  return (
    map[reason?.code] || {
      label: reason?.label || "Coincidencia",
      priority: 99,
    }
  );
}

function getVisibleReasons(reasons = []) {
  return reasons
    .filter((reason) => {
      if (reason?.matched === true) {
        return true;
      }

      if (Array.isArray(reason?.matched) && reason.matched.length > 0) {
        return true;
      }

      return false;
    })
    .map((reason) => ({
      ...reason,
      ...getReasonMeta(reason),
    }))
    .sort((a, b) => a.priority - b.priority)
    .slice(0, 4);
}

/* =========================================================
   ICON
========================================================= */

function DirectMatchIcon({ className = "h-5 w-5" }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M7 7h11" />
      <path d="m15 4 3 3-3 3" />

      <path d="M17 17H6" />
      <path d="m9 14-3 3 3 3" />
    </svg>
  );
}

/* =========================================================
   STATUS
========================================================= */

function resolveStatus(item, isHistory) {
  const hasConversation =
    item?.status === "chat_enabled" && item?.conversation_id;

  const counterpartInterested =
    item?.my_response === "pending" &&
    item?.counterpart_response === "interested";

  const waitingResponse =
    item?.my_response === "interested" &&
    item?.counterpart_response === "pending";

  if (item?.status === "archived") {
    return {
      label: "Archivada",
      description:
        "La compatibilidad dejó de estar disponible por cambios en alguna publicación.",
      className: "bg-slate-100 text-slate-700",
      boxClass: "border-slate-200 bg-slate-50",
    };
  }

  if (item?.my_response === "dismissed") {
    return {
      label: "Descartada",
      description:
        "Descartaste este match. Podés reactivarlo si querés reconsiderarlo.",
      className: "bg-slate-100 text-slate-700",
      boxClass: "border-slate-200 bg-slate-50",
    };
  }

  if (item?.counterpart_response === "dismissed") {
    return {
      label: "No disponible",
      description:
        "La otra inmobiliaria decidió no avanzar con esta oportunidad.",
      className: "bg-slate-100 text-slate-700",
      boxClass: "border-slate-200 bg-slate-50",
    };
  }

  if (hasConversation) {
    return {
      label: "En curso",
      description:
        "Ambas partes mostraron interés y la conversación ya está habilitada.",
      className: "bg-blue-100 text-blue-800",
      boxClass: "border-blue-100 bg-blue-50",
    };
  }

  if (counterpartInterested) {
    return {
      label: "Requiere tu atención",
      description:
        "La otra inmobiliaria quiere avanzar. Revisá el match y decidí si también te interesa.",
      className: "bg-amber-100 text-amber-800",
      boxClass: "border-amber-200 bg-amber-50",
    };
  }

  if (waitingResponse) {
    return {
      label: "Interés registrado",
      description:
        "Ya indicaste que te interesa. Estamos esperando la decisión de la otra inmobiliaria.",
      className: "bg-sky-100 text-sky-800",
      boxClass: "border-sky-100 bg-sky-50",
    };
  }

  if (item?.is_new && !isHistory) {
    return {
      label: "Nueva",
      description:
        "Esta oportunidad todavía no fue revisada. Podés abrirla para evaluar el match.",
      className: "bg-emerald-100 text-emerald-800",
      boxClass: "border-emerald-100 bg-emerald-50",
    };
  }

  return {
    label: "Pendiente de respuesta",
    description:
      "Todavía tenés que indicar si querés avanzar con esta oportunidad.",
    className: "bg-amber-100 text-amber-800",
    boxClass: "border-slate-200 bg-slate-50",
  };
}

/* =========================================================
   REASON CHIP
========================================================= */

function ReasonChip({ reason }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
      <Icon name="check" size={12} className="text-emerald-600" />

      {reason.label}
    </span>
  );
}

/* =========================================================
   CARD
========================================================= */

export default function CompatibilityCard({
  item,
  view = "active",
  onReactivate,
  reactivating = false,
}) {
  const navigate = useNavigate();

  const property = item?.property || {};
  const search = item?.search_request || {};

  const isSearchSide = item?.my_side === "search_request";
  const isHistory = view === "history";

  const matchMeta = getMatchMeta(item?.match_level, item?.score);

  const status = resolveStatus(item, isHistory);

  const reasons = getVisibleReasons(item?.reasons || []);

  const searchLocation = buildLocation(search?.location);

  const propertyLocation = buildLocation(property?.location);

  const searchBudgetMax = search?.budget?.max ?? null;

  const propertyArea = property?.total_area ?? property?.covered_area ?? null;

  const canReactivate = Boolean(item?.actions?.can_reactivate);

  const hasConversation = Boolean(
    item?.actions?.can_open_conversation && item?.conversation_id,
  );

  const detectedLabel = formatDetectedDate(
    item?.detected_at || item?.calculated_at,
  );

  function handleOpenDetail() {
    navigate(`/compatibilities/${item.id}`);
  }

  function handleOpenConversation() {
    if (!item?.conversation_id) {
      return;
    }

    navigate(`/conversations/${item.conversation_id}`);
  }

  return (
    <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
      {/* =====================================================
          HEADER
      ====================================================== */}

      <div className="border-b border-slate-100 p-5">
        <div className="flex items-start justify-between gap-4">
          <div className="flex min-w-0 items-center gap-3">
            <div
              className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${matchMeta.icon}`}
            >
              <DirectMatchIcon />
            </div>

            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <span
                  className={`text-[10px] font-extrabold uppercase tracking-[0.16em] ${matchMeta.text}`}
                >
                  Compatibilidad directa
                </span>

                <span
                  className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${status.className}`}
                >
                  {status.label}
                </span>
              </div>

              <h2 className="mt-1 truncate text-lg font-black tracking-tight text-slate-900">
                {matchMeta.label}
              </h2>
            </div>
          </div>

          <div className="shrink-0 text-right">
            <div className="text-2xl font-black tracking-tight text-slate-900">
              {Math.round(Number(item?.score || 0))}%
            </div>

            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">
              compatibilidad
            </div>
          </div>
        </div>
      </div>

      {/* =====================================================
          BODY
      ====================================================== */}

      <div className="p-5">
        {/* Comparación */}

        <div className="grid gap-3 md:grid-cols-2">
          {/* Búsqueda */}

          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="mb-2 flex items-center gap-2">
              <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200">
                <Icon name="search" size={15} />
              </div>

              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
                {isSearchSide ? "Tu búsqueda" : "Búsqueda compatible"}
              </span>
            </div>

            <div className="line-clamp-2 min-h-[40px] font-bold leading-snug text-slate-900">
              {search?.title || "Búsqueda sin título"}
            </div>

            {searchBudgetMax !== null && searchBudgetMax !== undefined && (
              <div className="mt-1 text-sm text-slate-500">
                Hasta {formatMoney(searchBudgetMax, search?.budget?.currency)}
              </div>
            )}

            {searchLocation && (
              <div className="mt-1 truncate text-xs text-slate-400">
                {searchLocation}
              </div>
            )}
          </div>

          {/* Propiedad */}

          <div className="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
            <div className="mb-2 flex items-center gap-2">
              <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-emerald-700 shadow-sm ring-1 ring-emerald-100">
                <Icon name="home" size={15} />
              </div>

              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-emerald-700">
                {isSearchSide ? "Propiedad encontrada" : "Tu propiedad"}
              </span>
            </div>

            <div className="line-clamp-2 min-h-[40px] font-bold leading-snug text-slate-900">
              {property?.title || "Propiedad sin título"}
            </div>

            <div className="mt-1 text-sm font-medium text-slate-500">
              {formatMoney(property?.price, property?.currency)}
            </div>

            <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400">
              {propertyLocation && (
                <span className="truncate">{propertyLocation}</span>
              )}

              {propertyArea !== null && propertyArea !== undefined && (
                <span>{Number(propertyArea)} m²</span>
              )}
            </div>
          </div>
        </div>

        {/* Razones */}

        {reasons.length > 0 && (
          <div className="mt-4 flex flex-wrap gap-2">
            {reasons.map((reason) => (
              <ReasonChip key={reason.code} reason={reason} />
            ))}
          </div>
        )}

        {/* Estado */}

        <div className={`mt-4 rounded-xl border px-4 py-3 ${status.boxClass}`}>
          <div className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
            Estado de la oportunidad
          </div>

          <div className="mt-1 text-sm font-bold text-slate-800">
            {status.description}
          </div>
        </div>

        {/* Footer */}

        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
          <div className="text-xs text-slate-400">
            Detectada {detectedLabel}
            {item?.id ? ` · #${item.id}` : ""}
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {isHistory && canReactivate && (
              <button
                type="button"
                disabled={reactivating}
                onClick={() => onReactivate?.(item)}
                className="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {reactivating ? "Reactivando..." : "Reactivar"}
              </button>
            )}

            {hasConversation && (
              <button
                type="button"
                onClick={handleOpenConversation}
                className="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
              >
                Conversación
              </button>
            )}

            <button
              type="button"
              onClick={handleOpenDetail}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
            >
              Ver detalle
              <Icon name="arrowRight" size={16} />
            </button>
          </div>
        </div>
      </div>
    </article>
  );
}
