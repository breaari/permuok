export function ProfileActionBar({
  isReview,
  isRejected,
  isUnpaid,
  isUnpaidChangesPending,
  isActive,
  isActiveChangesPending,
  canSubmit,
  footerHint,
  submitBlockReason,
  onSubmitReview,
  onGoToBilling,
}) {
  const showBillingButton = isUnpaid || isUnpaidChangesPending;

  const showSubmitButton =
    !isReview &&
    !isUnpaid &&
    !isUnpaidChangesPending &&
    !isActive &&
    !isActiveChangesPending;

  const disabledStatusLabel = isReview
    ? "En revisión"
    : isActiveChangesPending
    ? "Cambios en revisión"
    : isActive
    ? "Cuenta activa"
    : isRejected
    ? "Rechazada"
    : "Enviar a Revisión";

  const billingButtonLabel = isUnpaidChangesPending
    ? "Activar membresía"
    : "Ir a membresía";

  return (
    <footer className="fixed bottom-0 left-0 right-0 md:left-64 bg-white/80 backdrop-blur-md border-t border-slate-200 py-4 px-6 md:px-10 z-50">
      <div className="max-w-5xl mx-auto flex items-center justify-between gap-4">
        <div className="hidden md:flex items-center gap-2 text-slate-500 text-sm">
          <span
            className={
              footerHint.tone === "success"
                ? "text-emerald-600"
                : footerHint.tone === "info"
                ? "text-primary"
                : "text-amber-500"
            }
          >
            ●
          </span>
          {footerHint.label}
        </div>

        <div className="flex items-center gap-4 w-full md:w-auto">
          <button
            type="submit"
            form="profileForm"
            disabled={isReview}
            className="flex-1 md:flex-none px-6 py-3 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-colors disabled:opacity-60"
          >
            Guardar Perfil
          </button>

          {showBillingButton ? (
            <button
              type="button"
              onClick={onGoToBilling}
              className="flex-1 md:flex-none px-8 py-3 rounded-lg font-bold flex items-center justify-center gap-2 bg-primary text-white hover:bg-primary/90"
            >
              {billingButtonLabel}
            </button>
          ) : showSubmitButton ? (
            <button
              type="button"
              disabled={!canSubmit}
              onClick={onSubmitReview}
              className={`flex-1 md:flex-none px-8 py-3 rounded-lg font-bold flex items-center justify-center gap-2 ${
                canSubmit
                  ? "bg-primary text-white hover:bg-primary/90"
                  : "bg-slate-200 text-slate-400 cursor-not-allowed"
              }`}
              title={submitBlockReason || ""}
            >
              Enviar a Revisión
            </button>
          ) : (
            <button
              type="button"
              disabled
              className="flex-1 md:flex-none px-8 py-3 rounded-lg font-bold flex items-center justify-center gap-2 bg-slate-200 text-slate-400 cursor-not-allowed"
            >
              {disabledStatusLabel}
            </button>
          )}
        </div>
      </div>
    </footer>
  );
}