import { useEffect, useMemo, useState } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
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
  };

  return map[value] || value || "—";
}

function exchangeModeLabel(requirements) {
  if (!requirements) return "Sin criterios cargados";
  if (requirements.criteria_mode === "criteria") return "Busco con criterios";
  return "Escucho propuestas";
}

function yesNo(value) {
  return Number(value) === 1 ? "Sí" : "No";
}

function joinLocation(parts) {
  return parts.filter(Boolean).join(", ");
}

function getPropertyImages(images) {
  return images
    .map((image) => {
      const url = image?.view_url
        ? `${getApiBaseUrl()}${image.view_url}`
        : image?.url || null;

      return {
        ...image,
        url,
      };
    })
    .filter((image) => !!image.url);
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
      <p className={`text-sm font-semibold ${dark ? "text-white" : "text-slate-900"}`}>
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

export default function PropertyDetail() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const { id } = useParams();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3;

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [data, setData] = useState(null);
  const [activeImageIndex, setActiveImageIndex] = useState(0);

  async function loadDetail() {
    setLoading(true);
    setErr("");

    try {
      const res = await api.get(`/properties/${id}`);
      const payload = unwrap(res);
      setData(payload);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo cargar la publicación"));
      setData(null);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadDetail();
  }, [id]);

  if (!canAccess) return <Navigate to="/" replace />;

  const property = data?.property || null;
  const images = Array.isArray(data?.images) ? data.images : [];
  const requirements = data?.requirements || null;
  const requirementTypes = Array.isArray(data?.requirement_property_types)
    ? data.requirement_property_types
    : [];
  const requirementLocations = Array.isArray(data?.requirement_locations)
    ? data.requirement_locations
    : [];
  const amenities = Array.isArray(data?.amenities) ? data.amenities : [];

  const meta = statusMeta(property?.status);
  const gallery = getPropertyImages(images);
  const mainImage = gallery[activeImageIndex] || gallery[0] || null;

  const locationLabel = joinLocation([
    property?.city,
    property?.zone,
    property?.province,
    property?.country,
  ]);

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

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="sticky top-0 z-40 border-b border-white/60 bg-white/70 px-4 py-4 backdrop-blur-md sm:px-6 lg:px-10">
        <div className="mx-auto flex max-w-7xl flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-4 min-w-0">
            <button
              type="button"
              onClick={() => navigate("/properties")}
              className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full hover:bg-slate-100 transition-colors"
            >
              <Icon name="arrowLeft" size={18} className="text-slate-700" />
            </button>

            <div className="min-w-0">
              <h1 className="truncate text-xl font-black tracking-tight text-slate-900 md:text-2xl">
                {property?.title || "Detalle de publicación"}
              </h1>

              <div className="mt-1 flex flex-wrap items-center gap-2">
                {property?.status && (
                  <span
                    className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${meta.badgeClass}`}
                  >
                    {meta.label}
                  </span>
                )}

                <span className="text-xs font-medium text-slate-500">
                  Ref: #{property?.id || id}
                </span>
              </div>
            </div>
          </div>

          <div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            permuok.com
          </div>
        </div>
      </header>

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
                        <Icon name="syncAlt" size={16} className="text-emerald-700" />
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
                        <Icon name="building2" size={18} className="text-slate-900" />
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
                        <Icon name="mapPin" size={18} className="text-slate-900" />
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
                    Iniciar propuesta de permuta
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
                      <AmenityItem key={`${amenity.code || amenity}-${index}`}>
                        {amenity.label || amenity.code || amenity}
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
                    <InfoItem label="Creada" value={formatDate(property?.created_at)} />
                    <InfoItem
                      label="Publicada"
                      value={formatDate(property?.published_at)}
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
                      <Icon name="syncAlt" size={28} className="text-emerald-300" />
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

                        <div className="space-y-4">
                          <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                            Modalidades aceptadas
                          </p>

                          <div className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                              <span className="text-white/80">Abierto a propuestas</span>
                              <span
                                className={`font-bold ${
                                  Number(requirements.accepts_open_proposals) === 1
                                    ? "text-emerald-300"
                                    : "text-white/40"
                                }`}
                              >
                                {yesNo(requirements.accepts_open_proposals)}
                              </span>
                            </div>

                            <div className="flex items-center justify-between">
                              <span className="text-white/80">Permuta total</span>
                              <span
                                className={`font-bold ${
                                  Number(requirements.accepts_total_swap) === 1
                                    ? "text-emerald-300"
                                    : "text-white/40"
                                }`}
                              >
                                {yesNo(requirements.accepts_total_swap)}
                              </span>
                            </div>

                            <div className="flex items-center justify-between">
                              <span className="text-white/80">Permuta + diferencia</span>
                              <span
                                className={`font-bold ${
                                  Number(requirements.accepts_swap_plus_cash) === 1
                                    ? "text-emerald-300"
                                    : "text-white/40"
                                }`}
                              >
                                {yesNo(requirements.accepts_swap_plus_cash)}
                              </span>
                            </div>

                            <div className="flex items-center justify-between">
                              <span className="text-white/80">Permuta múltiple</span>
                              <span
                                className={`font-bold ${
                                  Number(requirements.accepts_multiple_swap) === 1
                                    ? "text-emerald-300"
                                    : "text-white/40"
                                }`}
                              >
                                {yesNo(requirements.accepts_multiple_swap)}
                              </span>
                            </div>

                            <div className="flex items-center justify-between">
                              <span className="text-white/80">Acepta dinero</span>
                              <span
                                className={`font-bold ${
                                  Number(requirements.accepts_cash_only) === 1
                                    ? "text-emerald-300"
                                    : "text-white/40"
                                }`}
                              >
                                {yesNo(requirements.accepts_cash_only)}
                              </span>
                            </div>
                          </div>
                        </div>

                        {(requirementTypes.length > 0 || requirementLocations.length > 0) && (
                          <div className="space-y-4">
                            <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                              Interés específico
                            </p>

                            <div className="flex flex-wrap gap-2">
                              {requirementTypes.length > 0 ? (
                                <SoftTag dark>
                                  Tipos deseados:{" "}
                                  {requirementTypes
                                    .map((type) => propertyTypeLabel(type))
                                    .join(", ")}
                                </SoftTag>
                              ) : (
                                <SoftTag dark>Tipos deseados: Sin tipos específicos</SoftTag>
                              )}

                              {requirementLocations.length > 0 ? (
                                <SoftTag dark>
                                  Ubicaciones deseadas:{" "}
                                  {requirementLocations
                                    .map((loc) =>
                                      joinLocation([
                                        loc.city,
                                        loc.zone,
                                        loc.province,
                                        loc.country,
                                      ])
                                    )
                                    .filter(Boolean)
                                    .join(" · ")}
                                </SoftTag>
                              ) : (
                                <SoftTag dark>
                                  Ubicaciones deseadas: Sin ubicaciones específicas
                                </SoftTag>
                              )}
                            </div>
                          </div>
                        )}

                        <div className="border-t border-white/10 pt-4">
                          <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                            Notas del propietario
                          </p>
                          <p className="text-sm italic text-white/70">
                            {requirements.notes || "—"}
                          </p>
                        </div>
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

      <footer className="mt-20 border-t border-slate-200 px-6 py-12 text-center">
        <p className="text-xs font-medium uppercase tracking-widest text-slate-400">
          permuok.com © 2026 — Intercambios inmobiliarios
        </p>
      </footer>
    </div>
  );
}