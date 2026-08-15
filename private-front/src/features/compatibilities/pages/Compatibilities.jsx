import {
  useCallback,
  useEffect,
  useState,
} from "react";

import {
  getCompatibilityRecommendations,
  respondToCompatibility,
} from "../api/compatibilities.api";

import CompatibilityCard from "../components/CompatibilityCard";

export default function Compatibilities() {
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

  const loadCompatibilities =
    useCallback(async () => {
      try {
        setLoading(true);
        setError("");

        const data =
          await getCompatibilityRecommendations({
            page,
            limit: 12,
            view,
          });

        setItems(data?.items ?? []);
        setMeta(data?.meta ?? null);
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

  function changeView(nextView) {
    if (nextView === view) {
      return;
    }

    setView(nextView);
    setPage(1);
    setActionMessage("");
  }

  async function handleReactivate(item) {
    if (
      !item?.id ||
      reactivatingId
    ) {
      return;
    }

    try {
      setReactivatingId(item.id);
      setActionMessage("");
      setError("");

      await respondToCompatibility(
        item.id,
        "pending",
      );

      /*
       * Al reactivarse deja de pertenecer
       * al historial, así que recargamos
       * esta vista.
       */
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

  const total =
    meta?.total ?? items.length;

  const isHistory =
    view === "history";

  return (
    <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      {/* Header */}
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
              Oportunidades detectadas automáticamente
              entre tus búsquedas y las publicaciones
              disponibles en PermuOK.
            </p>
          </div>

          <div className="shrink-0 text-sm font-medium text-slate-500">
            {total}{" "}
            {isHistory
              ? "registro"
              : "oportunidad"}
            {total === 1 ? "" : "es"}
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="mb-6 border-b border-slate-200">
        <div className="flex gap-6">
          <button
            type="button"
            onClick={() =>
              changeView("active")
            }
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
            onClick={() =>
              changeView("history")
            }
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

      {/* Mensaje éxito */}
      {actionMessage && (
        <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {actionMessage}
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {error}
        </div>
      )}

      {/* Loading */}
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
        <div className="space-y-5">
          {items.map((item) => (
            <CompatibilityCard
              key={item.id}
              item={item}
              view={view}
              onReactivate={
                handleReactivate
              }
              reactivating={
                reactivatingId ===
                item.id
              }
            />
          ))}
        </div>
      )}

      {/* Paginación */}
      {!loading &&
        meta &&
        meta.pages > 1 && (
          <div className="mt-7 flex items-center justify-between border-t border-slate-200 pt-5">
            <button
              type="button"
              disabled={page <= 1}
              onClick={() =>
                setPage((current) =>
                  Math.max(
                    1,
                    current - 1,
                  ),
                )
              }
              className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Anterior
            </button>

            <span className="text-sm font-medium text-slate-500">
              Página {meta.page} de{" "}
              {meta.pages}
            </span>

            <button
              type="button"
              disabled={
                page >= meta.pages
              }
              onClick={() =>
                setPage((current) =>
                  Math.min(
                    meta.pages,
                    current + 1,
                  ),
                )
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