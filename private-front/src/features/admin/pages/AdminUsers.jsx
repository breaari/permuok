import { useEffect, useMemo, useRef, useState, useCallback } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../../../api/http.js";
import { useAuth } from "../../auth/components/AuthContext";

import AdminUsersTabs from "../components/users/AdminUsersTabs.jsx";
import AdminUsersList from "../components/users/AdminUsersList.jsx";
import AdminUserStatusModal from "../components/users/AdminUsersStatusModal.jsx";
import AdminDetailError from "../components/detail/AdminDetailError";

const TABS = [
  { key: "real_estate", label: "Inmobiliarias" },
  { key: "agent", label: "Agentes" },
  { key: "investor", label: "Inversores" },
];

const STATUS_OPTIONS = [
  { key: "all", label: "Todos" },
  { key: "active", label: "Activos" },
  { key: "inactive", label: "Inactivos" },
];

const DEFAULT_PER_PAGE = 10;

function getPaginationRange({ totalPages, currentPage, siblingCount = 1 }) {
  const totalPageNumbers = siblingCount * 2 + 5;

  if (totalPages <= totalPageNumbers) {
    return Array.from({ length: totalPages }, (_, i) => i + 1);
  }

  const leftSiblingIndex = Math.max(currentPage - siblingCount, 1);
  const rightSiblingIndex = Math.min(currentPage + siblingCount, totalPages);

  const showLeftDots = leftSiblingIndex > 2;
  const showRightDots = rightSiblingIndex < totalPages - 1;

  const firstPageIndex = 1;
  const lastPageIndex = totalPages;

  if (!showLeftDots && showRightDots) {
    const leftItemCount = 3 + siblingCount * 2;
    const leftRange = Array.from({ length: leftItemCount }, (_, i) => i + 1);
    return [...leftRange, "…", lastPageIndex];
  }

  if (showLeftDots && !showRightDots) {
    const rightItemCount = 3 + siblingCount * 2;
    const start = totalPages - rightItemCount + 1;
    const rightRange = Array.from(
      { length: rightItemCount },
      (_, i) => start + i,
    );
    return [firstPageIndex, "…", ...rightRange];
  }

  const middleRange = Array.from(
    { length: rightSiblingIndex - leftSiblingIndex + 1 },
    (_, i) => leftSiblingIndex + i,
  );

  return [firstPageIndex, "…", ...middleRange, "…", lastPageIndex];
}

function PaginationPro({ page, perPage, total, onPageChange }) {
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  if (totalPages <= 1) return null;

  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end = Math.min(total, page * perPage);

  const range = useMemo(
    () =>
      getPaginationRange({
        totalPages,
        currentPage: page,
        siblingCount: 1,
      }),
    [totalPages, page],
  );

  return (
    <div className="mt-8 flex flex-col md:flex-row md:items-center md:justify-between border-t border-slate-200 pt-6 gap-4">
      <p className="text-sm text-slate-500">
        Mostrando{" "}
        <span className="font-bold text-slate-900">
          {start} - {end}
        </span>{" "}
        de <span className="font-bold text-slate-900">{total}</span> usuarios
      </p>

      <div className="flex gap-2 items-center flex-wrap">
        <button
          type="button"
          className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
          aria-label="Anterior"
        >
          <span className="text-lg leading-none">‹</span>
        </button>

        {range.map((item, idx) => {
          if (item === "…") {
            return (
              <span
                key={`dots-${idx}`}
                className="w-10 h-10 flex items-center justify-center text-slate-400 select-none"
              >
                …
              </span>
            );
          }

          return (
            <button
              key={item}
              type="button"
              className={
                item === page
                  ? "w-10 h-10 bg-primary text-white rounded-lg font-bold"
                  : "w-10 h-10 border border-slate-200 rounded-lg hover:bg-slate-50"
              }
              onClick={() => onPageChange(item)}
            >
              {item}
            </button>
          );
        })}

        <button
          type="button"
          className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50"
          disabled={page >= totalPages}
          onClick={() => onPageChange(page + 1)}
          aria-label="Siguiente"
        >
          <span className="text-lg leading-none">›</span>
        </button>
      </div>
    </div>
  );
}

export default function AdminUsers() {
  const { user } = useAuth();
  const isAdmin = Number(user?.role) === 1;
  const navigate = useNavigate();

  if (!isAdmin) return <Navigate to="/" replace />;

  const [tab, setTab] = useState("real_estate");
  const [statusFilter, setStatusFilter] = useState("all");
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [counts, setCounts] = useState({
    real_estate: 0,
    agent: 0,
    investor: 0,
  });
  const [q, setQ] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");

  const [page, setPage] = useState(1);
  const [perPage] = useState(DEFAULT_PER_PAGE);

  const [statusModalOpen, setStatusModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);
  const [statusBusy, setStatusBusy] = useState(false);

  const requestIdRef = useRef(0);

  const loadCounts = useCallback(async (query = "", nextStatus = "all") => {
    try {
      const params = new URLSearchParams();

      if (query?.trim()) {
        params.set("q", query.trim());
      }

      if (nextStatus && nextStatus !== "all") {
        params.set("status", nextStatus);
      }

      const qs = params.toString();
      const res = await api.get(`/admin/users/counts${qs ? `?${qs}` : ""}`);
      const data = unwrap(res);

      setCounts(
        data?.counts ?? {
          real_estate: 0,
          agent: 0,
          investor: 0,
        },
      );
    } catch {
      // no romper UI
    }
  }, []);

  const loadList = useCallback(
    async ({
      nextPage = 1,
      nextQuery = q,
      nextTab = tab,
      nextStatus = statusFilter,
    } = {}) => {
      const currentRequestId = ++requestIdRef.current;

      setErr("");
      setLoading(true);
      setItems([]);
      setMeta(null);

      try {
        const params = new URLSearchParams({
          role: nextTab,
          page: String(nextPage),
          per_page: String(perPage),
          status: nextStatus,
        });

        if (nextQuery?.trim()) {
          params.set("q", nextQuery.trim());
        }

        const res = await api.get(`/admin/users?${params.toString()}`);
        const data = unwrap(res);

        if (currentRequestId !== requestIdRef.current) return;

        setItems(Array.isArray(data?.items) ? data.items : []);
        setMeta(data?.meta ?? null);
        setPage(Number(data?.meta?.page || nextPage));
      } catch (e) {
        if (currentRequestId !== requestIdRef.current) return;

        setItems([]);
        setMeta(null);
        setErr(getErrorMessage(e, "No se pudieron cargar los usuarios"));
      } finally {
        if (currentRequestId === requestIdRef.current) {
          setLoading(false);
        }
      }
    },
    [tab, q, statusFilter, perPage],
  );

  useEffect(() => {
    setPage(1);
    loadCounts(q, statusFilter);
    loadList({
      nextPage: 1,
      nextQuery: q,
      nextTab: tab,
      nextStatus: statusFilter,
    });
  }, [tab, q, statusFilter, loadCounts, loadList]);

  function onChangeTab(nextTab) {
    if (nextTab === tab) return;
    setTab(nextTab);
  }

  function onSearchSubmit(e) {
    e.preventDefault();
    setQ(searchInput.trim());
  }

  function openStatusModal(userRow) {
    setSelectedUser(userRow);
    setStatusModalOpen(true);
  }

  function closeStatusModal() {
    if (statusBusy) return;
    setStatusModalOpen(false);
    setSelectedUser(null);
  }

  async function handleConfirmStatusChange({ is_active, reason }) {
    if (!selectedUser) return;

    setStatusBusy(true);
    setErr("");
    setOk("");

    try {
      await api.post("/admin/users/status", {
        user_id: selectedUser.id,
        is_active,
        reason,
      });

      setOk(
        Number(is_active) === 1
          ? "Usuario activado correctamente."
          : "Usuario desactivado correctamente.",
      );

      closeStatusModal();
      await loadCounts(q, statusFilter);
      await loadList({
        nextPage: page,
        nextQuery: q,
        nextTab: tab,
        nextStatus: statusFilter,
      });
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo actualizar el estado del usuario"));
    } finally {
      setStatusBusy(false);
    }
  }

  const { visibleItems, totalForPagination, effectivePage, effectivePerPage } =
    useMemo(() => {
      return {
        visibleItems: items,
        totalForPagination: Number(meta?.total || 0),
        effectivePage: Number(meta?.page || page),
        effectivePerPage: Number(meta?.per_page || perPage),
      };
    }, [items, meta, page, perPage]);

  return (
    <>
      <div className="space-y-8">
        <div className="space-y-2">
          <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
            Usuarios
          </h1>
          <p className="text-slate-500 text-base">
            Administrá inmobiliarias, agentes e inversores desde un único panel.
          </p>
        </div>

        {err && <AdminDetailError message={err} />}

        {ok && (
          <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {ok}
          </div>
        )}

        <div className="space-y-6">
          <AdminUsersTabs
            tabs={TABS}
            value={tab}
            onChange={onChangeTab}
            counts={counts}
          />

          <div className="flex flex-col lg:flex-row gap-4 lg:items-center">
            <form
              onSubmit={onSearchSubmit}
              className="flex w-full lg:w-auto lg:min-w-[420px] gap-2"
            >
              <input
                type="text"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="Buscar usuarios..."
                className="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-primary"
              />

              <button
                type="submit"
                className="rounded-lg bg-primary px-5 py-3 text-sm font-bold text-white hover:bg-primary/90 transition-colors"
              >
                Buscar
              </button>
            </form>

            <div className="flex flex-wrap gap-2">
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-medium outline-none focus:border-primary"
              >
                {STATUS_OPTIONS.map((option) => (
                  <option key={option.key} value={option.key}>
                    Estado de cuenta: {option.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        <AdminUsersList
          loading={loading}
          items={visibleItems}
          tab={tab}
          onOpenDetail={(id) => navigate(`/admin/users/${id}`)}
          onToggleStatus={openStatusModal}
        />

        {!loading && totalForPagination > 0 && (
          <PaginationPro
            page={effectivePage}
            perPage={effectivePerPage}
            total={totalForPagination}
            onPageChange={(p) =>
              loadList({
                nextPage: p,
                nextQuery: q,
                nextTab: tab,
                nextStatus: statusFilter,
              })
            }
          />
        )}
      </div>

      <AdminUserStatusModal
        open={statusModalOpen}
        user={selectedUser}
        busy={statusBusy}
        onClose={closeStatusModal}
        onConfirm={handleConfirmStatusChange}
      />
    </>
  );
}
