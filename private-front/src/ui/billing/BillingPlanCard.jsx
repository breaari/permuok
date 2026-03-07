import { Icon } from "../icons/Index";
import { getPlanMeta } from "./BillingHelpers";

export function BillingPlanCard({
  plan,
  plans = [],
  mode = "select", // select | blocked | current
  featured: featuredProp,
  buttonLabel,
  buttonDisabled = false,
  onAction = () => {},
}) {
  const meta = getPlanMeta(plan, plans);
  const featured = featuredProp ?? meta.featured;

  const isCurrent = mode === "current";
  const isBlocked = mode === "blocked";

  const resolvedButtonLabel =
    buttonLabel ??
    (isCurrent
      ? "Plan actualmente activo"
      : isBlocked
        ? "Elegir y pagar"
        : "Elegir este plan");

  return (
    <div
      className={`relative bg-white rounded-xl flex flex-col border p-8 transition-all ${
        isCurrent
          ? "border-2 border-primary shadow-xl"
          : featured
            ? "border-2 border-primary shadow-xl scale-[1.01]"
            : "border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
      }`}
    >
      {(isCurrent || (featured && meta.badge)) && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full text-[11px] font-black uppercase tracking-wider shadow-md bg-primary text-white">
          {isCurrent ? "Plan actual" : meta.badge}
        </div>
      )}

      <div className="mb-8">
        <h2 className="text-xl font-bold text-slate-900">{plan.name}</h2>

        <div className="mt-4 flex items-baseline gap-1">
          <span className="text-4xl font-black text-primary">
            ${Number(plan.price_ars).toLocaleString("es-AR")}
          </span>
          <span className="text-slate-500 font-medium">
            / {plan.duration_days} días
          </span>
        </div>

        <p className="text-sm text-slate-500 mt-2">{meta.description}</p>
      </div>

      <button
        type="button"
        disabled={buttonDisabled || isCurrent || isBlocked}
        onClick={onAction}
        className={`w-full rounded-xl py-3 px-4 font-bold transition-all mb-8 ${
          isCurrent
            ? "bg-slate-100 text-slate-400 cursor-not-allowed"
            : isBlocked
              ? featured
                ? "bg-primary/30 text-white cursor-not-allowed"
                : "bg-slate-200 text-slate-400 cursor-not-allowed"
              : featured
                ? "bg-primary text-white hover:bg-primary/90 shadow-md shadow-primary/20"
                : "bg-slate-100 text-slate-900 hover:bg-slate-200"
        } disabled:opacity-60`}
        title={isBlocked ? "Disponible solo para perfiles aprobados" : ""}
      >
        {resolvedButtonLabel}
      </button>

      <div className="space-y-4 text-sm pt-2 border-t border-slate-100">
        <div className="flex items-center gap-3 text-slate-700">
          <span className="text-primary">
            <Icon name="checkCircle" size={18} />
          </span>
          <span>
            <b>{plan.max_agents}</b>{" "}
            {plan.max_agents === 1 ? "Agente" : "Agentes"}
          </span>
        </div>

        <div className="flex items-center gap-3 text-slate-700">
          <span
            className={
              Number(plan.max_investors) > 0
                ? "text-primary"
                : "text-slate-300"
            }
          >
            <Icon
              name={
                Number(plan.max_investors) > 0
                  ? "checkCircle"
                  : "block"
              }
              size={18}
            />
          </span>
          <span>
            <b>{plan.max_investors}</b>{" "}
            {plan.max_investors === 1 ? "Inversor" : "Inversores"}
          </span>
        </div>

        <div className="flex items-center gap-3 text-slate-700">
          <span
            className={
              Number(plan.can_publish_projects) === 1
                ? "text-primary"
                : "text-slate-300"
            }
          >
            <Icon
              name={
                Number(plan.can_publish_projects) === 1
                  ? "checkCircle"
                  : "block"
              }
              size={18}
            />
          </span>
          <span>
            Publicar proyectos:{" "}
            <b>{Number(plan.can_publish_projects) === 1 ? "Sí" : "No"}</b>
          </span>
        </div>
      </div>
    </div>
  );
}