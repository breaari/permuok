import { Field } from "../../form/Field";
import PhoneField from "../../components/PhoneField";
import AddressField from "../../form/AddressField";

export function ProfileFormSection({
  profile,
  setProfile,
  isReview,
  isRejected,
  isActiveChangesPending,
  validationNote,
  mapsLoaded,
  mapsError,
  onSubmit,
}) {
  const isLocked = isReview;

  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
      <div className="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 className="text-xl font-bold">Datos de la Inmobiliaria</h2>

        {isLocked ? (
          <span className="text-xs font-semibold text-slate-500">Bloqueado</span>
        ) : isActiveChangesPending ? (
          <span className="text-xs font-semibold text-amber-700">
            Cambios pendientes
          </span>
        ) : isRejected ? (
          <span className="text-xs font-semibold text-rose-700">
            Revisión rechazada
          </span>
        ) : null}
      </div>

      {isRejected && (
        <div className="mx-6 mt-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
          <p className="font-bold mb-1">Tu perfil fue rechazado</p>
          <p>Corregí la información observada y volvé a enviarlo a revisión.</p>
          {!!validationNote && (
            <p className="mt-2">
              <b>Motivo:</b> {validationNote}
            </p>
          )}
        </div>
      )}

      {isActiveChangesPending && (
        <div className="mx-6 mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          Guardá los cambios que necesites. Tu cuenta sigue disponible, pero las
          modificaciones del perfil quedarán pendientes de revisión.
        </div>
      )}

      <form
        id="profileForm"
        onSubmit={onSubmit}
        className={isLocked ? "opacity-60" : ""}
      >
        <fieldset disabled={isLocked} className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <Field
              label="Nombre comercial"
              value={profile.name}
              onChange={(v) =>
                setProfile((p) => ({
                  ...p,
                  name: v.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s.&,'\-()/]/g, ""),
                }))
              }
              placeholder="Ej: Inmobiliaria Central"
            />

            <Field
              label="Razón social"
              value={profile.legal_name}
              onChange={(v) =>
                setProfile((p) => ({
                  ...p,
                  legal_name: v.replace(
                    /[^A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s.&,'\-()/]/g,
                    ""
                  ),
                }))
              }
              placeholder="Nombre Legal S.A."
            />

            <Field
              label="CUIT"
              value={profile.cuit}
              onChange={(v) =>
                setProfile((p) => ({
                  ...p,
                  cuit: v.replace(/\D/g, "").slice(0, 11),
                }))
              }
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
                disabled={isLocked}
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
              value={profile.phone || ""}
              onChange={(v) =>
                setProfile((p) => ({ ...p, phone: v || "" }))
              }
              disabled={isLocked}
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
  );
}