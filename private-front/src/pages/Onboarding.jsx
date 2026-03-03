import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../api/http";
import { useAuth } from "../auth/AuthContext";

export default function Onboarding() {
  const { access, loadMe } = useAuth();
  const nav = useNavigate();

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");

  const [realEstate, setRealEstate] = useState(null); // ✅ para fechas / estado

  const [profile, setProfile] = useState({
    name: "",
    legal_name: "",
    cuit: "",
    address: "",
    phone: "",
    email: "",
    website: "",
    instagram: "",
    facebook: "",
  });

  const [licenses, setLicenses] = useState([]);

  const [newLicense, setNewLicense] = useState({
    license_number: "",
    province: "",
    is_primary: true,
  });

  const level = access?.level;
  const isReview = level === "real_estate_review";
  const isUnpaid = level === "real_estate_unpaid";
  const isActive = level === "real_estate_active";
  const needsOnboarding =
    level === "real_estate_not_linked" || level === "real_estate_draft";

  const canSubmit = useMemo(() => {
    return !isReview && (licenses?.length ?? 0) > 0;
  }, [isReview, licenses]);

  async function load() {
    setErr("");
    setOk("");
    setLoading(true);

    try {
      const res = await api.get("/real-estate/me");
      const data = unwrap(res); // opción B: devuelve data directo

      setRealEstate(data?.real_estate ?? null);

      if (data?.real_estate) {
        setProfile({
          name: data.real_estate.name ?? "",
          legal_name: data.real_estate.legal_name ?? "",
          cuit: data.real_estate.cuit ?? "",
          address: data.real_estate.address ?? "",
          phone: data.real_estate.phone ?? "",
          email: data.real_estate.email ?? "",
          website: data.real_estate.website ?? "",
          instagram: data.real_estate.instagram ?? "",
          facebook: data.real_estate.facebook ?? "",
        });
      }

      setLicenses(Array.isArray(data?.licenses) ? data.licenses : []);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo cargar"));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function saveProfile(e) {
    e.preventDefault();
    setErr("");
    setOk("");

    try {
      await api.post("/real-estate/profile", profile);
      setOk("Perfil guardado.");
      await load();
      await loadMe({ force: true });
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo guardar"));
    }
  }

  async function addLicense(e) {
    e.preventDefault();
    setErr("");
    setOk("");

    try {
      await api.post("/real-estate/licenses", newLicense);
      setOk("Matrícula agregada.");
      setNewLicense({ license_number: "", province: "", is_primary: false });
      await load();
      await loadMe({ force: true });
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo agregar"));
    }
  }

  async function submitReview() {
    setErr("");
    setOk("");

    try {
      await api.post("/real-estate/submit-review", {});
      setOk("Enviado a revisión.");
      await loadMe({ force: true });
      await load();
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo enviar"));
    }
  }

  if (loading) return <div className="p-6">Cargando...</div>;

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Onboarding inmobiliaria</h1>
        <p className="text-sm text-gray-600">Estado actual: {level || "—"}</p>
      </div>

      {/* Avisos por estado */}
      {isActive && (
        <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm">
          Tu cuenta está activa. Podés usar la plataforma.{" "}
          <button className="underline" onClick={() => nav("/app")}>
            Ir al panel
          </button>
        </div>
      )}

      {isUnpaid && (
        <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm">
          Tu perfil ya fue aprobado. Falta activar la membresía.{" "}
          <button className="underline" onClick={() => nav("/billing")}>
            Ir a membresía
          </button>
        </div>
      )}

      {isReview && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm">
          Tu perfil ya fue enviado a revisión. Mientras tanto, la edición queda bloqueada.
        </div>
      )}

      {needsOnboarding && (
        <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
          Completá tu perfil y agregá al menos una matrícula para poder solicitar revisión.
        </div>
      )}

      {/* Estado / fechas */}
      <div className="rounded-2xl border p-4 text-sm space-y-1">
        <div>
          <b>Solicitud de revisión:</b> {realEstate?.review_requested_at ?? "—"}
        </div>
        <div>
          <b>Aprobado:</b> {realEstate?.approved_at ?? "—"}
        </div>
        <div>
          <b>Aprobado por:</b> {realEstate?.approved_by_email ?? "—"}
        </div>
        {realEstate?.validation_note && (
          <div className="text-red-700">
            <b>Motivo rechazo:</b> {realEstate.validation_note}
          </div>
        )}
      </div>

      {err && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
          {err}
        </div>
      )}
      {ok && (
        <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm">
          {ok}
        </div>
      )}

      {/* PERFIL */}
      <form onSubmit={saveProfile} className="rounded-2xl border p-4 space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold">Datos de la inmobiliaria</h2>
          {isReview && <span className="text-xs text-gray-500">Bloqueado</span>}
        </div>

        <fieldset disabled={isReview} className={isReview ? "opacity-60" : ""}>
          <Input
            label="Nombre comercial"
            value={profile.name}
            onChange={(v) => setProfile((p) => ({ ...p, name: v }))}
          />
          <Input
            label="Razón social"
            value={profile.legal_name}
            onChange={(v) => setProfile((p) => ({ ...p, legal_name: v }))}
          />
          <Input
            label="CUIT"
            value={profile.cuit}
            onChange={(v) => setProfile((p) => ({ ...p, cuit: v }))}
          />
          <Input
            label="Dirección"
            value={profile.address}
            onChange={(v) => setProfile((p) => ({ ...p, address: v }))}
          />
          <Input
            label="Teléfono"
            value={profile.phone}
            onChange={(v) => setProfile((p) => ({ ...p, phone: v }))}
          />
          <Input
            label="Email"
            value={profile.email}
            onChange={(v) => setProfile((p) => ({ ...p, email: v }))}
          />

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <Input
              label="Website (opcional)"
              value={profile.website}
              onChange={(v) => setProfile((p) => ({ ...p, website: v }))}
            />
            <Input
              label="Instagram (opcional)"
              value={profile.instagram}
              onChange={(v) => setProfile((p) => ({ ...p, instagram: v }))}
            />
            <Input
              label="Facebook (opcional)"
              value={profile.facebook}
              onChange={(v) => setProfile((p) => ({ ...p, facebook: v }))}
            />
          </div>

          <button className="rounded-lg bg-black px-4 py-2 text-white" type="submit">
            Guardar perfil
          </button>
        </fieldset>
      </form>

      {/* LICENCIAS */}
      <div className="rounded-2xl border p-4 space-y-4">
        <h2 className="font-semibold">Matrículas / licencias</h2>

        {licenses.length === 0 ? (
          <p className="text-sm text-gray-600">Todavía no cargaste matrículas.</p>
        ) : (
          <ul className="text-sm space-y-2">
            {licenses.map((l) => (
              <li key={l.id} className="rounded-lg bg-gray-50 p-2">
                <div>
                  <span className="font-medium">{l.license_number}</span> — {l.province}
                </div>
                {Number(l.is_primary) === 1 && (
                  <div className="text-xs text-gray-600">Principal</div>
                )}
              </li>
            ))}
          </ul>
        )}

        <fieldset disabled={isReview} className={isReview ? "opacity-60" : ""}>
          <form onSubmit={addLicense} className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <Input
              label="N° matrícula"
              value={newLicense.license_number}
              onChange={(v) => setNewLicense((s) => ({ ...s, license_number: v }))}
            />
            <Input
              label="Provincia"
              value={newLicense.province}
              onChange={(v) => setNewLicense((s) => ({ ...s, province: v }))}
            />

            <label className="text-sm flex items-center gap-2">
              <input
                type="checkbox"
                checked={!!newLicense.is_primary}
                onChange={(e) => setNewLicense((s) => ({ ...s, is_primary: e.target.checked }))}
              />
              Principal
            </label>

            <div className="md:col-span-3">
              <button className="rounded-lg border px-4 py-2" type="submit">
                Agregar matrícula
              </button>
            </div>
          </form>
        </fieldset>

        <button
          className="rounded-lg bg-black px-4 py-2 text-white disabled:opacity-60"
          onClick={submitReview}
          disabled={!canSubmit}
          title={!canSubmit ? "Necesitás al menos 1 matrícula para enviar a revisión" : ""}
        >
          {isReview ? "Ya enviado a revisión" : "Enviar a revisión"}
        </button>
      </div>
    </div>
  );
}

function Input({ label, value, onChange }) {
  return (
    <div>
      <label className="text-sm">{label}</label>
      <input
        className="mt-1 w-full rounded-lg border p-2"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    </div>
  );
}