import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { api, unwrap } from "../api/http";

export default function Review() {
  const { access, loadMe, logout } = useAuth();
  const nav = useNavigate();

  const [checking, setChecking] = useState(false);
  const [msg, setMsg] = useState("");

  const [reErr, setReErr] = useState("");
  const [reData, setReData] = useState(null); // ✅ FALTABA

  async function checkNow() {
    setMsg("");
    setChecking(true);
    try {
      const me = await loadMe({ force: true });

      const level = me?.access?.level;

      if (level === "real_estate_unpaid") {
        nav("/billing", { replace: true });
        return;
      }

      if (level === "real_estate_active") {
        nav("/app", { replace: true });
        return;
      }

      setMsg("Todavía en revisión.");
    } catch {
      setMsg("No se pudo actualizar el estado.");
    } finally {
      setChecking(false);
    }
  }

  async function loadRealEstate() {
    setReErr("");
    try {
      const res = await api.get("/real-estate/me");
      const data = unwrap(res); // soporta {success,status,data} o data directo
      setReData(data);
    } catch (e) {
      setReData(null);
      setReErr(e?.data?.message || e?.message || "No se pudo cargar tu perfil");
    }
  }

  // Poll simple (cada 15s) mientras esté en revisión
  useEffect(() => {
    loadRealEstate().catch(() => {});
    const id = setInterval(() => {
      loadMe({ force: true }).catch(() => {});
      loadRealEstate().catch(() => {});
    }, 15000);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Si cambia el estado, redirigir automáticamente
  useEffect(() => {
    if (access?.level === "real_estate_unpaid") nav("/billing", { replace: true });
    if (access?.level === "real_estate_active") nav("/app", { replace: true });
  }, [access?.level, nav]);

  return (
    <div className="min-h-screen p-6">
      <div className="mx-auto max-w-xl rounded-2xl border p-6 shadow-sm">
        <div className="flex items-center justify-between gap-4">
          <h1 className="text-2xl font-semibold">En revisión</h1>
          <button className="rounded-lg border px-4 py-2 text-sm" onClick={logout}>
            Cerrar sesión
          </button>
        </div>

        <p className="mt-2 text-sm text-gray-600">
          Tu matrícula y datos están en revisión. Vas a tener acceso limitado hasta que un
          administrador apruebe tu perfil.
        </p>

        <div className="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
          <div>
            <b>Estado actual:</b> {access?.level || "—"}
          </div>
        </div>

        {msg && <div className="mt-4 rounded-lg border p-3 text-sm">{msg}</div>}

        {reErr && (
          <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
            {reErr}
          </div>
        )}

        {reData && (
          <div className="mt-4 rounded-lg bg-gray-50 p-3 text-sm space-y-2">
            <div>
              <b>Solicitud enviada:</b>{" "}
              {reData?.real_estate?.review_requested_at ?? "—"}
            </div>

            <div>
              <b>Matrículas:</b>{" "}
              {(reData?.licenses?.length ?? 0) === 0 ? "—" : ""}
            </div>

            {(reData?.licenses ?? []).map((l) => (
              <div key={l.id} className="text-xs">
                {l.license_number} — {l.province}
                {Number(l.is_primary) === 1 ? " (principal)" : ""}
              </div>
            ))}
          </div>
        )}

        <div className="mt-6 flex flex-col gap-2">
          <button
            className="rounded-lg bg-black px-4 py-2 text-white disabled:opacity-60"
            onClick={checkNow}
            disabled={checking}
          >
            {checking ? "Actualizando..." : "Actualizar estado"}
          </button>

          <button
            className="rounded-lg border px-4 py-2 text-sm"
            onClick={() => nav("/onboarding")}
          >
            Ver mis datos
          </button>
        </div>
      </div>
    </div>
  );
}