import { Icon } from "../../../../ui/icons/Index";

function formatMoney(value) {
  const amount = Number(value || 0);

  if (!Number.isFinite(amount) || amount <= 0) {
    return "—";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 0,
  }).format(amount);
}

export default function AdminMembershipCard({
  membership,
  membershipStatus,
  membershipLabel,
  membershipTone,
  formatDate,

  plan = null,
  scheduledPlan = null,

  title = "Membresía",
  hideButton = false,
  buttonLabel = "Ver más detalles",
  onViewMore,

  emptyMessage = "No hay una membresía activa asociada.",
}) {
  const hasMembership = !!membership;
  const hasPlan = !!plan;

  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-fit">
      <div className="p-6 border-b border-slate-100 flex items-center gap-2">
        <span className="text-primary">
          <Icon name="creditCard" size={20} />
        </span>
        <h2 className="text-xl font-bold text-slate-900">{title}</h2>
      </div>

      <div className="p-6 space-y-4 text-sm">
        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Estado
          </span>
          <span
            className={`px-2 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest ${membershipTone}`}
          >
            {membershipLabel}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Plan actual
          </span>
          <span className="text-slate-900 font-bold text-right">
            {hasPlan ? plan.name : "—"}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Precio
          </span>
          <span className="text-slate-900 font-bold">
            {hasPlan ? formatMoney(plan.price_ars) : "—"}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Ciclo
          </span>
          <span className="text-slate-900 font-bold">
            {hasMembership ? "Mensual" : "—"}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Inicio
          </span>
          <span className="text-slate-900 font-bold">
            {formatDate(membership?.start_date)}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Vencimiento
          </span>
          <span className="text-slate-900 font-bold">
            {formatDate(membership?.end_date)}
          </span>
        </div>

        {!hasMembership && (
          <div className="pt-1 text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded p-3">
            {emptyMessage}
          </div>
        )}

        {membershipStatus === "cancel_at_period_end" && (
          <div className="pt-1 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded p-3">
            La renovación fue cancelada y la membresía seguirá activa hasta su vencimiento.
          </div>
        )}

        {membershipStatus === "scheduled_change" && (
          <div className="space-y-3">
            <div className="pt-1 text-xs text-sky-700 bg-sky-50 border border-sky-100 rounded p-3">
              Hay un cambio de plan programado para la próxima renovación.
            </div>

            <div className="rounded-lg border border-sky-100 bg-sky-50/50 p-3">
              <div className="flex justify-between items-center gap-4 text-xs">
                <span className="text-slate-500 font-bold uppercase tracking-widest">
                  Próximo plan
                </span>
                <span className="text-slate-900 font-bold text-right">
                  {scheduledPlan?.name || "—"}
                </span>
              </div>

              <div className="flex justify-between items-center gap-4 text-xs mt-3">
                <span className="text-slate-500 font-bold uppercase tracking-widest">
                  Próximo precio
                </span>
                <span className="text-slate-900 font-bold">
                  {scheduledPlan ? formatMoney(scheduledPlan.price_ars) : "—"}
                </span>
              </div>

              <div className="flex justify-between items-center gap-4 text-xs mt-3">
                <span className="text-slate-500 font-bold uppercase tracking-widest">
                  Fecha cambio
                </span>
                <span className="text-slate-900 font-bold">
                  {formatDate(membership?.scheduled_change_at)}
                </span>
              </div>
            </div>
          </div>
        )}

        {!hideButton && (
          <button
            type="button"
            onClick={onViewMore}
            className="w-full mt-6 border border-slate-300 hover:bg-slate-50 text-slate-700 py-3 rounded-lg font-extrabold transition-colors text-xs tracking-widest uppercase"
          >
            {buttonLabel}
          </button>
        )}
      </div>
    </section>
  );
}