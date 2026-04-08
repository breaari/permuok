import { useEffect, useMemo, useState } from "react";
import {
  Navigate,
  useLocation,
  useNavigate,
  useParams,
} from "react-router-dom";
import {
  api,
  unwrap,
  getApiBaseUrl,
  getErrorMessage,
} from "../../../api/http";
import { useAuth } from "../../auth/components/AuthContext";
import { Icon } from "../../../ui/icons/Index";

function formatMoney(value, currency = "USD") {
  const amount = Number(value || 0);

  if (!Number.isFinite(amount) || amount <= 0) {
    return "—";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(amount);
}

function formatDate(value) {
  if (!value) return "—";

  try {
    return new Date(value).toLocaleDateString("es-AR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

function statusMeta(status) {
  const map = {
    draft: {
      label: "En borrador",
      badgeClass: "bg-slate-100 text-slate-700 border-slate-200",
    },
    published: {
      label: "Publicada",
      badgeClass: "bg-emerald-100 text-emerald-700 border-emerald-200",
    },
    paused: {
      label: "Pausada",
      badgeClass: "bg-amber-100 text-amber-700 border-amber-200",
    },
    archived: {
      label: "Archivada",
      badgeClass: "bg-slate-200 text-slate-700 border-slate-300",
    },
    closed: {
      label: "Cerrada",
      badgeClass: "bg-rose-100 text-rose-700 border-rose-200",
    },
  };

  return (
    map[status] || {
      label: status || "Sin estado",
      badgeClass: "bg-slate-100 text-slate-700 border-slate-200",
    }
  );
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

function exchangeModeLabel(requirements) {
  if (!requirements) return "Sin criterios cargados";
  if (requirements.criteria_mode === "criteria") return "Busco con criterios";
  return "Escucho propuestas";
}

function joinLocation(parts) {
  return parts.filter(Boolean).join(", ");
}

function resolveImageUrl(rawUrl) {
  if (!rawUrl) return null;

  const value = String(rawUrl).trim();
  if (!value) return null;

  if (/^https?:\/\//i.test(value)) {
    return value;
  }

  const base = getApiBaseUrl().replace(/\/+$/, "");

  if (value.startsWith("/")) {
    return `${base}${value}`;
  }

  return `${base}/${value}`;
}

function normalizeArray(value) {
  return Array.isArray(value) ? value : [];
}

function extractProperty(detail) {
  if (!detail || typeof detail !== "object") return null;

  if (detail.property && typeof detail.property === "object") {
    return detail.property;
  }

  if (detail.item && typeof detail.item === "object") {
    return detail.item;
  }

  if (
    detail.id ||
    detail.title ||
    detail.description ||
    detail.property_type ||
    detail.city ||
    detail.province ||
    detail.country
  ) {
    return detail;
  }

  return null;
}

function extractImages(detail, property) {
  const candidates = [
    detail?.images,
    detail?.property_images,
    property?.images,
    property?.property_images,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

function extractRequirements(detail, property) {
  if (detail?.requirements && typeof detail.requirements === "object") {
    return detail.requirements;
  }

  if (
    detail?.criteria_mode ||
    detail?.accepts_total_swap !== undefined ||
    detail?.accepts_swap_plus_cash !== undefined ||
    detail?.accepts_multiple_swap !== undefined ||
    detail?.accepts_open_proposals !== undefined ||
    detail?.accepts_cash_only !== undefined
  ) {
    return detail;
  }

  if (property?.requirements && typeof property.requirements === "object") {
    return property.requirements;
  }

  return null;
}

function extractRequirementTypes(detail, requirements) {
  const candidates = [
    detail?.requirement_property_types,
    detail?.property_requirement_property_types,
    requirements?.property_types,
    requirements?.requirement_property_types,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

function extractRequirementLocations(detail, requirements) {
  const candidates = [
    detail?.requirement_locations,
    detail?.property_requirement_locations,
    requirements?.locations,
    requirements?.requirement_locations,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

function extractAmenities(detail, property) {
  const candidates = [
    detail?.amenities,
    detail?.property_amenities,
    property?.amenities,
    property?.property_amenities,
  ];

  for (const candidate of candidates) {
    if (Array.isArray(candidate)) return candidate;
  }

  return [];
}

function getPropertyImages(images) {
  return normalizeArray(images)
    .map((image) => {
      const rawUrl =
        image?.view_url ||
        image?.image_url ||
        image?.web_path ||
        image?.url ||
        image?.path ||
        image?.file_path ||
        image?.archive_path ||
        null;

      return {
        ...image,
        url: resolveImageUrl(rawUrl),
      };
    })
    .filter((image) => !!image.url);
}

function normalizeAmenityLabel(amenity) {
  if (typeof amenity === "string") return amenity;
  if (amenity && typeof amenity === "object") {
    return amenity.label || amenity.code || amenity.name || "Amenity";
  }
  return "Amenity";
}

function normalizeRequirementType(type) {
  if (typeof type === "string") return type;
  if (type && typeof type === "object") {
    return type.property_type || type.value || type.code || type.name || "";
  }
  return "";
}

function InfoItem({ label, value, dark = false }) {
  return (
    <div className="space-y-1">
      <p
        className={`text-[10px] uppercase font-bold tracking-[0.18em] ${
          dark ? "text-white/50" : "text-slate-400"
        }`}
      >
        {label}
      </p>
      <p
        className={`text-sm font-semibold ${
          dark ? "text-white" : "text-slate-900"
        }`}
      >
        {value || "—"}
      </p>
    </div>
  );
}

function AmenityItem({ children }) {
  return (
    <div className="flex items-center gap-3">
      <Icon name="checkCircle" size={18} className="text-emerald-600" />
      <span className="text-sm font-medium text-slate-700">{children}</span>
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

export default function PropertyDetail() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { id } = useParams();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3;

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [data, setData] = useState(null);
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  const [detailMode, setDetailMode] = useState(
    location.pathname.startsWith("/explore/properties/") ? "explore" : "owned"
  );

  useEffect(() => {
    let cancelled = false;

    async function loadDetail() {
      setLoading(true);
      setErr("");

      try {
        let payload = null;
        let mode = "owned";

        try {
          const ownedRes = await api.get(`/properties/${id}`);
          payload = unwrap(ownedRes);
          mode = "owned";
          console.log("OWNED PROPERTY DETAIL", payload);
        } catch (ownedError) {
          console.log("OWNED PROPERTY DETAIL ERROR", ownedError);

          const exploreRes = await api.get(`/explore/properties/${id}`);
          payload = unwrap(exploreRes);
          mode = "explore";
          console.log("EXPLORE PROPERTY DETAIL", payload);
        }

        if (cancelled) return;

        setData(payload);
        setDetailMode(mode);
        setActiveImageIndex(0);
      } catch (e) {
        if (cancelled) return;
        console.log("PROPERTY DETAIL FINAL ERROR", e);
        setErr(getErrorMessage(e, "No se pudo cargar la publicación"));
        setData(null);
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

  const property = extractProperty(data);
  const images = extractImages(data, property);
  const requirements = extractRequirements(data, property);
  const requirementTypes = extractRequirementTypes(data, requirements);
  const requirementLocations = extractRequirementLocations(data, requirements);
  const amenities = extractAmenities(data, property);

  console.log("PROPERTY DETAIL NORMALIZED", {
    raw: data,
    property,
    images,
    requirements,
    requirementTypes,
    requirementLocations,
    amenities,
    detailMode,
  });

  const meta = statusMeta(property?.status);
  const gallery = getPropertyImages(images);
  const mainImage = gallery[activeImageIndex] || gallery[0] || null;

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

  const summarySpecs = useMemo(
    () => [
      {
        label: "Sup. total",
        value: property?.total_area ? `${property.total_area} m²` : "—",
      },
      {
        label: "Sup. cubierta",
        value: property?.covered_area ? `${property.covered_area} m²` : "—",
      },
      {
        label: "Dormitorios",
        value: property?.bedrooms || "—",
      },
      {
        label: "Baños",
        value: property?.bathrooms || "—",
      },
      {
        label: "Garage",
        value: property?.garages || "—",
      },
      {
        label: "Antigüedad",
        value: property?.antiquity ? `${property.antiquity} años` : "—",
      },
    ],
    [property]
  );

  const acceptedModes = requirements
    ? [
        requirements.accepts_open_proposals ? "Abierto a propuestas" : null,
        requirements.accepts_total_swap ? "Permuta total" : null,
        requirements.accepts_swap_plus_cash ? "Permuta + diferencia" : null,
        requirements.accepts_multiple_swap ? "Permuta múltiple" : null,
        requirements.accepts_cash_only ? "Acepta dinero" : null,
      ].filter(Boolean)
    : [];

  const hasRequirementNotes = !!String(requirements?.notes || "").trim();

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200/70 bg-white px-4 py-6 sm:px-6 lg:px-10">
        <div className="mx-auto max-w-7xl">
          <button
            type="button"
            onClick={handleBack}
            className="inline-flex items-center gap-2 text-primary hover:text-emerald-700 transition-colors font-semibold text-sm w-fit"
          >
            <Icon name="arrowLeft" size={16} />
            Volver
          </button>
        </div>
      </header>

      <section className="bg-slate-900 px-4 py-12 sm:px-6 lg:px-10">
        <div className="mx-auto max-w-7xl">
          <p className="text-xs uppercase tracking-widest text-emerald-300 mb-3 font-semibold">
            Ref #{property?.id || id}
          </p>

          <h1 className="text-3xl md:text-5xl font-black text-white leading-tight tracking-tighter max-w-4xl">
            {property?.title || "Sin título"}
          </h1>
        </div>
      </section>

      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
        {err && (
          <div className="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            {err}
          </div>
        )}

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
              <div className="space-y-4 lg:col-span-8">
                <div className="relative aspect-[16/9] w-full overflow-hidden rounded-2xl bg-slate-200">
                  {mainImage ? (
                    <img
                      src={mainImage.url}
                      alt={property?.title || "Propiedad"}
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <div className="flex h-full items-center justify-center text-slate-400">
                      Sin imágenes
                    </div>
                  )}

                  {requirements && (
                    <div className="absolute right-4 top-4 rounded-full border border-white/20 bg-white/70 px-4 py-2 backdrop-blur-md">
                      <div className="flex items-center gap-2">
                        <Icon
                          name="syncAlt"
                          size={16}
                          className="text-emerald-700"
                        />
                        <span className="text-xs font-bold uppercase tracking-wider text-slate-900">
                          {exchangeModeLabel(requirements)}
                        </span>
                      </div>
                    </div>
                  )}
                </div>

                {gallery.length > 1 && (
                  <div className="grid grid-cols-4 gap-4">
                    {gallery.slice(0, 4).map((image, index) => {
                      const extraCount = gallery.length - 4;
                      const isLastVisible = index === 3 && extraCount > 0;

                      return (
                        <button
                          key={image.id || index}
                          type="button"
                          onClick={() => setActiveImageIndex(index)}
                          className="relative aspect-square overflow-hidden rounded-xl bg-slate-200"
                        >
                          <img
                            src={image.url}
                            alt={`Imagen ${index + 1}`}
                            className="h-full w-full object-cover transition-transform duration-500 hover:scale-105"
                          />

                          {isLastVisible && (
                            <div className="absolute inset-0 flex items-center justify-center bg-slate-900/45 backdrop-blur-[2px]">
                              <span className="text-lg font-bold text-white">
                                +{extraCount}
                              </span>
                            </div>
                          )}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>

              <div className="space-y-6 lg:col-span-4">
                <div className="rounded-2xl bg-white p-8 shadow-sm">
                  <div className="space-y-1">
                    <span className="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
                      Valor estimado
                    </span>
                    <div className="text-4xl font-black tracking-tighter text-slate-900">
                      {formatMoney(property?.price, property?.currency || "USD")}
                    </div>
                  </div>

                  <div className="pt-6 space-y-4">
                    <div className="flex items-start gap-3">
                      <div className="rounded-lg bg-slate-100 p-2">
                        <Icon
                          name="building2"
                          size={18}
                          className="text-slate-900"
                        />
                      </div>
                      <div>
                        <p className="text-xs font-medium text-slate-500">
                          Categoría
                        </p>
                        <p className="font-semibold text-slate-900">
                          {propertyTypeLabel(property?.property_type)}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-start gap-3">
                      <div className="rounded-lg bg-slate-100 p-2">
                        <Icon
                          name="mapPin"
                          size={18}
                          className="text-slate-900"
                        />
                      </div>
                      <div>
                        <p className="text-xs font-medium text-slate-500">
                          Ubicación
                        </p>
                        <p className="font-semibold text-slate-900">
                          {locationLabel || "—"}
                        </p>
                      </div>
                    </div>
                  </div>

                  <button
                    type="button"
                    className="mt-6 w-full rounded-xl bg-slate-900 py-4 text-sm font-bold tracking-tight text-white shadow-lg transition-all hover:opacity-90"
                  >
                    {detailMode === "explore"
                      ? "Iniciar propuesta de permuta"
                      : "Ver oportunidad de intercambio"}
                  </button>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-slate-100 p-6">
                  <h3 className="mb-4 text-sm font-bold uppercase tracking-widest text-slate-900">
                    Ficha técnica
                  </h3>

                  <div className="grid grid-cols-2 gap-x-2 gap-y-4">
                    {summarySpecs.map((item) => (
                      <div key={item.label} className="space-y-1">
                        <p className="text-[10px] font-bold uppercase tracking-tight text-slate-500">
                          {item.label}
                        </p>
                        <p className="text-lg font-black text-slate-900">
                          {item.value}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>

            <div className="mt-12 grid grid-cols-1 gap-12 lg:grid-cols-12">
              <div className="space-y-8 lg:col-span-7">
                <section>
                  <h2 className="mb-6 text-2xl font-black tracking-tight text-slate-900">
                    Descripción del inmueble
                  </h2>
                  <p className="text-lg leading-relaxed text-slate-600">
                    {property?.description || "—"}
                  </p>
                </section>

                <div className="h-px bg-slate-200" />

                {!!amenities.length && (
                  <section className="grid grid-cols-2 gap-6 md:grid-cols-3">
                    {amenities.map((amenity, index) => (
                      <AmenityItem
                        key={`${normalizeAmenityLabel(amenity)}-${index}`}
                      >
                        {normalizeAmenityLabel(amenity)}
                      </AmenityItem>
                    ))}
                  </section>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <h3 className="mb-6 text-xl font-black tracking-tight text-slate-900">
                    Información general
                  </h3>

                  <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <InfoItem label="País" value={property?.country} />
                    <InfoItem label="Provincia" value={property?.province} />
                    <InfoItem label="Ciudad" value={property?.city} />
                    <InfoItem label="Zona" value={property?.zone} />
                    <InfoItem label="Dirección" value={property?.address} />
                    <InfoItem label="Estado" value={meta.label} />
                    <InfoItem
                      label="Publicada"
                      value={formatDate(property?.published_at)}
                    />
                    <InfoItem
                      label="Última modificación"
                      value={formatDate(property?.updated_at)}
                    />
                  </div>
                </section>
              </div>

              <div className="lg:col-span-5">
                <div className="relative overflow-hidden rounded-2xl bg-slate-900 p-8 text-white shadow-2xl">
                  <div className="relative z-10 space-y-8">
                    <div className="flex items-center justify-between">
                      <h2 className="text-xl font-bold tracking-tight">
                        Criterios de Permuta
                      </h2>
                      <Icon
                        name="syncAlt"
                        size={28}
                        className="text-emerald-300"
                      />
                    </div>

                    {!requirements ? (
                      <div className="text-sm text-slate-300">
                        No hay criterios cargados.
                      </div>
                    ) : (
                      <>
                        <div className="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur-md">
                          <p className="mb-1 text-[10px] font-bold uppercase tracking-widest text-white/60">
                            Modo de intercambio
                          </p>
                          <p className="text-lg font-bold text-white">
                            {exchangeModeLabel(requirements)}
                          </p>
                        </div>

                        {acceptedModes.length > 0 && (
                          <div className="space-y-4">
                            <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                              Modalidades aceptadas
                            </p>

                            <div className="flex flex-wrap gap-2">
                              {acceptedModes.map((mode) => (
                                <SoftTag key={mode} dark>
                                  {mode}
                                </SoftTag>
                              ))}
                            </div>
                          </div>
                        )}

                        {(requirementTypes.length > 0 ||
                          requirementLocations.length > 0) && (
                          <div className="space-y-4">
                            <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                              Interés específico
                            </p>

                            <div className="flex flex-wrap gap-2">
                              {requirementTypes.length > 0 ? (
                                <SoftTag dark>
                                  Tipos deseados:{" "}
                                  {requirementTypes
                                    .map((type) =>
                                      propertyTypeLabel(
                                        normalizeRequirementType(type)
                                      )
                                    )
                                    .join(", ")}
                                </SoftTag>
                              ) : null}

                              {requirementLocations.length > 0 ? (
                                <SoftTag dark>
                                  Ubicaciones deseadas:{" "}
                                  {requirementLocations
                                    .map((loc) =>
                                      joinLocation([
                                        loc?.city,
                                        loc?.zone,
                                        loc?.province,
                                        loc?.country,
                                      ])
                                    )
                                    .filter(Boolean)
                                    .join(" · ")}
                                </SoftTag>
                              ) : null}
                            </div>
                          </div>
                        )}

                        {hasRequirementNotes && (
                          <div className="border-t border-white/10 pt-4">
                            <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                              Notas del propietario
                            </p>
                            <p className="text-sm italic text-white/70">
                              {requirements.notes}
                            </p>
                          </div>
                        )}
                      </>
                    )}
                  </div>

                  <div className="absolute -bottom-20 -right-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-[80px]" />
                </div>
              </div>
            </div>
          </>
        )}
      </main>
    </div>
  );
}