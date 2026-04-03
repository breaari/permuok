export default function SearchRequestDeleteModal({
  open,
  busy = false,
  title = "",
  onClose,
  onConfirm,
}) {
  if (!open) return null;

  function handleClose() {
    if (busy) return;
    onClose?.();
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
      <button
        type="button"
        className="absolute inset-0"
        onClick={handleClose}
        aria-label="Cerrar"
      />

      <div
        className="relative z-10 w-full max-w-lg rounded-2xl bg-white border border-slate-200 shadow-2xl overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="px-6 py-5 border-b border-slate-100">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h2 className="text-xl font-bold text-slate-900">
                Eliminar búsqueda
              </h2>
              <p className="text-sm text-slate-500 mt-1">
                Esta acción la quita de la experiencia activa del usuario.
              </p>
            </div>

            <button
              type="button"
              onClick={handleClose}
              disabled={busy}
              className="text-slate-400 hover:text-slate-600 disabled:opacity-60"
            >
              <span className="text-xl leading-none">×</span>
            </button>
          </div>
        </div>

        <div className="p-6 space-y-4">
          <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-4">
            <p className="text-sm font-bold text-rose-800">
              Vas a eliminar esta búsqueda
            </p>
            {!!title && (
              <p className="text-sm text-rose-700 mt-1 break-words">{title}</p>
            )}
          </div>

          <p className="text-sm text-slate-600">
            El historial podrá conservarse como registro interno, pero dejará de
            aparecer en tus listados y gestión activa.
          </p>
        </div>

        <div className="px-6 py-4 bg-slate-50 border-t border-slate-100">
          <div className="flex flex-col sm:flex-row gap-3">
            <button
              type="button"
              onClick={handleClose}
              disabled={busy}
              className="flex-1 px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold hover:bg-slate-50 disabled:opacity-60"
            >
              Cancelar
            </button>

            <button
              type="button"
              onClick={onConfirm}
              disabled={busy}
              className="flex-1 px-4 py-3 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 disabled:opacity-60"
            >
              {busy ? "Eliminando..." : "Eliminar búsqueda"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}