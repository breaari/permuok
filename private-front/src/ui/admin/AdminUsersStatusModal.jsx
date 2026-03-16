import { useEffect, useMemo, useState } from "react";

function getRoleLabel(role) {
  if (Number(role) === 2) return "Inmobiliaria";
  if (Number(role) === 3) return "Agente";
  if (Number(role) === 4) return "Inversor";
  return "Usuario";
}

export default function AdminUserStatusModal({
  open,
  user,
  busy = false,
  onClose,
  onConfirm,
}) {
  const [reason, setReason] = useState("");

  useEffect(() => {
    if (!open) {
      setReason("");
    }
  }, [open]);

  const isActive = Number(user?.is_active) === 1;
  const fullName = useMemo(() => {
    const first = user?.first_name || "";
    const last = user?.last_name || "";
    return `${first} ${last}`.trim();
  }, [user]);

  if (!open || !user) return null;

  async function handleSubmit(e) {
    e.preventDefault();

    await onConfirm({
      is_active: isActive ? 0 : 1,
      reason: isActive ? reason.trim() : null,
    });
  }

  return (
    <div className="fixed inset-0 z-50">
      <div
        className="absolute inset-0 bg-black/40"
        onClick={busy ? undefined : onClose}
      />

      <div className="absolute left-1/2 top-1/2 w-[92%] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white border border-slate-200 shadow-xl">
        <div className="p-5 border-b border-slate-100">
          <h3 className="text-lg font-extrabold text-slate-900">
            {isActive ? "Desactivar usuario" : "Activar usuario"}
          </h3>
          <p className="text-sm text-slate-500 mt-1">
            {fullName || "Usuario"} · {getRoleLabel(user.role)}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          {isActive ? (
            <>
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Si desactivás este usuario, no podrá seguir operando en la plataforma.
                {Number(user.role) === 2
                  ? " También se desactivarán sus agentes e inversores asociados."
                  : ""}
              </div>

              <div>
                <label className="block text-sm font-bold text-slate-700 mb-2">
                  Motivo de desactivación
                </label>
                <textarea
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  rows={4}
                  className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary resize-none"
                  placeholder="Indicá por qué se desactiva este usuario"
                  disabled={busy}
                />
              </div>
            </>
          ) : (
            <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
              Al activar este usuario, volverá a tener acceso a la plataforma.
              {Number(user.role) === 2
                ? " También se activarán sus agentes e inversores asociados."
                : ""}
            </div>
          )}

          <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              disabled={busy}
              className="rounded-lg border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={busy || (isActive && !reason.trim())}
              className={
                isActive
                  ? "rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 hover:bg-rose-100 disabled:opacity-60"
                  : "rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 hover:bg-emerald-100 disabled:opacity-60"
              }
            >
              {busy
                ? "Procesando..."
                : isActive
                  ? "Confirmar desactivación"
                  : "Confirmar activación"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}