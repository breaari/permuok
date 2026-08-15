import { useState } from "react";
import { Icon } from "../../../ui/icons/Index";

function getContactName(contactData) {
  const fullName = [contactData?.first_name, contactData?.last_name]
    .filter(Boolean)
    .join(" ")
    .trim();

  return fullName || "Contacto desbloqueado";
}

function normalizePhone(phone) {
  return String(phone || "").replace(/[^\d+]/g, "");
}

function getWhatsappHref(phone) {
  const normalized = normalizePhone(phone);
  if (!normalized) return "";

  const clean = normalized.replace(/^\+/, "");
  return `https://wa.me/${clean}`;
}

function normalizeWebsite(value) {
  const website = String(value || "").trim();
  if (!website) return "";

  if (/^https?:\/\//i.test(website)) return website;

  return `https://${website}`;
}

function normalizeSocial(value) {
  const social = String(value || "").trim();
  if (!social) return "";

  if (/^https?:\/\//i.test(social)) return social;

  return social;
}

export default function ConversationPrivacyCard({
  conversation,
  contactData,
  shareRequest,
  isShareRequestForMe,
  actionLoading,
  onRequestShare,
  onRespondShare,
}) {
  const isContactShared = Number(conversation?.contact_shared) === 1;

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:rounded-3xl sm:p-5">
      <p className="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400 sm:text-[11px] sm:tracking-[0.18em]">
        Privacidad
      </p>

      <h2 className="mt-2 text-base font-black leading-tight text-slate-900 sm:text-lg">
        {isContactShared ? "Datos desbloqueados" : "Datos protegidos"}
      </h2>

      {!isContactShared ? (
        <p className="mt-2 text-sm leading-relaxed text-slate-500">
          No compartas teléfonos, emails, redes sociales ni links externos hasta
          que ambas partes acepten compartir datos.
        </p>
      ) : null}

      {isContactShared ? (
        <UnlockedContact contactData={contactData} />
      ) : isShareRequestForMe ? (
        <div className="mt-4 space-y-3 sm:mt-5">
          <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p className="text-sm font-bold text-slate-900">
              La otra parte quiere compartir datos.
            </p>
            <p className="mt-1 text-xs leading-relaxed text-emerald-700">
              Si aceptás, ambos podrán ver los datos de contacto cargados.
            </p>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <button
              type="button"
              disabled={actionLoading}
              onClick={() => onRespondShare("accepted")}
              className="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:opacity-90 disabled:opacity-60"
            >
              Aceptar
            </button>

            <button
              type="button"
              disabled={actionLoading}
              onClick={() => onRespondShare("rejected")}
              className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            >
              Rechazar
            </button>
          </div>
        </div>
      ) : shareRequest?.status === "pending" ? (
        <div className="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">
          Solicitud pendiente de respuesta.
        </div>
      ) : (
        <button
          type="button"
          disabled={actionLoading}
          onClick={onRequestShare}
          className="mt-4 w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:opacity-90 disabled:opacity-60 sm:mt-5"
        >
          Solicitar compartir datos
        </button>
      )}
    </section>
  );
}

function UnlockedContact({ contactData }) {
  if (!contactData) {
    return (
      <div className="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">
        Ambas partes aceptaron compartir datos. No hay datos disponibles para
        mostrar.
      </div>
    );
  }

  const agencyName = contactData?.real_estate_name || "";
  const rawAddress = contactData?.real_estate_address || "";
  const locality = contactData?.real_estate_locality || "";
  const cleanAddress = rawAddress.split(",").slice(0, 1).join("").trim();
  const address = [cleanAddress, locality].filter(Boolean).join(", ");

  const email = contactData?.real_estate_email || contactData?.email || "";
  const phone = contactData?.real_estate_phone || contactData?.phone || "";
  const website = normalizeWebsite(contactData?.real_estate_website);
  const instagram = normalizeSocial(contactData?.real_estate_instagram);
  const facebook = normalizeSocial(contactData?.real_estate_facebook);
  const whatsappHref = getWhatsappHref(phone);

  return (
    <div className="mt-4 space-y-4">
      <div className="overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50 sm:rounded-3xl">
        <div className="p-4 sm:p-5">
          <div className="flex items-start gap-3">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white sm:h-12 sm:w-12">
              <Icon name="shieldCheck" size={21} />
            </div>

            <div className="min-w-0">
              <p className="text-[9px] font-black uppercase tracking-[0.14em] text-emerald-700 sm:text-[10px] sm:tracking-[0.16em]">
                Contacto habilitado
              </p>

              <h3 className="mt-1 break-words text-base font-black leading-tight text-slate-900 sm:text-lg">
                {agencyName || getContactName(contactData)}
              </h3>

              {agencyName ? (
                <p className="mt-1 break-words text-sm font-bold text-slate-700">
                  Contacto: {getContactName(contactData)}
                </p>
              ) : null}

              <p className="mt-1 text-xs font-semibold leading-relaxed text-emerald-700">
                Ambas partes aceptaron operar con datos visibles.
              </p>
            </div>
          </div>
        </div>

        {address ? (
          <div className="border-t border-emerald-200 bg-white/70 p-4">
            <div className="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                <Icon name="mapPin" size={17} />
              </div>

              <div className="min-w-0">
                <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">
                  Ubicación
                </p>

                <p className="mt-1 break-words text-sm font-black leading-snug text-slate-900">
                  {address}
                </p>
              </div>
            </div>
          </div>
        ) : null}

        {(email || phone || website || instagram || facebook) && (
          <div className="border-t border-emerald-200 bg-white/70 p-4">
            <div className="grid grid-cols-1 gap-3">
              {email ? (
                <ContactRow
                  icon="mail"
                  label={
                    contactData?.real_estate_email
                      ? "Email inmobiliaria"
                      : "Email"
                  }
                  value={email}
                  href={`mailto:${email}`}
                  copyValue={email}
                />
              ) : null}

              {phone ? (
                <ContactRow
                  icon="phone"
                  label={
                    contactData?.real_estate_phone
                      ? "Teléfono inmobiliaria"
                      : "Teléfono"
                  }
                  value={phone}
                  href={`tel:${normalizePhone(phone)}`}
                  copyValue={phone}
                />
              ) : null}

              {website ? (
                <ContactRow
                  icon="globe"
                  label="Sitio web"
                  value={website}
                  href={website}
                  copyValue={website}
                />
              ) : null}

              {instagram ? (
                <ContactRow
                  icon="instagram"
                  label="Instagram"
                  value={instagram}
                  href={instagram.startsWith("http") ? instagram : undefined}
                  copyValue={instagram}
                />
              ) : null}

              {facebook ? (
                <ContactRow
                  icon="facebook"
                  label="Facebook"
                  value={facebook}
                  href={facebook.startsWith("http") ? facebook : undefined}
                  copyValue={facebook}
                />
              ) : null}
            </div>
          </div>
        )}
      </div>

      {phone && whatsappHref ? (
        <a
          href={whatsappHref}
          target="_blank"
          rel="noreferrer"
          className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:opacity-90"
        >
          Contactar por WhatsApp
          <Icon name="arrowRight" size={16} />
        </a>
      ) : null}

      {!email && !phone && !website && !instagram && !facebook ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">
          El contacto fue desbloqueado, pero el otro usuario no tiene datos de
          contacto cargados.
        </div>
      ) : null}
    </div>
  );
}

function ContactRow({ icon, label, value, href, copyValue }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy(e) {
    e.preventDefault();
    e.stopPropagation();

    if (!copyValue) return;

    try {
      await navigator.clipboard.writeText(copyValue);
      setCopied(true);

      window.setTimeout(() => {
        setCopied(false);
      }, 1200);
    } catch {
      setCopied(false);
    }
  }

  const content = (
    <div className="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 sm:items-center">
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
        <Icon name={icon} size={17} />
      </div>

      <div className="min-w-0 flex-1">
        <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">
          {label}
        </p>
        <p className="break-words text-sm font-black text-slate-900 sm:truncate">
          {value}
        </p>
      </div>

      {copyValue ? (
        <button
          type="button"
          onClick={handleCopy}
          className="shrink-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[10px] font-black text-slate-600 hover:bg-slate-100"
        >
          {copied ? "Copiado" : "Copiar"}
        </button>
      ) : null}
    </div>
  );

  if (!href) return content;

  return (
    <a href={href} target="_blank" rel="noreferrer" className="block">
      {content}
    </a>
  );
}
