import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api, unwrap } from "../../../api/http.js";
import { Icon } from "../../../ui/icons/Index";
import RejectModal from "../components/RejectModal.jsx";

import AdminDetailHeader from "../components/detail/AdminDetailHeader";
import AdminDetailLoading from "../components/detail/AdminDetailLoading";
import AdminDetailEmpty from "../components/detail/AdminDetailEmpty";
import AdminRealEstateSection from "../components/detail/AdminRealEstateSection";
import AdminStageInfoBanner from "../components/detail/AdminStageInfoBanner";
import AdminLicensesSection from "../components/detail/AdminLicensesSection";
import AdminAdministrativeStatusCard from "../components/detail/AdminAdministrativeStatusCard";
import AdminMembershipCard from "../components/detail/AdminMembershipCard";
import AdminDetailError from "../components/detail/AdminDetailError";
import {
  ReviewStatusPill,
  ReviewFooterStatus,
} from "../components/detail/AdminReviewStatus";

import {
  formatDate,
  resolveStage,
  membershipStatusLabel,
  membershipStatusClasses,
} from "../components/detail/AdminDetailHelpers";

export default function AdminRealEstateDetail() {
  const navigate = useNavigate();
  const { id } = useParams();

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [busy, setBusy] = useState(false);

  const [data, setData] = useState(null);
  const re = data?.real_estate;
  const licenses = data?.licenses ?? [];

  const stage = resolveStage(re);
  const isPendingReview =
    stage === "initial_review" || stage === "changes_pending";
  const isChangesPending = stage === "changes_pending";
  const showMembershipCard =
    stage === "approved" || stage === "changes_pending";

  const requestedAt = useMemo(
    () =>
      formatDate(
        re?.changes_requested_at || re?.review_requested_at || re?.created_at,
      ),
    [re],
  );

  const [rejectOpen, setRejectOpen] = useState(false);

  async function load() {
    setErr("");
    setLoading(true);

    try {
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
  }, [id]);

  async function approve() {
    if (!re?.id || !isPendingReview) return;

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
    if (!re?.id || !isPendingReview) return;

    const cleanNote = String(note || "").trim();
    if (!cleanNote) {
      setErr("Completá el motivo del rechazo.");
      return;
    }

    setBusy(true);
    setErr("");

    try {
      await api.post("/admin/real-estates/approve", {
        real_estate_id: re.id,
        action: "reject",
        validation_note: cleanNote,
      });

      setRejectOpen(false);
      await load();
    } catch (e) {
      setErr(e?.data?.message || e?.message || "No se pudo rechazar");
    } finally {
      setBusy(false);
    }
  }

  function getHeaderSubtitle() {
    if (stage === "changes_pending") {
      return "Revisión de cambios sobre perfil aprobado";
    }
    if (stage === "initial_review") {
      return "Revisión de solicitud de alta de cuenta profesional";
    }
    if (stage === "ready_for_review") {
      return "Perfil completo pendiente de envío a revisión";
    }
    if (stage === "incomplete") {
      return "Perfil en borrador con información incompleta";
    }
    if (stage === "approved") {
      return "Perfil aprobado";
    }
    return "Perfil rechazado";
  }

  const adminStatusIsRejected = stage === "rejected";

  return (
    <>
      <div className="space-y-8 pb-28">
        {loading ? (
          <AdminDetailHeader
            backLabel="Volver a solicitudes"
            onBack={() => navigate("/admin/real-estates")}
            subtitle="Cargando detalle..."
          />
        ) : (
          <AdminDetailHeader
            backLabel="Volver a solicitudes"
            onBack={() => navigate("/admin/real-estates")}
            badge={<ReviewStatusPill realEstate={re} />}
            subtitle={getHeaderSubtitle()}
          />
        )}

        {err && <AdminDetailError message={err} />}

        {loading ? (
          <AdminDetailLoading />
        ) : !re ? (
          <AdminDetailEmpty message="No se encontró la inmobiliaria." />
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div className="lg:col-span-2 space-y-6">
              <AdminStageInfoBanner realEstate={re} />

              <AdminRealEstateSection
                realEstate={re}
                validationNote={stage === "rejected" ? re?.validation_note : ""}
                showValidationNote={false}
                showLinks={true}
              />

              <div className="flex items-center gap-6 px-1 flex-wrap">
                <div className="flex items-center gap-2 text-slate-500">
                  <Icon name="calendar" size={16} />
                  <span className="text-xs">
                    {stage === "changes_pending"
                      ? "Solicitado cambio:"
                      : stage === "initial_review"
                        ? "Solicitado:"
                        : "Creado:"}{" "}
                    <strong className="text-slate-700">{requestedAt}</strong>
                  </span>
                </div>

                <div className="flex items-center gap-2 text-slate-500">
                  <Icon name="hash" size={16} />
                  <span className="text-xs">
                    ID:{" "}
                    <strong className="text-slate-700">#{re?.id ?? "—"}</strong>
                  </span>
                </div>

                {!!re?.approved_at && (
                  <div className="flex items-center gap-2 text-slate-500">
                    <Icon name="checkCircle" size={16} />
                    <span className="text-xs">
                      Aprobado anterior:{" "}
                      <strong className="text-slate-700">
                        {formatDate(re.approved_at)}
                      </strong>
                    </span>
                  </div>
                )}
              </div>
            </div>

            <div className="lg:col-span-1 flex flex-col gap-6">
              <AdminLicensesSection licenses={licenses} />

              <AdminAdministrativeStatusCard
                isActive={stage === "rejected" ? 0 : 1}
                deactivatedAt={re?.validated_at}
                deactivationReason={re?.validation_note}
                formatDate={formatDate}
                statusLabel={(value) =>
                  Number(value) === 1 ? "Activa" : "Rechazada"
                }
                actionLabel={null}
                compact={true}
                customStatusLabel={
                  stage === "rejected" ? "Rechazada" : "Activa"
                }
                customStatusTone={stage === "rejected" ? "danger" : "success"}
                customNote={
                  stage === "rejected"
                    ? re?.validation_note
                      ? `"${re.validation_note}"`
                      : "—"
                    : "La cuenta se encuentra operativa a nivel administrativo."
                }
                showDeactivatedBy={false}
                showDeactivatedAt={false}
                showDeactivationReason={true}
              />
              {showMembershipCard && (
                <AdminMembershipCard
                  membership={re?.membership}
                  membershipStatus={re?.membership_status}
                  membershipLabel={membershipStatusLabel(re?.membership_status)}
                  membershipTone={membershipStatusClasses(
                    re?.membership_status,
                  )}
                  formatDate={formatDate}
                  onViewMore={() => navigate("/admin/memberships")}
                  emptyMessage="Esta inmobiliaria todavía no tiene una membresía activa asociada."
                />
              )}
            </div>
          </div>
        )}
      </div>

      {!loading && !!re && (
        <>
          <div className="fixed bottom-0 left-0 right-0 md:left-64 z-20 bg-white/95 backdrop-blur-md border-t border-slate-200 shadow-[0_-4px_20px_rgba(15,23,42,0.06)]">
            <div className="px-6 py-4">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="hidden md:flex flex-col">
                  <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    Estado de revisión
                  </p>
                  <ReviewFooterStatus realEstate={re} />
                </div>

                <div className="flex items-center gap-3 w-full md:w-auto">
                  <button
                    type="button"
                    disabled={busy || !isPendingReview}
                    onClick={() => setRejectOpen(true)}
                    className="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-3 rounded-lg border-2 border-rose-500 text-rose-600 font-bold hover:bg-rose-50 transition-all text-sm uppercase tracking-wide disabled:opacity-60"
                  >
                    <Icon name="block" size={18} />
                    {isChangesPending
                      ? "Rechazar cambios"
                      : "Rechazar solicitud"}
                  </button>

                  <button
                    type="button"
                    disabled={busy || !isPendingReview}
                    onClick={approve}
                    className="flex-1 md:flex-none flex items-center justify-center gap-2 px-10 py-3 rounded-lg bg-primary text-white font-bold hover:bg-primary/90 shadow-md shadow-primary/20 transition-all text-sm uppercase tracking-wide disabled:opacity-60"
                  >
                    <Icon name="checkCircle" size={18} />
                    {isChangesPending
                      ? "Aprobar cambios"
                      : "Aprobar inmobiliaria"}
                  </button>
                </div>
              </div>

              {!isPendingReview && (
                <div className="mt-2 text-xs text-slate-500">
                  {stage === "incomplete" &&
                    "Este perfil todavía no puede revisarse porque está incompleto."}
                  {stage === "ready_for_review" &&
                    "El perfil está listo, pero la inmobiliaria todavía no lo envió a revisión."}
                  {(stage === "approved" || stage === "rejected") &&
                    "Esta solicitud ya fue procesada."}
                </div>
              )}
            </div>
          </div>

          <RejectModal
            open={rejectOpen}
            title={
              isChangesPending
                ? "Rechazar cambios del perfil"
                : "Rechazar solicitud"
            }
            subtitle={re?.name ? `Inmobiliaria: ${re.name}` : ""}
            confirmLabel={busy ? "Procesando..." : "Confirmar rechazo"}
            busy={busy}
            noteLabel={
              isChangesPending
                ? "Motivo del rechazo de los cambios"
                : "Motivo del rechazo"
            }
            notePlaceholder={
              isChangesPending
                ? "Indicá qué cambios deben corregirse..."
                : "Indicá el motivo del rechazo..."
            }
            onClose={() => {
              if (busy) return;
              setRejectOpen(false);
            }}
            onConfirm={rejectConfirm}
          />
        </>
      )}
    </>
  );
}
