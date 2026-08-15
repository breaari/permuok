import { useEffect, useState } from "react";
import {
  Navigate,
  useLocation,
  useNavigate,
  useParams,
} from "react-router-dom";

import { api, unwrap, getErrorMessage } from "../../../api/http";
import { useAuth } from "../../auth/components/AuthContext";
import { startConversation } from "../../conversations/api/conversations.api";
import StartConversationModal from "../../conversations/components/StartConversationModal";

import DetailSection from "../../shared/detail/components/DetailSection";
import DetailInfoGrid from "../../shared/detail/components/DetailInfoGrid";
import DetailMapSection from "../../shared/detail/components/DetailMapSection";

import PropertyHeader from "../detail/components/PropertyHeader";
import PropertyGallery from "../detail/components/PropertyGallery";
import PropertySidebar from "../detail/components/PropertySidebar";
import PropertyExchangeCard from "../detail/components/PropertyExchangeCard";
import PropertyAmenitiesSection from "../detail/components/PropertyAmenitiesSection";
import PublicationStatusNotice from "../../shared/detail/components/PublicationStatusNotice";

import { useToast } from "../../../ui/toast/ToastProvider";

import {
  formatDate,
  joinLocation,
  statusMeta,
} from "../detail/propertyDetail.helpers";

import {
  extractAmenities,
  extractImages,
  extractProperty,
  extractRequirementLocations,
  extractRequirements,
  extractRequirementTypes,
  getPropertyImages,
} from "../detail/PropertyDetail.normalizers";

import {
  buildPropertyLocationData,
  buildPropertySpecs,
} from "../detail/propertySpecs";

export default function PropertyDetail() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { id } = useParams();
  const toast = useToast();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3 || role === 4;
  const isInvestor = role === 4;
  const canContact = role === 2 || role === 3;

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [data, setData] = useState(null);
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  const [actionLoading, setActionLoading] = useState(false);
  const [contactModalOpen, setContactModalOpen] = useState(false);
  const [detailMode, setDetailMode] = useState(
    location.pathname.startsWith("/explore/properties/") ? "explore" : "owned",
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
          location.pathname.startsWith("/explore/properties/")
        ) {
          const exploreRes = await api.get(`/explore/properties/${id}`);
          payload = unwrap(exploreRes);
          mode = "explore";
        } else {
          try {
            const ownedRes = await api.get(`/properties/${id}`);
            payload = unwrap(ownedRes);
            mode = "owned";
          } catch {
            const exploreRes = await api.get(`/explore/properties/${id}`);
            payload = unwrap(exploreRes);
            mode = "explore";
          }
        }

        if (cancelled) return;

        setData(payload);
        setDetailMode(mode);
        setActiveImageIndex(0);
      } catch (e) {
        if (cancelled) return;

        setErr(getErrorMessage(e, "No se pudo cargar la publicación"));
        setData(null);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    loadDetail();

    return () => {
      cancelled = true;
    };
  }, [id, isInvestor, location.pathname]);

  if (!canAccess) return <Navigate to="/" replace />;

  const property = extractProperty(data);
  const images = extractImages(data, property);
  const requirements = extractRequirements(data, property);
  const requirementTypes = extractRequirementTypes(data, requirements);
  const requirementLocations = extractRequirementLocations(data, requirements);
  const amenities = extractAmenities(data, property);

  const meta = statusMeta(property?.status);
  const gallery = getPropertyImages(images);
  const summarySpecs = buildPropertySpecs(property);
  const locationData = buildPropertyLocationData(property);

  const locationLabel = joinLocation([
    property?.city,
    property?.zone,
    property?.province,
    property?.country,
  ]);

  const backPath =
    detailMode === "explore" ? "/explore/properties" : "/properties";

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

  async function handleStartConversation(message) {
    try {
      setActionLoading(true);
      setErr("");

      const res = await startConversation({
        opportunity_type: "property",
        opportunity_id: Number(id),
        message,
      });

      const conversationId =
        res?.conversation?.id || res?.conversation_id || res?.id;

      if (!conversationId) {
        throw new Error("No se pudo obtener la conversación creada.");
      }

      setContactModalOpen(false);
      navigate(`/conversations/${conversationId}`);
    } catch (e) {
      toast.error(getErrorMessage(e, "No se pudo iniciar la conversación."));
    } finally {
      setActionLoading(false);
    }
  }

  function handleBlockedContact() {
    toast.info(
      "Las cuentas inversoras pueden explorar propiedades, pero no iniciar conversaciones ni propuestas.",
    );
  }
  return (
    <div className="min-h-screen bg-slate-50">
      <PropertyHeader property={property} id={id} onBack={handleBack} />

      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
        {err ? (
          <div className="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            {err}
          </div>
        ) : null}

        {property ? (
          <PublicationStatusNotice item={property} entityLabel="propiedad" />
        ) : null}

        {loading ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 shadow-sm">
            Cargando publicación...
          </div>
        ) : !property ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 shadow-sm">
            No se encontró la publicación.
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
              <PropertyGallery
                property={property}
                gallery={gallery}
                activeImageIndex={activeImageIndex}
                setActiveImageIndex={setActiveImageIndex}
                requirements={requirements}
              />

              <PropertySidebar
                property={property}
                detailMode={detailMode}
                locationLabel={locationLabel}
                summarySpecs={summarySpecs}
                actionLoading={actionLoading}
                canContact={canContact}
                contactDisabledReason={
                  !canContact
                    ? "Las cuentas inversoras pueden explorar propiedades, pero no iniciar conversaciones ni propuestas."
                    : ""
                }
                onContact={
                  canContact
                    ? () => setContactModalOpen(true)
                    : handleBlockedContact
                }
              />
            </div>

            <div className="mt-12 grid grid-cols-1 gap-12 lg:grid-cols-12">
              <div className="space-y-8 lg:col-span-7">
                <DetailSection title="Descripción del inmueble">
                  <p className="whitespace-pre-line text-lg leading-relaxed text-slate-600">
                    {property?.description || "—"}
                  </p>
                </DetailSection>

                <PropertyAmenitiesSection amenities={amenities} />

                <DetailSection
                  title="Información general"
                  description="Datos principales cargados para esta publicación."
                >
                  <DetailInfoGrid
                    items={[
                      { label: "País", value: property?.country },
                      { label: "Provincia", value: property?.province },
                      { label: "Ciudad", value: property?.city },
                      { label: "Zona", value: property?.zone },
                      { label: "Dirección", value: property?.address },
                      { label: "Estado", value: meta.label },
                      {
                        label: "Publicada",
                        value: formatDate(property?.published_at),
                      },
                      {
                        label: "Última actualización",
                        value: formatDate(property?.updated_at),
                      },
                    ]}
                  />
                </DetailSection>

                <DetailMapSection location={locationData} title="Ubicación" />
              </div>

              <div className="lg:col-span-5">
                <PropertyExchangeCard
                  requirements={requirements}
                  requirementTypes={requirementTypes}
                  requirementLocations={requirementLocations}
                />
              </div>
            </div>
          </>
        )}
      </main>

      {canContact && (
        <StartConversationModal
          open={contactModalOpen}
          title="Consultar publicación"
          opportunityTitle={property?.title || "esta publicación"}
          opportunityType="Publicación"
          opportunityLocation={locationLabel}
          opportunityPrice={
            property?.currency && property?.price
              ? `${property.currency} ${Number(property.price).toLocaleString("es-AR")}`
              : ""
          }
          opportunityImageUrl={
            gallery?.[0]?.url ||
            gallery?.[0]?.image_url ||
            gallery?.[0]?.path ||
            ""
          }
          defaultMessage={`Hola! Me interesa "${property?.title || "esta publicación"}" y quisiera recibir más información.`}
          loading={actionLoading}
          onClose={() => {
            if (!actionLoading) {
              setContactModalOpen(false);
            }
          }}
          onConfirm={handleStartConversation}
        />
      )}
    </div>
  );
}
