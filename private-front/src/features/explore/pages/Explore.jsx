import { useEffect, useMemo, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { getExplore } from "../api/explore.api";

import { Icon } from "../../../ui/icons/Index";
import PropertyCard from "../../properties/components/PropertyCard";
import SearchRequestCard from "../../search-requests/components/SearchRequestCard";
import DevelopmentCard from "../../developments/components/DevelopmentCard";
import { AMENITIES } from "../../shared/helpers/amenities";

const PROPERTY_TYPES = [
  { value: "", label: "Tipo de propiedad" },
  { value: "apartment", label: "Departamento" },
  { value: "house", label: "Casa" },
  { value: "land", label: "Lote" },
  { value: "commercial", label: "Local" },
  { value: "office", label: "Oficina" },
  { value: "warehouse", label: "Depósito" },
  { value: "garage", label: "Cochera" },
  { value: "other", label: "Otro" },
];

const EXCHANGE_MODES = [
  { value: "total_swap", label: "Permuta total" },
  { value: "swap_plus_cash", label: "Permuta + diferencia" },
  { value: "multiple_swap", label: "Múltiples propiedades" },
  { value: "open_proposals", label: "Escucha propuestas" },
  { value: "cash", label: "Efectivo" },
];

const DEVELOPMENT_STAGES = [
  { value: "", label: "Etapa del desarrollo" },
  { value: "land", label: "Lote / tierra" },
  { value: "prelaunch", label: "Prelanzamiento" },
  { value: "launch", label: "Lanzamiento" },
  { value: "presale", label: "Preventa" },
  { value: "under_construction", label: "En construcción" },
  { value: "finished", label: "Finalizado" },
];

export default function Explore({ defaultType = "all" }) {
  const navigate = useNavigate();
  const location = useLocation();

  const currentPathWithSearch = `${location.pathname}${location.search || ""}`;

  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [showAdvanced, setShowAdvanced] = useState(false);

  const [filters, setFilters] = useState({
    type: defaultType,
    q: "",
    property_type: "",
    value_min: "",
    value_max: "",
    currency: "USD",
    bedrooms_min: "",
    bathrooms_min: "",
    garages_min: "",
    area_min: "",
    exchange_modes: [],
    amenities: [],
    development_stage: "",
    sort: "recent",
    page: 1,
    limit: 12,
  });

  useEffect(() => {
    setFilters((prev) => ({
      ...prev,
      type: defaultType || "all",
      page: 1,
    }));
  }, [defaultType]);

  useEffect(() => {
    let cancelled = false;

    async function loadData() {
      try {
        setLoading(true);
        setError("");

        const res = await getExplore(filters);

        if (cancelled) return;

        setItems(Array.isArray(res?.items) ? res.items : []);
      } catch (err) {
        if (cancelled) return;

        setItems([]);
        setError(err?.message || "No se pudieron cargar los resultados.");
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadData();

    return () => {
      cancelled = true;
    };
  }, [filters]);

  const activeFiltersCount = useMemo(() => {
    let count = 0;

    if (filters.q) count++;
    if (filters.property_type) count++;
    if (filters.value_min) count++;
    if (filters.value_max) count++;
    if (filters.bedrooms_min) count++;
    if (filters.bathrooms_min) count++;
    if (filters.garages_min) count++;
    if (filters.area_min) count++;
    if (filters.development_stage) count++;
    if (filters.exchange_modes.length) count++;
    if (filters.amenities.length) count++;

    return count;
  }, [filters]);

  function setField(field, value) {
    setFilters((prev) => ({
      ...prev,
      [field]: value,
      page: field === "page" ? value : 1,
    }));
  }

  function toggleArrayField(field, value) {
    setFilters((prev) => {
      const current = Array.isArray(prev[field]) ? prev[field] : [];
      const exists = current.includes(value);

      return {
        ...prev,
        [field]: exists
          ? current.filter((item) => item !== value)
          : [...current, value],
        page: 1,
      };
    });
  }

  function clearFilters() {
    setFilters({
      type: defaultType || "all",
      q: "",
      property_type: "",
      value_min: "",
      value_max: "",
      currency: "USD",
      bedrooms_min: "",
      bathrooms_min: "",
      garages_min: "",
      area_min: "",
      exchange_modes: [],
      amenities: [],
      development_stage: "",
      sort: "recent",
      page: 1,
      limit: 12,
    });
  }

  function renderCard(result) {
    const type = result?.opportunity_type || result?.type;
    const item = result?.item || result;

    if (type === "property") {
      return (
        <PropertyCard
          key={`property-${item.id}`}
          item={item}
          variant="dashboard"
          detailHref={`/explore/properties/${item.id}`}
          detailState={{ from: currentPathWithSearch }}
        />
      );
    }

    if (type === "search_request") {
      return (
        <SearchRequestCard
          key={`search-${item.id}`}
          item={item}
          variant="dashboard"
          onView={() =>
            navigate(`/explore/search-requests/${item.id}`, {
              state: { from: currentPathWithSearch },
            })
          }
        />
      );
    }

    if (type === "development") {
      const developmentItem = {
        ...item,
        images: result?.images || item?.images || [],
        development_images:
          result?.development_images || item?.development_images || [],
        images_preview: result?.images_preview || item?.images_preview || [],
        cover_image_url:
          result?.cover_image_url ||
          item?.cover_image_url ||
          result?.image_url ||
          item?.image_url ||
          "",
      };

      return (
        <DevelopmentCard
          key={`development-${developmentItem.id}`}
          item={developmentItem}
          variant="dashboard"
          detailHref={`/explore/developments/${developmentItem.id}`}
          detailState={{ from: currentPathWithSearch }}
        />
      );
    }

    return null;
  }

  return (
    <main className="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
      <header className="mb-8">
        <h1 className="text-3xl font-black tracking-tight text-slate-900">
          Explorar oportunidades
        </h1>
        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
          Encontrá publicaciones, búsquedas y desarrollos compatibles dentro de
          la red.
        </p>
      </header>

      <nav className="mb-6 flex flex-wrap gap-3">
        {[
          { value: "all", label: "Todo" },
          { value: "property", label: "Publicaciones" },
          { value: "search_request", label: "Búsquedas" },
          { value: "development", label: "Desarrollos" },
        ].map((item) => {
          const active = filters.type === item.value;

          return (
            <button
              key={item.value}
              type="button"
              onClick={() => setField("type", item.value)}
              className={`rounded-xl px-5 py-2.5 text-sm font-bold transition ${
                active
                  ? "bg-slate-900 text-white"
                  : "bg-white text-slate-600 border border-slate-200 hover:bg-slate-50"
              }`}
            >
              {item.label}
            </button>
          );
        })}
      </nav>

      <section className="mb-8 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-center">
          <div className="relative min-w-[260px] flex-1">
            <Icon
              name="search"
              size={18}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
            />
            <input
              type="text"
              placeholder="Buscar ubicación, palabra clave..."
              value={filters.q}
              onChange={(e) => setField("q", e.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <select
            value={filters.property_type}
            onChange={(e) => setField("property_type", e.target.value)}
            className="min-w-[190px] rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          >
            {PROPERTY_TYPES.map((item) => (
              <option key={item.value || "all"} value={item.value}>
                {item.label}
              </option>
            ))}
          </select>

          <div className="flex rounded-xl bg-white p-1 border border-slate-200">
            {["USD", "ARS"].map((currency) => {
              const active = filters.currency === currency;

              return (
                <button
                  key={currency}
                  type="button"
                  onClick={() => setField("currency", currency)}
                  className={`rounded-lg px-4 py-2 text-xs font-black transition ${
                    active
                      ? "bg-slate-900 text-white"
                      : "text-slate-500 hover:bg-slate-50"
                  }`}
                >
                  {currency}
                </button>
              );
            })}
          </div>

          <div className="grid grid-cols-2 gap-3 xl:w-[260px]">
            <input
              type="number"
              placeholder="Valor mín."
              value={filters.value_min}
              onChange={(e) => setField("value_min", e.target.value)}
              className="no-spinner rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />

            <input
              type="number"
              placeholder="Valor máx."
              value={filters.value_max}
              onChange={(e) => setField("value_max", e.target.value)}
              className="no-spinner rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <button
            type="button"
            onClick={() => setShowAdvanced((prev) => !prev)}
            className={`inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-bold transition ${
              showAdvanced || activeFiltersCount > 0
                ? "border-slate-900 bg-slate-900 text-white"
                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
            }`}
          >
            <Icon name="slidersHorizontal" size={18} />
            Filtros avanzados
            {activeFiltersCount > 0 ? (
              <span className="rounded-full bg-white/20 px-2 py-0.5 text-[11px]">
                {activeFiltersCount}
              </span>
            ) : null}
          </button>

          <div className="flex items-center gap-3 xl:ml-auto">
            <select
              value={filters.sort}
              onChange={(e) => setField("sort", e.target.value)}
              className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            >
              <option value="recent">Más recientes</option>
              <option value="value_asc">Menor valor</option>
              <option value="value_desc">Mayor valor</option>
            </select>

            <button
              type="button"
              onClick={clearFilters}
              className="text-xs font-black uppercase tracking-[0.18em] text-slate-400 transition hover:text-slate-900"
            >
              Limpiar
            </button>
          </div>
        </div>

        {showAdvanced ? (
          <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <input
                type="number"
                placeholder="Dormitorios mín."
                value={filters.bedrooms_min}
                onChange={(e) => setField("bedrooms_min", e.target.value)}
                className="no-spinner rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />

              <input
                type="number"
                placeholder="Baños mín."
                value={filters.bathrooms_min}
                onChange={(e) => setField("bathrooms_min", e.target.value)}
                className="no-spinner rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />

              <input
                type="number"
                placeholder="Cocheras mín."
                value={filters.garages_min}
                onChange={(e) => setField("garages_min", e.target.value)}
                className="no-spinner rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />

              <input
                type="number"
                placeholder="Superficie mín. m²"
                value={filters.area_min}
                onChange={(e) => setField("area_min", e.target.value)}
                className="no-spinner rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />

              <select
                value={filters.development_stage}
                onChange={(e) => setField("development_stage", e.target.value)}
                className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 lg:col-span-2"
              >
                {DEVELOPMENT_STAGES.map((item) => (
                  <option key={item.value || "all"} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
              <div>
                <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">
                  Tipo de operación / permuta
                </p>
                <div className="flex flex-wrap gap-2">
                  {EXCHANGE_MODES.map((mode) => {
                    const active = filters.exchange_modes.includes(mode.value);

                    return (
                      <button
                        key={mode.value}
                        type="button"
                        onClick={() =>
                          toggleArrayField("exchange_modes", mode.value)
                        }
                        className={`rounded-full border px-3 py-1.5 text-xs font-bold transition ${
                          active
                            ? "border-emerald-500 bg-emerald-50 text-emerald-700"
                            : "border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100"
                        }`}
                      >
                        {mode.label}
                      </button>
                    );
                  })}
                </div>
              </div>

              <div>
                <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">
                  Amenities
                </p>
                <div className="flex flex-wrap gap-2">
                  {AMENITIES.map((amenity) => {
                    const active = filters.amenities.includes(amenity.value);

                    return (
                      <button
                        key={amenity.value}
                        type="button"
                        onClick={() =>
                          toggleArrayField("amenities", amenity.value)
                        }
                        className={`rounded-full border px-3 py-1.5 text-xs font-bold transition ${
                          active
                            ? "border-emerald-500 bg-emerald-50 text-emerald-700"
                            : "border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100"
                        }`}
                      >
                        {amenity.label}
                      </button>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </section>

      {error ? (
        <div className="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white px-5 py-8 text-sm text-slate-500 shadow-sm">
          Cargando resultados...
        </div>
      ) : !items.length ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
          No hay resultados para los filtros aplicados.
        </div>
      ) : (
        <div className="space-y-4">{items.map(renderCard)}</div>
      )}
    </main>
  );
}
