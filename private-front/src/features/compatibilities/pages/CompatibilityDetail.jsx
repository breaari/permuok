import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";

import {
  getCompatibilityDetail,
  markCompatibilityAsSeen,
  respondToCompatibility,
  saveCompatibilityFeedback,
} from "../api/compatibilities.api";

import { Icon } from "../../../ui/icons/Index";

import PropertyCard from "../../properties/components/PropertyCard";
import SearchRequestCard from "../../search-requests/components/SearchRequestCard";

/* =========================================================
   HELPERS
========================================================= */

function formatMoney(value, currency = "USD") {
  const amount = Number(value);

  if (!Number.isFinite(amount)) {
    return "—";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(amount);
}

function getReasonLabel(reason) {
  const labels = {
    property_type_match: "Es el tipo de propiedad buscado",
    exact_zone_match: "Coincide exactamente con la zona",
    city_match: "Coincide con la ciudad buscada",
    province_match: "Coincide con la provincia",
    price_in_range: "Está dentro del presupuesto",
    amenities_match: "Cumple los amenities solicitados",
    swap_mode_accepted: "La propiedad acepta permuta",
    exchange_offer_value_match:
      "El inmueble ofrecido tiene un valor compatible",
    cash_difference_capacity: "La diferencia puede ser cubierta",
    owner_difference_conditions: "La diferencia está dentro de lo aceptado",
  };

  return labels[reason?.code] || reason?.label || "Criterio compatible";
}

function isMatchedReason(reason) {
  if (reason?.matched === true) {
    return true;
  }

  if (Array.isArray(reason?.matched) && reason.matched.length > 0) {
    return true;
  }

  return false;
}

/* =========================================================
   SCORE
========================================================= */

function ScoreCircle({ score }) {
  const value = Math.round(Number(score || 0));

  return (
    <div className="flex h-24 w-24 shrink-0 flex-col items-center justify-center rounded-full border-[5px] border-emerald-500 bg-white">
      <span className="text-2xl font-black tracking-tight text-emerald-700">
        {value}%
      </span>

      <span className="mt-0.5 text-[10px] font-extrabold uppercase tracking-[0.12em] text-emerald-600">
        Match
      </span>
    </div>
  );
}

/* =========================================================
   FEEDBACK MODAL
========================================================= */

function FeedbackModal({ open, onClose, onSubmit, submitting }) {
  const [useful, setUseful] = useState(null);

  const [rating, setRating] = useState(null);

  const [comment, setComment] = useState("");

  const [error, setError] = useState("");

  useEffect(() => {
    if (!open) {
      return;
    }

    setUseful(null);
    setRating(null);
    setComment("");
    setError("");
  }, [open]);

  if (!open) {
    return null;
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (useful === null && rating === null && comment.trim() === "") {
      setError("Seleccioná al menos una opción o escribí un comentario.");

      return;
    }

    setError("");

    try {
      await onSubmit({
        useful,
        rating,
        comment: comment.trim(),
      });
    } catch (err) {
      setError(
        err?.response?.data?.error ||
          err?.message ||
          "No se pudo enviar tu opinión.",
      );
    }
  }

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]">
      <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
          <div>
            <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-emerald-700">
              Tu opinión nos ayuda
            </span>

            <h2 className="mt-1 text-xl font-black text-slate-900">
              ¿Qué te pareció este match?
            </h2>

            <p className="mt-2 text-sm leading-relaxed text-slate-500">
              Esta información nos ayuda a mejorar qué oportunidades te
              mostramos en PermuOK.
            </p>
          </div>

          <button
            type="button"
            onClick={onClose}
            disabled={submitting}
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          >
            <Icon name="close" size={18} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-5 sm:p-6">
          <div>
            <p className="text-sm font-bold text-slate-800">
              ¿Esta compatibilidad te resultó útil?
            </p>

            <div className="mt-3 grid grid-cols-2 gap-3">
              <button
                type="button"
                onClick={() => setUseful(true)}
                className={`rounded-xl border px-4 py-3 text-sm font-bold transition ${
                  useful === true
                    ? "border-emerald-500 bg-emerald-50 text-emerald-800"
                    : "border-slate-200 bg-white text-slate-600 hover:border-slate-300"
                }`}
              >
                Sí, me sirvió
              </button>

              <button
                type="button"
                onClick={() => setUseful(false)}
                className={`rounded-xl border px-4 py-3 text-sm font-bold transition ${
                  useful === false
                    ? "border-slate-500 bg-slate-100 text-slate-800"
                    : "border-slate-200 bg-white text-slate-600 hover:border-slate-300"
                }`}
              >
                No demasiado
              </button>
            </div>
          </div>

          <div className="mt-6">
            <p className="text-sm font-bold text-slate-800">
              ¿Cómo calificarías el match?
            </p>

            <div className="mt-3 flex gap-2">
              {[1, 2, 3, 4, 5].map((value) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setRating(value)}
                  className={`flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-black transition ${
                    rating !== null && value <= rating
                      ? "border-emerald-500 bg-emerald-50 text-emerald-700"
                      : "border-slate-200 bg-white text-slate-400 hover:border-slate-300"
                  }`}
                >
                  {value}
                </button>
              ))}
            </div>
          </div>

          <div className="mt-6">
            <label className="text-sm font-bold text-slate-800">
              ¿Querés contarnos algo más?
            </label>

            <textarea
              value={comment}
              onChange={(event) => setComment(event.target.value)}
              maxLength={2000}
              rows={4}
              placeholder="Ej. La zona era correcta, pero el rango de precio no era tan relevante..."
              className="mt-3 w-full resize-none rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
            />

            <div className="mt-1 text-right text-[11px] text-slate-400">
              {comment.length}/2000
            </div>
          </div>

          {error && (
            <div className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
              {error}
            </div>
          )}

          <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
            >
              Omitir por ahora
            </button>

            <button
              type="submit"
              disabled={submitting}
              className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {submitting ? "Enviando..." : "Enviar opinión"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* =========================================================
   PAGE
========================================================= */

export default function CompatibilityDetail() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [item, setItem] = useState(null);

  const [loading, setLoading] = useState(true);

  const [error, setError] = useState("");

  const [actionLoading, setActionLoading] = useState(false);

  const [actionError, setActionError] = useState("");

  const [actionSuccess, setActionSuccess] = useState("");

  const [feedbackOpen, setFeedbackOpen] = useState(false);

  const [feedbackSubmitting, setFeedbackSubmitting] = useState(false);

  const [feedbackSuccess, setFeedbackSuccess] = useState(false);

  const [publicationsTab, setPublicationsTab] = useState("property");

  /* =========================================================
     LOAD
  ========================================================= */

  useEffect(() => {
    async function loadDetail() {
      try {
        setLoading(true);
        setError("");

        const data = await getCompatibilityDetail(id);

        setItem(data || null);

        try {
          const updated = await markCompatibilityAsSeen(id);

          if (updated) {
            setItem(updated);
          }
        } catch (seenError) {
          console.error(
            "No se pudo marcar la compatibilidad como vista:",
            seenError,
          );
        }
      } catch (err) {
        console.error("Error cargando compatibilidad:", err);

        setError(
          err?.response?.data?.error ||
            err?.message ||
            "No se pudo cargar la compatibilidad.",
        );
      } finally {
        setLoading(false);
      }
    }

    loadDetail();
  }, [id]);

  const matchedReasons = useMemo(
    () => (item?.reasons || []).filter(isMatchedReason),
    [item],
  );

  /* =========================================================
     LOADING / ERROR
  ========================================================= */

  if (loading) {
    return (
      <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
          <p className="text-sm font-medium text-slate-500">
            Cargando detalle del match...
          </p>
        </div>
      </div>
    );
  }

  if (error || !item) {
    return (
      <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <button
          type="button"
          onClick={() => navigate("/compatibilities")}
          className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800"
        >
          <Icon name="arrowLeft" size={17} />
          Volver
        </button>

        <div className="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm font-medium text-red-700">
          {error || "Compatibilidad no encontrada."}
        </div>
      </div>
    );
  }

  /* =========================================================
     DATA
  ========================================================= */

  const property = item.property || {};

  const search = item.search_request || {};

  const swap = item.swap || {};

  const isSearchSide = item.my_side === "search_request";

  const myResponse = item.my_response || "pending";

  const counterpartResponse = item.counterpart_response || "pending";

  const isInterested = myResponse === "interested";

  const isDismissed = myResponse === "dismissed";

  const counterpartInterested =
    myResponse === "pending" && counterpartResponse === "interested";

  const counterpartDismissed = counterpartResponse === "dismissed";

  const hasConversation = Number(item?.conversation_id) > 0;

  const canReactivate = Boolean(
    item?.actions?.can_reactivate ||
    (isDismissed && item?.status === "dismissed"),
  );

  /*
   * Adaptamos los objetos del endpoint de
   * compatibilidades al contrato de las cards
   * oficiales de propiedades y búsquedas.
   */

  const propertyCardItem = {
    id: property.id,

    title: property.title,

    description: property.description,

    property_type: property.property_type || property.type,

    price: property.price,

    currency: property.currency,

    country: property.country || property.location?.country,

    province: property.province || property.location?.province,

    city: property.city || property.location?.city,

    zone: property.zone || property.location?.zone,

    total_area: property.total_area ?? property.features?.total_area,

    covered_area: property.covered_area ?? property.features?.covered_area,

    bedrooms: property.bedrooms ?? property.features?.bedrooms,

    bathrooms: property.bathrooms ?? property.features?.bathrooms,

    garages: property.garages ?? property.features?.garages,

    antiquity: property.antiquity ?? property.features?.antiquity,

    status: property.status || "published",

    cover_image_url: property.cover_image_url || property.image_url,

    images: property.images || [],
  };

  const searchCardItem = {
    id: search.id,

    title: search.title,

    description: search.description,

    country: search.country || search.location?.country,

    province: search.province || search.location?.province,

    city: search.city || search.location?.city,

    zone: search.zone || search.location?.zone,

    currency: search.currency || search.budget?.currency,

    min_value: search.min_value ?? search.budget?.min,

    max_value: search.max_value ?? search.budget?.max,

    min_total_area: search.min_total_area,

    min_covered_area: search.min_covered_area,

    min_bedrooms: search.min_bedrooms,

    min_bathrooms: search.min_bathrooms,

    min_garages: search.min_garages,

    payment_mode_cash: search.payment_mode_cash,

    payment_mode_swap: search.payment_mode_swap,

    property_types: search.property_types,

    status: search.status || "published",
  };

  /* =========================================================
     NAVIGATION
  ========================================================= */

  function openSearchRequest() {
    if (isSearchSide) {
      navigate(`/search-requests/${search.id}`);

      return;
    }

    navigate(`/explore/search-requests/${search.id}`);
  }

  function openProperty() {
    if (!isSearchSide) {
      navigate(`/properties/${property.id}`);

      return;
    }

    navigate(`/explore/properties/${property.id}`);
  }

  /* =========================================================
     ACTIONS
  ========================================================= */

  async function handleDecision(response, { openFeedback = true } = {}) {
    if (actionLoading) {
      return;
    }

    try {
      setActionLoading(true);
      setActionError("");
      setActionSuccess("");

      const updated = await respondToCompatibility(item.id, response);

      setItem(updated);

      if (response === "pending") {
        setActionSuccess("La compatibilidad fue reactivada.");

        return;
      }

      if (response === "interested" && Number(updated?.conversation_id) > 0) {
        setActionSuccess(
          "Hay interés mutuo. La conversación ya está habilitada.",
        );
      } else if (response === "interested") {
        setActionSuccess(
          "Registramos tu interés. Le avisamos a la otra parte.",
        );
      }

      if (openFeedback) {
        setFeedbackOpen(true);
      }
    } catch (err) {
      console.error("Error respondiendo compatibilidad:", err);

      setActionError(
        err?.response?.data?.error ||
          err?.message ||
          "No se pudo guardar tu decisión.",
      );
    } finally {
      setActionLoading(false);
    }
  }

  async function handleFeedbackSubmit(payload) {
    try {
      setFeedbackSubmitting(true);

      await saveCompatibilityFeedback(item.id, payload);

      setFeedbackOpen(false);
      setFeedbackSuccess(true);

      setTimeout(() => {
        setFeedbackSuccess(false);
      }, 4000);
    } catch (err) {
      console.error("Error guardando feedback:", err);

      throw err;
    } finally {
      setFeedbackSubmitting(false);
    }
  }

  /* =========================================================
     RENDER
  ========================================================= */

  return (
    <>
      <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        {/* =====================================================
            HEADER
        ====================================================== */}

        <div className="mb-6">
          <button
            type="button"
            onClick={() => navigate("/compatibilities")}
            className="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-800"
          >
            <Icon name="arrowLeft" size={17} />
            Volver a compatibilidades
          </button>

          <span className="block text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">
            Match inteligente
          </span>

          <h1 className="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
            Detalle de compatibilidad
          </h1>

          <p className="mt-2 max-w-2xl text-sm text-slate-500">
            Analizamos las condiciones de ambas publicaciones para detectar
            oportunidades comercialmente viables.
          </p>
        </div>

        {/* =====================================================
            MESSAGES
        ====================================================== */}

        {actionSuccess && (
          <div className="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <Icon
              name="checkCircle"
              size={19}
              className="mt-0.5 shrink-0 text-emerald-700"
            />

            <p className="text-sm font-medium text-emerald-800">
              {actionSuccess}
            </p>
          </div>
        )}

        {feedbackSuccess && (
          <div className="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <Icon
              name="checkCircle"
              size={19}
              className="mt-0.5 shrink-0 text-emerald-700"
            />

            <div>
              <p className="text-sm font-bold text-emerald-900">
                Gracias por tu opinión
              </p>

              <p className="mt-0.5 text-xs text-emerald-800/80">
                La vamos a usar para seguir mejorando la calidad de los matches.
              </p>
            </div>
          </div>
        )}

        {/* =====================================================
            MAIN GRID
        ====================================================== */}

        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
          {/* ===================================================
              MAIN COLUMN
          ==================================================== */}

          <div className="space-y-6">
            {/* SCORE */}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
              <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                <ScoreCircle score={item.score} />

                <div>
                  <span className="text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">
                    #{item.id}{" "}
                    {item.match_level === "total"
                      ? "Compatibilidad total"
                      : "Compatibilidad detectada"}
                  </span>

                  <h2 className="mt-1 text-xl font-black text-slate-900">
                    {item.match_level === "total"
                      ? "Encontramos una coincidencia muy fuerte"
                      : "Encontramos una oportunidad compatible"}
                  </h2>

                  <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
                    {item.match_reason ||
                      "La propiedad cumple varios de los criterios definidos."}
                  </p>
                </div>
              </div>
            </section>

            {/* =================================================
                PUBLICACIONES REALES
            ================================================== */}

            <section>
              <div className="mb-4">
                <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-400">
                  Publicaciones involucradas
                </span>

                <h2 className="mt-1 text-xl font-black text-slate-900">
                  Revisá el match completo
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                  Consultá la propiedad y la búsqueda que dieron origen a esta
                  compatibilidad.
                </p>
              </div>

              <div className="mb-5 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                <button
                  type="button"
                  onClick={() => setPublicationsTab("property")}
                  className={`rounded-lg px-4 py-2 text-sm font-bold transition ${
                    publicationsTab === "property"
                      ? "bg-white text-primary shadow-sm"
                      : "text-slate-500 hover:text-slate-900"
                  }`}
                >
                  {isSearchSide ? "Propiedad encontrada" : "Tu propiedad"}
                </button>

                <button
                  type="button"
                  onClick={() => setPublicationsTab("search")}
                  className={`rounded-lg px-4 py-2 text-sm font-bold transition ${
                    publicationsTab === "search"
                      ? "bg-white text-primary shadow-sm"
                      : "text-slate-500 hover:text-slate-900"
                  }`}
                >
                  {isSearchSide ? "Tu búsqueda" : "Búsqueda compatible"}
                </button>
              </div>

              {publicationsTab === "property" && (
                <PropertyCard
                  item={propertyCardItem}
                  variant="dashboard"
                  detailHref={
                    isSearchSide
                      ? `/explore/properties/${property.id}`
                      : `/properties/${property.id}`
                  }
                />
              )}

              {publicationsTab === "search" && (
                <SearchRequestCard
                  item={searchCardItem}
                  variant="dashboard"
                  compact
                  onView={openSearchRequest}
                />
              )}
            </section>

            {/* =================================================
                SWAP ANALYSIS
            ================================================== */}

            {swap.evaluated && (
              <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 className="text-lg font-black text-slate-900">
                  Análisis de la operación
                </h3>

                {swap.accepted && (
                  <div className="mt-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <Icon
                      name="checkCircle"
                      size={20}
                      className="mt-0.5 shrink-0 text-emerald-700"
                    />

                    <div>
                      <p className="font-bold text-emerald-900">
                        Operación posible
                      </p>

                      <p className="mt-1 text-sm leading-relaxed text-emerald-800/80">
                        La diferencia requerida está dentro de las condiciones
                        declaradas por las partes.
                      </p>
                    </div>
                  </div>
                )}

                <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                  <div className="rounded-xl bg-slate-50 p-4">
                    <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400">
                      Inmueble ofrecido
                    </span>

                    <p className="mt-2 text-lg font-black text-slate-900">
                      {formatMoney(swap.offered_value, swap.currency)}
                    </p>
                  </div>

                  <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-emerald-700">
                      Diferencia necesaria
                    </span>

                    <p className="mt-2 text-lg font-black text-emerald-800">
                      {formatMoney(swap.required_difference, swap.currency)}
                    </p>
                  </div>

                  <div className="rounded-xl bg-slate-50 p-4">
                    <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400">
                      Podés aportar hasta
                    </span>

                    <p className="mt-2 text-lg font-black text-slate-900">
                      {formatMoney(swap.available_difference, swap.currency)}
                    </p>
                  </div>
                </div>

                {(swap.accepted_difference_min !== null ||
                  swap.accepted_difference_max !== null) && (
                  <div className="mt-4 text-sm text-slate-500">
                    El propietario declaró aceptar una diferencia entre{" "}
                    <strong className="text-slate-700">
                      {formatMoney(swap.accepted_difference_min, swap.currency)}
                    </strong>{" "}
                    y{" "}
                    <strong className="text-slate-700">
                      {formatMoney(swap.accepted_difference_max, swap.currency)}
                    </strong>
                    .
                  </div>
                )}
              </section>
            )}
          </div>

          {/* ===================================================
              SIDEBAR
          ==================================================== */}

          <aside className="space-y-6">
            {/* ===============================================
                REASONS
            ================================================ */}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
              <h3 className="text-base font-black text-slate-900">
                ¿Por qué es un match?
              </h3>

              {matchedReasons.length > 0 ? (
                <div className="mt-4 space-y-3">
                  {matchedReasons.map((reason) => (
                    <div key={reason.code} className="flex items-start gap-3">
                      <Icon
                        name="checkCircle"
                        size={18}
                        className="mt-0.5 shrink-0 text-emerald-600"
                      />

                      <span className="text-sm leading-relaxed text-slate-600">
                        {getReasonLabel(reason)}
                      </span>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                  <p className="text-sm font-semibold text-slate-700">
                    No hay un desglose de criterios disponible para este match.
                  </p>

                  <p className="mt-1 text-xs leading-relaxed text-slate-500">
                    Este registro fue generado sin el detalle de evaluación del
                    motor.
                  </p>
                </div>
              )}
            </section>

            {/* ===============================================
                DECISION
            ================================================ */}

            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
                Tu decisión
              </span>

              {/* INTERÉS MUTUO / CHAT */}

              {hasConversation && (
                <>
                  <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div className="flex items-start gap-3">
                      <Icon
                        name="checkCircle"
                        size={20}
                        className="mt-0.5 shrink-0 text-emerald-700"
                      />

                      <div>
                        <p className="text-sm font-black text-emerald-900">
                          ¡Hay interés mutuo!
                        </p>

                        <p className="mt-1 text-xs leading-relaxed text-emerald-800/80">
                          Ambas partes quieren avanzar. Ya pueden conversar
                          dentro de PermuOK.
                        </p>
                      </div>
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={() =>
                      navigate(`/conversations/${item.conversation_id}`)
                    }
                    className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition hover:opacity-90"
                  >
                    Abrir conversación
                    <Icon name="arrowRight" size={17} />
                  </button>
                </>
              )}

              {/* OTRA PARTE YA INTERESADA */}

              {!hasConversation && counterpartInterested && (
                <>
                  <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div className="flex items-start gap-3">
                      <Icon
                        name="checkCircle"
                        size={20}
                        className="mt-0.5 shrink-0 text-emerald-700"
                      />

                      <div>
                        <p className="text-sm font-black text-emerald-900">
                          Hay interés en esta oportunidad
                        </p>

                        <p className="mt-1 text-xs leading-relaxed text-emerald-800/80">
                          La otra inmobiliaria ya indicó que quiere avanzar con
                          este match.
                        </p>
                      </div>
                    </div>
                  </div>

                  <p className="mt-4 text-sm leading-relaxed text-slate-500">
                    Si también te interesa, habilitaremos automáticamente una
                    conversación entre ambas partes.
                  </p>

                  <button
                    type="button"
                    onClick={() => handleDecision("interested")}
                    disabled={actionLoading}
                    className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <Icon name="check" size={17} />

                    {actionLoading ? "Guardando..." : "También me interesa"}
                  </button>

                  <button
                    type="button"
                    onClick={() => handleDecision("dismissed")}
                    disabled={actionLoading}
                    className="mt-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    No quiero avanzar
                  </button>
                </>
              )}

              {/* SIN RESPUESTAS */}

              {!hasConversation &&
                !counterpartInterested &&
                myResponse === "pending" &&
                !counterpartDismissed && (
                  <>
                    <h3 className="mt-1 text-lg font-black text-slate-900">
                      ¿Esta oportunidad te interesa?
                    </h3>

                    <p className="mt-2 text-sm leading-relaxed text-slate-500">
                      Si marcás interés, avisaremos a la otra inmobiliaria. La
                      conversación solo se habilitará si ambas partes quieren
                      avanzar.
                    </p>

                    <button
                      type="button"
                      onClick={() => handleDecision("interested")}
                      disabled={actionLoading}
                      className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <Icon name="check" size={17} />

                      {actionLoading ? "Guardando..." : "Me interesa"}
                    </button>

                    <button
                      type="button"
                      onClick={() => handleDecision("dismissed")}
                      disabled={actionLoading}
                      className="mt-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      Descartar
                    </button>
                  </>
                )}

              {/* YO INTERESADA */}

              {!hasConversation && isInterested && !counterpartDismissed && (
                <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                  <div className="flex items-start gap-3">
                    <Icon
                      name="checkCircle"
                      size={19}
                      className="mt-0.5 shrink-0 text-emerald-700"
                    />

                    <div>
                      <p className="text-sm font-bold text-emerald-900">
                        Marcaste que te interesa
                      </p>

                      <p className="mt-1 text-xs leading-relaxed text-emerald-800/80">
                        Le avisamos a la otra inmobiliaria. Si también muestra
                        interés, habilitaremos una conversación automáticamente.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* YO DESCARTÉ */}

              {isDismissed && (
                <>
                  <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p className="text-sm font-bold text-slate-700">
                      Descartaste esta oportunidad
                    </p>

                    <p className="mt-1 text-xs leading-relaxed text-slate-500">
                      El match quedó guardado en tu historial.
                    </p>
                  </div>

                  {canReactivate && (
                    <button
                      type="button"
                      onClick={() =>
                        handleDecision("pending", {
                          openFeedback: false,
                        })
                      }
                      disabled={actionLoading}
                      className="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      {actionLoading
                        ? "Reactivando..."
                        : "Reactivar compatibilidad"}
                    </button>
                  )}
                </>
              )}

              {/* OTRA PARTE DESCARTÓ */}

              {!hasConversation && counterpartDismissed && !isDismissed && (
                <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                  <p className="text-sm font-bold text-slate-700">
                    La otra parte decidió no avanzar
                  </p>

                  <p className="mt-1 text-xs leading-relaxed text-slate-500">
                    Conservamos esta compatibilidad en tu historial para que
                    puedas consultarla más adelante.
                  </p>
                </div>
              )}

              {/* ERRORS */}

              {actionError && (
                <div className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                  {actionError}
                </div>
              )}

              {/* FEEDBACK */}

              <div className="mt-5 border-t border-slate-100 pt-4">
                <p className="text-xs leading-relaxed text-slate-500">
                  ¿Este match te resultó útil?
                </p>

                <button
                  type="button"
                  onClick={() => setFeedbackOpen(true)}
                  className="mt-2 text-sm font-bold text-primary transition hover:opacity-80"
                >
                  Calificar compatibilidad
                </button>
              </div>
            </section>
          </aside>
        </div>
      </div>

      <FeedbackModal
        open={feedbackOpen}
        onClose={() => setFeedbackOpen(false)}
        onSubmit={handleFeedbackSubmit}
        submitting={feedbackSubmitting}
      />
    </>
  );
}
