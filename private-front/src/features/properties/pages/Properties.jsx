import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, Navigate } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../../../api/http.js";
import { useAuth } from "../../auth/components/AuthContext";
import PropertyCard from "../components/PropertyCard.jsx";
import PropertyDeleteModal from "../components/PropertyDeleteModal";
import Pagination from "../../admin/components/Pagination.jsx";
import { Icon } from "../../../ui/icons/Index";
import { deleteProperty } from "../api/properties.api.js";

const STATUS_OPTIONS = [
  { key: "", label: "Todas" },
  { key: "draft", label: "Borradores" },
  { key: "published", label: "Publicadas" },
  { key: "paused", label: "Pausadas" },
  { key: "archived", label: "Archivadas" },
  { key: "closed", label: "Cerradas" },
];

const PAGE_SIZE = 5;

export default function Properties() {
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
  const [selectedProperty, setSelectedProperty] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const requestIdRef = useRef(0);

  const load = useCallback(
    async ({
      nextStatus = status,
      nextQuery = q,
      nextPage = page,
    } = {}) => {
      const currentRequestId = ++requestIdRef.current;

      setErr("");
      setLoading(true);

      try {
        const res = await api.get("/properties", {
          params: {
            status: nextStatus || undefined,
            q: nextQuery?.trim() || undefined,
            page: nextPage,
            limit: PAGE_SIZE,
          },
        });

        const data = unwrap(res);

        if (currentRequestId !== requestIdRef.current) return;

        setItems(Array.isArray(data?.items) ? data.items : []);
        setMeta(data?.meta || null);
      } catch (e) {
        if (currentRequestId !== requestIdRef.current) return;
        setItems([]);
        setMeta(null);
        setErr(getErrorMessage(e, "No se pudieron cargar las publicaciones"));
      } finally {
        if (currentRequestId === requestIdRef.current) {
          setLoading(false);
        }
      }
    },
    [status, q, page]
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
    setSelectedProperty(item);
    setDeleteModalOpen(true);
  }

  function handleCloseDelete() {
    if (deleteLoading) return;
    setDeleteModalOpen(false);
    setSelectedProperty(null);
  }

  async function handleConfirmDelete() {
    if (!selectedProperty?.id) return;

    try {
      setDeleteLoading(true);
      setErr("");

      await deleteProperty(selectedProperty.id);

      const willCurrentPageBeEmpty = items.length === 1 && page > 1;
      const targetPage = willCurrentPageBeEmpty ? page - 1 : page;

      if (targetPage !== page) {
        setPage(targetPage);
      } else {
        await load({ nextStatus: status, nextQuery: q, nextPage: page });
      }

      setDeleteModalOpen(false);
      setSelectedProperty(null);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo eliminar la publicación"));
    } finally {
      setDeleteLoading(false);
    }
  }

  const title = useMemo(() => {
    if (role === 3) return "Publicaciones";
    return "Mis publicaciones";
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
            Gestioná tus propiedades cargadas, editá borradores, publicá, pausá
            o cerrá operaciones.
          </p>
        </div>

        <div className="flex flex-col lg:flex-row gap-4 sm:gap-5 lg:gap-6 items-start lg:items-center justify-between mb-6 sm:mb-8">
          <div className="flex flex-wrap gap-2 p-1 bg-slate-100 rounded-xl w-full lg:w-auto">
            {STATUS_OPTIONS.map((option) => {
              const active = status === option.key;

              return (
                <button
                  key={option.key}
                  type="button"
                  onClick={() => handleStatusChange(option.key)}
                  className={`px-3 sm:px-4 md:px-5 py-2 rounded-lg text-xs sm:text-sm transition-colors ${
                    active
                      ? "bg-white shadow-sm text-emerald-700 font-semibold"
                      : "text-slate-600 hover:bg-white/70 font-medium"
                  }`}
                >
                  {option.label}
                </button>
              );
            })}
          </div>

          <div className="flex flex-col sm:flex-row w-full lg:w-auto gap-3">
            <form
              onSubmit={onSearchSubmit}
              className="flex flex-col sm:flex-row gap-2 w-full lg:w-auto"
            >
              <div className="relative flex-1 lg:w-72">
                <Icon
                  name="search"
                  size={17}
                  className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                />

                <input
                  type="text"
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                  placeholder="Buscar por título o ID..."
                  className="w-full bg-white border border-slate-200 rounded-lg pl-10 pr-10 py-2.5 sm:py-3 text-sm shadow-sm outline-none focus:ring-2 focus:ring-slate-900/5 focus:border-slate-300"
                />

                {!!searchInput && (
                  <button
                    type="button"
                    onClick={handleClearSearch}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    aria-label="Limpiar búsqueda"
                  >
                    <Icon name="close" size={16} />
                  </button>
                )}
              </div>

              <button
                type="submit"
                className="px-4 sm:px-5 py-2.5 sm:py-3 rounded-lg text-sm font-bold border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition-colors"
              >
                Buscar
              </button>
            </form>

            <button
              type="button"
              onClick={() => navigate("/properties/new")}
              className="bg-emerald-600 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg text-sm font-bold flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 hover:opacity-90 active:scale-[0.99] transition-all whitespace-nowrap"
            >
              <Icon name="plusCircle" size={18} />
              Nueva publicación
            </button>
          </div>
        </div>

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
            No hay publicaciones para mostrar.
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-5 sm:gap-6 md:gap-8">
              {items.map((item) => (
                <PropertyCard
                  key={item.id}
                  item={item}
                  onOpenDetail={() => navigate(`/properties/${item.id}`)}
                  onEdit={() => navigate(`/properties/${item.id}/edit`)}
                  onDelete={() => handleOpenDelete(item)}
                />
              ))}
            </div>

            <Pagination meta={meta} onChange={handlePageChange} />
          </>
        )}
      </main>

      <PropertyDeleteModal
        open={deleteModalOpen}
        busy={deleteLoading}
        propertyTitle={selectedProperty?.title}
        propertyImageUrl={selectedProperty?.cover_image_url}
        onClose={handleCloseDelete}
        onConfirm={handleConfirmDelete}
      />
    </>
  );
}