import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../api/http";
import { useAuth } from "../auth/AuthContext";
import { Icon } from "../ui/icons/Index";
import { BillingPlanCard } from "../ui/billing/BillingPlanCard";
import { resolveCurrentPlan } from "../ui/billing/BillingHelpers";

function getChangeType(currentPlan, targetPlan) {
  if (!currentPlan || !targetPlan) return "unknown";

  const currentPrice = Number(currentPlan.price_ars ?? 0);
  const targetPrice = Number(targetPlan.price_ars ?? 0);

  if (targetPrice > currentPrice) return "upgrade";
  if (targetPrice < currentPrice) return "downgrade";
  return "same";
}

function getPlanActionMeta(currentPlan, targetPlan, processingPlanCode) {
  const changeType = getChangeType(currentPlan, targetPlan);
  const isCurrent = currentPlan?.id === targetPlan?.id;
  const isProcessing = processingPlanCode === targetPlan?.code;

  if (isCurrent) {
    return {
      mode: "current",
      buttonLabel: "Plan actualmente activo",
      helperLabel: "Tu plan actual",
      helperTone: "primary",
      disabled: true,
    };
  }

  if (isProcessing) {
    return {
      mode: "select",
      buttonLabel: "Procesando...",
      helperLabel:
        changeType === "upgrade"
          ? "Actualización inmediata"
          : "Cambio próximo ciclo",
      helperTone: changeType === "upgrade" ? "emerald" : "amber",
      disabled: true,
    };
  }

  if (changeType === "upgrade") {
    return {
      mode: "select",
      buttonLabel: "Actualizar ahora",
      helperLabel: "Se aplica de inmediato",
      helperTone: "emerald",
      disabled: false,
    };
  }

  if (changeType === "downgrade") {
    return {
      mode: "select",
      buttonLabel: "Programar cambio",
      helperLabel: "Se aplica en la próxima renovación",
      helperTone: "amber",
      disabled: false,
    };
  }

  return {
    mode: "select",
    buttonLabel: "Elegir este plan",
    helperLabel: "",
    helperTone: "slate",
    disabled: false,
  };
}

function HelperPill({ label, tone = "slate" }) {
  if (!label) return null;

  const toneClasses = {
    primary: "bg-primary/10 text-primary",
    emerald: "bg-emerald-100 text-emerald-700",
    amber: "bg-amber-100 text-amber-700",
    slate: "bg-slate-100 text-slate-600",
  };

  return (
    <span
      className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${
        toneClasses[tone] ?? toneClasses.slate
      }`}
    >
      {label}
    </span>
  );
}

export default function ChangePlan() {
  const nav = useNavigate();
  const { access } = useAuth();

  const [loading, setLoading] = useState(true);
  const [plans, setPlans] = useState([]);
  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");
  const [processingPlanCode, setProcessingPlanCode] = useState(null);
  const [cancelling, setCancelling] = useState(false);

  const level = access?.level;
  const isActive =
    level === "real_estate_active" ||
    level === "real_estate_active_changes_pending";

  useEffect(() => {
    if (!isActive) {
      nav("/billing", { replace: true });
    }
  }, [isActive, nav]);

  async function loadPlans() {
    setErr("");
    setLoading(true);

    try {
      const res = await api.get("/plans");
      const data = unwrap(res);
      setPlans(data?.plans ?? []);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudieron cargar los planes"));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPlans();
  }, []);

  const plansSorted = useMemo(() => {
    return [...plans].sort((a, b) => Number(a.price_ars) - Number(b.price_ars));
  }, [plans]);

  const currentPlan = useMemo(() => {
    return resolveCurrentPlan(plansSorted, access);
  }, [plansSorted, access]);

  async function startPlanChange(plan) {
    if (!plan || !currentPlan) return;

    setErr("");
    setOk("");
    setProcessingPlanCode(plan.code);

    try {
      const changeType = getChangeType(currentPlan, plan);

      const previewRes = await api.post("/billing/change-plan/preview", {
        target_plan_code: plan.code,
      });

      const preview = unwrap(previewRes);

      if (changeType === "upgrade") {
        const confirmRes = await api.post("/billing/change-plan/confirm", {
          target_plan_code: plan.code,
          mode: "immediate",
        });

        const data = unwrap(confirmRes);

        if (data?.init_point) {
          window.open(data.init_point, "_blank", "noopener,noreferrer");
          setOk("Se inició el proceso de actualización de plan.");
        } else {
          setErr("No se pudo generar el enlace de pago para el upgrade.");
        }

        return;
      }

      if (changeType === "downgrade") {
        await api.post("/billing/change-plan/confirm", {
          target_plan_code: plan.code,
          mode: "next_cycle",
        });

        setOk(
          `El cambio al plan "${plan.name}" quedó programado para la próxima renovación.`
        );
        return;
      }

      if (preview?.message) {
        setOk(preview.message);
      }
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo procesar el cambio de plan"));
    } finally {
      setProcessingPlanCode(null);
    }
  }

  async function cancelMembership() {
    setErr("");
    setOk("");
    setCancelling(true);

    try {
      await api.post("/billing/cancel", {});
      setOk(
        "La cancelación quedó programada. Tu membresía seguirá activa hasta el vencimiento actual."
      );
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo cancelar la renovación"));
    } finally {
      setCancelling(false);
    }
  }

  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <main className="max-w-7xl mx-auto w-full px-4 md:px-6 py-8 pb-20 space-y-8">
        <div className="space-y-2 text-left">
          <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
            Cambiar de plan
          </h1>
          <p className="text-slate-500 max-w-2xl text-base text-left">
            Seleccioná el plan que mejor se adapte a tus nuevas necesidades.
            Los upgrades se aplican de inmediato. Los cambios a un plan menor se
            programan para la próxima renovación.
          </p>
        </div>

        {err && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}

        {ok && (
          <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {ok}
          </div>
        )}

        {loading ? (
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-sm text-slate-500">
            Cargando planes...
          </div>
        ) : plansSorted.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-sm text-slate-500">
            No hay planes disponibles.
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            {plansSorted.map((plan) => {
              const actionMeta = getPlanActionMeta(
                currentPlan,
                plan,
                processingPlanCode
              );

              return (
                <div key={plan.id} className="space-y-3">
                  <HelperPill
                    label={actionMeta.helperLabel}
                    tone={actionMeta.helperTone}
                  />

                  <BillingPlanCard
                    plan={plan}
                    plans={plansSorted}
                    mode={actionMeta.mode}
                    buttonDisabled={actionMeta.disabled}
                    buttonLabel={actionMeta.buttonLabel}
                    onAction={() => {
                      if (!actionMeta.disabled) {
                        startPlanChange(plan);
                      }
                    }}
                  />
                </div>
              );
            })}
          </div>
        )}

        <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-100">
            <h2 className="text-xl font-bold text-slate-900">
              Cancelar membresía
            </h2>
            <p className="text-sm text-slate-500 mt-1">
              La cancelación se aplicará al finalizar el período actual. No se
              perderá el acceso de forma inmediata.
            </p>
          </div>

          <div className="p-6 flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <div className="text-sm text-slate-600">
              Tu plan seguirá activo hasta su fecha de vencimiento.
            </div>

            <button
              type="button"
              disabled={cancelling}
              onClick={cancelMembership}
              className="px-6 py-3 rounded-lg border border-red-300 text-red-600 font-bold hover:bg-red-50 transition-colors disabled:opacity-60"
            >
              {cancelling ? "Procesando..." : "Cancelar renovación"}
            </button>
          </div>
        </section>

        <div>
          <button
            type="button"
            onClick={() => nav("/billing")}
            className="flex items-center gap-2 px-2 py-2 text-slate-500 hover:text-slate-900 transition-colors font-semibold text-sm"
          >
            <Icon name="arrowLeft" size={16} />
            Cancelar y volver
          </button>
        </div>
      </main>
    </div>
  );
}