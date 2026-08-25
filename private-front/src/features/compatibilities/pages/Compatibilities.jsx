import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";

import { useNavigate } from "react-router-dom";

import {
  getCompatibilityRecommendations,
  respondToCompatibility,
} from "../api/compatibilities.api";

import CompatibilityCard from "../components/CompatibilityCard";

/* =========================================================
   FECHAS
========================================================= */

function parseDate(value) {
  if (!value) {
    return null;
  }

  const normalized =
    String(value).replace(" ", "T");

  const date = new Date(normalized);

  return Number.isNaN(date.getTime())
    ? null
    : date;
}

function startOfDay(date) {
  const value = new Date(date);

  value.setHours(0, 0, 0, 0);

  return value;
}

function startOfWeek(date) {
  const value = startOfDay(date);

  const day = value.getDay();

  const diff =
    day === 0 ? -6 : 1 - day;

  value.setDate(
    value.getDate() + diff,
  );

  return value;
}

function getDateGroup(value) {
  const date = parseDate(value);

  if (!date) {
    return "Anteriores";
  }

  const now = new Date();

  const today = startOfDay(now);

  const yesterday =
    new Date(today);

  yesterday.setDate(
    yesterday.getDate() - 1,
  );

  const currentWeek =
    startOfWeek(now);

  const previousWeek =
    new Date(currentWeek);

  previousWeek.setDate(
    previousWeek.getDate() - 7,
  );

  const itemDay =
    startOfDay(date);

  if (
    itemDay.getTime() ===
    today.getTime()
  ) {
    return "Hoy";
  }

  if (
    itemDay.getTime() ===
    yesterday.getTime()
  ) {
    return "Ayer";
  }

  if (itemDay >= currentWeek) {
    return "Esta semana";
  }

  if (
    itemDay >= previousWeek &&
    itemDay < currentWeek
  ) {
    return "Semana pasada";
  }

  return "Anteriores";
}

function groupItemsByDate(items) {
  const order = [
    "Hoy",
    "Ayer",
    "Esta semana",
    "Semana pasada",
    "Anteriores",
  ];

  const groups = {};

  order.forEach((group) => {
    groups[group] = [];
  });

  items.forEach((item) => {
    const group = getDateGroup(
      item?.detected_at ||
        item?.calculated_at,
    );

    groups[group].push(item);
  });

  return order
    .map((label) => ({
      label,
      items: groups[label],
    }))
    .filter(
      (group) =>
        group.items.length > 0,
    );
}

/* =========================================================
   SUMMARY
========================================================= */

function SummaryCard({
  value,
  label,
  description,
  accent = false,
}) {
  return (
    <div
      className={`rounded-2xl border bg-white p-4 shadow-sm ${
        accent
          ? "border-blue-200"
          : "border-slate-200"
      }`}
    >
      <div className="text-3xl font-black tracking-tight text-slate-900">
        {value}
      </div>

      <div className="mt-1 text-sm font-bold text-slate-800">
        {label}
      </div>

      <p className="mt-1 text-xs leading-relaxed text-slate-500">
        {description}
      </p>
    </div>
  );
}

/* =========================================================
   PAGE
========================================================= */

export default function Compatibilities() {
  const navigate = useNavigate();

  const [items, setItems] =
    useState([]);

  const [meta, setMeta] =
    useState(null);

  const [view, setView] =
    useState("active");

  const [page, setPage] =
    useState(1);

  const [loading, setLoading] =
    useState(true);

  const [error, setError] =
    useState("");

  const [
    reactivatingId,
    setReactivatingId,
  ] = useState(null);

  const [
    actionMessage,
    setActionMessage,
  ] = useState("");

  /* =========================================================
     LOAD
  ========================================================= */

  const loadCompatibilities =
    useCallback(async () => {
      try {
        setLoading(true);
        setError("");

        const data =
          await getCompatibilityRecommendations(
            {
              page,
              limit: 12,
              view,
            },
          );

        setItems(
          data?.items ?? [],
        );

        setMeta(
          data?.meta ?? null,
        );
      } catch (err) {
        console.error(
          "Error cargando compatibilidades:",
          err,
        );

        setError(
          err?.response?.data?.error ||
            err?.message ||
            "No se pudieron cargar las compatibilidades.",
        );
      } finally {
        setLoading(false);
      }
    }, [page, view]);

  useEffect(() => {
    loadCompatibilities();
  }, [loadCompatibilities]);

  /* =========================================================
     GROUPS
  ========================================================= */

  const groups = useMemo(
    () =>
      groupItemsByDate(items),
    [items],
  );

  /* =========================================================
     ACTIONS
  ========================================================= */

  function changeView(
    nextView,
  ) {
    if (nextView === view) {
      return;
    }

    setView(nextView);
    setPage(1);
    setActionMessage("");
  }

  async function handleReactivate(
    item,
  ) {
    if (
      !item?.id ||
      reactivatingId
    ) {
      return;
    }

    try {
      setReactivatingId(
        item.id,
      );

      setActionMessage("");
      setError("");

      await respondToCompatibility(
        item.id,
        "pending",
      );

      await loadCompatibilities();

      setActionMessage(
        "La compatibilidad fue reactivada y volvió a tus oportunidades activas.",
      );
    } catch (err) {
      console.error(
        "Error reactivando compatibilidad:",
        err,
      );

      setError(
        err?.response?.data?.error ||
          err?.message ||
          "No se pudo reactivar la compatibilidad.",
      );
    } finally {
      setReactivatingId(null);
    }
  }

  /* =========================================================
     DERIVED
  ========================================================= */

  const total =
    meta?.total ?? items.length;

  const summary =
    meta?.summary || {};

  const isHistory =
    view === "history";

  const newCount = Number(
    summary?.new || 0,
  );

  const todayCount = Number(
    summary?.today || 0,
  );

  const attentionCount =
    Number(
      summary?.needs_attention ||
        0,
    );

  const progressCount =
    Number(
      summary?.in_progress ||
        0,
    );

  /* =========================================================
     RENDER
  ========================================================= */

  return (
    <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      {/* =====================================================
          HEADER
      ====================================================== */}

      <div className="mb-6">
        <span className="text-xs font-bold uppercase tracking-[0.18em] text-primary">
          Match inteligente
        </span>

        <div className="mt-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
              Compatibilidades
            </h1>

            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
              Oportunidades detectadas
              automáticamente entre tus
              búsquedas y las
              publicaciones disponibles
              en PermuOK.
            </p>
          </div>

          <div className="shrink-0 text-sm font-medium text-slate-500">
            {total}{" "}
            {isHistory
              ? "registro"
              : "oportunidad"}
            {total === 1
              ? ""
              : "es"}
          </div>
        </div>
      </div>

      {/* =====================================================
          DIRECTAS / MULTILATERALES
      ====================================================== */}

      <div className="mb-7 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
        <button
          type="button"
          className="rounded-lg bg-white px-4 py-2 text-sm font-bold text-primary shadow-sm"
        >
          Directas
        </button>

        <button
          type="button"
          onClick={() =>
            navigate(
              "/compatibilities/multilateral",
            )
          }
          className="rounded-lg px-4 py-2 text-sm font-bold text-slate-500 transition hover:text-slate-900"
        >
          Multilaterales
        </button>
      </div>

      {/* =====================================================
          SUMMARY
      ====================================================== */}

      {!isHistory &&
        !loading && (
          <div className="mb-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <SummaryCard
              value={newCount}
              label="Nuevas"
              description="Todavía no revisadas."
              accent={
                newCount > 0
              }
            />

            <SummaryCard
              value={todayCount}
              label="Detectadas hoy"
              description="Nuevos matches generados hoy."
            />

            <SummaryCard
              value={
                attentionCount
              }
              label="Requieren tu atención"
              description="Esperan una decisión tuya."
              accent={
                attentionCount >
                0
              }
            />

            <SummaryCard
              value={
                progressCount
              }
              label="En curso"
              description="Con interés mutuo o conversación."
            />
          </div>
        )}

      {/* =====================================================
          ACTIVE / HISTORY
      ====================================================== */}

      <div className="mb-6 border-b border-slate-200">
        <div className="flex gap-6">
          {[
            [
              "active",
              "Activas",
            ],
            [
              "history",
              "Historial",
            ],
          ].map(
            ([key, label]) => (
              <button
                key={key}
                type="button"
                onClick={() =>
                  changeView(
                    key,
                  )
                }
                className={`relative pb-3 text-sm font-bold transition ${
                  view === key
                    ? "text-primary"
                    : "text-slate-500 hover:text-slate-800"
                }`}
              >
                {label}

                {view === key && (
                  <span className="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary" />
                )}
              </button>
            ),
          )}
        </div>
      </div>

      {/* =====================================================
          MESSAGES
      ====================================================== */}

      {actionMessage && (
        <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {actionMessage}
        </div>
      )}

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {error}
        </div>
      )}

      {/* =====================================================
          CONTENT
      ====================================================== */}

      {loading ? (
        <div className="grid gap-4 lg:grid-cols-2">
          {[1, 2, 3, 4].map(
            (item) => (
              <div
                key={item}
                className="h-[430px] animate-pulse rounded-2xl border border-slate-200 bg-white"
              />
            ),
          )}
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
          <div className="mx-auto max-w-md">
            <h2 className="text-lg font-black text-slate-900">
              {isHistory
                ? "Todavía no hay compatibilidades en tu historial"
                : "Todavía no encontramos compatibilidades"}
            </h2>

            <p className="mt-2 text-sm leading-relaxed text-slate-500">
              {isHistory
                ? "Los matches descartados o archivados van a quedar disponibles acá."
                : "Cuando aparezca una oportunidad compatible con tus búsquedas o propiedades, la vas a ver acá."}
            </p>
          </div>
        </div>
      ) : (
        <div className="space-y-8">
          {groups.map(
            (group) => (
              <section
                key={
                  group.label
                }
              >
                {/* Temporal */}

                <div className="mb-3 flex items-center gap-3">
                  <h2 className="text-xs font-black uppercase tracking-[0.15em] text-slate-500">
                    {
                      group.label
                    }
                  </h2>

                  <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">
                    {
                      group.items
                        .length
                    }
                  </span>

                  <div className="h-px flex-1 bg-slate-200" />
                </div>

                {/* Cards */}

                <div className="grid gap-4 lg:grid-cols-2">
                  {group.items.map(
                    (item) => (
                      <CompatibilityCard
                        key={
                          item.id
                        }
                        item={
                          item
                        }
                        view={
                          view
                        }
                        onReactivate={
                          handleReactivate
                        }
                        reactivating={
                          reactivatingId ===
                          item.id
                        }
                      />
                    ),
                  )}
                </div>
              </section>
            ),
          )}
        </div>
      )}

      {/* =====================================================
          PAGINATION
      ====================================================== */}

      {!loading &&
        meta &&
        Number(meta.pages) >
          1 && (
          <div className="mt-8 flex items-center justify-center gap-3">
            <button
              type="button"
              disabled={
                page <= 1
              }
              onClick={() =>
                setPage(
                  (current) =>
                    Math.max(
                      1,
                      current -
                        1,
                    ),
                )
              }
              className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Anterior
            </button>

            <span className="text-sm font-medium text-slate-500">
              Página{" "}
              {meta.page ||
                page}{" "}
              de{" "}
              {meta.pages}
            </span>

            <button
              type="button"
              disabled={
                page >=
                Number(
                  meta.pages,
                )
              }
              onClick={() =>
                setPage(
                  (current) =>
                    Math.min(
                      Number(
                        meta.pages,
                      ),
                      current +
                        1,
                    ),
                )
              }
              className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Siguiente
            </button>
          </div>
        )}
    </div>
  );
}