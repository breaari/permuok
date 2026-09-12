import { Icon } from "../../../ui/icons/Index";

export default function AdminOperationalStatusModal({
  open,
  realEstate,
  isActive,
  busy = false,
  onClose,
  onConfirm,
}) {
  if (!open || !realEstate) return null;

  const name =
    realEstate?.name ||
    realEstate?.legal_name ||
    "esta inmobiliaria";

  const suspending = isActive;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 px-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
    >
      <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 p-6">
          <div className="flex items-start gap-4">
            <div
              className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${
                suspending
                  ? "bg-rose-50 text-rose-600"
                  : "bg-emerald-50 text-emerald-600"
              }`}
            >
              <Icon
                name={suspending ? "block" : "checkCircle"}
                size={24}
              />
            </div>

            <div>
              <p className="text-xs font-extrabold uppercase tracking-widest text-slate-400">
                Estado administrativo
              </p>

              <h2 className="mt-1 text-xl font-extrabold text-slate-900">
                {suspending
                  ? "Suspender inmobiliaria"
                  : "Reactivar inmobiliaria"}
              </h2>
            </div>
          </div>

          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50"
            aria-label="Cerrar"
          >
            <Icon name="close" size={20} />
          </button>
        </div>

        <div className="p-6">
          <p className="text-sm leading-relaxed text-slate-600">
            {suspending ? (
              <>
                Estás por suspender a{" "}
                <strong className="text-slate-900">{name}</strong>.
              </>
            ) : (
              <>
                Estás por reactivar a{" "}
                <strong className="text-slate-900">{name}</strong>.
              </>
            )}
          </p>

          {suspending ? (
            <div className="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
              <div className="flex gap-3">
                <Icon
                  name="shieldAlert"
                  size={20}
                  className="mt-0.5 shrink-0 text-rose-600"
                />

                <div>
                  <p className="text-sm font-bold text-rose-900">
                    El acceso se bloqueará inmediatamente.
                  </p>

                  <p className="mt-1 text-sm leading-relaxed text-rose-700">
                    La inmobiliaria, sus agentes y sus inversores no podrán
                    seguir operando en PermuOK hasta que la cuenta sea
                    reactivada.
                  </p>
                </div>
              </div>
            </div>
          ) : (
            <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
              <div className="flex gap-3">
                <Icon
                  name="checkCircle"
                  size={20}
                  className="mt-0.5 shrink-0 text-emerald-600"
                />

                <p className="text-sm leading-relaxed text-emerald-800">
                  La inmobiliaria y sus usuarios volverán a poder operar en la
                  plataforma.
                </p>
              </div>
            </div>
          )}

          <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              onClick={onClose}
              disabled={busy}
              className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
            >
              Cancelar
            </button>

            <button
              type="button"
              onClick={onConfirm}
              disabled={busy}
              className={
                suspending
                  ? "rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-rose-700 disabled:opacity-50"
                  : "rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
              }
            >
              {busy
                ? "Procesando..."
                : suspending
                  ? "Confirmar suspensión"
                  : "Confirmar reactivación"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}