import { Icon } from "../../../../ui/icons/Index";

export default function AdminAdministrativeStatusCard({
  isActive,
  deactivatedByEmail,
  deactivatedAt,
  deactivationReason,
  formatDate,
  statusLabel,
  actionLabel,
  actionDisabled = true,
  onAction,

  title = "Estado administrativo",
  activeNote = "La cuenta se encuentra operativa.",
  emptyReasonLabel = "—",

  showDeactivatedBy = true,
  showDeactivatedAt = true,
  showDeactivationReason = true,

  compact = false,
  customStatusLabel = null,
  customStatusTone = null, // "success" | "warning" | "danger" | "neutral"
  customNote = null,

  actionClassName = null,
}) {
  const active = Number(isActive) === 1;

  const toneMap = {
    success: {
      wrap: "bg-slate-50 border-slate-200",
      title: "text-slate-900",
      dim: "text-slate-500",
      divider: "border-slate-200",
      box: "bg-white border-slate-200 text-slate-700",
      button: "bg-slate-200 hover:bg-slate-300 text-slate-700",
      dot: "bg-emerald-500",
      status: "text-emerald-600",
      badge: "bg-emerald-100 text-emerald-700 rounded-full",
    },
    warning: {
      wrap: "bg-amber-50 border-amber-200",
      title: "text-amber-900",
      dim: "text-amber-800",
      divider: "border-amber-200",
      box: "bg-white/80 border-amber-100 text-amber-900",
      button: "bg-amber-600 hover:bg-amber-700 text-white",
      dot: "bg-amber-500",
      status: "text-amber-700",
      badge: "bg-amber-100 text-amber-700 rounded-full",
    },
    danger: {
      wrap: "bg-rose-50 border-rose-200",
      title: "text-rose-900",
      dim: "text-rose-800",
      divider: "border-rose-200",
      box: "bg-white/80 border-rose-100 text-rose-900",
      button: "bg-rose-600 hover:bg-rose-700 text-white",
      dot: "bg-rose-500",
      status: "text-rose-600",
      badge: "bg-rose-100 text-rose-700 rounded-full",
    },
    neutral: {
      wrap: "bg-slate-50 border-slate-200",
      title: "text-slate-900",
      dim: "text-slate-500",
      divider: "border-slate-200",
      box: "bg-white border-slate-200 text-slate-700",
      button: "bg-slate-200 hover:bg-slate-300 text-slate-700",
      dot: "bg-slate-400",
      status: "text-slate-600",
      badge: "bg-slate-100 text-slate-700 rounded-full",
    },
  };

  const resolvedToneKey = customStatusTone || (active ? "success" : "warning");
  const tone = toneMap[resolvedToneKey] || toneMap.neutral;

  const resolvedStatusLabel =
    customStatusLabel ||
    (typeof statusLabel === "function" ? statusLabel(isActive) : "—");

  const resolvedNote =
    customNote ?? (active ? activeNote : deactivationReason || emptyReasonLabel);

  const shouldShowMetaRows =
    !compact && (showDeactivatedBy || showDeactivatedAt);

  const shouldShowNote =
    showDeactivationReason &&
    String(resolvedNote || "").trim() !== "";

  const resolvedActionClassName =
    actionClassName ||
    tone.button;

  return (
    <section className={`p-6 rounded-xl border h-fit ${tone.wrap}`}>
      <div className="flex items-center gap-2 mb-6">
        <span className={tone.title}>
          <Icon name="shieldAlert" size={20} />
        </span>
        <h2 className={`text-xl font-bold ${tone.title}`}>{title}</h2>
      </div>

      <div className="space-y-4 text-sm">
        <div
          className={`flex justify-between items-center ${
            shouldShowMetaRows || shouldShowNote
              ? `py-2.5 border-b ${tone.divider}`
              : ""
          }`}
        >
          <span
            className={`${tone.dim} font-bold uppercase text-[10px] tracking-widest`}
          >
            Estado actual
          </span>

          {compact ? (
            <span
              className={`px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest ${tone.badge}`}
            >
              {resolvedStatusLabel}
            </span>
          ) : (
            <div className="flex items-center gap-2">
              <span className={`w-2 h-2 rounded-full ${tone.dot}`} />
              <span className={`font-extrabold uppercase ${tone.status}`}>
                {resolvedStatusLabel}
              </span>
            </div>
          )}
        </div>

        {shouldShowMetaRows && showDeactivatedBy && (
          <div
            className={`flex justify-between items-center py-2.5 border-b ${tone.divider}`}
          >
            <span
              className={`${tone.dim} font-bold uppercase text-[10px] tracking-widest`}
            >
              Desactivado por
            </span>
            <span className={`${tone.title} font-bold text-right`}>
              {deactivatedByEmail || "—"}
            </span>
          </div>
        )}

        {shouldShowMetaRows && showDeactivatedAt && (
          <div
            className={`flex justify-between items-center py-2.5 border-b ${tone.divider}`}
          >
            <span
              className={`${tone.dim} font-bold uppercase text-[10px] tracking-widest`}
            >
              Fecha
            </span>
            <span className={`${tone.title} font-bold`}>
              {formatDate(deactivatedAt)}
            </span>
          </div>
        )}

        {shouldShowNote && (
          <div className={compact ? "" : "pt-3"}>
            <span
              className={`${tone.dim} font-bold uppercase text-[10px] tracking-widest block mb-2`}
            >
              {compact ? "Detalle" : "Motivo"}
            </span>

            <div className={`p-4 rounded-lg border text-xs italic ${tone.box}`}>
              {resolvedNote}
            </div>
          </div>
        )}
      </div>

      {actionLabel ? (
        <button
          type="button"
          disabled={actionDisabled}
          onClick={onAction}
          className={`w-full mt-6 py-3 rounded-lg font-extrabold transition-colors shadow-sm uppercase text-xs tracking-widest disabled:opacity-70 ${resolvedActionClassName}`}
        >
          {actionLabel}
        </button>
      ) : null}
    </section>
  );
}