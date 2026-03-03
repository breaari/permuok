import { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { api, unwrap } from "../api/http";
import { useAuth } from "../auth/AuthContext";
import { getLastPayment } from "../billing/lastPayment";

function getErr(e, fallback) {
  return e?.data?.message || e?.data?.error || e?.message || fallback;
}

export default function BillingSuccess() {
  const nav = useNavigate();
  const [sp] = useSearchParams();
  const { loadMe } = useAuth();

  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState("Verificando pago...");
  const [err, setErr] = useState("");

  async function verify() {
    setErr("");
    setLoading(true);

    try {
      // MP suele devolver algunos params; tomamos lo que haya
      const qsPref = sp.get("preference_id");
      const qsExt = sp.get("external_reference");

      const last = getLastPayment();
      const pref = qsPref || last?.preference_id || null;
      const ext = qsExt || last?.external_reference || null;

      if (!pref && !ext) {
        setMsg("No encontré referencia del pago. Volvé a Membresía y tocá Verificar.");
        setLoading(false);
        return;
      }

      const qs = pref
        ? `?preference_id=${encodeURIComponent(pref)}`
        : `?external_reference=${encodeURIComponent(ext)}`;

      const res = await api.get(`/billing/status${qs}`);
      const data = unwrap(res);
      const p = data?.payment ?? null;

      if (!p) {
        setMsg("No se encontró el pago aún. Probá nuevamente en unos segundos.");
        setLoading(false);
        return;
      }

      // Si el webhook ya lo marcó aprobado, /me pasa a active
      if (p.status === "approved" || p.mp_status === "approved") {
        await loadMe({ force: true });
        nav("/app", { replace: true });
        return;
      }

      setMsg(
        `Pago recibido, pero todavía no está aprobado. Estado: ${p.status || "—"}${
          p.mp_status ? ` (MP: ${p.mp_status})` : ""
        }`
      );
    } catch (e) {
      setErr(getErr(e, "No se pudo verificar el pago"));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    verify();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className="min-h-screen p-6">
      <div className="mx-auto max-w-xl rounded-2xl border p-6 shadow-sm space-y-4">
        <h1 className="text-2xl font-semibold">Pago exitoso</h1>

        {err ? (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm">{err}</div>
        ) : (
          <div className="rounded-lg bg-gray-50 p-3 text-sm">{msg}</div>
        )}

        <div className="flex gap-2">
          <button
            className="rounded-lg bg-black px-4 py-2 text-white disabled:opacity-60"
            disabled={loading}
            onClick={verify}
          >
            {loading ? "Verificando..." : "Verificar ahora"}
          </button>

          <button className="rounded-lg border px-4 py-2 text-sm" onClick={() => nav("/billing")}>
            Ir a Membresía
          </button>
        </div>
      </div>
    </div>
  );
}