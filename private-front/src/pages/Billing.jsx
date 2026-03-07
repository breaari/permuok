import { useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../api/http";
import { useAuth } from "../auth/AuthContext";

import { BillingUnpaidView } from "../ui/billing/BillingUnpaidView";
import { BillingActiveView } from "../ui/billing/BillingActiveView";
import { BillingBlockedView } from "../ui/billing/BillingBlockedView";

export default function Billing() {
  const { access } = useAuth();

  const [loading, setLoading] = useState(true);
  const [plans, setPlans] = useState([]);
  const [err, setErr] = useState("");
  const [processingPlanCode, setProcessingPlanCode] = useState(null);
  const [paymentStarted, setPaymentStarted] = useState(false);

  const level = access?.level;

  const isUnpaid =
    level === "real_estate_unpaid" ||
    level === "real_estate_unpaid_changes_pending";

  const isUnpaidChangesPending =
    level === "real_estate_unpaid_changes_pending";

  const isActive = level === "real_estate_active";
  const isActiveChangesPending = level === "real_estate_active_changes_pending";

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

  async function startPayment(planCode) {
    setErr("");
    setProcessingPlanCode(planCode);

    try {
      const res = await api.post("/billing/create-preference", {
        plan_code: planCode,
      });

      const data = unwrap(res);

      if (data?.init_point) {
        setPaymentStarted(true);
        window.open(data.init_point, "_blank", "noopener,noreferrer");
      } else {
        setErr("No se pudo generar el enlace de pago.");
      }
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo iniciar el proceso de pago"));
    } finally {
      setProcessingPlanCode(null);
    }
  }

  if (isUnpaid) {
    return (
      <BillingUnpaidView
        loading={loading}
        err={err}
        plans={plansSorted}
        paymentStarted={paymentStarted}
        processingPlanCode={processingPlanCode}
        onStartPayment={startPayment}
        isChangesPending={isUnpaidChangesPending}
      />
    );
  }

  if (isActive || isActiveChangesPending) {
    return (
      <BillingActiveView
        err={err}
        plans={plansSorted}
        access={access}
        isChangesPending={isActiveChangesPending}
      />
    );
  }

  return (
    <BillingBlockedView
      level={level}
      loading={loading}
      err={err}
      plans={plansSorted}
    />
  );
}