import { Icon } from "../../../ui/icons/Index";

export default function PropertyPublishChoiceModal({
  open,
  busy = false,
  onClose,
  onSaveDraft,
  onPublishNow,
}) {
  if (!open) return null;

  function handleSafeClose() {
    if (busy) return;
    onClose?.();
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
      <button
        type="button"
        aria-label="Cerrar modal"
        className="absolute inset-0 cursor-default"
        onClick={handleSafeClose}
      />

      <div
        className="relative z-10 w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden border border-slate-200"
        onClick={(e) => e.stopPropagation()}
        onMouseDown={(e) => e.stopPropagation()}
      >
        <div className="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
          <div>
            <h2 className="text-xl font-bold text-slate-900 flex items-center gap-2">
              <Icon name="rocket" size={20} className="text-emerald-600" />
              Finalizar publicación
            </h2>
            <p className="text-xs text-slate-500 mt-1">
              Elegí si querés guardar esta propiedad como borrador o publicarla ahora.
            </p>
          </div>

          <button
            type="button"
            onClick={handleSafeClose}
            className="text-slate-400 hover:text-slate-600 transition-colors disabled:opacity-60"
            aria-label="Cerrar"
            disabled={busy}
          >
            <span className="text-xl leading-none">×</span>
          </button>
        </div>

        <div className="p-6 space-y-4">
          <button
            type="button"
            disabled={busy}
            onClick={onSaveDraft}
            className="w-full rounded-xl border border-slate-200 bg-white p-4 text-left hover:bg-slate-50 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <div className="flex items-start gap-3">
              <div className="mt-0.5 text-slate-500">
                <Icon name="editNote" size={18} />
              </div>

              <div>
                <p className="text-sm font-bold text-slate-900">
                  Guardar como borrador
                </p>
                <p className="text-xs text-slate-500 mt-1">
                  Se guarda la propiedad con imágenes y criterios, pero queda sin publicar.
                </p>
              </div>
            </div>
          </button>

          <button
            type="button"
            disabled={busy}
            onClick={onPublishNow}
            className="w-full rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-left hover:bg-emerald-100 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <div className="flex items-start gap-3">
              <div className="mt-0.5 text-emerald-700">
                <Icon name="rocket" size={18} />
              </div>

              <div>
                <p className="text-sm font-bold text-emerald-800">
                  Publicar ahora
                </p>
                <p className="text-xs text-emerald-700 mt-1">
                  Se guarda todo y la propiedad se publica inmediatamente.
                </p>
              </div>
            </div>
          </button>
        </div>

        <div className="px-6 py-4 bg-slate-50 border-t border-slate-100">
          <div className="flex flex-col sm:flex-row gap-3">
            <button
              type="button"
              disabled={busy}
              onClick={handleSafeClose}
              className="flex-1 px-6 py-3 border border-slate-200 text-slate-600 font-bold rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-60"
            >
              Cancelar
            </button>

            <div className="flex-1 px-6 py-3 rounded-lg bg-slate-100 text-slate-500 font-medium text-center">
              {busy ? "Procesando..." : "Elegí una opción"}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}