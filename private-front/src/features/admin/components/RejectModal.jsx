import { useEffect, useState } from "react";

export default function RejectModal({
  open,
  title,
  subtitle,
  confirmLabel = "Confirmar",
  busy = false,
  noteLabel = "Motivo del rechazo",
  notePlaceholder = "Escribí el motivo...",
  onClose,
  onConfirm,
}) {
  const [note, setNote] = useState("");

  useEffect(() => {
    if (open) setNote("");
  }, [open]);

  if (!open) return null;

  function handleClose() {
    if (busy) return;
    onClose?.();
  }

  function handleConfirm() {
    const cleanNote = note.trim();
    if (!cleanNote || busy) return;
    onConfirm?.(cleanNote);
  }

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        className="absolute inset-0 bg-black/40"
        onClick={handleClose}
        aria-label="Cerrar modal"
      />

      <div className="absolute left-1/2 top-1/2 w-[92%] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white shadow-xl border border-slate-200 p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <div className="text-lg font-extrabold text-slate-900">
              {title}
            </div>
            {subtitle && (
              <div className="text-sm text-slate-500 mt-1">{subtitle}</div>
            )}
          </div>

          <button
            type="button"
            className="rounded-lg border px-3 py-2 text-sm"
            onClick={handleClose}
            disabled={busy}
          >
            Cerrar
          </button>
        </div>

        <div className="mt-4">
          <label className="block text-sm font-semibold text-slate-700 mb-1">
            {noteLabel}
          </label>

          <textarea
            className="w-full min-h-[110px] rounded-lg border border-slate-200 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60"
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder={notePlaceholder}
            disabled={busy}
          />
        </div>

        <div className="mt-4 flex gap-2 justify-end">
          <button
            type="button"
            className="rounded-lg border px-4 py-2 text-sm"
            onClick={handleClose}
            disabled={busy}
          >
            Cancelar
          </button>

          <button
            type="button"
            className="rounded-lg bg-black text-white px-4 py-2 text-sm font-bold disabled:opacity-60"
            disabled={busy || !note.trim()}
            onClick={handleConfirm}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}