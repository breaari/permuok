import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Icon } from "../icons/Index";
import { resolveCurrentPlan, getPlanMeta } from "./BillingHelpers";

function getUsageMeta(used, limit, labelSingular, labelPlural) {
  const safeUsed = Number(used ?? 0);
  const safeLimit = Number(limit ?? 0);
  const percent =
    safeLimit > 0 ? Math.min(100, Math.round((safeUsed / safeLimit) * 100)) : 0;

  let helper = "Sin cupo disponible";
  let helperClass = "text-slate-400";

  if (safeLimit > 0) {
    const available = Math.max(safeLimit - safeUsed, 0);

    if (available === 0) {
      helper = "Límite alcanzado";
      helperClass = "text-amber-600";
    } else if (available === 1) {
      helper = `1 ${labelSingular} disponible`;
      helperClass = "text-slate-400";
    } else {
      helper = `${available} ${labelPlural} disponibles`;
      helperClass = "text-slate-400";
    }
  }

  return {
    percent,
    helper,
    helperClass,
  };
}

export function BillingActiveView({ err, plans, access, isChangesPending }) {
  const nav = useNavigate();

  const currentPlan = useMemo(() => {
    return resolveCurrentPlan(plans, access);
  }, [plans, access]);

  const agentsLimit = Number(access?.limits?.agents ?? 0);
  const investorsLimit = Number(access?.limits?.investors ?? 0);
  const realEstateUsersLimit = Number(access?.limits?.real_estate_users ?? 1);

  const agentsUsed = Number(access?.usage?.agents ?? 0);
  const investorsUsed = Number(access?.usage?.investors ?? 0);
  const realEstateUsersUsed = Number(access?.usage?.real_estate_users ?? 1);

  const usersUsage = getUsageMeta(
    realEstateUsersUsed,
    realEstateUsersLimit,
    "usuario",
    "usuarios",
  );

  const agentsUsage = getUsageMeta(
    agentsUsed,
    agentsLimit,
    "agente",
    "agentes",
  );

  const investorsUsage = getUsageMeta(
    investorsUsed,
    investorsLimit,
    "inversor",
    "inversores",
  );

  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <main className="max-w-7xl mx-auto w-full px-4 md:px-6 py-8 pb-20 space-y-8">
        <div
          className={`rounded-xl border p-4 md:p-5 flex items-start gap-3 ${
            isChangesPending
              ? "border-amber-200 bg-amber-50"
              : "border-emerald-200 bg-emerald-50"
          }`}
        >
          <span
            className={
              isChangesPending
                ? "text-amber-600 mt-0.5"
                : "text-emerald-600 mt-0.5"
            }
          >
            <Icon
              name={isChangesPending ? "info" : "checkCircle"}
              size={18}
            />
          </span>

          <div>
            <p className="text-slate-900 font-bold text-base">
              {isChangesPending
                ? "Tu membresía está activa y tu perfil tiene cambios pendientes"
                : "Tu membresía está activa"}
            </p>
            <p className="text-sm text-slate-700">
              {isChangesPending
                ? "Podés seguir operando normalmente. Los cambios del perfil quedarán sujetos a revisión."
                : "Tu inmobiliaria ya cuenta con una membresía activa y puede operar normalmente."}
            </p>
          </div>
        </div>

        <div className="flex items-center justify-between gap-4 flex-wrap">
          <div className="space-y-2 text-left">
            <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
              Membresía
            </h1>
            <p className="text-slate-500 max-w-2xl text-base text-left">
              Consultá tu plan actual, el uso de cupos y administrá los usuarios
              de tu cuenta.
            </p>
          </div>

          <span
            className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${
              isChangesPending
                ? "bg-amber-100 text-amber-700"
                : "bg-primary/10 text-primary"
            }`}
          >
            {isChangesPending ? "Activa con cambios pendientes" : "Activa"}
          </span>
        </div>

        {err && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}

        <section className="grid grid-cols-1 md:grid-cols-5 gap-6">
          <div className="md:col-span-3 flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
              <div className="flex items-start gap-4 mb-5">
                <div className="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                  <Icon name="badge" size={24} />
                </div>

                <div>
                  <h2 className="text-xl font-bold text-slate-900">
                    {currentPlan?.name || "Plan activo"}
                  </h2>

                  {currentPlan ? (
                    <p className="text-primary font-semibold mt-1">
                      ${Number(currentPlan.price_ars).toLocaleString("es-AR")}
                      <span className="text-slate-500 font-normal text-sm">
                        {" "}
                        / {currentPlan.duration_days} días
                      </span>
                    </p>
                  ) : (
                    <p className="text-slate-500 text-sm mt-1">
                      Plan asociado a tu membresía actual
                    </p>
                  )}
                </div>
              </div>

              <p className="text-slate-600 text-sm leading-relaxed mb-8">
                {currentPlan
                  ? getPlanMeta(currentPlan, plans).description
                  : "Tu plan actual permite gestionar usuarios y operar en la plataforma según los límites contratados."}
              </p>
            </div>

            <div className="flex flex-wrap gap-3">
              <button
                type="button"
                onClick={() => nav("/users")}
                className="flex-1 min-w-[180px] px-4 py-3 bg-primary text-white text-sm font-bold rounded-lg hover:bg-primary/90 transition-colors"
              >
                Administrar usuarios
              </button>

              <button
                type="button"
                onClick={() => nav("/billing/change-plan")}
                className="flex-1 min-w-[180px] px-4 py-3 bg-slate-100 text-slate-700 text-sm font-bold rounded-lg hover:bg-slate-200 transition-colors"
              >
                Cambiar plan
              </button>
            </div>
          </div>

          <div className="md:col-span-2 flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 className="text-base font-bold text-slate-900">Uso del cupo</h3>

            <div className="flex flex-col gap-2">
              <div className="flex justify-between items-end">
                <span className="text-sm font-medium text-slate-700">
                  Usuarios
                </span>
                <span className="text-xs font-bold text-primary">
                  {realEstateUsersUsed} / {realEstateUsersLimit}
                </span>
              </div>

              <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                <div
                  className="h-full bg-primary rounded-full transition-all"
                  style={{ width: `${usersUsage.percent}%` }}
                />
              </div>

              <p className={`text-[11px] font-medium ${usersUsage.helperClass}`}>
                {usersUsage.helper}
              </p>
            </div>

            <div className="flex flex-col gap-2">
              <div className="flex justify-between items-end">
                <span className="text-sm font-medium text-slate-700">
                  Agentes
                </span>
                <span
                  className={`text-xs font-bold ${
                    agentsUsage.percent > 0 ? "text-primary" : "text-slate-500"
                  }`}
                >
                  {agentsUsed} / {agentsLimit}
                </span>
              </div>

              <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                <div
                  className="h-full bg-primary rounded-full transition-all"
                  style={{ width: `${agentsUsage.percent}%` }}
                />
              </div>

              <p className={`text-[11px] font-medium ${agentsUsage.helperClass}`}>
                {agentsUsage.helper}
              </p>
            </div>

            <div className="flex flex-col gap-2">
              <div className="flex justify-between items-end">
                <span className="text-sm font-medium text-slate-700">
                  Inversores
                </span>
                <span
                  className={`text-xs font-bold ${
                    investorsUsage.percent > 0
                      ? "text-primary"
                      : "text-slate-500"
                  }`}
                >
                  {investorsUsed} / {investorsLimit}
                </span>
              </div>

              <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                <div
                  className="h-full bg-primary rounded-full transition-all"
                  style={{ width: `${investorsUsage.percent}%` }}
                />
              </div>

              <p
                className={`text-[11px] font-medium ${investorsUsage.helperClass}`}
              >
                {investorsUsage.helper}
              </p>
            </div>
          </div>
        </section>

        <section className="rounded-xl bg-slate-100/70 border border-slate-200 p-4 flex items-start gap-3">
          <span className="text-slate-400 mt-0.5">
            <Icon name="info" size={18} />
          </span>

          <p className="text-xs text-slate-500 leading-relaxed">
            Tu membresía determina la cantidad de usuarios y capacidades
            disponibles en la plataforma. Si necesitás ampliar cupos o habilitar
            nuevas funcionalidades, podés solicitar un cambio de plan desde esta
            misma sección.
          </p>
        </section>
      </main>
    </div>
  );
}