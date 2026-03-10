import { useEffect, useMemo, useRef, useState } from "react";
import { api, unwrap, getErrorMessage } from "../api/http";
import { useAuth } from "../auth/AuthContext";

import { BillingUnpaidView } from "../ui/billing/BillingUnpaidView";
import { BillingActiveView } from "../ui/billing/BillingActiveView";
import { BillingBlockedView } from "../ui/billing/BillingBlockedView";

export default function Billing() {
  const { access, loadMe } = useAuth();

  const [loading, setLoading] = useState(true);
  const [plans, setPlans] = useState([]);
  const [err, setErr] = useState("");
  const [processingPlanCode, setProcessingPlanCode] = useState(null);
  const [paymentStarted, setPaymentStarted] = useState(false);

  const pollingRef = useRef(null);

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

  async function refreshMembershipStatus({ silent = false } = {}) {
    try {
      await loadMe?.({ force: true });
    } catch (e) {
      if (!silent) {
        setErr(getErrorMessage(e, "No se pudo actualizar el estado de la membresía"));
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

  useEffect(() => {
    if (!paymentStarted) return;

    if (isActive || isActiveChangesPending) {
      setPaymentStarted(false);

      if (pollingRef.current) {
        window.clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
    }
  }, [paymentStarted, isActive, isActiveChangesPending]);

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
        onRefreshStatus={() => refreshMembershipStatus()}
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