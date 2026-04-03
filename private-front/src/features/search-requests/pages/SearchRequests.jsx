import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { getErrorMessage } from "../../../api/http.js";
import { useAuth } from "../../auth/components/AuthContext.jsx";
import Pagination from "../../admin/components/Pagination.jsx";
import {
  deleteSearchRequest,
  listSearchRequests,
} from "../api/searchRequests.api.js";
import {
  SearchRequestCard,
  SearchRequestDeleteModal,
  SearchRequestFilters,
} from "../components";

const PAGE_SIZE = 5;

export default function SearchRequests() {
  const { user } = useAuth();
  const navigate = useNavigate();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3;

  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  const [status, setStatus] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);

  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const requestIdRef = useRef(0);

  const load = useCallback(
    async ({ nextStatus = status, nextQuery = q, nextPage = page } = {}) => {
      const currentRequestId = ++requestIdRef.current;

      setErr("");
      setLoading(true);

      try {
        const data = await listSearchRequests({
          status: nextStatus || undefined,
          q: nextQuery?.trim() || undefined,
          page: nextPage,
          limit: PAGE_SIZE,
        });

        if (currentRequestId !== requestIdRef.current) return;

        setItems(Array.isArray(data?.items) ? data.items : []);
        setMeta(data?.meta || null);
      } catch (e) {
        if (currentRequestId !== requestIdRef.current) return;
        setItems([]);
        setMeta(null);
        setErr(getErrorMessage(e, "No se pudieron cargar las búsquedas"));
      } finally {
        if (currentRequestId === requestIdRef.current) {
          setLoading(false);
        }
      }
    },
    [status, q, page],
  );

  useEffect(() => {
    load({ nextStatus: status, nextQuery: q, nextPage: page });
  }, [status, q, page, load]);

  function onSearchSubmit(e) {
    e.preventDefault();
    setPage(1);
    setQ(searchInput.trim());
  }

  function handleClearSearch() {
    setSearchInput("");
    setQ("");
    setPage(1);
  }

  function handleStatusChange(nextStatus) {
    setPage(1);
    setStatus(nextStatus);
  }

  function handlePageChange(nextPage) {
    setPage(nextPage);
  }

  function handleOpenDelete(item) {
    setSelectedRequest(item);
    setDeleteModalOpen(true);
  }

  function handleCloseDelete() {
    if (deleteLoading) return;
    setDeleteModalOpen(false);
    setSelectedRequest(null);
  }

  async function handleConfirmDelete() {
    if (!selectedRequest?.id) return;

    try {
      setDeleteLoading(true);
      setErr("");

      await deleteSearchRequest(selectedRequest.id);

      const willCurrentPageBeEmpty = items.length === 1 && page > 1;
      const targetPage = willCurrentPageBeEmpty ? page - 1 : page;

      if (targetPage !== page) {
        setPage(targetPage);
      } else {
        await load({ nextStatus: status, nextQuery: q, nextPage: page });
      }

      setDeleteModalOpen(false);
      setSelectedRequest(null);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo eliminar la búsqueda"));
    } finally {
      setDeleteLoading(false);
    }
  }

  const title = useMemo(() => {
    if (role === 3) return "Búsquedas";
    return "Mis búsquedas";
  }, [role]);

  if (!canAccess) return <Navigate to="/" replace />;

  return (
    <>
      <main className="max-w-6xl mx-auto px-4 sm:px-5 md:px-6 lg:px-12 py-6 sm:py-8 md:py-12 min-h-screen">
        <div className="mb-6 sm:mb-8 md:mb-10">
          <h1 className="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight mb-2 sm:mb-3 text-slate-900 text-center md:text-left">
            {title}
          </h1>

          <p className="text-sm sm:text-base md:text-lg text-slate-500 leading-relaxed max-w-2xl mx-auto md:mx-0 text-center md:text-left">
            Publicá los pedidos de búsqueda de tus clientes, definí cómo pueden
            pagar y gestioná el estado de cada oportunidad.
          </p>
        </div>

        <SearchRequestFilters
          status={status}
          searchInput={searchInput}
          onStatusChange={handleStatusChange}
          onSearchInputChange={setSearchInput}
          onSearchSubmit={onSearchSubmit}
          onClearSearch={handleClearSearch}
          onCreateNew={() => navigate("/search-requests/new")}
        />

        {err && (
          <div className="mb-5 sm:mb-6 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}

        {loading ? (
          <div className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 text-sm text-slate-500 shadow-sm">
            Cargando...
          </div>
        ) : !items.length ? (
          <div className="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 text-sm text-slate-500 shadow-sm">
            No hay búsquedas para mostrar.
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-5 sm:gap-6 md:gap-8">
              {items.map((item) => (
                <SearchRequestCard
                  key={item.id}
                  item={item}
                  onManage={() => navigate(`/search-requests/${item.id}`)}
                  onEdit={() => navigate(`/search-requests/${item.id}/edit`)}
                  onDelete={() => handleOpenDelete(item)}
                />
              ))}
            </div>

            <Pagination meta={meta} onChange={handlePageChange} />
          </>
        )}
      </main>

      <SearchRequestDeleteModal
        open={deleteModalOpen}
        busy={deleteLoading}
        title={selectedRequest?.title}
        onClose={handleCloseDelete}
        onConfirm={handleConfirmDelete}
      />
    </>
  );
}
