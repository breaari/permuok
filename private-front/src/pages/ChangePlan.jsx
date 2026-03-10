import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../api/http";
import { useAuth } from "../auth/AuthContext";
import { Icon } from "../ui/icons/Index";
import { BillingPlanCard } from "../ui/billing/BillingPlanCard";
import { resolveCurrentPlan } from "../ui/billing/BillingHelpers";
import { CancelMembershipModal } from "../ui/billing/CancelMembershipModal";

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
  const { access, loadMe } = useAuth();

  const [loading, setLoading] = useState(true);
  const [plans, setPlans] = useState([]);
  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");
  const [processingPlanCode, setProcessingPlanCode] = useState(null);
  const [cancelling, setCancelling] = useState(false);
  const [paymentStarted, setPaymentStarted] = useState(false);
  const [cancelModalOpen, setCancelModalOpen] = useState(false);

  const pollingRef = useRef(null);

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

  async function refreshMembershipStatus({ silent = false } = {}) {
    try {
      await loadMe?.({ force: true });
    } catch (e) {
      if (!silent) {
        setErr(
          getErrorMessage(e, "No se pudo actualizar el estado de la membresía"),
        );
      }
    }
  }

  useEffect(() => {
    loadPlans();
  }, []);

  useEffect(() => {
    if (!paymentStarted) return;

    pollingRef.current = window.setInterval(() => {
      refreshMembershipStatus({ silent: true });
    }, 5000);

    return () => {
      if (pollingRef.current) {
        window.clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
    };
  }, [paymentStarted]);

  useEffect(() => {
    if (!paymentStarted) return;

    async function handleWindowFocus() {
      await refreshMembershipStatus({ silent: true });
    }

    async function handleVisibilityChange() {
      if (document.visibilityState === "visible") {
        await refreshMembershipStatus({ silent: true });
      }
    }

    window.addEventListener("focus", handleWindowFocus);
    document.addEventListener("visibilitychange", handleVisibilityChange);

    return () => {
      window.removeEventListener("focus", handleWindowFocus);
      document.removeEventListener("visibilitychange", handleVisibilityChange);
    };
  }, [paymentStarted]);

  const plansSorted = useMemo(() => {
    return [...plans].sort((a, b) => Number(a.price_ars) - Number(b.price_ars));
  }, [plans]);

  const currentPlan = useMemo(() => {
    return resolveCurrentPlan(plansSorted, access);
  }, [plansSorted, access]);

  const scheduledPlan = useMemo(() => {
    const scheduledPlanId = access?.membership?.scheduled_plan_id;
    if (!scheduledPlanId) return null;

    return (
      plansSorted.find((plan) => Number(plan.id) === Number(scheduledPlanId)) ??
      null
    );
  }, [plansSorted, access]);

  const hasScheduledDowngrade = !!scheduledPlan;
  const cancelAtPeriodEnd =
    Number(access?.membership?.cancel_at_period_end ?? 0) === 1;

  const previousCurrentPlanIdRef = useRef(currentPlan?.id ?? null);

  useEffect(() => {
    const previousCurrentPlanId = previousCurrentPlanIdRef.current;
    const nextCurrentPlanId = currentPlan?.id ?? null;

    if (
      paymentStarted &&
      previousCurrentPlanId &&
      nextCurrentPlanId &&
      previousCurrentPlanId !== nextCurrentPlanId
    ) {
      setPaymentStarted(false);
      setOk("El cambio de plan fue aplicado correctamente.");

      if (pollingRef.current) {
        window.clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
    }

    previousCurrentPlanIdRef.current = nextCurrentPlanId;
  }, [currentPlan?.id, paymentStarted]);

  async function startPlanChange(plan) {
    if (!plan || !currentPlan) return;

    setErr("");
    setOk("");
    setProcessingPlanCode(plan.code);

    try {
      const changeType = getChangeType(currentPlan, plan);

      await api.post("/billing/change-plan/preview", {
        target_plan_code: plan.code,
      });

      if (changeType === "upgrade") {
        const confirmRes = await api.post("/billing/change-plan/confirm", {
          target_plan_code: plan.code,
          mode: "immediate",
        });

        const data = unwrap(confirmRes);

        if (data?.init_point) {
          setPaymentStarted(true);
          window.open(data.init_point, "_blank", "noopener,noreferrer");
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
          `El cambio al plan "${plan.name}" quedó programado para la próxima renovación.`,
        );
        await refreshMembershipStatus({ silent: true });
        return;
      }
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo procesar el cambio de plan"));
    } finally {
      setProcessingPlanCode(null);
    }
  }

  function openCancelModal() {
    if (cancelling || cancelAtPeriodEnd) return;
    setCancelModalOpen(true);
  }

  function closeCancelModal() {
    if (cancelling) return;
    setCancelModalOpen(false);
  }

  async function cancelMembership() {
    setErr("");
    setOk("");
    setCancelling(true);

    try {
      await api.post("/billing/cancel", {});
      setOk(
        "La cancelación quedó programada. Tu membresía seguirá activa hasta el vencimiento actual.",
      );
      setCancelModalOpen(false);
      await refreshMembershipStatus({ silent: true });
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo cancelar la renovación"));
    } finally {
      setCancelling(false);
    }
  }

  return (
    <>
      <div className="bg-background-light min-h-[calc(100vh-64px)]">
        <main className="max-w-7xl mx-auto w-full px-4 md:px-6 py-8 pb-20 space-y-8">
          <div className="space-y-2 text-left">
            <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
              Cambiar de plan
            </h1>
            <p className="text-slate-500 max-w-2xl text-base text-left">
              Seleccioná el plan que mejor se adapte a tus nuevas necesidades.
              Los upgrades se aplican de inmediato. Los cambios a un plan menor
              se programan para la próxima renovación.
            </p>
          </div>

          {paymentStarted && (
            <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 md:p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
              <div className="flex items-start gap-3">
                <span className="text-primary mt-0.5">
                  <Icon name="checkCircle" size={18} />
                </span>

                <div>
                  <p className="text-slate-900 font-bold text-base">
                    Cambio de plan iniciado
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
                onClick={() => refreshMembershipStatus()}
                className="text-sm font-bold text-primary flex items-center gap-1 hover:underline underline-offset-4"
              >
                Actualizar ahora
                <span className="text-primary">→</span>
              </button>
            </div>
          )}

          {hasScheduledDowngrade && (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 md:p-5 flex items-start gap-3">
              <span className="text-amber-600 mt-0.5">
                <Icon name="info" size={18} />
              </span>

              <div>
                <p className="text-slate-900 font-bold text-base">
                  Tenés un cambio de plan programado
                </p>
                <p className="text-sm text-slate-700">
                  Al finalizar el período actual se aplicará el plan{" "}
                  <b>{scheduledPlan?.name}</b>.
                </p>
              </div>
            </div>
          )}

          {cancelAtPeriodEnd && !hasScheduledDowngrade && (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 md:p-5 flex items-start gap-3">
              <span className="text-amber-600 mt-0.5">
                <Icon name="info" size={18} />
              </span>

              <div>
                <p className="text-slate-900 font-bold text-base">
                  Tenés una cancelación programada
                </p>
                <p className="text-sm text-slate-700">
                  Tu membresía seguirá activa hasta el vencimiento actual y luego
                  no se renovará. Si seleccionás otro plan para la próxima
                  renovación, esta cancelación se reemplazará por ese cambio
                  programado.
                </p>
              </div>
            </div>
          )}

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
                const isScheduled =
                  Number(access?.membership?.scheduled_plan_id ?? 0) ===
                  Number(plan.id);

                const actionMeta = isScheduled
                  ? {
                      mode: "select",
                      buttonLabel: "Cambio programado",
                      helperLabel: "Próxima renovación",
                      helperTone: "amber",
                      disabled: true,
                    }
                  : getPlanActionMeta(
                      currentPlan,
                      plan,
                      processingPlanCode,
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
                {cancelAtPeriodEnd
                  ? "La renovación ya está cancelada. Si elegís otro plan para el próximo ciclo, esa cancelación se reemplazará automáticamente."
                  : "Tu plan seguirá activo hasta su fecha de vencimiento."}
              </div>

              <button
                type="button"
                disabled={cancelling || cancelAtPeriodEnd}
                onClick={openCancelModal}
                className="px-6 py-3 rounded-lg border border-red-300 text-red-600 font-bold hover:bg-red-50 transition-colors disabled:opacity-60"
              >
                {cancelling
                  ? "Procesando..."
                  : cancelAtPeriodEnd
                    ? "Renovación cancelada"
                    : "Cancelar renovación"}
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

      <CancelMembershipModal
        open={cancelModalOpen}
        busy={cancelling}
        onClose={closeCancelModal}
        onConfirm={cancelMembership}
      />
    </>
  );
}