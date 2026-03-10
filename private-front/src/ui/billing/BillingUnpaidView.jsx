import { Icon } from "../icons/Index";
import { BillingPlanCard } from "./BillingPlanCard";

export function BillingUnpaidView({
  loading,
  err,
  plans,
  paymentStarted,
  processingPlanCode,
  onStartPayment,
  onRefreshStatus,
  isChangesPending = false,
}) {
  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <main className="max-w-7xl mx-auto w-full px-4 md:px-6 py-8 pb-20 space-y-8">
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 md:p-5 flex items-start gap-3">
          <span className="text-amber-600 mt-0.5">
            <Icon name="info" size={18} />
          </span>

          <div>
            <p className="text-slate-900 font-bold text-base">
              {isChangesPending
                ? "Tu perfil tiene cambios pendientes"
                : "Tu cuenta está aprobada"}
            </p>
            <p className="text-sm text-slate-700">
              {isChangesPending
                ? "Tu perfil quedó con cambios pendientes de revisión, pero igualmente necesitás una membresía activa para comenzar a operar en la plataforma."
                : "Necesitás una membresía activa para comenzar a operar en la plataforma."}
            </p>
          </div>
        </div>

        {paymentStarted && (
          <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 md:p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div className="flex items-start gap-3">
              <span className="text-primary mt-0.5">
                <Icon name="checkCircle" size={18} />
              </span>

              <div>
                <p className="text-slate-900 font-bold text-base">
                  Pago iniciado
                </p>
                <p className="text-sm text-slate-700">
                  Se abrió una nueva pestaña para completar el pago. Cuando la
                  acreditación impacte, esta pantalla se actualizará
                  automáticamente.
                </p>
              </div>
            </div>

            <button
              type="button"
              onClick={onRefreshStatus}
              className="text-sm font-bold text-primary flex items-center gap-1 hover:underline underline-offset-4"
            >
              Actualizar ahora
              <span className="text-primary">→</span>
            </button>
          </div>
        )}

        <div className="space-y-2 text-left">
          <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
            Membresía
          </h1>
          <p className="text-slate-500 max-w-2xl text-base text-left">
            Seleccioná el plan que mejor se adapte a tu inmobiliaria. Todos los
            planes incluyen acceso a la plataforma y herramientas para gestionar
            tu operación.
          </p>
        </div>

        {err && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}

        {loading ? (
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-sm text-slate-500">
            Cargando planes...
          </div>
        ) : plans.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-sm text-slate-500">
            No hay planes disponibles.
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            {plans.map((plan) => (
              <BillingPlanCard
                key={plan.id}
                plan={plan}
                plans={plans}
                mode="select"
                buttonDisabled={processingPlanCode !== null}
                buttonLabel={
                  processingPlanCode === plan.code
                    ? "Procesando..."
                    : "Elegir y pagar"
                }
                onAction={() => onStartPayment(plan.code)}
              />
            ))}
          </div>
        )}
      </main>
    </div>
  );
}