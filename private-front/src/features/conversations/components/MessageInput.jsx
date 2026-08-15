import { useState } from "react";

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

export default function MessageInput({ onSend, disabled = false }) {
  const toast = useToast();

  const [body, setBody] = useState("");
  const [sending, setSending] = useState(false);

  async function sendMessage() {
    const cleanBody = body.trim();

    if (!cleanBody || disabled || sending) return;

    if (hasSensitiveData(cleanBody)) {
      toast.warning(
        "Para proteger la operación, los datos de contacto se comparten solo cuando ambas partes aceptan.",
      );

      return;
    }

    try {
      setSending(true);

      await onSend(cleanBody);
      setBody("");
    } catch (err) {
      toast.error(err?.message || "No se pudo enviar el mensaje.");
    } finally {
      setSending(false);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    await sendMessage();
  }

  async function handleKeyDown(e) {
    if (e.key !== "Enter") return;
    if (e.shiftKey) return;

    e.preventDefault();
    await sendMessage();
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="border-t border-slate-200 bg-white p-3 sm:p-4"
    >
      <div className="flex items-end gap-2 sm:gap-3">
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          onKeyDown={handleKeyDown}
          disabled={disabled || sending}
          rows={1}
          placeholder="Escribí un mensaje..."
          className="max-h-32 min-h-[46px] flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 disabled:opacity-60 sm:min-h-[48px] sm:px-4"
        />

        <button
          type="submit"
          disabled={disabled || sending || !body.trim()}
          className="flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white transition hover:opacity-90 disabled:opacity-50 sm:h-12 sm:w-12"
          title="Enviar"
          aria-label="Enviar mensaje"
        >
          <Icon name="arrowRight" size={19} />
        </button>
      </div>

      <p className="mt-2 text-[10px] font-medium leading-relaxed text-slate-400 sm:text-[11px]">
        Enter para enviar · Shift + Enter para salto de línea
      </p>
    </form>
  );
}
