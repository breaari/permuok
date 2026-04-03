import { Icon } from "../../../../ui/icons/Index";
import { AdminDetailField } from "./AdminDetailField";
import AdminExternalLinkChip from "./AdminExternalLinkChip";

export default function AdminRealEstateSection({
  detail,
  realEstate,
  validationNote,
  showValidationNote = false,
  showLinks = true,
  title = "Inmobiliaria asociada",
}) {
  const source = realEstate || detail || {};

  const name = source.real_estate_name || source.name;
  const legalName = source.real_estate_legal_name || source.legal_name;
  const cuit = source.real_estate_cuit || source.cuit;
  const email = source.real_estate_email || source.email;
  const phone = source.real_estate_phone || source.phone;
  const address = source.real_estate_address || source.address;

  const website = source.real_estate_website || source.website;
  const instagram = source.real_estate_instagram || source.instagram;
  const facebook = source.real_estate_facebook || source.facebook;

  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex items-center gap-3">
        <span className="text-primary">
          <Icon name="building2" size={24} />
        </span>
        <h2 className="text-xl font-bold text-slate-900">{title}</h2>
      </div>

      <div className="p-6">
        {showValidationNote && !!validationNote && (
          <div className="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p className="font-bold mb-1">Motivo informado</p>
            <p>{validationNote}</p>
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
          <AdminDetailField label="Nombre comercial" value={name} strong />
          <AdminDetailField label="Razón social" value={legalName} />
          <AdminDetailField label="CUIT" value={cuit} />
          <AdminDetailField label="Email corporativo" value={email} />
          <AdminDetailField label="Teléfono" value={phone} />
          <AdminDetailField label="Dirección" value={address} />
        </div>

        {showLinks && (
          <div className="mt-8 pt-8 border-t border-slate-100">
            <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-5">
              Presencia digital y redes
            </h4>

            <div className="flex flex-wrap gap-3">
              <AdminExternalLinkChip
                href={website}
                label="Sitio Web"
                tone="default"
              />
              <AdminExternalLinkChip
                href={instagram}
                label="Instagram"
                tone="instagram"
              />
              <AdminExternalLinkChip
                href={facebook}
                label="Facebook"
                tone="facebook"
              />
            </div>

            {!website && !instagram && !facebook && (
              <p className="text-sm text-slate-500 mt-3">
                No se informaron enlaces de presencia digital.
              </p>
            )}
          </div>
        )}
      </div>
    </section>
  );
}