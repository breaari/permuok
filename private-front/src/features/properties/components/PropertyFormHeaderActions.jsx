import { Icon } from "../../../ui/icons/Index";

function getStatusMeta(status) {
  const map = {
    draft: ["Borrador", "bg-slate-100 text-slate-700 border-slate-200"],
    published: ["Publicado", "bg-emerald-100 text-emerald-700 border-emerald-200"],
    paused: ["Pausado", "bg-amber-100 text-amber-700 border-amber-200"],
    archived: ["Archivado", "bg-slate-200 text-slate-700 border-slate-300"],
    closed: ["Cerrado", "bg-rose-100 text-rose-700 border-rose-200"],
  };

  const [label, className] = map[status] || map.draft;
  return { label, className };
}

function ActionTile({ icon, children, onClick, disabled = false, variant = "default" }) {
  const variants = {
    default: "border-slate-200 bg-white text-slate-700 hover:bg-slate-50",
    primary:
      "border-emerald-600 bg-emerald-600 text-white hover:opacity-95 shadow-md shadow-emerald-600/20",
    warning: "border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100/80",
    danger: "border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100/80",
  };

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={`flex items-center justify-center gap-2.5 rounded-lg border px-4 py-2 transition-colors active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 ${variants[variant]}`}
    >
      <Icon name={icon} size={20} />
      <span className="text-[12px] font-bold tracking-tight">{children}</span>
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
  const effectiveStatus = isEditMode ? status : "draft";
  const statusMeta = getStatusMeta(effectiveStatus);

  const isPublished = effectiveStatus === "published";
  const isPaused = effectiveStatus === "paused";
  const isArchived = effectiveStatus === "archived";
  const isClosed = effectiveStatus === "closed";

  return (
    <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
      <div className="p-5 sm:p-6 md:p-8">
        {!isEditMode ? (
          <div className="flex items-center justify-between gap-4">
            <div className="space-y-2">
              <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Estado actual
              </span>

              <div className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 ${statusMeta.className}`}>
                <span className="text-sm font-semibold">{statusMeta.label}</span>
              </div>
            </div>

            <ActionTile
              icon="editNote"
              onClick={onSaveDraft}
              disabled={isSubmitting}
              variant="primary"
            >
              Guardar borrador
            </ActionTile>
          </div>
        ) : (
          <>
            <div className="mb-8 flex flex-col justify-between gap-6 border-b border-slate-200/70 pb-8 md:flex-row md:items-center">
              <div className="space-y-2">
                <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                  Estado actual
                </span>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                  <div className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 ${statusMeta.className}`}>
                    <span className="text-sm font-semibold">{statusMeta.label}</span>
                  </div>

                  <p className="text-sm font-medium text-slate-500">
                    Gestioná el estado de la propiedad desde acá.
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={onPreview}
                disabled={isSubmitting}
                className="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-800 transition-all hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <Icon name="eye" size={18} />
                Vista previa
              </button>
            </div>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-5">
              <ActionTile
                icon="editNote"
                onClick={onSaveDraft}
                disabled={isSubmitting || isClosed}
              >
                Guardar borrador
              </ActionTile>

              <ActionTile
                icon="rocket"
                onClick={onPublish}
                disabled={isSubmitting || isPublished || isClosed}
                variant="primary"
              >
                {isPaused || isArchived ? "Republicar" : "Publicar"}
              </ActionTile>

              <ActionTile
                icon="pause"
                onClick={onPause}
                disabled={isSubmitting || !isPublished || isClosed}
                variant="warning"
              >
                Pausar
              </ActionTile>

              <ActionTile
                icon="archive"
                onClick={onArchive}
                disabled={isSubmitting || isArchived || isClosed}
              >
                Archivar
              </ActionTile>

              <ActionTile
                icon="trash"
                onClick={onDelete}
                disabled={isSubmitting}
                variant="danger"
              >
                Eliminar
              </ActionTile>
            </div>
          </>
        )}
      </div>
    </section>
  );
}