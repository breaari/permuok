// pages/MyProfile.jsx
import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { Icon } from "../ui/icons/Index";
import { useRealEstateProfile } from "../hooks/useRealEstateProfile";
import { Field } from "../ui/form/Field";
import { LicenseModal } from "../ui/realEstate/LicenseModal";
import PhoneField from "../ui/components/PhoneField";
import AddressField from "../ui/form/AddressField";
import { useGoogleMaps } from "../ui/maps/UseGoogleMaps";

function formatDate(value) {
  if (!value) return "—";
  try {
    return new Date(value).toLocaleDateString("es-AR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

export default function MyProfile() {
  const { access, loadMe } = useAuth();
  const nav = useNavigate();
  const { isLoaded: mapsLoaded, loadError: mapsError } = useGoogleMaps();
  const {
    loading,
    err,
    ok,
    realEstate,
    profile,
    setProfile,
    licenses,
    profileOk,
    hasLicenses,
    actions,
    profileValidation,
  } = useRealEstateProfile({ loadMe });

  const level = access?.level;
  const isReview = level === "real_estate_review";
  const isUnpaid = level === "real_estate_unpaid";
  const isActive = level === "real_estate_active";
  const needsOnboarding =
    level === "real_estate_not_linked" || level === "real_estate_draft";

  const canSubmit = useMemo(() => {
    return !isReview && profileOk && hasLicenses;
  }, [isReview, profileOk, hasLicenses]);

  const requestedAt = useMemo(
    () => formatDate(realEstate?.review_requested_at || realEstate?.created_at),
    [realEstate],
  );

  const banner = useMemo(() => {
    if (isActive) {
      return {
        tone: "success",
        title: "Tu cuenta está activa",
        desc: "Podés usar la plataforma.",
        cta: { label: "Ir al panel", onClick: () => nav("/app") },
      };
    }
    if (isUnpaid) {
      return {
        tone: "warn",
        title: "Tu perfil fue aprobado",
        desc: "Falta activar la membresía.",
        cta: { label: "Ir a membresía", onClick: () => nav("/billing") },
      };
    }
    if (isReview) {
      return {
        tone: "info",
        title: "En revisión",
        desc: "Tu perfil fue enviado a revisión. La edición queda bloqueada.",
      };
    }
    if (needsOnboarding) {
      return {
        tone: "info",
        title: "Completá tu perfil",
        desc: "Completá tu perfil y agregá al menos una matrícula para poder solicitar revisión.",
      };
    }
    return {
      tone: "info",
      title: "Mi Perfil",
      desc: "Actualizá los datos de tu agencia y tus matrículas.",
    };
  }, [isActive, isUnpaid, isReview, needsOnboarding, nav]);

  const bannerToneClasses = {
    info: "border-primary/20 bg-primary/5",
    warn: "border-amber-300/40 bg-amber-50",
    success: "border-emerald-300/40 bg-emerald-50",
  };

  const footerHint = useMemo(() => {
    if (isReview) return { label: "En revisión", tone: "info" };
    if (!profileOk || !hasLicenses)
      return { label: "Pendiente de completar", tone: "warn" };
    return { label: "Listo para enviar", tone: "success" };
  }, [isReview, profileOk, hasLicenses]);

  const [licenseOpen, setLicenseOpen] = useState(false);

  const submitBlockReason = useMemo(() => {
    if (isReview) return "Tu perfil ya fue enviado a revisión.";
    if (!profileValidation?.ok) return profileValidation.reason;
    if (!hasLicenses) return "Agregá al menos una matrícula.";
    return "";
  }, [isReview, profileValidation, hasLicenses]);

  if (loading) {
    return <div className="p-6 text-sm text-slate-500">Cargando...</div>;
  }

  const hasRealEstate = !!realEstate?.id; // o realEstate?.id != null
  const canAddLicense = !isReview && hasRealEstate;

  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <main className="flex-1 max-w-5xl mx-auto w-full px-4 md:px-6 py-8 pb-32">
        {/* Banner */}
        <div
          className={`mb-8 p-4 md:p-5 rounded-xl border flex flex-col md:flex-row items-start md:items-center justify-between gap-4 ${
            bannerToneClasses[banner.tone] ?? bannerToneClasses.info
          }`}
        >
          <div className="flex gap-3">
            <span className="text-primary mt-0.5">
              <Icon name="info" size={18} />
            </span>
            <div>
              <p className="text-slate-900 font-bold text-base">
                {banner.title}
              </p>
              <p className="text-slate-600 text-sm">{banner.desc}</p>
            </div>
          </div>

          {banner.cta && (
            <button
              type="button"
              onClick={banner.cta.onClick}
              className="text-sm font-bold text-primary flex items-center gap-1 hover:underline underline-offset-4"
            >
              {banner.cta.label}
              <span className="text-primary">→</span>
            </button>
          )}
        </div>

        {/* Head */}
        <div className="space-y-2 mb-8">
          <h1 className="text-3xl font-black tracking-tight">Mi Perfil</h1>
          <p className="text-slate-500">
            Completá los datos de tu agencia para comenzar a operar en la
            plataforma.
          </p>

          {/* mini meta */}
          <div className="text-xs text-slate-500 flex flex-wrap gap-x-6 gap-y-1 pt-2">
            <span>
              <b>Solicitado:</b> {requestedAt}
            </span>
            <span>
              <b>Aprobado:</b> {formatDate(realEstate?.approved_at)}
            </span>
            {!!realEstate?.approved_by_email && (
              <span>
                <b>Aprobado por:</b> {realEstate.approved_by_email}
              </span>
            )}
            {!!realEstate?.validation_note && (
              <span className="text-rose-700">
                <b>Motivo rechazo:</b> {realEstate.validation_note}
              </span>
            )}
          </div>
        </div>

        {err && (
          <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}
        {ok && (
          <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {ok}
          </div>
        )}

        {/* Section 1: Agency Data */}
        <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
          <div className="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 className="text-xl font-bold">Datos de la Inmobiliaria</h2>
            {isReview && (
              <span className="text-xs font-semibold text-slate-500">
                Bloqueado
              </span>
            )}
          </div>

          <form
            id="profileForm"
            onSubmit={actions.saveProfile}
            className={isReview ? "opacity-60" : ""}
          >
            <fieldset disabled={isReview} className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Field
                  label="Nombre comercial"
                  value={profile.name}
                  onChange={(v) => setProfile((p) => ({ ...p, name: v }))}
                  placeholder="Ej: Inmobiliaria Central"
                />
                <Field
                  label="Razón social"
                  value={profile.legal_name}
                  onChange={(v) => setProfile((p) => ({ ...p, legal_name: v }))}
                  placeholder="Nombre Legal S.A."
                />
                <Field
                  label="CUIT"
                  value={profile.cuit}
                  onChange={(v) => setProfile((p) => ({ ...p, cuit: v }))}
                  placeholder="30-00000000-0"
                />
                <Field
                  label="Email de contacto"
                  type="email"
                  value={profile.email}
                  onChange={(v) => setProfile((p) => ({ ...p, email: v }))}
                  placeholder="info@inmobiliaria.com"
                />

                <div className="md:col-span-2">
                  <AddressField
                    label="Dirección"
                    value={profile.address}
                    disabled={isReview}
                    isLoaded={mapsLoaded}
                    onChangeAddress={(data) =>
                      setProfile((p) => ({
                        ...p,
                        address: data.address || "",
                        address_place_id: data.place_id || "",
                        address_lat: data.lat ?? "",
                        address_lng: data.lng ?? "",
                        address_locality: data.locality || "",
                        address_province: data.province || "",
                        address_postal_code: data.postal_code || "",
                      }))
                    }
                  />
                  {mapsError && (
                    <p className="mt-1 text-xs text-red-700">
                      No se pudo cargar Google Maps.
                    </p>
                  )}
                </div>

                <PhoneField
                  label="Teléfono"
                  value={profile.phone || ""} // evita warnings si viene null
                  onChange={(v) =>
                    setProfile((p) => ({ ...p, phone: v || "" }))
                  }
                  disabled={isReview}
                  required
                  defaultCountry="AR"
                  variant="profile"
                />
                <Field
                  label="Sitio Web"
                  value={profile.website}
                  onChange={(v) => setProfile((p) => ({ ...p, website: v }))}
                  placeholder="https://www.tuweb.com"
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <Field
                  label="Instagram (Opcional)"
                  value={profile.instagram}
                  onChange={(v) => setProfile((p) => ({ ...p, instagram: v }))}
                  placeholder="@tuusuario"
                />
                <Field
                  label="Facebook (Opcional)"
                  value={profile.facebook}
                  onChange={(v) => setProfile((p) => ({ ...p, facebook: v }))}
                  placeholder="facebook.com/tuagencia"
                />
              </div>

              <div className="mt-6">
                <button
                  type="submit"
                  className="px-6 py-3 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-colors"
                >
                  Guardar Perfil
                </button>
              </div>
            </fieldset>
          </form>
        </section>

        {/* Section 2: Licenses */}
        <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 className="text-xl font-bold">Matrículas / Licencias</h2>

            {/* Desktop button */}
            <button
              type="button"
              disabled={isReview || !hasRealEstate}
              onClick={() => {
                if (!hasRealEstate) {
                  // opcional: setErr("Primero completá y guardá tu perfil para cargar matrículas.");
                  return;
                }
                setLicenseOpen(true);
              }}
              className="hidden md:flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg font-bold text-sm hover:bg-primary/90 transition-colors disabled:opacity-60"
            >
              <span>+</span>
              Agregar Matrícula
            </button>
          </div>

          {/* Body */}
          {!hasRealEstate ? (
            <div className="p-12 text-center">
              <p className="text-lg font-bold">Primero completá tu perfil</p>
              <p className="text-slate-500 text-sm mt-1">
                Guardá los datos de la inmobiliaria para poder cargar
                matrículas.
              </p>
            </div>
          ) : licenses.length === 0 ? (
            <div className="p-12 flex flex-col items-center justify-center text-center space-y-4">
              <div className="size-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                <Icon name="badge" size={28} />
              </div>

              <div className="max-w-xs">
                <p className="text-lg font-bold">No has cargado matrículas</p>
                <p className="text-slate-500 text-sm mt-1">
                  Debes cargar al menos una matrícula profesional vigente para
                  poder validar tu cuenta.
                </p>
              </div>

              {/* Mobile button */}
              <button
                type="button"
                disabled={isReview || !hasRealEstate}
                onClick={() => {
                  if (!hasRealEstate) return;
                  setLicenseOpen(true);
                }}
                className="md:hidden flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-lg font-bold text-sm disabled:opacity-60"
              >
                <span>+</span>
                Agregar Matrícula
              </button>
            </div>
          ) : (
            <div className="p-6 space-y-3">
              {licenses.map((l) => (
                <div
                  key={l.id}
                  className="rounded-xl border border-slate-200 p-4 flex items-start justify-between gap-4"
                >
                  <div className="min-w-0">
                    <p className="font-bold text-slate-900 truncate">
                      {l.license_number}{" "}
                      {Number(l.is_primary) === 1 && (
                        <span className="ml-2 text-[10px] font-black bg-primary text-white px-2 py-0.5 rounded">
                          PRINCIPAL
                        </span>
                      )}
                    </p>

                    {/* OJO: si tu backend ahora devuelve province_name en vez de province, ajustá acá */}
                    <p className="text-sm text-slate-500">
                      {l.province ?? l.province_name ?? "—"}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* Modal */}
        <LicenseModal
          open={licenseOpen}
          disabled={isReview}
          onClose={() => setLicenseOpen(false)}
          onSave={actions.addLicense}
          errorMessage={err}
        />
      </main>
      {!canSubmit && submitBlockReason && (
        <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          {submitBlockReason}
        </div>
      )}
      {/* Action bar inferior */}
      {!licenseOpen && (
        <footer className="fixed bottom-0 left-0 right-0 md:left-64 bg-white/80 backdrop-blur-md border-t border-slate-200 py-4 px-6 md:px-10 z-50">
          <div className="max-w-5xl mx-auto flex items-center justify-between gap-4">
            <div className="hidden md:flex items-center gap-2 text-slate-500 text-sm">
              <span
                className={
                  footerHint.tone === "success"
                    ? "text-emerald-600"
                    : "text-amber-500"
                }
              >
                ●
              </span>
              {footerHint.label}
            </div>

            <div className="flex items-center gap-4 w-full md:w-auto">
              <button
                type="submit"
                form="profileForm"
                disabled={isReview}
                className="flex-1 md:flex-none px-6 py-3 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-colors disabled:opacity-60"
              >
                Guardar Perfil
              </button>

              <button
                type="button"
                disabled={!canSubmit}
                onClick={actions.submitReview}
                className={`flex-1 md:flex-none px-8 py-3 rounded-lg font-bold flex items-center justify-center gap-2 ${
                  canSubmit
                    ? "bg-primary text-white hover:bg-primary/90"
                    : "bg-slate-200 text-slate-400 cursor-not-allowed"
                }`}
                title={submitBlockReason || ""}
              >
                Enviar a Revisión
              </button>
            </div>
          </div>
        </footer>
      )}
    </div>
  );
}
