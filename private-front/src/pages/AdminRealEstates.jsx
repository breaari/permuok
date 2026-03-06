// pages/AdminRealEstates.jsx
// - Lista + tabs + counts + paginación
// - Navega al detalle en /admin/real-estates/:id
// NOTAS IMPORTANTES:
// 1) NO uses useParams acá (no hay :id en esta ruta).
// 2) No navegues con navigate(`${re.id}`) porque depende de rutas relativas.
//    Usá navigate(`/admin/real-estates/${re.id}`) y listo.
// 3) En loadList, si querés seguir usando los endpoints legacy (/pending, /approved, /rejected),
//    dejalo como está. Si migrás al nuevo /admin/real-estates?status=..., te dejo comentario.

import { useEffect, useMemo, useState, useCallback } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { api, unwrap } from "../api/http";
import { useAuth } from "../auth/AuthContext";

import RealEstateTabs from "../ui/admin/RealEstateTabs";
import RealEstateRequestsList from "../ui/admin/RealEstateRequestsList";

const TABS = [
  { key: "pending", label: "Pendientes", path: "/admin/real-estates/pending" },
  { key: "approved", label: "Aprobadas", path: "/admin/real-estates/approved" },
  { key: "rejected", label: "Rechazadas", path: "/admin/real-estates/rejected" },
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
    const rightRange = Array.from({ length: rightItemCount }, (_, i) => start + i);
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
    () => getPaginationRange({ totalPages, currentPage: page, siblingCount: 1 }),
    [totalPages, page]
  );

  return (
    <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-6 gap-4">
      <p className="text-sm text-slate-500">
        Mostrando{" "}
        <span className="font-bold text-slate-900">
          {start} - {end}
        </span>{" "}
        de <span className="font-bold text-slate-900">{total}</span> solicitudes
      </p>

      <div className="flex gap-2 items-center">
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

export default function AdminRealEstates() {
  const { user } = useAuth();
  const isAdmin = Number(user?.role) === 1;
  const navigate = useNavigate();
  if (!isAdmin) return <Navigate to="/" replace />;

  const [tab, setTab] = useState("pending");
  const currentTab = useMemo(() => TABS.find((t) => t.key === tab), [tab]);

  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  const [page, setPage] = useState(1);
  const [perPage] = useState(DEFAULT_PER_PAGE);

  // meta backend opcional: { page, per_page, total }
  const [meta, setMeta] = useState(null);

  // counts siempre visibles en tabs
  const [counts, setCounts] = useState({ pending: 0, approved: 0, rejected: 0 });

  const loadCounts = useCallback(async () => {
    try {
      const res = await api.get("/admin/real-estates/counts");
      const data = unwrap(res);
      setCounts(data?.counts ?? { pending: 0, approved: 0, rejected: 0 });
    } catch {
      // no rompas UI
    }
  }, []);

  const loadList = useCallback(
    async ({ nextPage = 1 } = {}) => {
      setErr("");
      setLoading(true);

      try {
        // LEGACY (tu setup actual):
        const res = await api.get(currentTab.path, {
          params: { page: nextPage, per_page: perPage },
        });

        // NUEVO (si migrás al endpoint unificado):
        // const res = await api.get("/admin/real-estates", {
        //   params: { status: tab, page: nextPage, per_page: perPage },
        // });

        const data = unwrap(res);
        setItems(data?.items ?? []);

        if (data?.meta?.total != null) {
          setMeta(data.meta);
          setPage(Number(data.meta.page || nextPage));
        } else {
          setMeta(null);
          setPage(nextPage);
        }
      } catch (e) {
        setErr(e?.data?.message || e?.message || "Error al cargar");
      } finally {
        setLoading(false);
      }
    },
    [currentTab.path, perPage]
  );

  // init + tab changes
  useEffect(() => {
    setPage(1);
    loadCounts();
    loadList({ nextPage: 1 });
  }, [tab, loadCounts, loadList]);

  function onChangeTab(nextTab) {
    if (nextTab === tab) return;
    setTab(nextTab);
  }

  const { visibleItems, totalForPagination, effectivePage, effectivePerPage } = useMemo(() => {
    if (meta?.total != null) {
      return {
        visibleItems: items,
        totalForPagination: Number(meta.total),
        effectivePage: Number(meta.page || page),
        effectivePerPage: Number(meta.per_page || perPage),
      };
    }

    const total = items.length;
    const start = (page - 1) * perPage;
    const end = start + perPage;

    return {
      visibleItems: items.slice(start, end),
      totalForPagination: total,
      effectivePage: page,
      effectivePerPage: perPage,
    };
  }, [items, meta, page, perPage]);

  return (
    <div className="space-y-8">
      <div className="space-y-2">
        <h1 className="text-3xl font-black tracking-tight text-slate-900">
          Solicitudes de Revisión
        </h1>
        <p className="text-slate-500">
          Gestioná las peticiones de registro y aprobación de nuevas agencias inmobiliarias.
        </p>
      </div>

      {err && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
          {err}
        </div>
      )}

      <RealEstateTabs tabs={TABS} value={tab} onChange={onChangeTab} counts={counts} />

      <RealEstateRequestsList
        loading={loading}
        items={visibleItems}
        tab={tab}
        // IMPORTANTE: la lista devuelve el id directo
        onOpenDetail={(id) => navigate(`/admin/real-estates/${id}`)}
        // Si quisieras relativa (solo si estás seguro que la ruta base es /admin/real-estates):
        // onOpenDetail={(re) => navigate(`${re.id}`)}
      />

      {!loading && totalForPagination > 0 && (
        <PaginationPro
          page={effectivePage}
          perPage={effectivePerPage}
          total={totalForPagination}
          onPageChange={(p) => {
            if (meta?.total != null) loadList({ nextPage: p });
            else setPage(p);
          }}
        />
      )}
    </div>
  );
}