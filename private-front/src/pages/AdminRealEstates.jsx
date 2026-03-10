// pages/AdminRealEstates.jsx
import { useEffect, useMemo, useRef, useState, useCallback } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { api, unwrap } from "../api/http";
import { useAuth } from "../auth/AuthContext";

import RealEstateTabs from "../ui/admin/RealEstateTabs";
import RealEstateRequestsList from "../ui/admin/RealEstateRequestsList";

const TABS = [
  { key: "draft", label: "Borradores" },
  { key: "initial_review", label: "Revisión inicial" },
  { key: "changes_pending", label: "Cambios" },
  { key: "approved", label: "Aprobadas" },
  { key: "rejected", label: "Rechazadas" },
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
      (_, i) => start + i
    );
    return [firstPageIndex, "…", ...rightRange];
  }

  const middleRange = Array.from(
    { length: rightSiblingIndex - leftSiblingIndex + 1 },
    (_, i) => leftSiblingIndex + i
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
    [totalPages, page]
  );

  return (
    <div className="mt-6 md:mt-8 border-t border-slate-200 pt-5 md:pt-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <p className="text-sm text-slate-500">
          Mostrando{" "}
          <span className="font-bold text-slate-900">
            {start} - {end}
          </span>{" "}
          de <span className="font-bold text-slate-900">{total}</span> solicitudes
        </p>

        <div className="flex items-center justify-between gap-3 md:hidden">
          <button
            type="button"
            className="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            disabled={page <= 1}
            onClick={() => onPageChange(page - 1)}
          >
            Anterior
          </button>

          <div className="shrink-0 rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">
            {page} / {totalPages}
          </div>

          <button
            type="button"
            className="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            disabled={page >= totalPages}
            onClick={() => onPageChange(page + 1)}
          >
            Siguiente
          </button>
        </div>

        <div className="hidden md:flex gap-2 items-center">
          <button
            type="button"
            className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50"
            disabled={page <= 1}
            onClick={() => onPageChange(page - 1)}
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
          >
            <span className="text-lg leading-none">›</span>
          </button>
        </div>
      </div>
    </div>
  );
}

export default function AdminRealEstates() {
  const { user } = useAuth();
  const isAdmin = Number(user?.role) === 1;
  const navigate = useNavigate();

  if (!isAdmin) return <Navigate to="/" replace />;

  const [tab, setTab] = useState("initial_review");
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  const [page, setPage] = useState(1);
  const [perPage] = useState(DEFAULT_PER_PAGE);

  const [meta, setMeta] = useState(null);
  const [counts, setCounts] = useState({
    draft: 0,
    initial_review: 0,
    changes_pending: 0,
    approved: 0,
    rejected: 0,
  });

  const requestIdRef = useRef(0);

  const loadCounts = useCallback(async () => {
    try {
      const res = await api.get("/admin/real-estates/counts");
      const data = unwrap(res);

      setCounts(
        data?.counts ?? {
          draft: 0,
          initial_review: 0,
          changes_pending: 0,
          approved: 0,
          rejected: 0,
        }
      );
    } catch {
      // no romper UI
    }
  }, []);

  const loadList = useCallback(
    async ({ nextPage = 1 } = {}) => {
      const currentRequestId = ++requestIdRef.current;

      setErr("");
      setLoading(true);
      setItems([]);
      setMeta(null);

      try {
        const res = await api.get("/admin/real-estates", {
          params: {
            status: tab,
            page: nextPage,
            per_page: perPage,
          },
        });

        const data = unwrap(res);

        if (currentRequestId !== requestIdRef.current) return;

        setItems(Array.isArray(data?.items) ? data.items : []);
        setMeta(data?.meta ?? null);
        setPage(Number(data?.meta?.page || nextPage));
      } catch (e) {
        if (currentRequestId !== requestIdRef.current) return;

        setItems([]);
        setMeta(null);
        setErr(e?.data?.message || e?.message || "Error al cargar");
      } finally {
        if (currentRequestId === requestIdRef.current) {
          setLoading(false);
        }
      }
    },
    [tab, perPage]
  );

  useEffect(() => {
    setPage(1);
    loadCounts();
    loadList({ nextPage: 1 });
  }, [tab, loadCounts, loadList]);

  function onChangeTab(nextTab) {
    if (nextTab === tab) return;
    setTab(nextTab);
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
    <div className="space-y-6 md:space-y-8 px-4 md:px-0">
      <div className="space-y-2">
        <h1 className="text-2xl md:text-3xl font-black tracking-tight text-slate-900">
          Solicitudes de Revisión
        </h1>
        <p className="text-sm md:text-base text-slate-500 max-w-3xl">
          Gestioná borradores, revisiones iniciales, cambios pendientes y
          estados finales de las inmobiliarias.
        </p>
      </div>

      {err && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
          {err}
        </div>
      )}

      <div className="md:hidden">
        <label
          htmlFor="real-estate-status"
          className="block text-sm font-semibold text-slate-700 mb-2"
        >
          Estado
        </label>
        <select
          id="real-estate-status"
          value={tab}
          onChange={(e) => onChangeTab(e.target.value)}
          className="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-primary"
        >
          {TABS.map((t) => (
            <option key={t.key} value={t.key}>
              {t.label} ({counts?.[t.key] ?? 0})
            </option>
          ))}
        </select>
      </div>

      <div className="hidden md:block">
        <RealEstateTabs
          tabs={TABS}
          value={tab}
          onChange={onChangeTab}
          counts={counts}
        />
      </div>

      <RealEstateRequestsList
        loading={loading}
        items={visibleItems}
        tab={tab}
        onOpenDetail={(id) => navigate(`/admin/real-estates/${id}`)}
      />

      {!loading && totalForPagination > 0 && (
        <PaginationPro
          page={effectivePage}
          perPage={effectivePerPage}
          total={totalForPagination}
          onPageChange={(p) => loadList({ nextPage: p })}
        />
      )}
    </div>
  );
}