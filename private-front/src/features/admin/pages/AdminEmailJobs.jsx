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
    value: "sent",
    label: "Enviados",
  },
];

const EMAIL_TYPE_LABELS = {
  compatibility_mutual_interest:
    "Interés mutuo",

  high_match:
    "Match alto",

  match_daily_digest:
    "Resumen diario de matches",

  new_message:
    "Nuevo mensaje",

  new_conversation:
    "Nueva conversación",
};

function emailTypeLabel(type) {
  if (!type) {
    return "Email";
  }

  return (
    EMAIL_TYPE_LABELS[type] ||
    type
  );
}

function statusLabel(status) {
  switch (status) {
    case "failed":
      return "Fallido";

    case "pending":
      return "Pendiente";

    case "processing":
      return "Procesando";

    case "sent":
      return "Enviado";

    default:
      return status || "Desconocido";
  }
}

function statusClasses(status) {
  switch (status) {
    case "failed":
      return "border-rose-200 bg-rose-50 text-rose-700";

    case "pending":
      return "border-amber-200 bg-amber-50 text-amber-700";

    case "processing":
      return "border-sky-200 bg-sky-50 text-sky-700";

    case "sent":
      return "border-emerald-200 bg-emerald-50 text-emerald-700";

    default:
      return "border-slate-200 bg-slate-50 text-slate-600";
  }
}

function relatedUrl(job) {
  const id =
    Number(job?.related_id || 0);

  if (id <= 0) {
    return null;
  }

  switch (job?.related_type) {
    case "conversation":
      return `/conversations/${id}`;

    case "compatibility":
      return `/compatibilities/${id}`;

    default:
      return null;
  }
}

function relatedLabel(job) {
  const id =
    Number(job?.related_id || 0);

  if (!job?.related_type) {
    return "Sin relación";
  }

  switch (job.related_type) {
    case "conversation":
      return id > 0
        ? `Conversación #${id}`
        : "Conversación";

    case "compatibility":
      return id > 0
        ? `Compatibilidad #${id}`
        : "Compatibilidad";

    default:
      return id > 0
        ? `${job.related_type} #${id}`
        : job.related_type;
  }
}

function EmailJobCard({ job }) {
  const url =
    relatedUrl(job);

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-black text-slate-900">
              {emailTypeLabel(
                job.email_type
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
            Email job #{job.id}
          </p>

          <p className="mt-3 break-words text-sm font-black text-slate-800">
            {job.subject}
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
            Destinatario
          </p>

          <p className="mt-1 break-all text-sm font-bold text-slate-700">
            {job.recipient_name
              ? `${job.recipient_name} · `
              : ""}
            {job.email_to}
          </p>
        </div>

        <div>
          <p className="text-xs font-bold text-slate-400">
            Relacionado con
          </p>

          <p className="mt-1 text-sm font-bold text-slate-700">
            {relatedLabel(job)}
          </p>
        </div>

        <div>
          <p className="text-xs font-bold text-slate-400">
            Creado
          </p>

          <p className="mt-1 text-sm font-bold text-slate-700">
            {job.created_at || "—"}
          </p>
        </div>

        <div>
          <p className="text-xs font-bold text-slate-400">
            Enviado
          </p>

          <p className="mt-1 text-sm font-bold text-slate-700">
            {job.sent_at || "—"}
          </p>
        </div>
      </div>

      {job.last_error ? (
        <div className="mt-5 rounded-xl border border-rose-100 bg-rose-50 p-4">
          <p className="text-xs font-black uppercase tracking-wide text-rose-500">
            Último error
          </p>

          <pre className="mt-2 whitespace-pre-wrap break-words font-sans text-sm font-semibold leading-6 text-rose-800">
            {job.last_error}
          </pre>
        </div>
      ) : null}

      {url ? (
        <div className="mt-5">
          <Link
            to={url}
            className="inline-flex items-center gap-2 text-sm font-black text-slate-700 hover:text-slate-950"
          >
            Ver relacionado
            <span aria-hidden>
              →
            </span>
          </Link>
        </div>
      ) : null}
    </div>
  );
}

export default function AdminEmailJobs() {
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
            "/admin/system/email-jobs",
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
            "No se pudieron cargar los emails."
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
          Cola de emails
        </h1>

        <p className="mt-2 text-sm font-medium text-slate-500">
          Estado y diagnóstico
          de los correos generados
          por PermuOK.
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {STATUS_OPTIONS.map(
          (option) => (
            <button
              key={option.value}
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
          Cargando emails...
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
            No hay emails en este estado
          </h2>

          <p className="mt-2 text-sm font-medium text-slate-500">
            Actualmente no existen
            emails con estado{" "}
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
              ? "email"
              : "emails"}
          </p>

          {items.map((job) => (
            <EmailJobCard
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