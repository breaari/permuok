import { useEffect, useMemo, useState } from "react";
import { Icon } from "../../../ui/icons/Index";
import { useToast } from "../../../ui/toast/ToastProvider";

function hasSensitiveData(text) {
  const value = String(text || "");

  const patterns = [
    /[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i,
    /\bhttps?:\/\/[^\s]+|\bwww\.[^\s]+/i,
    /(?:\+?\d[\d\s().-]{7,}\d)/,
    /\b(wsp|whatsapp|wa\.me)\b/i,
    /\b(instagram|ig|@[\w.]{3,})\b/i,
  ];

  return patterns.some((pattern) => pattern.test(value));
}

function getImageUrl(imageUrl) {
  if (!imageUrl) return "";

  if (/^https?:\/\//i.test(imageUrl)) return imageUrl;

  const apiBase =
    import.meta.env.VITE_API_BASE_URL || "http://localhost/permuok/public";

  const cleanBase = apiBase.replace(/\/$/, "");
  const cleanUrl = imageUrl.startsWith("/") ? imageUrl : `/${imageUrl}`;

  return `${cleanBase}${cleanUrl}`;
}

export default function StartConversationModal({
  open,
  title = "Iniciar conversación",
  opportunityTitle = "esta oportunidad",
  opportunityType = "Oportunidad",
  opportunityLocation = "",
  opportunityPrice = "",
  opportunityImageUrl = "",
  defaultMessage = "Hola! Me interesa esta oportunidad y quisiera recibir más información.",
  loading = false,
  error = "",
  onClose,
  onConfirm,
}) {
  const toast = useToast();

  const [message, setMessage] = useState(defaultMessage);

  const imageUrl = useMemo(
    () => getImageUrl(opportunityImageUrl),
    [opportunityImageUrl],
  );

  const characters = message.trim().length;
  const canSubmit = characters > 0 && !loading;

  useEffect(() => {
    if (open) {
      setMessage(defaultMessage);
    }
  }, [open, defaultMessage]);

  useEffect(() => {
    if (!open) return;

    function handleKeyDown(e) {
      if (e.key === "Escape" && !loading) {
        onClose?.();
      }
    }

    window.addEventListener("keydown", handleKeyDown);

    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [open, loading, onClose]);

  if (!open) return null;

  async function handleSubmit(e) {
    e.preventDefault();

    const cleanMessage = message.trim();
    if (!cleanMessage || loading) return;

    if (hasSensitiveData(cleanMessage)) {
      toast.warning(
        "Para proteger la operación, no compartas teléfono, email, redes sociales ni links hasta que ambas partes acepten compartir datos.",
      );
      return;
    }

    await onConfirm(cleanMessage);
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 px-3 py-3 backdrop-blur-sm sm:items-center sm:px-4 sm:py-6"
      role="dialog"
      aria-modal="true"
    >
      <div className="max-h-[calc(100vh-1.5rem)] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100vh-3rem)] sm:rounded-3xl">
        <div className="flex items-start justify-between gap-4 border-b border-slate-200 p-4 sm:p-6">
          <div className="min-w-0">
            <p className="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-600 sm:text-[11px] sm:tracking-[0.18em]">
              Consulta protegida
            </p>

            <h2 className="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
              {title}
            </h2>

            <p className="mt-2 text-sm leading-relaxed text-slate-500">
              Revisá el mensaje antes de enviarlo. La otra parte lo recibirá en
              su inbox de Permuok.
            </p>
          </div>

          <button
            type="button"
            onClick={onClose}
            disabled={loading}
            className="shrink-0 rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50"
            aria-label="Cerrar"
          >
            <Icon name="close" size={20} />
          </button>
        </div>

        <form
          onSubmit={handleSubmit}
          className="max-h-[calc(100vh-9rem)] overflow-y-auto p-4 sm:max-h-[calc(100vh-12rem)] sm:p-6"
        >
          <div className="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 sm:mb-5">
            {imageUrl ? (
              <div className="h-28 bg-slate-200 sm:h-36">
                <img
                  src={imageUrl}
                  alt={opportunityTitle}
                  className="h-full w-full object-cover"
                  loading="lazy"
                />
              </div>
            ) : null}

            <div className="p-3 sm:p-4">
              <div className="mb-2 flex flex-wrap items-center gap-2">
                <span className="rounded-full bg-slate-900 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.12em] text-white sm:text-[10px] sm:tracking-[0.14em]">
                  {opportunityType}
                </span>

                {opportunityPrice ? (
                  <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.12em] text-emerald-700 sm:text-[10px] sm:tracking-[0.14em]">
                    {opportunityPrice}
                  </span>
                ) : null}
              </div>

              <h3 className="break-words text-sm font-black leading-tight text-slate-900 sm:text-base">
                {opportunityTitle}
              </h3>

              {opportunityLocation ? (
                <div className="mt-2 flex items-start gap-2 text-xs font-semibold leading-relaxed text-slate-500 sm:text-sm">
                  <Icon name="mapPin" size={15} className="mt-0.5 shrink-0" />
                  <span className="break-words">{opportunityLocation}</span>
                </div>
              ) : null}
            </div>
          </div>

          <div className="flex items-center justify-between gap-3">
            <label className="text-sm font-black text-slate-800">
              Mensaje inicial
            </label>

            <span
              className={`text-xs font-bold ${
                characters > 600 ? "text-amber-600" : "text-slate-400"
              }`}
            >
              {characters}/800
            </span>
          </div>

          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value.slice(0, 800))}
            disabled={loading}
            rows={4}
            className="mt-2 w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm leading-relaxed text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 disabled:opacity-60 sm:px-4"
            placeholder="Escribí tu consulta..."
            autoFocus
          />

          <div className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3 sm:px-4">
            <div className="flex gap-3">
              <Icon
                name="shieldCheck"
                size={18}
                className="mt-0.5 shrink-0 text-amber-700"
              />
              <p className="text-xs font-semibold leading-relaxed text-amber-800">
                No compartas teléfono, email, redes sociales ni links externos.
                Esos datos se habilitan solo si ambas partes aceptan
                compartirlos.
              </p>
            </div>
          </div>

          {error ? (
            <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-3 text-sm font-semibold leading-relaxed text-rose-700 sm:px-4">
              {error}
            </div>
          ) : null}

          <div className="mt-5 flex flex-col-reverse gap-3 sm:mt-6 sm:flex-row sm:justify-end">
            <button
              type="button"
              onClick={onClose}
              disabled={loading}
              className="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={!canSubmit}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-lg transition hover:opacity-90 disabled:opacity-50"
            >
              {loading ? "Enviando..." : "Enviar consulta"}
              {!loading ? <Icon name="arrowRight" size={16} /> : null}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
