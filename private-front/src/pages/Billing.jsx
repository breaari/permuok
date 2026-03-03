import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../api/http";
import { useAuth } from "../auth/AuthContext";

const LS_PAYMENT_KEY = "permuok_billing_payment_in_flight";

function loadPaymentFromLS() {
  try {
    const raw = localStorage.getItem(LS_PAYMENT_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    // mínimo requerido para poder verificar
    if (!parsed?.preference_id && !parsed?.external_reference) return null;
    return parsed;
  } catch {
    return null;
  }
}

function savePaymentToLS(payment) {
  try {
    if (!payment) {
      localStorage.removeItem(LS_PAYMENT_KEY);
      return;
    }
    localStorage.setItem(LS_PAYMENT_KEY, JSON.stringify(payment));
  } catch {
    // ignore
  }
}

export default function Billing() {
  const nav = useNavigate();
  const { access, loadMe, logout } = useAuth();

  const [loading, setLoading] = useState(true);
  const [plans, setPlans] = useState([]);
  const [err, setErr] = useState("");

  // ✅ inicializa desde localStorage
  const [payment, setPayment] = useState(() => loadPaymentFromLS());

  const [checking, setChecking] = useState(false);

  const level = access?.level;

  // si ya está activo, no debería estar acá
  useEffect(() => {
    if (level === "real_estate_active") nav("/app", { replace: true });
  }, [level, nav]);

  // ✅ persistir cuando cambia
  useEffect(() => {
    savePaymentToLS(payment);
  }, [payment]);

  async function loadPlans() {
    setErr("");
    setLoading(true);
    try {
      const res = await api.get("/plans");
      const data = unwrap(res); // => {plans:[...]}
      setPlans(data?.plans ?? []);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudieron cargar los planes"));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPlans();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const plansSorted = useMemo(() => {
    return [...plans].sort((a, b) => Number(a.price_ars) - Number(b.price_ars));
  }, [plans]);

  async function startPayment(planCode) {
    setErr("");
    setChecking(true);
    try {
      const res = await api.post("/billing/create-preference", { plan_code: planCode });
      const data = unwrap(res); // => {payment_id, preference_id, init_point, external_reference}
      setPayment(data);

      localStorage.setItem("permuok_last_payment", JSON.stringify(data));

      // Abrir checkout en nueva pestaña
      window.open(data.init_point, "_blank", "noopener,noreferrer");

      // refrescar /me por si ya estaba activo (pruebas dev)
      await loadMe({ force: true });
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo iniciar el pago"));
    } finally {
      setChecking(false);
    }
  }

  useEffect(() => {
  if (!payment) {
    const raw = localStorage.getItem("permuok_last_payment");
    if (raw) {
      try { setPayment(JSON.parse(raw)); } catch {}
    }
  }
  // eslint-disable-next-line react-hooks/exhaustive-deps
}, []);

  function reopenCheckout() {
    if (!payment?.init_point) {
      setErr("No tengo el link (init_point) para reabrir. Iniciá un pago nuevamente.");
      return;
    }
    window.open(payment.init_point, "_blank", "noopener,noreferrer");
  }

  function clearAttempt() {
    setErr("");
    setPayment(null); // ✅ esto también limpia LS por el useEffect
  }

  async function checkStatusNow({ silent = false } = {}) {
    if (!payment?.preference_id && !payment?.external_reference) {
      if (!silent) setErr("No hay un pago iniciado para verificar.");
      return;
    }

    if (!silent) setErr("");
    setChecking(true);

    try {
      const qs = payment?.preference_id
        ? `?preference_id=${encodeURIComponent(payment.preference_id)}`
        : `?external_reference=${encodeURIComponent(payment.external_reference)}`;

      const res = await api.get(`/billing/status${qs}`);
      const data = unwrap(res);
      const p = data?.payment ?? null;

      if (!p) {
        if (!silent) setErr("No se encontró el pago. Probá iniciar nuevamente.");
        return;
      }

      // si ya está approved en DB (webhook), entonces /me debería pasar a active
      if (p.status === "approved" || p.mp_status === "approved") {
        // ✅ limpiamos el intento local (ya no hace falta)
        setPayment(null);
        await loadMe({ force: true });
        return;
      }

      // estados comunes: pending / created / rejected / cancelled
      if (!silent) {
        setErr(
          `Estado del pago: ${p.status || "—"}${p.mp_status ? ` (MP: ${p.mp_status})` : ""}`
        );
      }
    } catch (e) {
      if (!silent) setErr(getErrorMessage(e, "No se pudo consultar el estado del pago"));
    } finally {
      setChecking(false);
    }
  }

  // ✅ Polling suave mientras estoy en /billing y haya payment iniciado
  useEffect(() => {
    if (!payment?.preference_id && !payment?.external_reference) return;

    const id = setInterval(() => {
      checkStatusNow({ silent: true }).catch(() => {});
    }, 8000);

    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [payment?.preference_id, payment?.external_reference]);

  // Si /me cambia a activo, Gate te manda a /app
  useEffect(() => {
    if (access?.level === "real_estate_active") nav("/app", { replace: true });
  }, [access?.level, nav]);

  return (
    <div className="min-h-screen p-6">
      <div className="mx-auto max-w-3xl space-y-6">
        <div className="flex items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold">Membresía</h1>
            <p className="mt-1 text-sm text-gray-600">
              Tu cuenta está aprobada, pero necesitás una membresía activa para usar la plataforma.
            </p>
            <p className="mt-1 text-xs text-gray-500">
              Estado actual: <b>{access?.level || "—"}</b>
            </p>
          </div>

          <button className="rounded-lg border px-4 py-2 text-sm" onClick={logout}>
            Cerrar sesión
          </button>
        </div>

        {err && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
            {err}
          </div>
        )}

        {loading ? (
          <div className="rounded-2xl border p-6">Cargando planes...</div>
        ) : plansSorted.length === 0 ? (
          <div className="rounded-2xl border p-6">No hay planes disponibles.</div>
        ) : (
          <div className="grid gap-4 md:grid-cols-3">
            {plansSorted.map((p) => (
              <div key={p.id} className="rounded-2xl border p-4 shadow-sm">
                <div className="text-lg font-semibold">{p.name}</div>
                <div className="mt-1 text-sm text-gray-600">
                  ${Number(p.price_ars).toLocaleString("es-AR")} / {p.duration_days} días
                </div>

                <div className="mt-3 text-sm">
                  <div>
                    Agentes: <b>{p.max_agents}</b>
                  </div>
                  <div>
                    Inversores: <b>{p.max_investors}</b>
                  </div>
                  <div>
                    Publicar proyectos:{" "}
                    <b>{Number(p.can_publish_projects) === 1 ? "Sí" : "No"}</b>
                  </div>
                </div>

                <button
                  className="mt-4 w-full rounded-lg bg-black px-4 py-2 text-white disabled:opacity-60"
                  disabled={checking}
                  onClick={() => startPayment(p.code)}
                >
                  {checking ? "Procesando..." : "Elegir y pagar"}
                </button>
              </div>
            ))}
          </div>
        )}

        {/* ✅ Pago en curso */}
        <div className="rounded-2xl border p-4 space-y-3">
          <div className="flex items-start justify-between gap-3">
            <div>
              <div className="font-semibold">Pago en curso</div>
              <div className="text-sm text-gray-600">
                Si ya abriste Mercado Pago y cerraste la pestaña, podés reabrirlo desde acá.
              </div>
            </div>

            <div className="flex gap-2">
              <button
                className="rounded-lg border px-4 py-2 text-sm disabled:opacity-60"
                disabled={checking || !payment?.init_point}
                onClick={reopenCheckout}
              >
                Reabrir MP
              </button>
              <button
                className="rounded-lg border px-4 py-2 text-sm disabled:opacity-60"
                disabled={checking || !payment}
                onClick={clearAttempt}
                title="Borra el intento guardado (localStorage)"
              >
                Cancelar intento
              </button>
            </div>
          </div>

          <div className="text-xs text-gray-500">
            {payment ? (
              <>
                <div>
                  <b>preference_id:</b> {payment.preference_id || "—"}
                </div>
                <div>
                  <b>external_reference:</b> {payment.external_reference || "—"}
                </div>
              </>
            ) : (
              "No hay un pago guardado en curso."
            )}
          </div>
        </div>

        {/* ✅ Verificación */}
        <div className="rounded-2xl border p-4">
          <div className="flex items-center justify-between gap-3">
            <div>
              <div className="font-semibold">Verificación de pago</div>
              <div className="text-sm text-gray-600">
                Si pagaste en la pestaña de Mercado Pago, volvé acá y tocá “Verificar”.
              </div>
            </div>

            <button
              className="rounded-lg border px-4 py-2 text-sm disabled:opacity-60"
              disabled={checking || (!payment?.preference_id && !payment?.external_reference)}
              onClick={() => checkStatusNow()}
            >
              {checking ? "Verificando..." : "Verificar"}
            </button>
          </div>
        </div>

        <div className="rounded-2xl border p-4">
          <div className="font-semibold">Acciones</div>
          <div className="mt-2 flex flex-col gap-2 md:flex-row">
            <button
              className="rounded-lg border px-4 py-2 text-sm"
              onClick={() => nav("/onboarding")}
            >
              Ver mis datos
            </button>
            <button
              className="rounded-lg border px-4 py-2 text-sm"
              onClick={() => loadMe({ force: true })}
            >
              Refrescar estado
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}