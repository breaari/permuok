import { Icon } from "../../../ui/icons/Index";

export default function SearchRequestFormFooterActions({
  isSubmitting = false,
  isEditMode = false,
  onBack,
  onSubmit,
}) {
  return (
    <div className="sticky bottom-4">
      <div className="rounded-2xl border border-slate-200 bg-white/95 backdrop-blur px-4 sm:px-5 py-4 shadow-lg">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <p className="text-sm font-semibold text-slate-900">Último paso</p>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Revisá las condiciones antes de finalizar la búsqueda.
            </p>
          </div>

          <div className="flex flex-col-reverse sm:flex-row gap-3">
            <button
              type="button"
              disabled={isSubmitting}
              onClick={onBack}
              className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <Icon name="chevronLeft" size={18} />
              Volver al Paso 1
            </button>

            <button
              type="button"
              disabled={isSubmitting}
              onClick={onSubmit}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 hover:opacity-90 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <Icon name="checkCircle" size={18} />
              {isSubmitting
                ? isEditMode
                  ? "Actualizando..."
                  : "Guardando..."
                : isEditMode
                  ? "Actualizar datos"
                  : "Finalizar búsqueda"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}