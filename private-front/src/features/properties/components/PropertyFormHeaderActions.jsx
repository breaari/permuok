import { Icon } from "../../../ui/icons/Index";

function getStatusMeta(status) {
  const map = {
    draft: {
      label: "Borrador",
      dot: "bg-slate-400",
      pill: "bg-slate-100 text-slate-700 border-slate-200",
    },
    published: {
      label: "Publicada",
      dot: "bg-emerald-500",
      pill: "bg-emerald-100 text-emerald-700 border-emerald-200",
    },
    paused: {
      label: "Pausada",
      dot: "bg-amber-500",
      pill: "bg-amber-100 text-amber-700 border-amber-200",
    },
    archived: {
      label: "Archivada",
      dot: "bg-slate-500",
      pill: "bg-slate-200 text-slate-700 border-slate-300",
    },
    closed: {
      label: "Cerrada",
      dot: "bg-rose-500",
      pill: "bg-rose-100 text-rose-700 border-rose-200",
    },
    deleted: {
      label: "Eliminada",
      dot: "bg-rose-600",
      pill: "bg-rose-100 text-rose-700 border-rose-200",
    },
  };

  return (
    map[status] || {
      label: "Sin estado",
      dot: "bg-slate-400",
      pill: "bg-slate-100 text-slate-600 border-slate-200",
    }
  );
}

function ActionTile({
  icon,
  children,
  onClick,
  disabled = false,
  variant = "default",
}) {
  const variants = {
    default:
      "border-slate-200 bg-white text-slate-700 hover:bg-slate-50",
    primary:
      "bg-emerald-600 border-emerald-600 text-white hover:opacity-95 shadow-md shadow-emerald-600/20",
    warning:
      "bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100/80",
    danger:
      "bg-rose-50 border-rose-200 text-rose-700 hover:bg-rose-100/80",
  };

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={`flex items-center justify-center gap-2.5 px-4 py-2 rounded-lg border transition-colors active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed ${variants[variant]}`}
    >
      <Icon name={icon} size={20} />
      <span className="text-[12px] font-bold tracking-tight">
        {children}
      </span>
    </button>
  );
}

export default function PropertyFormHeaderActions({
  status = "",
  isEditMode = false,
  isSubmitting = false,
  onSaveDraft,
  onPublish,
  onPause,
  onArchive,
  onDelete,
  onPreview,
}) {
  const statusMeta = getStatusMeta(status);

  const isPublished = status === "published";
  const isArchived = status === "archived";
  const isDeleted = status === "deleted";
  const isClosed = status === "closed";

  return (
    <section className="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
      <div className="p-5 sm:p-6 md:p-8">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 border-b border-slate-200/70 pb-8">
          <div className="space-y-2">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
              Estado actual
            </span>

            <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
              <div
                className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full border ${statusMeta.pill}`}
              >
                <span className={`w-2 h-2 rounded-full ${statusMeta.dot}`} />
                <span className="text-sm font-semibold">{statusMeta.label}</span>
              </div>

              {isEditMode && (
                <p className="text-sm text-slate-500 font-medium">
                  Podés gestionar el estado de la publicación desde acá.
                </p>
              )}
            </div>
          </div>

          <div className="flex items-center">
            <button
              type="button"
              onClick={onPreview}
              disabled={isSubmitting}
              className="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 rounded-lg transition-all border border-slate-200 disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <Icon name="eye" size={18} />
              Vista previa
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3 md:grid-cols-5">
          <ActionTile
            icon="editNote"
            onClick={onSaveDraft}
            disabled={isSubmitting || isDeleted}
            variant="default"
          >
            Guardar borrador
          </ActionTile>

          <ActionTile
            icon="rocket"
            onClick={onPublish}
            disabled={isSubmitting || isPublished || isDeleted || isClosed}
            variant="primary"
          >
            Publicar
          </ActionTile>

          <ActionTile
            icon="pause"
            onClick={onPause}
            disabled={isSubmitting || !isPublished || isDeleted || isClosed}
            variant="warning"
          >
            Pausar
          </ActionTile>

          <ActionTile
            icon="archive"
            onClick={onArchive}
            disabled={isSubmitting || isArchived || isDeleted}
            variant="default"
          >
            Archivar
          </ActionTile>

          <ActionTile
            icon="trash"
            onClick={onDelete}
            disabled={isSubmitting || isDeleted}
            variant="danger"
          >
            Eliminar
          </ActionTile>
        </div>
      </div>
    </section>
  );
}