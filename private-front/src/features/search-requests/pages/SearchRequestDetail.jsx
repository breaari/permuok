import { useEffect, useMemo, useState } from "react";
import {
  Navigate,
  useLocation,
  useNavigate,
  useParams,
} from "react-router-dom";

import { api, getErrorMessage, unwrap } from "../../../api/http";
import { useAuth } from "../../auth/components/AuthContext";
import { Icon } from "../../../ui/icons/Index";
import { startConversation } from "../../conversations/api/conversations.api";
import StartConversationModal from "../../conversations/components/StartConversationModal";

import DetailSection from "../../shared/detail/components/DetailSection";
import DetailMapSection from "../../shared/detail/components/DetailMapSection";
import PublicationStatusNotice from "../../shared/detail/components/PublicationStatusNotice";
import SearchRequestHeader from "../detail/components/SearchRequestHeader";
import SearchRequestSidebar from "../detail/components/SearchRequestSidebar";
import SearchRequestConditionsSection from "../detail/components/SearchRequestConditionsSection";
import SearchRequestAmenitiesSection from "../detail/components/SearchRequestAmenitiesSection";
import SearchRequestNotesSection from "../detail/components/SearchRequestNotesSection";
import { useToast } from "../../../ui/toast/ToastProvider";

import {
  extractAmenities,
  extractPropertyTypes,
  extractRequest,
} from "../detail/searchRequestDetail.normalizers";

import {
  buildSearchRequestConditions,
  buildSearchRequestLocationData,
  buildSearchRequestQuickFacts,
  buildSearchRequestSummary,
} from "../detail/searchRequestSpecs";

export default function SearchRequestDetail() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { id } = useParams();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3 || role === 4;
  const isInvestor = role === 4;
  const canContact = role === 2 || role === 3;

  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [contactModalOpen, setContactModalOpen] = useState(false);
  const [err, setErr] = useState("");
  const [detailMode, setDetailMode] = useState(
    location.pathname.startsWith("/explore/search-requests/")
      ? "explore"
      : "owned",
  );
  const toast = useToast();
  useEffect(() => {
    let cancelled = false;

    async function loadDetail() {
      try {
        setLoading(true);
        setErr("");

        let payload = null;
        let mode = "owned";

        if (
          isInvestor ||
          location.pathname.startsWith("/explore/search-requests/")
        ) {
          const exploreRes = await api.get(`/explore/search-requests/${id}`);
          payload = unwrap(exploreRes);
          mode = "explore";
        } else {
          try {
            const ownedRes = await api.get(`/search-requests/${id}`);
            payload = unwrap(ownedRes);
            mode = "owned";
          } catch {
            const exploreRes = await api.get(`/explore/search-requests/${id}`);
            payload = unwrap(exploreRes);
            mode = "explore";
          }
        }

        if (cancelled) return;

        setDetail(payload);
        setDetailMode(mode);
      } catch (error) {
        if (cancelled) return;

        setErr(getErrorMessage(error, "No se pudo cargar la búsqueda."));
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

  const request = extractRequest(detail);

  const propertyTypes = useMemo(
    () => extractPropertyTypes(detail, request),
    [detail, request],
  );

  const amenities = useMemo(
    () => extractAmenities(detail, request),
    [detail, request],
  );

  const summaryItems = useMemo(
    () => buildSearchRequestSummary(request, propertyTypes),
    [request, propertyTypes],
  );

  const conditionItems = useMemo(
    () => buildSearchRequestConditions(request),
    [request],
  );

  const quickFacts = useMemo(
    () => buildSearchRequestQuickFacts(request, propertyTypes),
    [request, propertyTypes],
  );

  const locationData = useMemo(
    () => buildSearchRequestLocationData(request),
    [request],
  );

  const backPath =
    detailMode === "explore" ? "/explore/search-requests" : "/search-requests";

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
        opportunity_type: "search_request",
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
      "Las cuentas inversoras pueden explorar búsquedas, pero no iniciar conversaciones ni propuestas.",
    );
  }
  if (loading) {
    return (
      <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-10">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
          Cargando búsqueda...
        </div>
      </div>
    );
  }

  if (err && !request?.id) {
    return (
      <div className="mx-auto max-w-7xl space-y-4 px-4 py-10 sm:px-6 lg:px-10">
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
          {err}
        </div>

        <button
          type="button"
          onClick={handleBack}
          className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-900"
        >
          <Icon name="arrowLeft" size={16} />
          Volver
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <SearchRequestHeader
        request={request}
        id={id}
        propertyTypes={propertyTypes}
        onBack={handleBack}
      />

      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
        {err ? (
          <div className="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            {err}
          </div>
        ) : null}

        {request ? (
          <PublicationStatusNotice item={request} entityLabel="búsqueda" />
        ) : null}

        <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
          <div className="space-y-8 lg:col-span-8">
            <DetailSection title="Descripción de la búsqueda">
              <p className="whitespace-pre-line text-lg leading-relaxed text-slate-600">
                {request?.description || "—"}
              </p>
            </DetailSection>

            <SearchRequestConditionsSection items={conditionItems} />

            <SearchRequestAmenitiesSection amenities={amenities} />

            <SearchRequestNotesSection notes={request?.notes} />

            <DetailMapSection
              location={locationData}
              title="Ubicación"
              approximate
            />
          </div>

          <SearchRequestSidebar
            request={request}
            quickFacts={quickFacts}
            summaryItems={summaryItems}
            actionLoading={actionLoading}
            canContact={canContact}
            contactDisabledReason={
              !canContact
                ? "Las cuentas inversoras pueden explorar búsquedas, pero no iniciar conversaciones ni propuestas."
                : ""
            }
            onContact={
              canContact
                ? () => setContactModalOpen(true)
                : handleBlockedContact
            }
          />
        </div>
      </main>
      {canContact && (
        <StartConversationModal
          open={contactModalOpen}
          title="Contactar búsqueda"
          opportunityTitle={request?.title || "esta búsqueda"}
          opportunityType="Búsqueda"
          opportunityLocation={[
            request?.city,
            request?.zone,
            request?.province,
            request?.country,
          ]
            .filter(Boolean)
            .join(", ")}
          opportunityPrice={
            request?.currency && request?.budget_max
              ? `Hasta ${request.currency} ${Number(
                  request.budget_max,
                ).toLocaleString("es-AR")}`
              : request?.currency && request?.budget_min
                ? `Desde ${request.currency} ${Number(
                    request.budget_min,
                  ).toLocaleString("es-AR")}`
                : ""
          }
          opportunityImageUrl={
            request?.cover_image_url || request?.image_url || ""
          }
          defaultMessage={`Hola! Vi la búsqueda "${
            request?.title || "esta búsqueda"
          }" y creo que podría tener una oportunidad que encaje.`}
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
