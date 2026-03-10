import { Icon } from "../icons/Index";

export function CancelMembershipModal({
  open,
  busy = false,
  onClose,
  onConfirm,
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50">
      <div
        className="absolute inset-0 bg-black/40"
        onClick={busy ? undefined : onClose}
      />

      <div className="absolute left-1/2 top-1/2 w-[92%] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white shadow-xl border border-slate-200 p-5">
        <div className="flex items-start gap-3">
          <div className="mt-0.5 text-amber-600">
            <Icon name="info" size={18} />
          </div>

          <div className="min-w-0">
            <h3 className="text-lg font-extrabold text-slate-900">
              Confirmar cancelación
            </h3>
            <p className="mt-2 text-sm text-slate-600">
              Vas a cancelar la renovación automática de tu membresía.
            </p>
            <p className="mt-2 text-sm text-slate-600">
              Tu plan seguirá activo hasta el vencimiento del período actual y
              después no se renovará.
            </p>
          </div>
        </div>

        <div className="mt-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
          >
            Volver
          </button>

          <button
            type="button"
            onClick={onConfirm}
            disabled={busy}
            className="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-100 disabled:opacity-60"
          >
            {busy ? "Procesando..." : "Confirmar cancelación"}
          </button>
        </div>
      </div>
    </div>
  );
}