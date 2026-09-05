import { useEffect, useState } from "react";
import {
  Link,
  useNavigate,
  useSearchParams,
} from "react-router-dom";

import {
  api,
  unwrap,
  getErrorMessage,
} from "../../../api/http";

import { Icon } from "../../../ui/icons/Index";

const STATUS_OPTIONS = [
  {
    value: "failed",
    label: "Fallidos",
  },
  {
    value: "pending",
    label: "Pendientes",
  },
  {
    value: "processing",
    label: "Procesando",
  },
  {
    value: "completed",
    label: "Completados",
  },
];

const JOB_TYPE_LABELS = {
  property_recalculate:
    "Recalcular propiedad",

  search_request_recalculate:
    "Recalcular búsqueda",

  property_archive:
    "Archivar propiedad",

  search_request_archive:
    "Archivar búsqueda",

  property_quality_recalculate:
    "Recalcular calidad de propiedad",

  property_ai_analyze:
    "Análisis IA de propiedad",

  search_request_ai_analyze:
    "Análisis IA de búsqueda",

  development_ai_analyze:
    "Análisis IA de desarrollo",

  currency_rate_update:
    "Actualizar cotización",

  multilateral_recalculate:
    "Recalcular operaciones multilaterales",

  match_daily_digest:
    "Resumen diario de matches",
};

function jobTypeLabel(jobType) {
  if (!jobType) {
    return "Tipo desconocido";
  }

  return (
    JOB_TYPE_LABELS[jobType] ||
    jobType
  );
}

function entityLabel(job) {
  const id =
    Number(job?.entity_id || 0);

  switch (job?.job_type) {
    case "property_recalculate":
    case "property_archive":
    case "property_quality_recalculate":
    case "property_ai_analyze":
      return `Propiedad #${id}`;

    case "search_request_recalculate":
    case "search_request_archive":
    case "search_request_ai_analyze":
      return `Búsqueda #${id}`;

    case "development_ai_analyze":
      return `Desarrollo #${id}`;

    case "currency_rate_update":
      return "Cotización general";

    case "multilateral_recalculate":
      return "Proceso global";

    case "match_daily_digest":
      return "Proceso global";

    default:
      return id > 0
        ? `Entidad #${id}`
        : "Sin entidad";
  }
}

function entityUrl(job) {
  const id =
    Number(job?.entity_id || 0);

  if (id <= 0) {
    return null;
  }

  switch (job?.job_type) {
    case "property_recalculate":
    case "property_archive":
    case "property_quality_recalculate":
    case "property_ai_analyze":
      return `/properties/${id}`;

    case "search_request_recalculate":
    case "search_request_archive":
    case "search_request_ai_analyze":
      return `/search-requests/${id}`;

    case "development_ai_analyze":
      return `/developments/${id}`;

    default:
      return null;
  }
}

function statusClasses(status) {
  switch (status) {
    case "failed":
      return "bg-rose-50 text-rose-700 border-rose-200";

    case "pending":
      return "bg-amber-50 text-amber-700 border-amber-200";

    case "processing":
      return "bg-sky-50 text-sky-700 border-sky-200";

    case "completed":
      return "bg-emerald-50 text-emerald-700 border-emerald-200";

    default:
      return "bg-slate-50 text-slate-600 border-slate-200";
  }
}

function statusLabel(status) {
  switch (status) {
    case "failed":
      return "Fallido";

    case "pending":
      return "Pendiente";

    case "processing":
      return "Procesando";

    case "completed":
      return "Completado";

    default:
      return status || "Desconocido";
  }
}

function JobCard({ job }) {
  const url =
    entityUrl(job);

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-black text-slate-900">
              {jobTypeLabel(
                job.job_type
              )}
            </h3>

            <span
              className={[
                "rounded-full border px-2.5 py-1 text-xs font-black",
                statusClasses(
                  job.status
                ),
              ].join(" ")}
            >
              {statusLabel(
                job.status
              )}
            </span>
          </div>

          <p className="mt-1 text-xs font-semibold text-slate-400">
            Job #{job.id}
          </p>
        </div>

        <div className="text-right">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
            Intentos
          </p>

          <p className="mt-1 text-lg font-black text-slate-900">
            {job.attempts} /{" "}
            {job.max_attempts}
          </p>
        </div>
      </div>

      <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <p className="text-xs font-bold text-slate-400">
            Entidad
          </p>

          <p className="mt-1 text-sm font-bold text-slate-700">
            {entityLabel(job)}
          </p>
        </div>

        <div>
          <p className="text-xs font-bold text-slate-400">
            Prioridad
          </p>

          <p className="mt-1 text-sm font-bold text-slate-700">
            {job.priority}
          </p>
        </div>

        <div>
          <p className="text-xs font-bold text-slate-400">
            Creado
          </p>

          <p className="mt-1 text-sm font-bold text-slate-700">
            {job.created_at ||
              "—"}
          </p>
        </div>

        <div>
          <p className="text-xs font-bold text-slate-400">
            Actualizado
          </p>

          <p className="mt-1 text-sm font-bold text-slate-700">
            {job.updated_at ||
              "—"}
          </p>
        </div>
      </div>

      {job.reference_id ? (
        <div className="mt-4 text-sm font-semibold text-slate-500">
          Referencia: #
          {job.reference_id}
        </div>
      ) : null}

      {job.error_message ? (
        <div className="mt-5 rounded-xl border border-rose-100 bg-rose-50 p-4">
          <p className="text-xs font-black uppercase tracking-wide text-rose-500">
            Último error
          </p>

          <pre className="mt-2 whitespace-pre-wrap break-words font-sans text-sm font-semibold leading-6 text-rose-800">
            {job.error_message}
          </pre>
        </div>
      ) : null}

      {url ? (
        <div className="mt-5">
          <Link
            to={url}
            className="inline-flex items-center gap-2 text-sm font-black text-slate-700 hover:text-slate-950"
          >
            Ver entidad
            <span aria-hidden>
              →
            </span>
          </Link>
        </div>
      ) : null}
    </div>
  );
}

export default function AdminCompatibilityJobs() {
  const navigate =
    useNavigate();

  const [
    searchParams,
    setSearchParams,
  ] = useSearchParams();

  const status =
    searchParams.get("status") ||
    "failed";

  const page =
    Math.max(
      1,
      Number(
        searchParams.get("page") ||
          1
      )
    );

  const [loading, setLoading] =
    useState(true);

  const [err, setErr] =
    useState("");

  const [items, setItems] =
    useState([]);

  const [
    pagination,
    setPagination,
  ] = useState({
    page: 1,
    total_pages: 1,
    total: 0,
  });

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        setErr("");

        const res =
          await api.get(
            "/admin/system/compatibility-jobs",
            {
              params: {
                status,
                page,
                limit: 20,
              },
            }
          );

        const payload =
          unwrap(res);

        setItems(
          payload?.items || []
        );

        setPagination(
          payload?.pagination || {
            page: 1,
            total_pages: 1,
            total: 0,
          }
        );
      } catch (error) {
        setItems([]);

        setErr(
          getErrorMessage(
            error,
            "No se pudieron cargar los trabajos."
          )
        );
      } finally {
        setLoading(false);
      }
    }

    load();
  }, [status, page]);

  function changeStatus(
    nextStatus
  ) {
    setSearchParams({
      status: nextStatus,
      page: "1",
    });
  }

  function changePage(
    nextPage
  ) {
    setSearchParams({
      status,
      page: String(
        nextPage
      ),
    });
  }

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <button
          type="button"
          onClick={() =>
            navigate("/admin")
          }
          className="inline-flex items-center gap-2 text-sm font-black text-slate-500 hover:text-slate-900"
        >
          ← Volver al dashboard
        </button>

        <p className="mt-5 text-xs font-black uppercase tracking-[0.18em] text-slate-400">
          Salud del sistema
        </p>

        <h1 className="mt-2 text-2xl font-black tracking-tight text-slate-900">
          Jobs de matching
        </h1>

        <p className="mt-2 text-sm font-medium text-slate-500">
          Diagnóstico de la cola
          de compatibilidades y
          procesos asociados.
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {STATUS_OPTIONS.map(
          (option) => (
            <button
              key={
                option.value
              }
              type="button"
              onClick={() =>
                changeStatus(
                  option.value
                )
              }
              className={[
                "rounded-xl border px-4 py-2 text-sm font-black transition",
                status ===
                option.value
                  ? "border-slate-900 bg-slate-900 text-white"
                  : "border-slate-200 bg-white text-slate-600 hover:border-slate-300",
              ].join(" ")}
            >
              {option.label}
            </button>
          )
        )}
      </div>

      {err ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">
          {err}
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm font-semibold text-slate-500 shadow-sm">
          Cargando trabajos...
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
            <Icon
              name="badgeCheck"
              size={22}
            />
          </div>

          <h2 className="mt-4 text-lg font-black text-slate-900">
            No hay jobs en este estado
          </h2>

          <p className="mt-2 text-sm font-medium text-slate-500">
            Actualmente no existen
            trabajos con estado{" "}
            <strong>
              {statusLabel(
                status
              ).toLowerCase()}
            </strong>
            .
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          <p className="text-sm font-bold text-slate-500">
            {pagination.total}{" "}
            {pagination.total === 1
              ? "trabajo"
              : "trabajos"}
          </p>

          {items.map((job) => (
            <JobCard
              key={job.id}
              job={job}
            />
          ))}
        </div>
      )}

      {!loading &&
      pagination.total_pages >
        1 ? (
        <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <button
            type="button"
            disabled={
              page <= 1
            }
            onClick={() =>
              changePage(
                page - 1
              )
            }
            className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Anterior
          </button>

          <p className="text-sm font-bold text-slate-500">
            Página{" "}
            {pagination.page} de{" "}
            {
              pagination.total_pages
            }
          </p>

          <button
            type="button"
            disabled={
              page >=
              pagination.total_pages
            }
            onClick={() =>
              changePage(
                page + 1
              )
            }
            className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Siguiente
          </button>
        </div>
      ) : null}
    </div>
  );
}