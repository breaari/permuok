import {
  useNavigate,
} from "react-router-dom";

import { Icon } from "../../../ui/icons/Index";

function formatMoney(
  value,
  currency = "USD",
) {
  const amount = Number(value);

  if (!Number.isFinite(amount)) {
    return "—";
  }

  return new Intl.NumberFormat(
    "es-AR",
    {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    },
  ).format(amount);
}

function buildLocation(
  location = {},
) {
  return [
    location?.zone,
    location?.city,
    location?.province,
  ]
    .filter(Boolean)
    .join(", ");
}

function getMatchMeta(
  level,
  score,
) {
  const numericScore =
    Number(score || 0);

  if (
    level === "total" ||
    numericScore >= 95
  ) {
    return {
      label: "Match total",
      text: "text-emerald-700",
      border:
        "border-emerald-500",
      badge:
        "bg-emerald-100 text-emerald-800",
    };
  }

  if (
    level === "high" ||
    numericScore >= 80
  ) {
    return {
      label: "Match alto",
      text: "text-sky-700",
      border: "border-sky-500",
      badge:
        "bg-sky-100 text-sky-800",
    };
  }

  if (
    level === "medium" ||
    numericScore >= 60
  ) {
    return {
      label: "Match medio",
      text: "text-amber-700",
      border:
        "border-amber-500",
      badge:
        "bg-amber-100 text-amber-800",
    };
  }

  return {
    label: "Match",
    text: "text-slate-600",
    border: "border-slate-400",
    badge:
      "bg-slate-100 text-slate-700",
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
      label:
        "Amenities compatibles",
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
      label:
        "Diferencia cubierta",
      priority: 7,
    },

    owner_difference_conditions: {
      label:
        "Diferencia aceptada",
      priority: 8,
    },
  };

  return (
    map[reason?.code] || {
      label:
        reason?.label ||
        "Coincidencia",
      priority: 99,
    }
  );
}

function getVisibleReasons(
  reasons = [],
) {
  return reasons
    .filter((reason) => {
      if (
        reason?.matched === true
      ) {
        return true;
      }

      if (
        Array.isArray(
          reason?.matched,
        ) &&
        reason.matched.length > 0
      ) {
        return true;
      }

      return false;
    })
    .map((reason) => ({
      ...reason,
      ...getReasonMeta(reason),
    }))
    .sort(
      (a, b) =>
        a.priority - b.priority,
    )
    .slice(0, 4);
}

function MatchScore({
  score,
  meta,
}) {
  const numericScore =
    Math.round(
      Number(score || 0),
    );

  return (
    <div
      className={`flex h-24 w-24 shrink-0 flex-col items-center justify-center rounded-full border-[4px] bg-white ${meta.border}`}
    >
      <span
        className={`text-2xl font-black tracking-tight ${meta.text}`}
      >
        {numericScore}%
      </span>

      <span
        className={`mt-0.5 text-[10px] font-extrabold uppercase tracking-[0.12em] ${meta.text}`}
      >
        Match
      </span>
    </div>
  );
}

function ReasonChip({
  reason,
}) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
      <Icon
        name="check"
        size={12}
        className="text-emerald-600"
      />

      {reason.label}
    </span>
  );
}

function HistoryStatus({
  item,
}) {
  if (
    item?.status === "archived"
  ) {
    return (
      <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
        <p className="text-sm font-bold text-slate-700">
          Compatibilidad archivada
        </p>

        <p className="mt-1 text-xs text-slate-500">
          Este match dejó de estar
          disponible por cambios en
          alguna de las publicaciones.
        </p>
      </div>
    );
  }

  if (
    item?.my_response ===
    "dismissed"
  ) {
    return (
      <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
        <p className="text-sm font-bold text-slate-700">
          Descartaste este match
        </p>

        <p className="mt-1 text-xs text-slate-500">
          Podés volver a activarlo si
          querés reconsiderar la
          oportunidad.
        </p>
      </div>
    );
  }

  if (
    item?.counterpart_response ===
    "dismissed"
  ) {
    return (
      <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
        <p className="text-sm font-bold text-slate-700">
          La otra parte no avanzó
          con este match
        </p>

        <p className="mt-1 text-xs text-slate-500">
          Conservamos el registro en
          tu historial.
        </p>
      </div>
    );
  }

  return null;
}

export default function CompatibilityCard({
  item,
  view = "active",
  onReactivate,
  reactivating = false,
}) {
  const navigate =
    useNavigate();

  const property =
    item?.property || {};

  const search =
    item?.search_request || {};

  const isSearchSide =
    item?.my_side ===
    "search_request";

  const isHistory =
    view === "history";

  const matchMeta =
    getMatchMeta(
      item?.match_level,
      item?.score,
    );

  const reasons =
    getVisibleReasons(
      item?.reasons || [],
    );

  const searchLocation =
    buildLocation(
      search?.location,
    );

  const propertyLocation =
    buildLocation(
      property?.location,
    );

  const searchBudgetMax =
    search?.budget?.max ?? null;

  const propertyArea =
    property?.total_area ??
    property?.covered_area ??
    null;

  const canReactivate =
    Boolean(
      item?.actions
        ?.can_reactivate,
    );

  const hasConversation =
    Boolean(
      item?.actions
        ?.can_open_conversation &&
        item?.conversation_id,
    );

  function handleOpenDetail() {
    navigate(
      `/compatibilities/${item.id}`,
    );
  }

  function handleOpenConversation() {
    if (!item?.conversation_id) {
      return;
    }

    navigate(
      `/conversations/${item.conversation_id}`,
    );
  }

  return (
    <article
      className={`rounded-2xl border bg-white p-5 shadow-sm transition-all duration-300 hover:shadow-md sm:p-6 ${
        isHistory
          ? "border-slate-200"
          : "border-slate-200 hover:border-slate-300"
      }`}
    >
      <div className="flex flex-col gap-5 xl:flex-row xl:items-center">
        {/* Score */}
        <div className="flex shrink-0 items-center gap-4 xl:block">
          <MatchScore
            score={item?.score}
            meta={matchMeta}
          />

          <div className="xl:hidden">
            <span
              className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${matchMeta.badge}`}
            >
              {matchMeta.label}
            </span>

            <p className="mt-1 text-xs text-slate-400">
              Compatibilidad #
              {item.id}
            </p>
          </div>
        </div>

        {/* Contenido */}
        <div className="min-w-0 flex-1">
          <div className="mb-4 hidden items-center gap-2 xl:flex">
            <span
              className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${matchMeta.badge}`}
            >
              {matchMeta.label}
            </span>

            <span className="text-xs font-medium text-slate-400">
              #{item.id}
            </span>

            {isHistory && (
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                Historial
              </span>
            )}
          </div>

          {/* Motivos */}
          {reasons.length > 0 && (
            <div className="mb-4 flex flex-wrap gap-2">
              {reasons.map(
                (reason) => (
                  <ReasonChip
                    key={reason.code}
                    reason={reason}
                  />
                ),
              )}
            </div>
          )}

          {/* Comparación */}
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
            {/* Búsqueda */}
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div className="mb-2 flex items-center gap-2">
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200">
                  <Icon
                    name="search"
                    size={15}
                  />
                </div>

                <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
                  {isSearchSide
                    ? "Tu búsqueda"
                    : "Búsqueda compatible"}
                </span>
              </div>

              <h3 className="line-clamp-1 text-base font-extrabold text-slate-900">
                {search?.title ||
                  "Búsqueda sin título"}
              </h3>

              <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                {searchLocation && (
                  <span className="flex items-center gap-1.5">
                    <Icon
                      name="mapPin"
                      size={14}
                      className="text-slate-400"
                    />

                    {searchLocation}
                  </span>
                )}

                {searchBudgetMax !==
                  null &&
                  searchBudgetMax !==
                    undefined && (
                    <span className="font-semibold text-slate-600">
                      Hasta{" "}
                      {formatMoney(
                        searchBudgetMax,
                        search?.budget
                          ?.currency,
                      )}
                    </span>
                  )}
              </div>
            </div>

            {/* Propiedad */}
            <div className="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
              <div className="mb-2 flex items-center gap-2">
                <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-emerald-700 shadow-sm ring-1 ring-emerald-100">
                  <Icon
                    name="home"
                    size={15}
                  />
                </div>

                <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-emerald-700">
                  {isSearchSide
                    ? "Propiedad encontrada"
                    : "Tu propiedad"}
                </span>
              </div>

              <div className="flex items-start justify-between gap-3">
                <h3 className="line-clamp-1 min-w-0 text-base font-extrabold text-slate-900">
                  {property?.title ||
                    "Propiedad sin título"}
                </h3>

                <span className="shrink-0 text-base font-black text-slate-900">
                  {formatMoney(
                    property?.price,
                    property?.currency,
                  )}
                </span>
              </div>

              <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                {propertyLocation && (
                  <span className="flex items-center gap-1.5">
                    <Icon
                      name="mapPin"
                      size={14}
                      className="text-slate-400"
                    />

                    {propertyLocation}
                  </span>
                )}

                {propertyArea !== null &&
                  propertyArea !==
                    undefined && (
                    <span className="flex items-center gap-1.5">
                      <Icon
                        name="ruler"
                        size={14}
                        className="text-slate-400"
                      />

                      {Number(
                        propertyArea,
                      )}{" "}
                      m²
                    </span>
                  )}
              </div>
            </div>
          </div>

          {/* Estado histórico */}
          {isHistory && (
            <div className="mt-4">
              <HistoryStatus
                item={item}
              />
            </div>
          )}
        </div>

        {/* Acciones */}
        <div className="flex w-full shrink-0 flex-col gap-2 xl:w-auto xl:min-w-[180px]">
          {hasConversation ? (
            <button
              type="button"
              onClick={
                handleOpenConversation
              }
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:opacity-90"
            >
              Abrir conversación

              <Icon
                name="arrowRight"
                size={17}
              />
            </button>
          ) : (
            <button
              type="button"
              onClick={
                handleOpenDetail
              }
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:opacity-90"
            >
              Ver detalle

              <Icon
                name="arrowRight"
                size={17}
              />
            </button>
          )}

          {isHistory &&
            canReactivate && (
              <button
                type="button"
                disabled={
                  reactivating
                }
                onClick={() =>
                  onReactivate?.(
                    item,
                  )
                }
                className="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {reactivating
                  ? "Reactivando..."
                  : "Reactivar"}
              </button>
            )}
        </div>
      </div>
    </article>
  );
}