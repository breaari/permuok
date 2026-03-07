import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Icon } from "../icons/Index";
import { BillingPlanCard } from "./BillingPlanCard";

export function BillingBlockedView({ level, loading, err, plans }) {
  const nav = useNavigate();

  const content = useMemo(() => {
    switch (level) {
      case "real_estate_draft":
      case "real_estate_not_linked":
        return {
          title: "Completá tu perfil",
          desc: "Antes de contratar una membresía necesitás completar el perfil de la inmobiliaria y enviarlo a revisión.",
        };

      case "real_estate_review":
        return {
          title: "Perfil en revisión",
          desc: "Tu perfil está siendo revisado. Cuando sea aprobado vas a poder contratar una membresía.",
        };

      case "real_estate_rejected":
        return {
          title: "Perfil rechazado",
          desc: "Corregí tu perfil y volvé a enviarlo a revisión. Una vez aprobado, vas a poder contratar una membresía.",
        };

      default:
        return {
          title: "Membresía no disponible",
          desc: "Todavía no tenés permisos para contratar una membresía.",
        };
    }
  }, [level]);

  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <main className="max-w-7xl mx-auto w-full px-4 md:px-6 py-8 pb-20 space-y-8">
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 md:p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div className="flex items-start gap-3">
            <span className="text-amber-600 mt-0.5">
              <Icon name="info" size={18} />
            </span>

            <div>
              <p className="text-slate-900 font-bold text-base">
                {content.title}
              </p>
              <p className="text-sm text-slate-700">{content.desc}</p>
            </div>
          </div>

          <button
            type="button"
            onClick={() => nav("/my-profile")}
            className="text-sm font-bold text-primary flex items-center gap-1 hover:underline underline-offset-4"
          >
            Ir a mi perfil
            <span className="text-primary">→</span>
          </button>
        </div>

        <div className="space-y-2 text-left">
          <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
            Membresía
          </h1>
          <p className="text-slate-500 max-w-2xl text-base text-left">
            Ya podés conocer los planes disponibles. La contratación se habilita
            únicamente para perfiles aprobados.
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
                mode="blocked"
              />
            ))}
          </div>
        )}
      </main>
    </div>
  );
}