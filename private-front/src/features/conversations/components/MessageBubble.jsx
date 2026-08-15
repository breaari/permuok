import { Icon } from "../../../ui/icons/Index";

export default function MessageBubble({
  message,
  currentUserId,
  seen = false,
}) {
  const isMine = Number(message?.sender_user_id) === Number(currentUserId);
  const isSystem = Number(message?.is_system || 0) === 1;

  if (isSystem) {
    return (
      <div className="flex justify-center px-2">
        <div className="max-w-full rounded-full bg-slate-100 px-3 py-2 text-center text-[11px] font-semibold text-slate-500 sm:px-4 sm:text-xs">
          {message?.sanitized_body || message?.body}
        </div>
      </div>
    );
  }

  return (
    <div className={`flex ${isMine ? "justify-end" : "justify-start"}`}>
      <div
        className={`max-w-[90%] sm:max-w-[78%] rounded-2xl px-3 py-3 sm:px-4 text-sm leading-relaxed shadow-sm ${
          isMine
            ? "bg-slate-900 text-white"
            : "border border-slate-200 bg-white text-slate-700"
        }`}
      >
        <p className="whitespace-pre-line break-words">
          {message?.sanitized_body || message?.body}
        </p>

        <div
          className={`mt-2 flex items-center justify-end gap-1 text-[10px] font-semibold ${
            isMine ? "text-white/50" : "text-slate-400"
          }`}
        >
          <span>{formatTime(message?.created_at)}</span>

          {isMine ? (
            <span className="inline-flex items-center gap-1">
              {seen ? (
                <>
                  <Icon name="checkCheck" size={12} />
                  <span className="hidden sm:inline">Visto</span>
                </>
              ) : (
                <>
                  <Icon name="check" size={12} />
                  <span className="hidden sm:inline">Enviado</span>
                </>
              )}
            </span>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function formatTime(value) {
  if (!value) return "";

  try {
    return new Date(value).toLocaleTimeString("es-AR", {
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return "";
  }
}
