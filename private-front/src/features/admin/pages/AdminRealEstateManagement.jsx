import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { Navigate, useNavigate } from "react-router-dom";

import { api, getErrorMessage, unwrap } from "../../../api/http.js";

import { useAuth } from "../../auth/components/AuthContext.jsx";

const TABS = [
  {
    key: "all",
    label: "Todas",
  },
  {
    key: "active",
    label: "Activas",
  },
  {
    key: "suspended",
    label: "Suspendidas",
  },
];

const DEFAULT_PER_PAGE = 10;

function membershipLabel(status) {
  switch (status) {
    case "active":
      return "Activa";

    case "pending":
      return "Pendiente";

    case "expired":
      return "Vencida";

    case "cancelled":
      return "Cancelada";

    default:
      return "Sin membresía";
  }
}

function membershipClasses(status) {
  switch (status) {
    case "active":
      return "bg-emerald-50 text-emerald-700 border-emerald-200";

    case "pending":
      return "bg-amber-50 text-amber-700 border-amber-200";

    case "expired":
      return "bg-slate-100 text-slate-600 border-slate-200";

    case "cancelled":
      return "bg-rose-50 text-rose-700 border-rose-200";

    default:
      return "bg-slate-50 text-slate-500 border-slate-200";
  }
}

function operationalLabel(status) {
  return Number(status) === 1 ? "Activa" : "Suspendida";
}

function operationalClasses(status) {
  return Number(status) === 1
    ? "bg-emerald-50 text-emerald-700 border-emerald-200"
    : "bg-rose-50 text-rose-700 border-rose-200";
}

function Pagination({ page, totalPages, onChange }) {
  if (totalPages <= 1) {
    return null;
  }

  return (
    <div className="flex items-center justify-between border-t border-slate-200 pt-5">
      <button
        type="button"
        disabled={page <= 1}
        onClick={() => onChange(page - 1)}
        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
      >
        Anterior
      </button>

      <span className="text-sm font-semibold text-slate-500">
        Página <span className="text-slate-900">{page}</span> de{" "}
        <span className="text-slate-900">{totalPages}</span>
      </span>

      <button
        type="button"
        disabled={page >= totalPages}
        onClick={() => onChange(page + 1)}
        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
      >
        Siguiente
      </button>
    </div>
  );
}

function StatCard({ label, value, active = false, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-2xl border p-5 text-left transition ${
        active
          ? "border-primary bg-primary/5 shadow-sm"
          : "border-slate-200 bg-white hover:border-slate-300"
      }`}
    >
      <p className="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">
        {label}
      </p>

      <p className="mt-2 text-3xl font-black tracking-tight text-slate-900">
        {value}
      </p>
    </button>
  );
}

export default function AdminRealEstateManagement() {
  const { user } = useAuth();
  const navigate = useNavigate();

  const isAdmin = Number(user?.role || 0) === 1;

  const [status, setStatus] = useState("all");

  const [items, setItems] = useState([]);

  const [counts, setCounts] = useState({
    total: 0,
    active: 0,
    suspended: 0,
  });

  const [loading, setLoading] = useState(true);

  const [err, setErr] = useState("");

  const [searchInput, setSearchInput] = useState("");

  const [q, setQ] = useState("");

  const [page, setPage] = useState(1);

  const [meta, setMeta] = useState(null);

  const requestIdRef = useRef(0);

  const perPage = DEFAULT_PER_PAGE;

  const loadCounts = useCallback(async (query = "") => {
    try {
      const params = new URLSearchParams();

      if (query.trim()) {
        params.set("q", query.trim());
      }

      const qs = params.toString();

      const res = await api.get(
        `/admin/real-estates/operational/counts${qs ? `?${qs}` : ""}`,
      );

      const data = unwrap(res);

      setCounts({
        total: Number(data?.counts?.total || 0),

        active: Number(data?.counts?.active || 0),

        suspended: Number(data?.counts?.suspended || 0),
      });
    } catch {
      // No bloqueamos la pantalla
      // si sólo falla el resumen.
    }
  }, []);

  const loadList = useCallback(
    async ({ nextStatus = status, nextPage = page, nextQuery = q } = {}) => {
      const requestId = ++requestIdRef.current;

      setLoading(true);
      setErr("");

      try {
        const params = new URLSearchParams({
          status: nextStatus,

          page: String(nextPage),

          per_page: String(perPage),
        });

        if (nextQuery.trim()) {
          params.set("q", nextQuery.trim());
        }

        const res = await api.get(
          `/admin/real-estates/operational?${params.toString()}`,
        );

        const data = unwrap(res);

        if (requestId !== requestIdRef.current) {
          return;
        }

        setItems(Array.isArray(data?.items) ? data.items : []);

        setMeta(data?.meta || null);

        setPage(Number(data?.meta?.page || nextPage));
      } catch (e) {
        if (requestId !== requestIdRef.current) {
          return;
        }

        setItems([]);
        setMeta(null);

        setErr(getErrorMessage(e, "No se pudieron cargar las inmobiliarias."));
      } finally {
        if (requestId === requestIdRef.current) {
          setLoading(false);
        }
      }
    },
    [status, page, q, perPage],
  );

  useEffect(() => {
    setPage(1);

    loadCounts(q);

    loadList({
      nextStatus: status,

      nextPage: 1,

      nextQuery: q,
    });
  }, [status, q, loadCounts, loadList]);

  const totalPages = useMemo(
    () => Math.max(1, Number(meta?.pages || 1)),
    [meta],
  );

  function handleStatusChange(nextStatus) {
    if (nextStatus === status) {
      return;
    }

    setStatus(nextStatus);
  }

  function handleSearchSubmit(e) {
    e.preventDefault();

    setQ(searchInput.trim());
  }

  function clearSearch() {
    setSearchInput("");
    setQ("");
  }

  if (!isAdmin) {
    return <Navigate to="/" replace />;
  }

  return (
    <div className="space-y-8">
      <div className="space-y-2">
        <h1 className="text-3xl font-black tracking-tight text-slate-900 md:text-4xl">
          Inmobiliarias
        </h1>

        <p className="max-w-3xl text-base text-slate-500">
          Gestioná las inmobiliarias habilitadas en la plataforma, su estado
          operativo, membresía y actividad.
        </p>
      </div>

      {err ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">
          {err}
        </div>
      ) : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
          label="Todas"
          value={counts.total}
          active={status === "all"}
          onClick={() => handleStatusChange("all")}
        />

        <StatCard
          label="Activas"
          value={counts.active}
          active={status === "active"}
          onClick={() => handleStatusChange("active")}
        />

        <StatCard
          label="Suspendidas"
          value={counts.suspended}
          active={status === "suspended"}
          onClick={() => handleStatusChange("suspended")}
        />
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-200 p-5">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex gap-2 overflow-x-auto">
              {TABS.map((tab) => (
                <button
                  key={tab.key}
                  type="button"
                  onClick={() => handleStatusChange(tab.key)}
                  className={`whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold transition ${
                    status === tab.key
                      ? "bg-slate-900 text-white"
                      : "bg-slate-100 text-slate-600 hover:bg-slate-200"
                  }`}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            <form
              onSubmit={handleSearchSubmit}
              className="flex w-full gap-2 lg:max-w-md"
            >
              <input
                type="text"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Buscar por nombre, CUIT, email o teléfono..."
                className="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary"
              />

              <button
                type="submit"
                className="rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white transition hover:bg-primary/90"
              >
                Buscar
              </button>

              {q ? (
                <button
                  type="button"
                  onClick={clearSearch}
                  className="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50"
                >
                  Limpiar
                </button>
              ) : null}
            </form>
          </div>
        </div>

        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500">
            Cargando inmobiliarias...
          </div>
        ) : items.length === 0 ? (
          <div className="p-8 text-center">
            <p className="font-bold text-slate-800">
              No se encontraron inmobiliarias.
            </p>

            <p className="mt-1 text-sm text-slate-500">
              Probá cambiando el filtro o la búsqueda.
            </p>
          </div>
        ) : (
          <>
            <div className="hidden overflow-x-auto lg:block">
              <table className="w-full">
                <thead className="bg-slate-50">
                  <tr className="text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
                    <th className="px-5 py-4">Inmobiliaria</th>

                    <th className="px-5 py-4">Estado</th>

                    <th className="px-5 py-4">Membresía</th>

                    <th className="px-5 py-4 text-center">Usuarios</th>

                    <th className="px-5 py-4 text-center">Propiedades</th>

                    <th className="px-5 py-4 text-center">Búsquedas</th>

                    <th className="px-5 py-4 text-center">Desarrollos</th>

                    <th className="px-5 py-4 text-right">Acción</th>
                  </tr>
                </thead>

                <tbody className="divide-y divide-slate-100">
                  {items.map((item) => (
                    <tr key={item.id} className="transition hover:bg-slate-50">
                      <td className="px-5 py-4">
                        <p className="font-bold text-slate-900">
                          {item.name ||
                            item.legal_name ||
                            `Inmobiliaria #${item.id}`}
                        </p>

                        <p className="mt-1 text-xs text-slate-500">
                          {item.email || item.cuit || "—"}
                        </p>
                      </td>

                      <td className="px-5 py-4">
                        <span
                          className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${operationalClasses(
                            item.status,
                          )}`}
                        >
                          {operationalLabel(item.status)}
                        </span>
                      </td>

                      <td className="px-5 py-4">
                        <div className="space-y-1">
                          <p className="text-sm font-bold text-slate-800">
                            {item?.plan?.name || "Sin plan"}
                          </p>

                          <span
                            className={`inline-flex rounded-full border px-2 py-0.5 text-[11px] font-bold ${membershipClasses(
                              item.membership_status,
                            )}`}
                          >
                            {membershipLabel(item.membership_status)}
                          </span>
                        </div>
                      </td>

                      <td className="px-5 py-4 text-center font-bold text-slate-700">
                        {item.users_count}
                      </td>

                      <td className="px-5 py-4 text-center font-bold text-slate-700">
                        {item.properties_count}
                      </td>

                      <td className="px-5 py-4 text-center font-bold text-slate-700">
                        {item.search_requests_count}
                      </td>

                      <td className="px-5 py-4 text-center font-bold text-slate-700">
                        {item.developments_count}
                      </td>

                      <td className="px-5 py-4 text-right">
                        <button
                          type="button"
                          onClick={() =>
                            navigate(`/admin/real-estates/${item.id}`, {
                              state: {
                                from: "/admin/real-estate-management",
                                backLabel: "Volver a inmobiliarias",
                              },
                            })
                          }
                          className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                          Ver detalle
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="divide-y divide-slate-100 lg:hidden">
              {items.map((item) => (
                <div key={item.id} className="space-y-4 p-5">
                  <div className="flex items-start justify-between gap-4">
                    <div>
                      <p className="font-bold text-slate-900">
                        {item.name || item.legal_name}
                      </p>

                      <p className="mt-1 text-xs text-slate-500">
                        {item.email || item.cuit || "—"}
                      </p>
                    </div>

                    <span
                      className={`shrink-0 rounded-full border px-2.5 py-1 text-xs font-bold ${operationalClasses(
                        item.status,
                      )}`}
                    >
                      {operationalLabel(item.status)}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                    <div>
                      <p className="text-xs text-slate-400">Plan</p>

                      <p className="font-bold text-slate-800">
                        {item?.plan?.name || "Sin plan"}
                      </p>
                    </div>

                    <div>
                      <p className="text-xs text-slate-400">Membresía</p>

                      <p className="font-bold text-slate-800">
                        {membershipLabel(item.membership_status)}
                      </p>
                    </div>

                    <div>
                      <p className="text-xs text-slate-400">Usuarios</p>

                      <p className="font-bold text-slate-800">
                        {item.users_count}
                      </p>
                    </div>

                    <div>
                      <p className="text-xs text-slate-400">Publicaciones</p>

                      <p className="font-bold text-slate-800">
                        {item.properties_count}
                      </p>
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={() =>
                      navigate(`/admin/real-estates/${item.id}`, {
                        state: {
                          from: "/admin/real-estate-management",
                          backLabel: "Volver a inmobiliarias",
                        },
                      })
                    }
                    className="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white"
                  >
                    Ver detalle
                  </button>
                </div>
              ))}
            </div>

            <div className="p-5">
              <Pagination
                page={Number(meta?.page || page)}
                totalPages={totalPages}
                onChange={(nextPage) =>
                  loadList({
                    nextStatus: status,

                    nextPage,

                    nextQuery: q,
                  })
                }
              />
            </div>
          </>
        )}
      </div>
    </div>
  );
}
