import { getApiBaseUrl } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";

export default function PropertyDeleteModal({
  open,
  busy = false,
  propertyTitle = "",
  propertyImageUrl = "",
  onClose,
  onConfirm,
}) {
  if (!open) return null;

  function handleSafeClose() {
    if (busy) return;
    onClose?.();
  }

  const imageUrl = propertyImageUrl
    ? propertyImageUrl.startsWith("http")
      ? propertyImageUrl
      : `${getApiBaseUrl()}${propertyImageUrl}`
    : "";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
      <button
        type="button"
        aria-label="Cerrar modal"
        className="absolute inset-0 bg-slate-950/35 backdrop-blur-sm"
        onClick={handleSafeClose}
      />

      <div
        className="relative z-10 w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        onClick={(e) => e.stopPropagation()}
        onMouseDown={(e) => e.stopPropagation()}
      >
        <button
          type="button"
          onClick={handleSafeClose}
          disabled={busy}
          aria-label="Cerrar"
          className="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-60"
        >
          <Icon name="close" size={18} />
        </button>

        <div className="p-6 sm:p-8">
          <div className="mb-6 flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-700">
              <Icon name="warning" size={22} />
            </div>

            <div>
              <h2 className="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-900">
                Eliminar publicación
              </h2>
              <p className="mt-1 text-sm text-slate-500">
                Confirmá esta acción antes de continuar.
              </p>
            </div>
          </div>

          <div className="mb-6 rounded-xl border-l-4 border-rose-500 bg-rose-50 px-4 py-4">
            <p className="text-sm font-semibold text-rose-800">
              Esta acción es permanente desde la experiencia del usuario.
            </p>
          </div>

          <div className="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">
              Confirmar eliminación de
            </p>

            <div className="mt-3 flex items-center gap-3">
              <div className="h-16 w-16 overflow-hidden rounded-lg border border-slate-200 bg-white shrink-0">
                {imageUrl ? (
                  <img
                    src={imageUrl}
                    alt={propertyTitle || "Publicación"}
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <div className="flex h-full w-full items-center justify-center text-slate-400">
                    <Icon name="home" size={20} />
                  </div>
                )}
              </div>

              <div className="min-w-0">
                <p className="text-base sm:text-lg font-bold text-slate-900 break-words">
                  {propertyTitle || "Publicación sin título"}
                </p>
              </div>
            </div>
          </div>

          <p className="text-sm leading-relaxed text-slate-600">
            La publicación dejará de aparecer en tus listados, búsquedas,
            matches y experiencia activa. El historial relacionado podrá
            conservarse como registro, pero la publicación dejará de estar
            disponible.
          </p>
        </div>

        <div className="border-t border-slate-100 bg-slate-50 px-6 py-4 sm:px-8">
          <div className="flex flex-col gap-3 sm:flex-row">
            <button
              type="button"
              disabled={busy}
              onClick={handleSafeClose}
              className="order-2 sm:order-1 flex-1 rounded-xl border border-slate-200 px-6 py-3 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 disabled:opacity-60"
            >
              Cancelar
            </button>

            <button
              type="button"
              disabled={busy}
              onClick={onConfirm}
              className="order-1 sm:order-2 flex-1 rounded-xl bg-gradient-to-br from-rose-600 to-rose-800 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-rose-600/20 transition-all hover:opacity-95 disabled:opacity-60"
            >
              {busy ? "Eliminando..." : "Eliminar publicación"}
            </button>
          </div>
        </div>

        <div className="h-1 w-full bg-slate-200" />
      </div>
    </div>
  );
}