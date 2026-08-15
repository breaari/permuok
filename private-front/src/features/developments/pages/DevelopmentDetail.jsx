import { useEffect, useMemo, useState } from "react";
import {
  Navigate,
  useLocation,
  useNavigate,
  useParams,
} from "react-router-dom";

import { getErrorMessage } from "../../../api/http";
import { useAuth } from "../../auth/components/AuthContext";
import { useToast } from "../../../ui/toast/ToastProvider";
import { startConversation } from "../../conversations/api/conversations.api";
import StartConversationModal from "../../conversations/components/StartConversationModal";

import DetailSection from "../../shared/detail/components/DetailSection";
import DetailMapSection from "../../shared/detail/components/DetailMapSection";
import PublicationStatusNotice from "../../shared/detail/components/PublicationStatusNotice";
import { getDevelopmentDetail } from "../api/developments.api";

import DevelopmentHeader from "../detail/components/DevelopmentHeader";
import DevelopmentGallery from "../detail/components/DevelopmentGallery";
import DevelopmentSidebar from "../detail/components/DevelopmentSidebar";
import DevelopmentAmenitiesSection from "../detail/components/DevelopmentAmenitiesSection";
import DevelopmentUnitTypesCard from "../detail/components/DevelopmentUnitTypesCard";
import DevelopmentCommercialLinks from "../detail/components/DevelopmentCommercialLinks";

import {
  extractDevelopment,
  extractDevelopmentAmenities,
  extractDevelopmentImages,
  extractDevelopmentUnitTypes,
  getDevelopmentImages,
} from "../detail/developmentDetail.normalizers";

import {
  buildDevelopmentLocationData,
  buildDevelopmentSpecs,
} from "../detail/developmentSpecs";

import { joinLocation } from "../detail/developmentDetail.helpers";

export default function DevelopmentDetail() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { id } = useParams();
  const toast = useToast();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3 || role === 4;
  const isInvestor = role === 4;
  const canContact = !isInvestor;

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [detail, setDetail] = useState(null);
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  const [actionLoading, setActionLoading] = useState(false);
  const [contactModalOpen, setContactModalOpen] = useState(false);
  const [detailMode, setDetailMode] = useState(
    location.pathname.startsWith("/explore/developments/")
      ? "explore"
      : "owned",
  );

  useEffect(() => {
    let cancelled = false;

    async function loadDetail() {
      setLoading(true);
      setErr("");

      try {
        let payload = null;
        let mode = "owned";

        if (
          isInvestor ||
          location.pathname.startsWith("/explore/developments/")
        ) {
          payload = await getDevelopmentDetail(id, true);
          mode = "explore";
        } else {
          try {
            payload = await getDevelopmentDetail(id, false);
            mode = "owned";
          } catch {
            payload = await getDevelopmentDetail(id, true);
            mode = "explore";
          }
        }

        if (cancelled) return;

        setDetail(payload);
        setDetailMode(mode);
        setActiveImageIndex(0);
      } catch (e) {
        if (cancelled) return;

        setErr(getErrorMessage(e, "No se pudo cargar el desarrollo"));
        setDetail(null);
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadDetail();

    return () => {
      cancelled = true;
    };
  }, [id, isInvestor, location.pathname]);

  if (!canAccess) return <Navigate to="/" replace />;

  const development = extractDevelopment(detail);
  const rawImages = extractDevelopmentImages(detail, development);
  const unitTypes = extractDevelopmentUnitTypes(detail, development);
  const amenities = extractDevelopmentAmenities(detail, development);

  const gallery = getDevelopmentImages(rawImages);

  const locationLabel = joinLocation([
    development?.city,
    development?.zone,
    development?.province,
    development?.country,
  ]);

  const summarySpecs = useMemo(
    () => buildDevelopmentSpecs(development, unitTypes),
    [development, unitTypes],
  );

  const locationData = useMemo(
    () => buildDevelopmentLocationData(development),
    [development],
  );

  const backPath =
    detailMode === "explore" ? "/explore/developments" : "/developments";

  const from = location.state?.from || null;

  function handleBack() {
    if (from) {
      navigate(from);
      return;
    }

    if (window.history.length > 1) {
      navigate(-1);
      return;
    }

    navigate(backPath);
  }

  function openContactModal() {
    if (!canContact) {
      toast.info(
        "Las cuentas inversoras pueden explorar oportunidades, pero no iniciar conversaciones.",
      );
      return;
    }

    setContactModalOpen(true);
  }

  async function handleStartConversation(message) {
    if (!canContact) {
      toast.info(
        "Las cuentas inversoras pueden explorar oportunidades, pero no iniciar conversaciones.",
      );
      return;
    }

    try {
      setActionLoading(true);

      const res = await startConversation({
        opportunity_type: "development",
        opportunity_id: Number(id),
        message,
      });

      const conversationId =
        res?.conversation?.id || res?.conversation_id || res?.id;

      if (!conversationId) {
        throw new Error("No se pudo obtener la conversación creada.");
      }

      setContactModalOpen(false);
      toast.success("Consulta enviada correctamente.");
      navigate(`/conversations/${conversationId}`);
    } catch (e) {
      toast.error(e?.message || "No se pudo iniciar la conversación.");
    } finally {
      setActionLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-10">
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 shadow-sm">
          Cargando desarrollo...
        </div>
      </div>
    );
  }

  if (err && !development) {
    return (
      <div className="mx-auto max-w-7xl space-y-4 px-4 py-10 sm:px-6 lg:px-10">
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
          {err}
        </div>

        <button
          type="button"
          onClick={handleBack}
          className="text-sm font-semibold text-slate-500 transition hover:text-slate-900"
        >
          Volver
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <DevelopmentHeader
        development={development}
        id={id}
        onBack={handleBack}
      />

      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
        {err ? (
          <div className="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            {err}
          </div>
        ) : null}

        {development ? (
          <PublicationStatusNotice
            item={development}
            entityLabel="desarrollo"
          />
        ) : null}

        {!development ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 shadow-sm">
            No se encontró el desarrollo.
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
              <DevelopmentGallery
                development={development}
                gallery={gallery}
                activeImageIndex={activeImageIndex}
                setActiveImageIndex={setActiveImageIndex}
              />

              <DevelopmentSidebar
                development={development}
                detailMode={detailMode}
                locationLabel={locationLabel}
                summarySpecs={summarySpecs}
                actionLoading={actionLoading}
                canContact={canContact}
                contactDisabledReason={
                  !canContact
                    ? "Las cuentas inversoras pueden explorar desarrollos, pero no iniciar conversaciones ni propuestas."
                    : ""
                }
                onContact={openContactModal}
              />
            </div>

            <div className="mt-12 grid grid-cols-1 gap-12 lg:grid-cols-12">
              <div className="space-y-8 lg:col-span-7">
                <DetailSection title="Descripción del desarrollo">
                  <p className="whitespace-pre-line text-lg leading-relaxed text-slate-600">
                    {development?.description || "—"}
                  </p>
                </DetailSection>

                <DevelopmentAmenitiesSection amenities={amenities} />

                <DetailMapSection location={locationData} title="Ubicación" />
              </div>

              <div className="space-y-6 lg:col-span-5">
                <DevelopmentUnitTypesCard unitTypes={unitTypes} />

                <DevelopmentCommercialLinks development={development} />
              </div>
            </div>
          </>
        )}
      </main>

      {canContact ? (
        <StartConversationModal
          open={contactModalOpen}
          title="Consultar desarrollo"
          opportunityTitle={development?.title || "este desarrollo"}
          opportunityType="Desarrollo"
          opportunityLocation={locationLabel}
          opportunityPrice={
            development?.currency && development?.price_from
              ? `Desde ${development.currency} ${Number(
                  development.price_from,
                ).toLocaleString("es-AR")}`
              : ""
          }
          opportunityImageUrl={
            gallery?.[0]?.url ||
            gallery?.[0]?.image_url ||
            gallery?.[0]?.path ||
            ""
          }
          defaultMessage={`Hola! Me interesa el desarrollo "${
            development?.title || "este desarrollo"
          }" y quisiera recibir más información.`}
          loading={actionLoading}
          error=""
          onClose={() => {
            if (!actionLoading) {
              setContactModalOpen(false);
            }
          }}
          onConfirm={handleStartConversation}
        />
      ) : null}
    </div>
  );
}
