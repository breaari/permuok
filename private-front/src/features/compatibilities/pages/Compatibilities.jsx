import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";

import {
  getCompatibilityRecommendations,
  respondToCompatibility,
} from "../api/compatibilities.api";

import CompatibilityCard from "../components/CompatibilityCard";

/* =========================================================
   ICONOS
========================================================= */

function SparklesIcon({ className = "h-5 w-5" }) {
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
      <path d="M12 3l1.2 3.2L16.5 7.5l-3.3 1.3L12 12l-1.2-3.2-3.3-1.3 3.3-1.3L12 3Z" />
      <path d="M18 13l.8 2.2L21 16l-2.2.8L18 19l-.8-2.2L15 16l2.2-.8L18 13Z" />
      <path d="M6 14l.7 1.8L8.5 16.5l-1.8.7L6 19l-.7-1.8-1.8-.7 1.8-.7L6 14Z" />
    </svg>
  );
}

function ArrowRightIcon({ className = "h-4 w-4" }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </svg>
  );
}

/* =========================================================
   FECHAS
========================================================= */

function parseDate(value) {
  if (!value) {
    return null;
  }

  const normalized = String(value).replace(" ", "T");
  const date = new Date(normalized);

  return Number.isNaN(date.getTime()) ? null : date;
}

function startOfDay(date) {
  const value = new Date(date);

  value.setHours(0, 0, 0, 0);

  return value;
}

function startOfWeek(date) {
  const value = startOfDay(date);

  const day = value.getDay();
  const diff = day === 0 ? -6 : 1 - day;

  value.setDate(value.getDate() + diff);

  return value;
}

function getDateGroup(value) {
  const date = parseDate(value);

  if (!date) {
    return "Anteriores";
  }

  const now = new Date();

  const today = startOfDay(now);

  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  const currentWeek = startOfWeek(now);

  const previousWeek = new Date(currentWeek);
  previousWeek.setDate(previousWeek.getDate() - 7);

  const nextPreviousWeek = new Date(previousWeek);
  nextPreviousWeek.setDate(nextPreviousWeek.getDate() + 7);

  const itemDay = startOfDay(date);

  if (itemDay.getTime() === today.getTime()) {
    return "Hoy";
  }

  if (itemDay.getTime() === yesterday.getTime()) {
    return "Ayer";
  }

  if (itemDay >= currentWeek) {
    return "Esta semana";
  }

  if (itemDay >= previousWeek && itemDay < nextPreviousWeek) {
    return "Semana pasada";
  }

  return "Anteriores";
}

function groupItemsByDate(items) {
  const order = ["Hoy", "Ayer", "Esta semana", "Semana pasada", "Anteriores"];

  const groups = {};

  order.forEach((group) => {
    groups[group] = [];
  });

  items.forEach((item) => {
    const group = getDateGroup(item?.detected_at || item?.calculated_at);

    groups[group].push(item);
  });

  return order
    .map((label) => ({
      label,
      items: groups[label],
    }))
    .filter((group) => group.items.length > 0);
}

/* =========================================================
   SUMMARY CARD
========================================================= */

function SummaryCard({
  label,
  value,
  description,
  type = "neutral",
  badge = null,
}) {
  const styles = {
    new: {
      border: "border-emerald-200",
      dot: "bg-emerald-500",
      badge: "bg-emerald-50 text-emerald-700",
    },

    today: {
      border: "border-slate-200",
      dot: "bg-slate-300",
      badge: "bg-slate-100 text-slate-600",
    },

    attention: {
      border: "border-amber-200",
      dot: "bg-amber-400",
      badge: "bg-amber-50 text-amber-700",
    },

    progress: {
      border: "border-blue-200",
      dot: "bg-blue-500",
      badge: "bg-blue-50 text-blue-700",
    },

    neutral: {
      border: "border-slate-200",
      dot: "bg-slate-300",
      badge: "bg-slate-100 text-slate-600",
    },
  };

  const current = styles[type] || styles.neutral;

  return (
    <div
      className={`rounded-2xl border bg-white p-4 shadow-sm transition-shadow hover:shadow-md ${current.border}`}
    >
      <div className="mb-3 flex min-h-[22px] items-center justify-between">
        <span className={`h-2 w-2 rounded-full ${current.dot}`} />

        {badge && (
          <span
            className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${current.badge}`}
          >
            {badge}
          </span>
        )}
      </div>

      <div className="text-3xl font-black tracking-tight text-slate-900">
        {value}
      </div>

      <div className="mt-1 text-sm font-bold text-slate-800">{label}</div>

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

  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [view, setView] = useState("active");
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [reactivatingId, setReactivatingId] = useState(null);
  const [actionMessage, setActionMessage] = useState("");

  /* =========================================================
     LOAD
  ========================================================= */

  const loadCompatibilities = useCallback(async () => {
    try {
      setLoading(true);
      setError("");

      const data = await getCompatibilityRecommendations({
        page,
        limit: 12,
        view,
      });

      setItems(data?.items ?? []);
      setMeta(data?.meta ?? null);
    } catch (err) {
      console.error("Error cargando compatibilidades:", err);

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

  const groups = useMemo(() => groupItemsByDate(items), [items]);

  /* =========================================================
     ACTIONS
  ========================================================= */

  function changeView(nextView) {
    if (nextView === view) {
      return;
    }

    setView(nextView);
    setPage(1);
    setActionMessage("");
  }

  function handleReviewNew() {
    const firstNew = items.find((item) => item?.is_new);

    if (!firstNew?.id) {
      return;
    }

    navigate(`/compatibilities/${firstNew.id}`);
  }

  async function handleReactivate(item) {
    if (!item?.id || reactivatingId) {
      return;
    }

    try {
      setReactivatingId(item.id);
      setActionMessage("");
      setError("");

      await respondToCompatibility(item.id, "pending");

      await loadCompatibilities();

      setActionMessage(
        "La compatibilidad fue reactivada y volvió a tus oportunidades activas.",
      );
    } catch (err) {
      console.error("Error reactivando compatibilidad:", err);

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
     DERIVED DATA
  ========================================================= */

  const total = meta?.total ?? items.length;
  const summary = meta?.summary || {};
  const isHistory = view === "history";

  const newCount = Number(summary?.new || 0);
  const todayCount = Number(summary?.today || 0);
  const attentionCount = Number(summary?.needs_attention || 0);
  const progressCount = Number(summary?.in_progress || 0);

  /* =========================================================
     RENDER
  ========================================================= */

  return (
    <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      {/* =====================================================
          HEADER
      ====================================================== */}

      <div className="mb-6">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <span className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">
              Match inteligente
            </span>

            <h1 className="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
              Compatibilidades
            </h1>

            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
              Oportunidades detectadas automáticamente entre tus búsquedas y las
              publicaciones disponibles en PermuOK.
            </p>
          </div>

          <div className="shrink-0 text-sm font-medium text-slate-500">
            {total} {isHistory ? "registro" : "oportunidad"}
            {total === 1 ? "" : "es"}
          </div>
        </div>
      </div>

      {/* =====================================================
          RESUMEN
      ====================================================== */}

      {!isHistory && !loading && (
        <div className="mb-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
          <SummaryCard
            label="Nuevas"
            value={newCount}
            description="Todavía no revisadas."
            type="new"
            badge={newCount > 0 ? "Pendientes" : null}
          />

          <SummaryCard
            label="Detectadas hoy"
            value={todayCount}
            description="Nuevos matches generados hoy."
            type="today"
          />

          <SummaryCard
            label="Requieren tu atención"
            value={attentionCount}
            description="Esperan una decisión tuya."
            type="attention"
            badge={attentionCount > 0 ? "Acción" : null}
          />

          <SummaryCard
            label="En curso"
            value={progressCount}
            description="Con interés mutuo o conversación."
            type="progress"
            badge={progressCount > 0 ? "Activas" : null}
          />
        </div>
      )}

      {/* =====================================================
          NUEVAS OPORTUNIDADES
      ====================================================== */}

      {!isHistory && !loading && newCount > 0 && (
        <div className="mb-7 overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
          <div className="flex flex-col sm:flex-row sm:items-stretch">
            {/* Acento lateral */}
            <div className="hidden w-1.5 bg-emerald-500 sm:block" />

            {/* Contenido */}
            <div className="flex flex-1 items-center gap-4 p-5 sm:p-6">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                <SparklesIcon />
              </div>

              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-emerald-700">
                    Nuevas oportunidades
                  </span>

                  <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                    {newCount} nueva{newCount === 1 ? "" : "s"}
                  </span>
                </div>

                <h2 className="mt-1 text-lg font-black tracking-tight text-slate-900 sm:text-xl">
                  Tenés {newCount} match{newCount === 1 ? "" : "es"} nuevo
                  {newCount === 1 ? "" : "s"} para revisar
                </h2>

                <p className="mt-1 text-sm leading-relaxed text-slate-500">
                  PermuOK encontró nuevas coincidencias entre tus publicaciones
                  y búsquedas.
                </p>
              </div>
            </div>

            {/* CTA */}
            <div className="flex items-center border-t border-slate-100 px-5 py-4 sm:border-l sm:border-t-0 sm:px-6">
              <button
                type="button"
                onClick={handleReviewNew}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:opacity-90 sm:w-auto"
              >
                Revisar nuevas

                <ArrowRightIcon />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* =====================================================
          TABS
      ====================================================== */}

      <div className="mb-6 border-b border-slate-200">
        <div className="flex gap-6">
          <button
            type="button"
            onClick={() => changeView("active")}
            className={`relative pb-3 text-sm font-bold transition ${
              view === "active"
                ? "text-primary"
                : "text-slate-500 hover:text-slate-800"
            }`}
          >
            Activas

            {view === "active" && (
              <span className="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary" />
            )}
          </button>

          <button
            type="button"
            onClick={() => changeView("history")}
            className={`relative pb-3 text-sm font-bold transition ${
              view === "history"
                ? "text-primary"
                : "text-slate-500 hover:text-slate-800"
            }`}
          >
            Historial

            {view === "history" && (
              <span className="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary" />
            )}
          </button>
        </div>
      </div>

      {/* =====================================================
          MENSAJES
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
          CONTENIDO
      ====================================================== */}

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
          <p className="text-sm font-medium text-slate-500">
            Cargando compatibilidades...
          </p>
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
          <div className="mx-auto max-w-md">
            <h2 className="text-base font-bold text-slate-900">
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
          {groups.map((group) => (
            <section key={group.label}>
              {/* Grupo temporal */}

              <div className="mb-3 flex items-center gap-3">
                <h2 className="text-xs font-black uppercase tracking-[0.15em] text-slate-500">
                  {group.label}
                </h2>

                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">
                  {group.items.length}
                </span>

                <div className="h-px flex-1 bg-slate-200" />
              </div>

              {/* Cards */}

              <div className="space-y-4">
                {group.items.map((item) => (
                  <CompatibilityCard
                    key={item.id}
                    item={item}
                    view={view}
                    onReactivate={handleReactivate}
                    reactivating={reactivatingId === item.id}
                  />
                ))}
              </div>
            </section>
          ))}
        </div>
      )}

      {/* =====================================================
          PAGINACIÓN
      ====================================================== */}

      {!loading && meta && meta.pages > 1 && (
        <div className="mt-7 flex items-center justify-between border-t border-slate-200 pt-5">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Anterior
          </button>

          <span className="text-sm font-medium text-slate-500">
            Página {meta.page} de {meta.pages}
          </span>

          <button
            type="button"
            disabled={page >= meta.pages}
            onClick={() =>
              setPage((current) => Math.min(meta.pages, current + 1))
            }
            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Siguiente
          </button>
        </div>
      )}
    </div>
  );
}