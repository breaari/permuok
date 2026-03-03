import { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { api, unwrap } from "../api/http";
import { useAuth } from "../auth/AuthContext";
import { getLastPayment } from "../billing/lastPayment";

function getErr(e, fallback) {
  return e?.data?.message || e?.data?.error || e?.message || fallback;
}

export default function BillingPending() {
  const nav = useNavigate();
  const [sp] = useSearchParams();
  const { loadMe } = useAuth();

  const [checking, setChecking] = useState(false);
  const [err, setErr] = useState("");
  const [statusMsg, setStatusMsg] = useState("");

  async function check() {
    setErr("");
    setChecking(true);

    try {
      const qsPref = sp.get("preference_id");
      const qsExt = sp.get("external_reference");

      const last = getLastPayment();
      const pref = qsPref || last?.preference_id || null;
      const ext = qsExt || last?.external_reference || null;

      if (!pref && !ext) {
        setStatusMsg("No encontré la referencia del pago. Volvé a Membresía y reiniciá el pago.");
        return;
      }

      const qs = pref
        ? `?preference_id=${encodeURIComponent(pref)}`
        : `?external_reference=${encodeURIComponent(ext)}`;

      const res = await api.get(`/billing/status${qs}`);
      const data = unwrap(res);
      const p = data?.payment ?? null;

      if (!p) {
        setStatusMsg("Aún no aparece el pago. Probá de nuevo en unos segundos.");
        return;
      }

      if (p.status === "approved" || p.mp_status === "approved") {
        await loadMe({ force: true });
        nav("/app", { replace: true });
        return;
      }

      setStatusMsg(
        `Estado actual: ${p.status || "—"}${p.mp_status ? ` (MP: ${p.mp_status})` : ""}`
      );
    } catch (e) {
      setErr(getErr(e, "No se pudo consultar el estado del pago"));
    } finally {
      setChecking(false);
    }
  }

  useEffect(() => {
    check();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className="min-h-screen p-6">
      <div className="mx-auto max-w-xl rounded-2xl border p-6 shadow-sm space-y-4">
        <h1 className="text-2xl font-semibold">Pago pendiente</h1>

        <p className="text-sm text-gray-600">
          Tu pago todavía no figura como aprobado. Podés verificar el estado y, si hace falta,
          volver a intentar desde Membresía.
        </p>

        {err && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm">{err}</div>
        )}
        {statusMsg && <div className="rounded-lg bg-gray-50 p-3 text-sm">{statusMsg}</div>}

        <div className="flex gap-2">
          <button
            className="rounded-lg bg-black px-4 py-2 text-white disabled:opacity-60"
            disabled={checking}
            onClick={check}
          >
            {checking ? "Verificando..." : "Verificar"}
          </button>

          <button className="rounded-lg border px-4 py-2 text-sm" onClick={() => nav("/billing")}>
            Ir a Membresía
          </button>
        </div>
      </div>
    </div>
  );
}