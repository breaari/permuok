import { Icon } from "../../../ui/icons/Index";

function getStatusMeta(status) {
  const map = {
    draft: {
      label: "En borrador",
      className: "bg-slate-50 text-slate-600 border-slate-200",
      dotClassName: "bg-slate-400",
    },
    published: {
      label: "Publicada",
      className: "bg-emerald-50 text-emerald-700 border-emerald-200",
      dotClassName: "bg-emerald-500",
    },
    paused: {
      label: "Pausada",
      className: "bg-amber-50 text-amber-700 border-amber-200",
      dotClassName: "bg-amber-500",
    },
    archived: {
      label: "Archivada",
      className: "bg-slate-100 text-slate-700 border-slate-300",
      dotClassName: "bg-slate-500",
    },
    closed: {
      label: "Cerrada",
      className: "bg-rose-50 text-rose-700 border-rose-200",
      dotClassName: "bg-rose-500",
    },
    deleted: {
      label: "Eliminada",
      className: "bg-rose-50 text-rose-700 border-rose-200",
      dotClassName: "bg-rose-500",
    },
  };

  return (
    map[status] || {
      label: "Sin estado",
      className: "bg-slate-50 text-slate-600 border-slate-200",
      dotClassName: "bg-slate-400",
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

  return "Sin referencia";
}

function formatLocation(item) {
  return [item?.city, item?.zone, item?.province].filter(Boolean).join(", ");
}

function normalizePropertyTypes(raw) {
  if (!raw) return [];

  if (Array.isArray(raw)) {
    return raw
      .map((item) => {
        if (typeof item === "string") return item;
        if (typeof item === "object" && item !== null) {
          return item.property_type || item.value || item.code || "";
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

function formatPropertyTypes(item) {
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

  const types = normalizePropertyTypes(item?.property_types);
  if (!types.length) return "Sin tipo definido";

  return types.map((t) => map[t] || t).join(", ");
}

function formatPaymentLabel(item) {
  const cash = !!item?.payment_mode_cash;
  const swap = !!item?.payment_mode_swap;

  if (cash && swap) return "Dinero + permuta";
  if (cash) return "Solo dinero";
  if (swap) return "Solo permuta";

  return "Sin modalidad definida";
}

export default function SearchRequestCard({
  item,
  onEdit,
  onManage,
  onDelete,
  onView,
  variant = "owned",
}) {
  const status = getStatusMeta(item?.status);
  const location = formatLocation(item);
  const propertyTypes = formatPropertyTypes(item);
  const payment = formatPaymentLabel(item);
  const budget = formatMoneyRange(item);

  const isDashboard = variant === "dashboard";

  return (
    <article className="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden">
      <div className="p-5 sm:p-6">
        <div className="grid grid-cols-1 xl:grid-cols-[minmax(290px,1.3fr)_minmax(190px,0.9fr)_minmax(180px,0.75fr)_minmax(220px,0.9fr)] gap-6 items-start">
          <div className="min-w-0">
            <div className="flex items-center gap-3 flex-wrap">
              {!isDashboard && (
                <span
                  className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-bold tracking-wide ${status.className}`}
                >
                  <span className={`w-2 h-2 rounded-full ${status.dotClassName}`} />
                  {status.label}
                </span>
              )}

              <span className="text-xs text-slate-400 font-medium">
                #{item?.id || "—"}
              </span>
            </div>

            <div className="mt-3 min-w-0">
              <h3 className="text-2xl font-extrabold text-primary leading-tight break-words line-clamp-2">
                {item?.title || "Sin título"}
              </h3>

              <p className="mt-2 text-sm text-slate-500 line-clamp-2 break-words">
                {item?.description || "Sin descripción."}
              </p>
            </div>
          </div>

          <div className="min-w-0 xl:border-l xl:border-slate-200 xl:pl-6">
            <InfoBlock
              label="Ubicación"
              value={location || "Sin ubicación"}
              clamp={2}
            />

            <div className="mt-4 flex flex-wrap gap-2">
              {!!item?.min_bedrooms && (
                <SpecChip icon="bed">{item.min_bedrooms}+ d.</SpecChip>
              )}

              {!!item?.min_bathrooms && (
                <SpecChip icon="bath">{item.min_bathrooms}+ b.</SpecChip>
              )}

              {!!item?.min_garages && (
                <SpecChip icon="car">{item.min_garages}+ coch.</SpecChip>
              )}

              {!!item?.min_total_area && (
                <SpecChip icon="ruler">
                  {Number(item.min_total_area)} m² mín.
                </SpecChip>
              )}
            </div>
          </div>

          <div className="min-w-0 xl:border-l xl:border-slate-200 xl:pl-6">
            <InfoBlock
              label="Tipo buscado"
              value={propertyTypes}
              clamp={2}
            />

            <div className="mt-4">
              <InfoBlock
                label="Modalidad"
                value={payment}
                clamp={2}
              />
            </div>
          </div>

          <div className="flex items-center justify-between gap-3 flex-wrap">
            <div className="text-left">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Rango de valor
              </p>
              <p className="mt-1 text-xl font-extrabold text-primary leading-tight">
                {budget}
              </p>
            </div>

            <div className="flex items-center gap-2">
              {isDashboard ? (
                <button
                  type="button"
                  onClick={onView}
                  className="px-4 py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all inline-flex items-center gap-2"
                >
                  Ver más
                  <Icon name="arrowRight" size={16} />
                </button>
              ) : (
                <>
                  <button
                    type="button"
                    onClick={onManage}
                    className="px-4 py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all"
                  >
                    Gestionar
                  </button>

                  <button
                    type="button"
                    onClick={onEdit}
                    className="px-4 py-2.5 border border-slate-200 rounded-xl font-bold text-sm text-slate-700 hover:bg-slate-50 transition-all"
                  >
                    Editar
                  </button>

                  <button
                    type="button"
                    onClick={onDelete}
                    title="Eliminar"
                    className="w-10 h-10 flex items-center justify-center rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all"
                  >
                    <Icon name="trash" size={16} />
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </article>
  );
}

function InfoBlock({ label, value, clamp = 1 }) {
  const clampClass = clamp === 2 ? "line-clamp-2" : "truncate";

  return (
    <div className="space-y-1 min-w-0">
      <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
        {label}
      </p>
      <p className={`text-sm font-semibold text-slate-800 min-w-0 ${clampClass}`}>
        {value}
      </p>
    </div>
  );
}

function SpecChip({ icon, children }) {
  return (
    <div className="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-slate-100 rounded-lg text-slate-600">
      <Icon name={icon} size={15} className="text-slate-400" />
      <span className="text-[11px] font-bold">{children}</span>
    </div>
  );
}