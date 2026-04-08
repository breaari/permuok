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

function getStatusMeta(status) {
  const map = {
    draft: {
      label: "En borrador",
      className: "bg-slate-100 text-slate-700 border-slate-200",
    },
    published: {
      label: "Publicada",
      className: "bg-emerald-100 text-emerald-700 border-emerald-200",
    },
    paused: {
      label: "Pausada",
      className: "bg-amber-100 text-amber-700 border-amber-200",
    },
    archived: {
      label: "Archivada",
      className: "bg-slate-200 text-slate-700 border-slate-300",
    },
    closed: {
      label: "Cerrada",
      className: "bg-rose-100 text-rose-700 border-rose-200",
    },
    deleted: {
      label: "Eliminada",
      className: "bg-rose-100 text-rose-700 border-rose-200",
    },
  };

  return (
    map[status] || {
      label: status || "Sin estado",
      className: "bg-slate-100 text-slate-600 border-slate-200",
    }
  );
}

function formatMoneyRange(item) {
  const currency = item?.currency || "USD";
  const min = item?.min_value;
  const max = item?.max_value;

  const format = (value) =>
    new Intl.NumberFormat("es-AR", {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    }).format(Number(value || 0));

  if (min && max) return `${format(min)} - ${format(max)}`;
  if (min) return `Desde ${format(min)}`;
  if (max) return `Hasta ${format(max)}`;
  return "Sin referencia definida";
}

function formatPaymentLabel(item) {
  const cash = !!item?.payment_mode_cash;
  const swap = !!item?.payment_mode_swap;

  if (cash && swap) return "Dinero + permuta";
  if (cash) return "Solo dinero";
  if (swap) return "Solo permuta";
  return "Sin modalidad definida";
}

function propertyTypeLabel(value) {
  const map = {
    house: "Casa",
    apartment: "Departamento",
    land: "Lote",
    commercial: "Local",
    office: "Oficina",
    warehouse: "Depósito",
    country_house: "Casa quinta",
    ph: "PH",
    garage: "Cochera",
    hotel: "Hotel",
    development: "Desarrollo",
    other: "Otro",
  };

  return map[value] || value || "—";
}

function joinLocation(parts) {
  return parts.filter(Boolean).join(", ");
}

function normalizePropertyTypes(raw) {
  if (!raw) return [];

  if (Array.isArray(raw)) {
    return raw
      .map((item) => {
        if (typeof item === "string") return item;
        if (typeof item === "object" && item !== null) {
          return (
            item.property_type || item.value || item.code || item.name || ""
          );
        }
        return "";
      })
      .filter(Boolean);
  }

  if (typeof raw === "string") {
    return raw
      .split(",")
      .map((v) => v.trim())
      .filter(Boolean);
  }

  return [];
}

function extractRequest(detail) {
  if (!detail || typeof detail !== "object") return {};

  if (detail.search_request && typeof detail.search_request === "object") {
    return detail.search_request;
  }

  if (detail.request && typeof detail.request === "object") {
    return detail.request;
  }

  if (detail.item && typeof detail.item === "object") {
    return detail.item;
  }

  if (
    detail.id ||
    detail.title ||
    detail.description ||
    detail.status ||
    detail.city ||
    detail.zone ||
    detail.province
  ) {
    return detail;
  }

  return {};
}

function extractPropertyTypes(detail, request) {
  const candidates = [
    detail?.property_types,
    detail?.search_request_property_types,
    detail?.types,
    request?.property_types,
  ];

  for (const candidate of candidates) {
    const normalized = normalizePropertyTypes(candidate);
    if (normalized.length) return normalized;
  }

  return [];
}

function extractAmenities(detail, request) {
  const candidates = [
    detail?.amenities,
    detail?.search_request_amenities,
    request?.amenities,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate) && candidate.length) {
      return candidate
        .map((item) =>
          typeof item === "string"
            ? item
            : item?.label || item?.name || item?.code || "",
        )
        .filter(Boolean);
    }
  }

  return [];
}

function SectionTitle({ children }) {
  return (
    <h3 className="text-xl font-bold text-slate-900 mb-6 flex items-center gap-3">
      <span className="w-1 h-6 rounded-full bg-emerald-500" />
      {children}
    </h3>
  );
}

function SummaryCard({ label, value }) {
  return (
    <div className="rounded-2xl bg-slate-50 border border-slate-200 p-5 transition hover:bg-white hover:shadow-sm">
      <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">
        {label}
      </p>
      <p className="text-lg font-semibold text-slate-900">{value || "—"}</p>
    </div>
  );
}

function DetailCell({ label, value, highlight = false }) {
  return (
    <div className="bg-white p-5">
      <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1">
        {label}
      </p>
      <p
        className={`text-base font-semibold ${
          highlight
            ? "text-slate-900"
            : value === "—"
              ? "text-slate-400"
              : "text-slate-900"
        }`}
      >
        {value || "—"}
      </p>
    </div>
  );
}

function SoftTag({ children, dark = false }) {
  return (
    <span
      className={
        dark
          ? "inline-flex rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90"
          : "inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"
      }
    >
      {children}
    </span>
  );
}

function boolText(value) {
  return Number(value) === 1 ? "Sí" : "No";
}

function urgencyMeta(value) {
  const urgency = String(value || "").toLowerCase();

  if (!urgency) return null;

  const map = {
    low: {
      label: "Baja",
      className: "bg-slate-100 text-slate-700 border-slate-200",
    },
    medium: {
      label: "Media",
      className: "bg-amber-50 text-amber-700 border-amber-200",
    },
    high: {
      label: "Alta",
      className: "bg-rose-50 text-rose-700 border-rose-200",
    },
  };

  return (
    map[urgency] || {
      label: value,
      className: "bg-slate-100 text-slate-700 border-slate-200",
    }
  );
}

export default function SearchRequestDetail() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { id } = useParams();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3;

  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [detailMode, setDetailMode] = useState(
    location.pathname.startsWith("/explore/search-requests/")
      ? "explore"
      : "owned"
  );

  useEffect(() => {
    let cancelled = false;

    async function loadDetail() {
      try {
        setLoading(true);
        setErr("");

        let payload = null;
        let mode = "owned";

        try {
          const ownedRes = await api.get(`/search-requests/${id}`);
          payload = unwrap(ownedRes);
          mode = "owned";
          console.log("OWNED SEARCH REQUEST DETAIL", payload);
        } catch (ownedError) {
          console.log("OWNED SEARCH REQUEST DETAIL ERROR", ownedError);

          const exploreRes = await api.get(`/explore/search-requests/${id}`);
          payload = unwrap(exploreRes);
          mode = "explore";
          console.log("EXPLORE SEARCH REQUEST DETAIL", payload);
        }

        if (cancelled) return;

        setDetail(payload);
        setDetailMode(mode);
      } catch (error) {
        if (cancelled) return;
        console.log("SEARCH REQUEST DETAIL FINAL ERROR", error);
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
  }, [id]);

  if (!canAccess) return <Navigate to="/" replace />;

  const request = extractRequest(detail);
  const propertyTypes = useMemo(
    () => extractPropertyTypes(detail, request),
    [detail, request]
  );
  const amenities = useMemo(
    () => extractAmenities(detail, request),
    [detail, request]
  );

  console.log("SEARCH REQUEST DETAIL NORMALIZED", {
    raw: detail,
    request,
    propertyTypes,
    amenities,
    detailMode,
  });

  const status = getStatusMeta(request?.status);
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

  const locationLabel = joinLocation([
    request?.city,
    request?.zone,
    request?.province,
  ]);

  const summaryItems = [
    {
      label: "Ubicación",
      value: locationLabel || "Sin ubicación",
    },
    {
      label: "Tipo buscado",
      value: propertyTypes.length
        ? propertyTypes.map((item) => propertyTypeLabel(item)).join(", ")
        : "Sin definir",
    },
    {
      label: "Modalidad",
      value: formatPaymentLabel(request),
    },
    {
      label: "Rango de valor",
      value: formatMoneyRange(request),
    },
  ];

  const conditionItems = [
    {
      label: "Superficie total mínima",
      value: request?.min_total_area
        ? `${Number(request.min_total_area)} m²`
        : null,
    },
    {
      label: "Superficie cubierta mínima",
      value: request?.min_covered_area
        ? `${Number(request.min_covered_area)} m²`
        : null,
    },
    {
      label: "Antigüedad máxima",
      value: request?.max_antiquity ? `${request.max_antiquity} años` : null,
    },
    {
      label: "Dormitorios mínimos",
      value: request?.min_bedrooms || null,
    },
    {
      label: "Baños mínimos",
      value: request?.min_bathrooms || null,
    },
    {
      label: "Cocheras mínimas",
      value: request?.min_garages || null,
    },
    {
      label: "Abierto a otras zonas",
      value:
        request?.open_to_other_zones !== undefined &&
        request?.open_to_other_zones !== null
          ? boolText(request.open_to_other_zones)
          : null,
    },
    {
      label: "Urgencia",
      value: request?.urgency || null,
      isUrgency: true,
    },
    {
      label: "Estado buscado",
      value: request?.property_condition || null,
    },
  ];

  const visibleConditionItems = conditionItems.filter((item) => !!item.value);
  const hasConditionsSection = visibleConditionItems.length > 0;
  const hasAmenitiesSection = amenities.length > 0;
  const hasNotesSection = !!String(request?.notes || "").trim();

  const quickFacts = [
    {
      label: "Presupuesto",
      value:
        request?.min_value || request?.max_value
          ? formatMoneyRange(request)
          : null,
    },
    {
      label: "Tipo",
      value: propertyTypes.length
        ? propertyTypes.map((item) => propertyTypeLabel(item)).join(", ")
        : null,
    },
    {
      label: "Localidad",
      value: request?.city || request?.zone || request?.province || null,
    },
    {
      label: "Urgencia",
      value: urgencyMeta(request?.urgency)?.label || null,
      accent: "amber",
    },
    {
      label: "Permuta",
      value:
        request?.payment_mode_swap !== undefined &&
        request?.payment_mode_swap !== null
          ? request?.payment_mode_swap
            ? "Acepta"
            : "No"
          : null,
      accent: request?.payment_mode_swap ? "green" : null,
    },
  ].filter((item) => !!item.value);

  if (loading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 py-10">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
          Cargando búsqueda...
        </div>
      </div>
    );
  }

  if (err) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 py-10 space-y-4">
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
          {err}
        </div>

        <button
          type="button"
          onClick={handleBack}
          className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-900 transition"
        >
          <Icon name="arrowLeft" size={16} />
          Volver
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200/70 bg-white px-4 py-8 sm:px-6 lg:px-10">
        <div className="mx-auto max-w-7xl">
          <div className="flex flex-col gap-4">
            <button
              type="button"
              onClick={handleBack}
              className="inline-flex items-center gap-2 text-primary hover:text-emerald-700 transition-colors font-semibold text-sm w-fit"
            >
              <Icon name="arrowLeft" size={16} />
              Volver
            </button>
          </div>
        </div>
      </header>

      <section className="relative overflow-hidden bg-slate-900 px-4 py-16 sm:px-6 lg:px-10">
        <div
          className="absolute inset-0 opacity-10 pointer-events-none"
          style={{
            backgroundImage:
              "radial-gradient(circle at 2px 2px, #86efac 1px, transparent 0)",
            backgroundSize: "32px 32px",
          }}
        />

        <div className="relative z-10 mx-auto max-w-7xl">
          <div className="max-w-3xl">
            <p className="text-xs uppercase tracking-widest text-emerald-300 mb-3 font-semibold">
              Ref #{request?.id || id}
            </p>

            <h2 className="text-4xl md:text-5xl font-black text-white leading-tight tracking-tighter">
              {request?.title || "Sin título"}
            </h2>

            <div className="mt-8 flex flex-wrap gap-6 text-emerald-200 font-bold tracking-wide">
              {locationLabel ? (
                <span className="flex items-center gap-1">
                  <Icon name="mapPin" size={18} />
                  {locationLabel}
                </span>
              ) : null}

              {propertyTypes.length ? (
                <span className="flex items-center gap-1">
                  <Icon name="home" size={18} />
                  {propertyTypes
                    .map((item) => propertyTypeLabel(item))
                    .join(", ")}
                </span>
              ) : null}

              {request?.min_value || request?.max_value ? (
                <span className="flex items-center gap-1">
                  <Icon name="wallet" size={18} />
                  {formatMoneyRange(request)}
                </span>
              ) : null}
            </div>
          </div>
        </div>
      </section>

      <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-10">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 space-y-12">
            <section>
              <SectionTitle>Resumen de la oportunidad</SectionTitle>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {summaryItems.map((item) => (
                  <SummaryCard
                    key={item.label}
                    label={item.label}
                    value={item.value}
                  />
                ))}
              </div>
            </section>

            {hasConditionsSection && (
              <section>
                <SectionTitle>Condiciones buscadas</SectionTitle>

                <div className="rounded-2xl overflow-hidden border border-slate-200 bg-slate-200/80">
                  <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-px">
                    {visibleConditionItems.map((item) => {
                      if (item.isUrgency) {
                        const urgency = urgencyMeta(item.value);

                        return (
                          <div key={item.label} className="bg-white p-5">
                            <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-2">
                              {item.label}
                            </p>

                            <span
                              className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wider ${urgency?.className || "bg-slate-100 text-slate-700 border-slate-200"}`}
                            >
                              <span className="w-2 h-2 rounded-full bg-current opacity-70" />
                              {urgency?.label || item.value}
                            </span>
                          </div>
                        );
                      }

                      return (
                        <DetailCell
                          key={item.label}
                          label={item.label}
                          value={item.value}
                          highlight
                        />
                      );
                    })}
                  </div>
                </div>
              </section>
            )}

            {hasAmenitiesSection && (
              <section>
                <SectionTitle>Amenities deseadas</SectionTitle>

                <div className="flex flex-wrap gap-2">
                  {amenities.map((item, index) => (
                    <SoftTag key={`${item}-${index}`}>{item}</SoftTag>
                  ))}
                </div>
              </section>
            )}

            {hasNotesSection && (
              <section>
                <SectionTitle>Observaciones</SectionTitle>

                <div className="bg-white p-8 rounded-2xl shadow-sm border-l-4 border-slate-900 border border-slate-200">
                  <p className="text-lg italic text-slate-800 font-medium">
                    “{request?.notes}”
                  </p>
                </div>
              </section>
            )}
          </div>

          <div className="space-y-6">
            <div className="bg-slate-900 text-white p-8 rounded-2xl shadow-xl lg:sticky lg:top-8">
              <h4 className="text-lg font-bold mb-6 flex items-center gap-2">
                <Icon name="bolt" size={18} className="text-emerald-300" />
                Lectura rápida
              </h4>

              <div className="space-y-4">
                {quickFacts.map((item) => (
                  <div
                    key={item.label}
                    className="flex justify-between items-center gap-4 py-3 border-b border-white/10 last:border-b-0"
                  >
                    <span className="text-sm text-slate-300">{item.label}</span>
                    <span
                      className={`font-bold text-right ${
                        item.accent === "amber"
                          ? "text-amber-300"
                          : item.accent === "green"
                            ? "text-emerald-300"
                            : "text-white"
                      }`}
                    >
                      {item.value}
                    </span>
                  </div>
                ))}
              </div>

              <button
                type="button"
                className="w-full mt-8 bg-emerald-300 text-slate-900 py-4 rounded-xl font-bold hover:brightness-95 transition-all text-sm uppercase tracking-wider"
              >
                Contactar intercambio
              </button>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}