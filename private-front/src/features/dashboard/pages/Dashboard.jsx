import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Icon } from "../../../ui/icons/Index";
import { api, getApiBaseUrl, getErrorMessage, unwrap } from "../../../api/http";
import { listSearchRequests } from "../../search-requests/api/searchRequests.api";

function HeroCard({
  propertiesCount = 0,
  searchesCount = 0,
  onExploreProperties,
  onExploreSearches,
}) {
  return (
    <section className="rounded-[28px] border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
      <div className="grid grid-cols-1 xl:grid-cols-[1.3fr_0.9fr] gap-8 items-center">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">
            Inicio
          </p>

          <h1 className="mt-3 text-2xl sm:text-2xl lg:text-4xl font-black tracking-tight text-slate-900 leading-tight">
            Explorá publicaciones y búsquedas activas en la red.
          </h1>

          <p className="mt-4 max-w-2xl text-base sm:text-lg text-slate-500 leading-relaxed">
            Descubrí propiedades disponibles para intercambio, oportunidades
            abiertas y pedidos de búsqueda cargados por otras inmobiliarias.
          </p>

          <div className="mt-6 flex flex-wrap gap-3">
            <button
              type="button"
              onClick={onExploreProperties}
              className="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-white hover:opacity-90"
            >
              <Icon name="building2" size={18} />
              Explorar publicaciones
            </button>

            <button
              type="button"
              onClick={onExploreSearches}
              className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
            >
              <Icon name="search" size={18} />
              Ver búsquedas activas
            </button>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <MiniStat
            label="Publicaciones activas"
            value={propertiesCount}
            icon="building2"
          />
          <MiniStat
            label="Búsquedas activas"
            value={searchesCount}
            icon="search"
          />
        </div>
      </div>
    </section>
  );
}

function MiniStat({ label, value, icon }) {
  return (
    <article className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-xs font-medium text-slate-500">{label}</p>
          <p className="mt-2 text-2xl font-black text-slate-900">{value}</p>
        </div>

        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-700 border border-slate-200">
          <Icon name={icon} size={18} />
        </div>
      </div>
    </article>
  );
}

function SectionHeader({ title, description, actionLabel, onAction }) {
  return (
    <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 className="text-2xl font-bold text-slate-900">{title}</h2>
        {description ? (
          <p className="mt-1 text-sm text-slate-500">{description}</p>
        ) : null}
      </div>

      {actionLabel ? (
        <button
          type="button"
          onClick={onAction}
          className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
        >
          {actionLabel}
          <Icon name="arrowRight" size={16} />
        </button>
      ) : null}
    </div>
  );
}

function SegmentTabs({ value, onChange }) {
  const tabs = [
    { key: "all", label: "Todo" },
    { key: "properties", label: "Publicaciones" },
    { key: "searches", label: "Búsquedas" },
  ];

  return (
    <div className="inline-flex rounded-2xl border border-slate-200 bg-white p-1">
      {tabs.map((tab) => {
        const active = value === tab.key;

        return (
          <button
            key={tab.key}
            type="button"
            onClick={() => onChange(tab.key)}
            className={`rounded-xl px-4 py-2 text-sm font-bold transition ${
              active
                ? "bg-slate-900 text-white"
                : "text-slate-600 hover:bg-slate-50"
            }`}
          >
            {tab.label}
          </button>
        );
      })}
    </div>
  );
}

function PropertyBadge({ text, variant = "default" }) {
  const styles = {
    default: "bg-slate-900/75 text-white",
    success: "bg-emerald-500 text-white",
    info: "bg-blue-500 text-white",
  };

  return (
    <span
      className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider shadow-sm ${styles[variant]}`}
    >
      {text}
    </span>
  );
}

function formatPropertyPrice(item) {
  const currency = item?.currency || "USD";
  const value = item?.price ?? item?.value ?? item?.total_value ?? null;

  if (value === null || value === undefined || value === "") {
    return "Consultar";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value));
}

function formatPropertyLocation(item) {
  return [item?.city, item?.zone, item?.province].filter(Boolean).join(", ");
}

function formatPropertyMode(item) {
  const labels = [];

  if (item?.exchange_type) {
    return String(item.exchange_type);
  }

  if (item?.accepts_total_swap) labels.push("Permuta directa");
  if (item?.accepts_swap_plus_cash) labels.push("Permuta + dinero");
  if (item?.accepts_cash_only) labels.push("Solo dinero");

  if (!labels.length) return "Disponible";
  return labels[0];
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

function getPropertyCardImage(item) {
  const rawImage =
    item?.cover_image_url ||
    item?.cover_url ||
    item?.main_image ||
    item?.thumbnail ||
    item?.image_url ||
    item?.view_url ||
    item?.web_path ||
    item?.url ||
    item?.path ||
    item?.file_path ||
    item?.archive_path ||
    null;

  return resolveImageUrl(rawImage);
}

function FeaturedPropertyCard({ item, onClick }) {
  const location = formatPropertyLocation(item);
  const price = formatPropertyPrice(item);
  const mode = formatPropertyMode(item);

  const image =
    getPropertyCardImage(item) ||
    "https://images.unsplash.com/photo-1568605114967-8130f3a36994?q=80&w=1200&auto=format&fit=crop";

  return (
    <button
      type="button"
      onClick={onClick}
      className="group overflow-hidden rounded-3xl border border-slate-200 bg-white text-left shadow-sm transition hover:shadow-lg"
    >
      <div className="relative h-64 overflow-hidden">
        <img
          src={image}
          alt={item?.title || "Propiedad"}
          className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
        />

        <div className="absolute left-4 top-4 flex items-center gap-2">
          <PropertyBadge text={mode} variant="success" />
          {item?.status === "published" ? (
            <PropertyBadge text="Activa" variant="default" />
          ) : null}
        </div>

        <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/75 to-transparent p-4">
          <p className="flex items-center gap-1 text-sm font-medium text-white">
            <Icon name="mapPin" size={14} />
            {location || "Ubicación no definida"}
          </p>
        </div>
      </div>

      <div className="p-5">
        <div className="flex items-start justify-between gap-4">
          <h3 className="line-clamp-1 text-lg font-bold text-slate-900">
            {item?.title || "Sin título"}
          </h3>
          <p className="whitespace-nowrap text-lg font-black text-emerald-600">
            {price}
          </p>
        </div>

        <p className="mt-3 text-sm text-slate-500 line-clamp-2">
          {item?.description || "Propiedad disponible dentro de la red."}
        </p>
      </div>
    </button>
  );
}

function SearchOpportunityCard({ item, onClick }) {
  const location = [item?.city, item?.zone, item?.province]
    .filter(Boolean)
    .join(", ");

  const type =
    Array.isArray(item?.property_types) && item.property_types.length
      ? item.property_types.join(", ")
      : item?.property_types || "Sin tipo definido";

  const payment =
    item?.payment_mode_cash && item?.payment_mode_swap
      ? "Dinero + permuta"
      : item?.payment_mode_cash
      ? "Solo dinero"
      : item?.payment_mode_swap
      ? "Solo permuta"
      : "Sin modalidad definida";

  const currency = item?.currency || "USD";

  const formatMoney = (value) =>
    new Intl.NumberFormat("es-AR", {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    }).format(Number(value));

  let budget = "Sin referencia definida";
  if (item?.min_value && item?.max_value) {
    budget = `${formatMoney(item.min_value)} - ${formatMoney(item.max_value)}`;
  } else if (item?.min_value) {
    budget = `Desde ${formatMoney(item.min_value)}`;
  } else if (item?.max_value) {
    budget = `Hasta ${formatMoney(item.max_value)}`;
  }

  return (
    <button
      type="button"
      onClick={onClick}
      className="w-full rounded-3xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:shadow-md"
    >
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)_auto]">
        <div className="min-w-0">
          <h3 className="text-lg font-bold text-slate-900 line-clamp-2">
            {item?.title || "Sin título"}
          </h3>
          <p className="mt-1 text-sm text-slate-500 line-clamp-1">
            {location || "Ubicación no definida"}
          </p>
        </div>

        <div className="min-w-0 lg:border-l lg:border-slate-200 lg:pl-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
            Tipo / modalidad
          </p>
          <p className="mt-1 text-sm font-semibold text-slate-800 line-clamp-1">
            {type}
          </p>
          <p className="mt-1 text-xs text-slate-500 line-clamp-1">
            {payment}
          </p>
        </div>

        <div className="lg:text-right">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
            Rango de valor
          </p>
          <p className="mt-1 text-base font-extrabold text-primary">
            {budget}
          </p>
        </div>
      </div>
    </button>
  );
}

function EmptyState({ title, description }) {
  return (
    <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
      <p className="text-base font-bold text-slate-900">{title}</p>
      <p className="mt-2 text-sm text-slate-500">{description}</p>
    </div>
  );
}

export default function Dashboard() {
  const navigate = useNavigate();
  const [segment, setSegment] = useState("all");

  const [properties, setProperties] = useState([]);
  const [searches, setSearches] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;

    async function loadDashboardData() {
      try {
        setLoading(true);
        setError("");

        const [propertiesRes, searchesRes] = await Promise.all([
          api.get("/properties", {
            params: {
              page: 1,
              limit: 6,
              status: "published",
            },
          }),
          listSearchRequests({
            page: 1,
            limit: 6,
            status: "published",
          }),
        ]);

        const propertiesData = unwrap(propertiesRes);
        const propertyItems = Array.isArray(propertiesData?.items)
          ? propertiesData.items
          : Array.isArray(propertiesData?.properties)
          ? propertiesData.properties
          : [];

        const searchItems = Array.isArray(searchesRes?.items)
          ? searchesRes.items
          : [];

        if (cancelled) return;

        setProperties(propertyItems);
        setSearches(searchItems);
      } catch (err) {
        if (cancelled) return;
        setError(getErrorMessage(err, "No se pudo cargar el inicio."));
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadDashboardData();

    return () => {
      cancelled = true;
    };
  }, []);

  const visibleProperties = useMemo(() => {
    if (segment === "searches") return [];
    return properties.slice(0, 3);
  }, [segment, properties]);

  const visibleSearches = useMemo(() => {
    if (segment === "properties") return [];
    return searches.slice(0, 4);
  }, [segment, searches]);

  return (
    <main className="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
      <HeroCard
        propertiesCount={properties.length}
        searchesCount={searches.length}
        onExploreProperties={() => navigate("/properties")}
        onExploreSearches={() => navigate("/search-requests")}
      />

      <section className="mt-8 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900">
            Explorar oportunidades
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            Descubrí publicaciones destacadas y búsquedas activas dentro de la red.
          </p>
        </div>

        <SegmentTabs value={segment} onChange={setSegment} />
      </section>

      {error && (
        <div className="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      )}

      {loading ? (
        <div className="mt-6 rounded-2xl border border-slate-200 bg-white px-5 py-8 text-sm text-slate-500 shadow-sm">
          Cargando inicio...
        </div>
      ) : (
        <>
          {visibleProperties.length > 0 && (
            <section className="mt-8">
              <SectionHeader
                title="Publicaciones destacadas"
                description="Propiedades activas disponibles para explorar."
                actionLabel="Ver publicaciones"
                onAction={() => navigate("/properties")}
              />

              <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                {visibleProperties.map((item) => (
                  <FeaturedPropertyCard
                    key={item.id}
                    item={item}
                    onClick={() => navigate(`/properties/${item.id}`)}
                  />
                ))}
              </div>
            </section>
          )}

          {visibleSearches.length > 0 && (
            <section className="mt-10">
              <SectionHeader
                title="Búsquedas activas"
                description="Pedidos recientes publicados dentro de la red."
                actionLabel="Ver búsquedas"
                onAction={() => navigate("/search-requests")}
              />

              <div className="space-y-4">
                {visibleSearches.map((item) => (
                  <SearchOpportunityCard
                    key={item.id}
                    item={item}
                    onClick={() => navigate(`/search-requests/${item.id}`)}
                  />
                ))}
              </div>
            </section>
          )}

          {!visibleProperties.length && !visibleSearches.length && (
            <section className="mt-8">
              <EmptyState
                title="Todavía no hay oportunidades para mostrar"
                description="Cuando existan publicaciones o búsquedas activas dentro de la red, las vas a ver acá."
              />
            </section>
          )}
        </>
      )}
    </main>
  );
}