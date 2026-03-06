// pages/AdminRealEstateDetail.jsx
import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api, unwrap } from "../api/http";
import { Icon } from "../ui/icons/Index";
import RejectModal from "../ui/admin/RejectModal";

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

function ReviewStatusPill({ status }) {
  // status: 0 pending, 1 approved, 2 rejected (según tu BD)
  if (Number(status) === 1) {
    return (
      <span className="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Aprobada
      </span>
    );
  }
  if (Number(status) === 2) {
    return (
      <span className="px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Rechazada
      </span>
    );
  }
  return (
    <span className="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider">
      Pendiente
    </span>
  );
}

function FooterStatus({ status }) {
  if (Number(status) === 1) {
    return (
      <div className="flex items-center gap-2">
        <span className="size-2 rounded-full bg-emerald-500" />
        <p className="text-sm font-bold text-slate-700">Aprobada</p>
      </div>
    );
  }
  if (Number(status) === 2) {
    return (
      <div className="flex items-center gap-2">
        <span className="size-2 rounded-full bg-rose-500" />
        <p className="text-sm font-bold text-slate-700">Rechazada</p>
      </div>
    );
  }
  return (
    <div className="flex items-center gap-2">
      <span className="size-2 rounded-full bg-amber-500 animate-pulse" />
      <p className="text-sm font-bold text-slate-700">
        Pendiente de aprobación
      </p>
    </div>
  );
}

function LinkChip({ href, label, tone = "default" }) {
  if (!href) return null;

  const normalizedHref = buildExternalUrl(href, tone);
  if (!normalizedHref) return null;

  const base =
    "flex items-center gap-2.5 px-4 py-2.5 rounded-lg bg-slate-50 border border-slate-200 hover:bg-white transition-all group";
  const tones = {
    default: "hover:border-primary",
    instagram: "hover:border-pink-500 hover:bg-pink-50",
    facebook: "hover:border-blue-600 hover:bg-blue-50",
  };

  const iconName =
    tone === "instagram"
      ? "instagram"
      : tone === "facebook"
        ? "facebook"
        : "globe";

  return (
    <a
      className={`${base} ${tones[tone] ?? tones.default}`}
      href={normalizedHref}
      target="_blank"
      rel="noreferrer"
    >
      <span className="text-slate-400 group-hover:text-primary transition-colors">
        <Icon name={iconName} size={18} />
      </span>
      <span className="text-sm font-semibold text-slate-700">{label}</span>
    </a>
  );
}

function buildExternalUrl(value, tone = "default") {
  const raw = String(value || "").trim();
  if (!raw) return "";

  if (/^https?:\/\//i.test(raw)) return raw;

  const cleaned = raw.replace(/^@+/, "").trim();
  if (!cleaned) return "";

  if (tone === "instagram") {
    if (/instagram\.com\//i.test(cleaned)) return `https://${cleaned}`;
    return `https://instagram.com/${cleaned}`;
  }

  if (tone === "facebook") {
    if (/facebook\.com\//i.test(cleaned)) return `https://${cleaned}`;
    return `https://facebook.com/${cleaned}`;
  }

  return `https://${cleaned}`;
}

export default function AdminRealEstateDetail() {
  const navigate = useNavigate();
  const { id } = useParams();

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [busy, setBusy] = useState(false);

  const [data, setData] = useState(null); // { real_estate, licenses }
  const re = data?.real_estate;
  const licenses = data?.licenses ?? [];

  const requestedAt = useMemo(
    () => formatDate(re?.review_requested_at || re?.created_at),
    [re],
  );

  const [rejectOpen, setRejectOpen] = useState(false);

  async function load() {
    setErr("");
    setLoading(true);
    try {
      // Opción recomendada: GET /admin/real-estates/{id}
      const res = await api.get(`/admin/real-estates/${id}`);
      const d = unwrap(res);
      setData({
        real_estate: d?.real_estate ?? d,
        licenses: d?.licenses ?? [],
      });
    } catch (e) {
      setErr(e?.data?.message || e?.message || "No se pudo cargar el detalle");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function approve() {
    if (!re?.id) return;
    setBusy(true);
    setErr("");
    try {
      await api.post("/admin/real-estates/approve", {
        real_estate_id: re.id,
        action: "approve",
      });
      await load();
    } catch (e) {
      setErr(e?.data?.message || e?.message || "No se pudo aprobar");
    } finally {
      setBusy(false);
    }
  }

  async function rejectConfirm(note) {
    if (!re?.id) return;
    setBusy(true);
    setErr("");
    try {
      await api.post("/admin/real-estates/approve", {
        real_estate_id: re.id,
        action: "reject",
        note: note.trim(),
      });
      setRejectOpen(false);
      await load();
    } catch (e) {
      setErr(e?.data?.message || e?.message || "No se pudo rechazar");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <div className="w-full py-8 pb-32">
        <div className="max-w-7xl mx-auto w-full px-6 md:px-10">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-2 text-slate-400 text-xs mb-3">
            <span className="hover:text-primary transition-colors cursor-default">
              Panel de Control
            </span>
            <span className="opacity-60">›</span>
            <span className="hover:text-primary transition-colors cursor-default">
              Solicitudes
            </span>
            <span className="opacity-60">›</span>
            <span className="text-slate-900 font-semibold uppercase tracking-wider">
              Revisión Detallada
            </span>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <button
                type="button"
                onClick={() => navigate(-1)}
                className="flex items-center justify-center size-10 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all"
              >
                <Icon name="arrowLeft" size={18} />
              </button>

              <div>
                <div className="flex items-center gap-3 flex-wrap">
                  <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-slate-900">
                    {loading ? "Cargando..." : re?.name || "—"}
                  </h1>
                  {!loading && (
                    <ReviewStatusPill status={re?.validation_status} />
                  )}
                </div>
                <p className="text-slate-500 text-sm">
                  Revisión de solicitud de alta de cuenta profesional
                </p>
              </div>
            </div>
          </div>
        </div>

        {err && (
          <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}

          {loading ? (
            <div className="text-sm text-slate-500">Cargando detalle...</div>
          ) : (
            <>
              {/* Body */}
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start pb-32">
              {/* Left */}
              <div className="lg:col-span-8 space-y-6">
                <div className="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                  <div className="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                    <span className="text-primary">
                      <Icon name="briefcase" size={18} />
                    </span>
                    <h3 className="text-base font-bold text-slate-800 uppercase tracking-wide">
                      Datos de la Inmobiliaria
                    </h3>
                  </div>

                  <div className="p-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-8">
                      <div className="space-y-1">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                          Nombre Comercial
                        </p>
                        <p className="text-slate-900 font-bold text-lg">
                          {re?.name || "—"}
                        </p>
                      </div>

                      <div className="space-y-1">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                          Razón Social
                        </p>
                        <p className="text-slate-900 font-medium text-lg">
                          {re?.legal_name || "—"}
                        </p>
                      </div>

                      <div className="space-y-1">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                          CUIT
                        </p>
                        <p className="text-slate-900 font-mono text-base">
                          {re?.cuit || "—"}
                        </p>
                      </div>

                      <div className="space-y-1">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                          Dirección
                        </p>
                        <p className="text-slate-900 text-base">
                          {re?.address || "—"}
                        </p>
                      </div>

                      <div className="space-y-1">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                          Teléfono
                        </p>
                        <p className="text-slate-900 text-base">
                          {re?.phone || "—"}
                        </p>
                      </div>

                      <div className="space-y-1">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                          Email de Contacto
                        </p>
                        <p className="text-primary font-semibold text-base break-all">
                          {re?.email || "—"}
                        </p>
                      </div>
                    </div>

                    <div className="mt-10 pt-8 border-t border-slate-100">
                      <h4 className="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-5">
                        Presencia Digital y Redes
                      </h4>

                      <div className="flex flex-wrap gap-3">
                        <LinkChip
                          href={re?.website}
                          label="Sitio Web"
                          tone="default"
                        />
                        <LinkChip
                          href={re?.instagram}
                          label="Instagram"
                          tone="instagram"
                        />
                        <LinkChip
                          href={re?.facebook}
                          label="Facebook"
                          tone="facebook"
                        />
                      </div>

                      {!re?.website && !re?.instagram && !re?.facebook && (
                        <p className="text-sm text-slate-500 mt-3">
                          No se informaron enlaces de presencia digital.
                        </p>
                      )}
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-6 px-1 flex-wrap">
                  <div className="flex items-center gap-2 text-slate-500">
                    <Icon name="calendar" size={16} />
                    <span className="text-xs">
                      Solicitado:{" "}
                      <strong className="text-slate-700">{requestedAt}</strong>
                    </span>
                  </div>

                  <div className="flex items-center gap-2 text-slate-500">
                    <Icon name="hash" size={16} />
                    <span className="text-xs">
                      ID:{" "}
                      <strong className="text-slate-700">
                        #{re?.id ?? "—"}
                      </strong>
                    </span>
                  </div>
                </div>
              </div>

              {/* Right */}
              <div className="lg:col-span-4 space-y-6">
                <div className="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden sticky top-24">
                  <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div className="flex items-center gap-3">
                      <span className="text-primary">
                        <Icon name="badge" size={18} />
                      </span>
                      <h3 className="text-base font-bold text-slate-800 uppercase tracking-wide">
                        Matrículas
                      </h3>
                    </div>

                    <span className="bg-slate-200 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded uppercase">
                      {licenses.length} Activas
                    </span>
                  </div>

                  <div className="p-5 space-y-4">
                    {!licenses.length ? (
                      <div className="text-sm text-slate-500">
                        Sin matrículas cargadas.
                      </div>
                    ) : (
                      licenses.map((lic, idx) => {
                        const isPrimary = !!lic?.is_primary || idx === 0;
                        return (
                          <div
                            key={lic?.id ?? idx}
                            className={
                              isPrimary
                                ? "p-4 rounded-lg border border-primary/30 bg-primary/[0.03]"
                                : "p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-all"
                            }
                          >
                            <div className="flex items-center justify-between mb-3">
                              {isPrimary ? (
                                <span className="text-[10px] font-black bg-primary text-white px-2 py-0.5 rounded flex items-center gap-1">
                                  <span className="opacity-90">★</span>
                                  PRINCIPAL
                                </span>
                              ) : (
                                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                  Provincia
                                </span>
                              )}

                              {!!lic?.file_url && (
                                <a
                                  href={lic.file_url}
                                  target="_blank"
                                  rel="noreferrer"
                                  className="text-slate-400 hover:text-primary transition-colors"
                                  title="Ver archivo"
                                >
                                  <Icon name="eye" size={18} />
                                </a>
                              )}
                            </div>

                            <div className="space-y-3">
                              <div>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                  Provincia
                                </p>
                                <p className="text-sm font-bold text-slate-800">
                                  {lic?.province || "—"}
                                </p>
                              </div>

                              <div>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                  Número de Matrícula
                                </p>
                                <p
                                  className={
                                    isPrimary
                                      ? "text-xl font-black text-primary tracking-tight"
                                      : "text-lg font-bold text-slate-900 tracking-tight"
                                  }
                                >
                                  {lic?.license_number || "—"}
                                </p>
                              </div>
                            </div>
                          </div>
                        );
                      })
                    )}
                  </div>
                </div>
              </div>
            </div>

            </>
          )}
        </div>

        {!loading && (
          <>
            {/* Footer dentro del layout (no fixed global) */}
            <div className="sticky bottom-0 z-10 -mx-6 w-[calc(100%+3rem)] bg-white/90 backdrop-blur-md border-t border-slate-200 shadow-sm">
              <div className="px-6 py-4">
                <div className="flex items-center justify-between gap-6">
                  <div className="hidden md:flex flex-col">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                      Estado de Revisión
                    </p>
                    <FooterStatus status={re?.validation_status} />
                  </div>

                  <div className="flex items-center gap-3 w-full md:w-auto">
                    <button
                      type="button"
                      disabled={busy || Number(re?.validation_status) !== 0}
                      onClick={() => setRejectOpen(true)}
                      className="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-3 rounded-lg border-2 border-red-500 text-red-600 font-bold hover:bg-red-50 transition-all text-sm uppercase tracking-wide disabled:opacity-60"
                    >
                      <Icon name="block" size={18} />
                      Rechazar solicitud
                    </button>

                    <button
                      type="button"
                      disabled={busy || Number(re?.validation_status) !== 0}
                      onClick={approve}
                      className="flex-1 md:flex-none flex items-center justify-center gap-2 px-10 py-3 rounded-lg bg-primary text-white font-bold hover:bg-primary/90 shadow-md shadow-primary/20 transition-all text-sm uppercase tracking-wide disabled:opacity-60"
                    >
                      <Icon name="checkCircle" size={18} />
                      Aprobar inmobiliaria
                    </button>
                  </div>
                </div>

                {Number(re?.validation_status) !== 0 && (
                  <div className="mt-2 text-xs text-slate-500">
                    Esta solicitud ya fue procesada.
                  </div>
                )}
              </div>
            </div>

            <RejectModal
              open={rejectOpen}
              title="Rechazar solicitud"
              subtitle={re?.name ? `Inmobiliaria: ${re.name}` : ""}
              confirmLabel={busy ? "Procesando..." : "Confirmar rechazo"}
              busy={busy}
              onClose={() => {
                if (busy) return;
                setRejectOpen(false);
              }}
              onConfirm={rejectConfirm}
            />
          </>
        )}
      </div>
    </div>
  );
}
