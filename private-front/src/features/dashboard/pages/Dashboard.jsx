import { useEffect, useMemo, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { Icon } from "../../../ui/icons/Index";
import { getErrorMessage } from "../../../api/http";
import SearchRequestCard from "../../search-requests/components/SearchRequestCard";
import PropertyCard from "../../properties/components/PropertyCard";
import DevelopmentCard from "../../developments/components/DevelopmentCard";
import { getExplore } from "../../explore/api/explore.api";

function HeroCard({
  propertiesCount = 0,
  searchesCount = 0,
  developmentsCount = 0,
  onExploreProperties,
  onExploreSearches,
  onExploreDevelopments,
}) {
  return (
    <section className="rounded-[28px] border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
      <div className="grid grid-cols-1 xl:grid-cols-[1.3fr_0.9fr] gap-8 items-center">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">
            Inicio
          </p>

          <h1 className="mt-3 text-2xl sm:text-2xl lg:text-4xl font-black tracking-tight text-slate-900 leading-tight">
            Explorá publicaciones, búsquedas y desarrollos activos en la red.
          </h1>

          <p className="mt-4 max-w-2xl text-base sm:text-lg text-slate-500 leading-relaxed">
            Descubrí propiedades disponibles para intercambio, oportunidades
            abiertas, pedidos de búsqueda y desarrollos cargados por otras
            inmobiliarias.
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

            <button
              type="button"
              onClick={onExploreDevelopments}
              className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
            >
              <Icon name="layoutGrid" size={18} />
              Ver desarrollos
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 xl:grid-cols-1 gap-4">
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
          <MiniStat
            label="Desarrollos activos"
            value={developmentsCount}
            icon="layoutGrid"
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
    { key: "developments", label: "Desarrollos" },
  ];

  return (
    <div className="w-full lg:w-auto">
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {tabs.map((tab) => {
          const active = value === tab.key;

          return (
            <button
              key={tab.key}
              type="button"
              onClick={() => onChange(tab.key)}
              className={`rounded-xl px-4 py-2.5 text-xs font-bold transition sm:text-sm ${
                active
                  ? "bg-slate-900 text-white"
                  : "border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
              }`}
            >
              {tab.label}
            </button>
          );
        })}
      </div>
    </div>
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
  const location = useLocation();
  const [segment, setSegment] = useState("all");

  const [properties, setProperties] = useState([]);
  const [searches, setSearches] = useState([]);
  const [developments, setDevelopments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const currentPathWithSearch = `${location.pathname}${location.search || ""}`;

  useEffect(() => {
    let cancelled = false;

    async function loadDashboardData() {
      try {
        setLoading(true);
        setError("");

        const data = await getExplore({
          opportunity_type: "all",
          page: 1,
          limit: 18,
        });

        const results = Array.isArray(data?.items) ? data.items : [];

        const propertyItems = results
          .filter((result) => result?.opportunity_type === "property")
          .map((result) => result?.item || result)
          .filter(Boolean);

        const searchItems = results
          .filter((result) => result?.opportunity_type === "search_request")
          .map((result) => result?.item || result)
          .filter(Boolean);

        const developmentItems = results
          .filter((result) => result?.opportunity_type === "development")
          .map((result) => result?.item || result)
          .filter(Boolean);

        if (cancelled) return;

        setProperties(propertyItems);
        setSearches(searchItems);
        setDevelopments(developmentItems);
      } catch (err) {
        if (cancelled) return;
        setError(getErrorMessage(err, "No se pudo cargar el inicio."));
        setProperties([]);
        setSearches([]);
        setDevelopments([]);
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
    if (segment === "searches" || segment === "developments") return [];
    return properties.slice(0, 3);
  }, [segment, properties]);

  const visibleSearches = useMemo(() => {
    if (segment === "properties" || segment === "developments") return [];
    return searches.slice(0, 4);
  }, [segment, searches]);

  const visibleDevelopments = useMemo(() => {
    if (segment === "properties" || segment === "searches") return [];
    return developments.slice(0, 3);
  }, [segment, developments]);

  return (
    <main className="mx-auto w-full max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-10">
      <HeroCard
        propertiesCount={properties.length}
        searchesCount={searches.length}
        developmentsCount={developments.length}
        onExploreProperties={() => navigate("/explore/properties")}
        onExploreSearches={() => navigate("/explore/search-requests")}
        onExploreDevelopments={() => navigate("/explore/developments")}
      />

      <section className="mt-6 flex flex-col gap-4 sm:mt-8 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-bold text-slate-900 sm:text-2xl">
            Explorar oportunidades
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            Descubrí publicaciones destacadas, búsquedas activas y desarrollos
            dentro de la red.
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
                onAction={() => navigate("/explore/properties")}
              />

              <div className="space-y-4">
                {visibleProperties.map((item) => (
                  <PropertyCard
                    key={item.id}
                    item={item}
                    variant="dashboard"
                    detailHref={`/explore/properties/${item.id}`}
                    detailState={{ from: currentPathWithSearch }}
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
                onAction={() => navigate("/explore/search-requests")}
              />

              <div className="space-y-4">
                {visibleSearches.map((item) => (
                  <SearchRequestCard
                    key={item.id}
                    item={item}
                    variant="dashboard"
                    onView={() =>
                      navigate(`/explore/search-requests/${item.id}`, {
                        state: { from: currentPathWithSearch },
                      })
                    }
                  />
                ))}
              </div>
            </section>
          )}

          {visibleDevelopments.length > 0 && (
            <section className="mt-10">
              <SectionHeader
                title="Desarrollos destacados"
                description="Proyectos activos cargados por otras inmobiliarias."
                actionLabel="Ver desarrollos"
                onAction={() => navigate("/explore/developments")}
              />

              <div className="space-y-4">
                {visibleDevelopments.map((item) => (
                  <DevelopmentCard
                    key={item.id}
                    item={item}
                    variant="dashboard"
                    detailHref={`/explore/developments/${item.id}`}
                    detailState={{ from: currentPathWithSearch }}
                  />
                ))}
              </div>
            </section>
          )}

          {!visibleProperties.length &&
            !visibleSearches.length &&
            !visibleDevelopments.length && (
              <section className="mt-8">
                <EmptyState
                  title="Todavía no hay oportunidades para mostrar"
                  description="Cuando existan publicaciones, búsquedas o desarrollos activos dentro de la red, los vas a ver acá."
                />
              </section>
            )}
        </>
      )}
    </main>
  );
}
