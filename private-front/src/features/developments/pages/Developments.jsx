import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, Navigate } from "react-router-dom";
import { getErrorMessage } from "../../../api/http";
import { useAuth } from "../../auth/components/AuthContext";
import Pagination from "../../admin/components/Pagination.jsx";
import { Icon } from "../../../ui/icons/Index";
import {
  archiveDevelopment,
  closeDevelopment,
  deleteDevelopment,
  listMyDevelopments,
  pauseDevelopment,
  publishDevelopment,
} from "../api/developments.api";
import DevelopmentCard from "../components/DevelopmentCard";
import DevelopmentDeleteModal from "../components/DevelopmentDeleteModal";
import { useToast } from "../../../ui/toast/ToastProvider";

const STATUS_OPTIONS = [
  { key: "all", label: "Todos" },
  { key: "draft", label: "Borradores" },
  { key: "published", label: "Publicados" },
  { key: "paused", label: "Pausados" },
  { key: "archived", label: "Archivados" },
  { key: "closed", label: "Cerrados" },
];

const PAGE_SIZE = 5;

function ConfirmBar({ action, onConfirm, onCancel, loading }) {
  if (!action) return null;

  return (
    <div className="mb-5 sm:mb-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="font-bold">{action.title}</p>
          <p className="mt-1 text-amber-800">{action.description}</p>
        </div>

        <div className="flex gap-2">
          <button
            type="button"
            onClick={onCancel}
            className="rounded-lg border border-amber-200 bg-white px-4 py-2 font-semibold text-amber-900 hover:bg-amber-50"
          >
            Cancelar
          </button>

          <button
            type="button"
            onClick={onConfirm}
            disabled={loading}
            className="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:opacity-90 disabled:opacity-60"
          >
            {loading ? "Procesando..." : action.confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function Developments() {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3;

  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  const [status, setStatus] = useState("all");
  const [searchInput, setSearchInput] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);

  const [confirmAction, setConfirmAction] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [selectedDevelopment, setSelectedDevelopment] = useState(null);
  const requestIdRef = useRef(0);

  const load = useCallback(
    async ({ nextStatus = status, nextQuery = q, nextPage = page } = {}) => {
      const currentRequestId = ++requestIdRef.current;

      setErr("");
      setLoading(true);

      try {
        const data = await listMyDevelopments({
          page: nextPage,
          limit: PAGE_SIZE,
          status: nextStatus === "all" ? undefined : nextStatus,
          q: nextQuery?.trim() || undefined,
        });

        if (currentRequestId !== requestIdRef.current) return;

        setItems(Array.isArray(data?.items) ? data.items : []);
        setMeta(data?.meta || null);
      } catch (e) {
        if (currentRequestId !== requestIdRef.current) return;
        setItems([]);
        setMeta(null);
        setErr(getErrorMessage(e, "No se pudieron cargar los desarrollos"));
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

  function askAction(type, item) {
    const map = {
      publish: {
        title: `Publicar "${item?.title || "este desarrollo"}"`,
        description: "El desarrollo va a quedar visible para toda la red.",
        confirmLabel: "Publicar",
      },
      pause: {
        title: `Pausar "${item?.title || "este desarrollo"}"`,
        description:
          "El desarrollo dejará de mostrarse en explorar hasta volver a publicarlo.",
        confirmLabel: "Pausar",
      },
      archive: {
        title: `Archivar "${item?.title || "este desarrollo"}"`,
        description:
          "Se guardará como archivado y dejará de estar visible en explorar.",
        confirmLabel: "Archivar",
      },
      close: {
        title: `Cerrar "${item?.title || "este desarrollo"}"`,
        description:
          "Se marcará como cerrado y ya no estará disponible como proyecto activo.",
        confirmLabel: "Cerrar",
      },
      delete: {
        title: `Eliminar "${item?.title || "este desarrollo"}"`,
        description: "Esta acción lo quitará de tus listados activos.",
        confirmLabel: "Eliminar",
      },
    };

    setConfirmAction({
      type,
      item,
      ...map[type],
    });
  }

  function openDeleteModal(item) {
    setSelectedDevelopment(item);
    setDeleteModalOpen(true);
  }

  function closeDeleteModal() {
    if (actionLoading) return;
    setDeleteModalOpen(false);
    setSelectedDevelopment(null);
  }

  async function confirmDelete() {
    if (!selectedDevelopment?.id) return;

    try {
      setActionLoading(true);
      setErr("");

      await deleteDevelopment(selectedDevelopment.id);

      toast.success("Desarrollo eliminado correctamente.");

      const willCurrentPageBeEmpty = items.length === 1 && page > 1;
      const targetPage = willCurrentPageBeEmpty ? page - 1 : page;

      if (targetPage !== page) {
        setPage(targetPage);
      } else {
        await load({ nextStatus: status, nextQuery: q, nextPage: page });
      }

      setDeleteModalOpen(false);
      setSelectedDevelopment(null);
    } catch (e) {
      toast.error(getErrorMessage(e, "No se pudo eliminar el desarrollo"));
    } finally {
      setActionLoading(false);
    }
  }

  async function handleConfirmAction() {
    if (!confirmAction?.item?.id) return;

    try {
      setActionLoading(true);
      setErr("");

      const id = confirmAction.item.id;

      if (confirmAction.type === "publish") {
        await publishDevelopment(id);
        toast.success("Desarrollo publicado correctamente.");
      } else if (confirmAction.type === "pause") {
        await pauseDevelopment(id);
        toast.success("Desarrollo pausado correctamente.");
      } else if (confirmAction.type === "archive") {
        await archiveDevelopment(id);
        toast.success("Desarrollo archivado correctamente.");
      } else if (confirmAction.type === "close") {
        await closeDevelopment(id);
        toast.success("Desarrollo cerrado correctamente.");
      } else if (confirmAction.type === "delete") {
        await deleteDevelopment(id);
        toast.success("Desarrollo eliminado correctamente.");
      }

      const willCurrentPageBeEmpty = items.length === 1 && page > 1;
      const targetPage = willCurrentPageBeEmpty ? page - 1 : page;

      if (targetPage !== page) {
        setPage(targetPage);
      } else {
        await load({ nextStatus: status, nextQuery: q, nextPage: page });
      }

      setConfirmAction(null);
    } catch (e) {
      toast.error(getErrorMessage(e, "No se pudo completar la acción"));
    } finally {
      setActionLoading(false);
    }
  }

  const title = useMemo(() => {
    if (role === 3) return "Desarrollos";
    return "Mis desarrollos";
  }, [role]);

  if (!canAccess) return <Navigate to="/" replace />;

  return (
    <main className="max-w-6xl mx-auto px-4 sm:px-5 md:px-6 lg:px-12 py-6 sm:py-8 md:py-12 min-h-screen">
      <div className="mb-6 sm:mb-8 md:mb-10">
        <h1 className="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight mb-2 sm:mb-3 text-slate-900 text-center md:text-left">
          {title}
        </h1>

        <p className="text-sm sm:text-base md:text-lg text-slate-500 leading-relaxed max-w-2xl mx-auto md:mx-0 text-center md:text-left">
          Gestioná tus proyectos en desarrollo, editá borradores, publicá,
          pausá, archivá o cerrá operaciones.
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
            onClick={() => navigate("/developments/new")}
            className="bg-emerald-600 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg text-sm font-bold flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 hover:opacity-90 active:scale-[0.99] transition-all whitespace-nowrap"
          >
            <Icon name="plusCircle" size={18} />
            Nuevo desarrollo
          </button>
        </div>
      </div>

      <ConfirmBar
        action={confirmAction}
        onConfirm={handleConfirmAction}
        onCancel={() => setConfirmAction(null)}
        loading={actionLoading}
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
          No hay desarrollos para mostrar.
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-5 sm:gap-6 md:gap-8">
            {items.map((item) => (
              <DevelopmentCard
                key={item.id}
                item={item}
                variant="owned"
                detailHref={`/developments/${item.id}`}
                detailState={{ from: "/developments" }}
                onEdit={() => navigate(`/developments/${item.id}/edit`)}
                onDelete={() => openDeleteModal(item)}
                onPause={() => askAction("pause", item)}
                onArchive={() => askAction("archive", item)}
                onPublish={() => askAction("publish", item)}
                onClose={() => askAction("close", item)}
              />
            ))}
          </div>

          <Pagination meta={meta} onChange={handlePageChange} />
        </>
      )}

      <DevelopmentDeleteModal
        open={deleteModalOpen}
        busy={actionLoading}
        developmentTitle={selectedDevelopment?.title}
        developmentImageUrl={selectedDevelopment?.cover_image_url}
        onClose={closeDeleteModal}
        onConfirm={confirmDelete}
      />
    </main>
  );
}
