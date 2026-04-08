import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { getApiBaseUrl } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";
import { PROPERTY_TYPES } from "../utils/PropertyFormHelpers";

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

function formatPropertyType(value) {
  const found = PROPERTY_TYPES.find((item) => item.value === value);
  if (found) return found.label;
  return value || "Propiedad";
}

function getBaseProperty(item) {
  return item?.property || item || {};
}

function getRequirementSource(item) {
  return item?.requirements || item?.property_requirements || {};
}

function buildLocation(item) {
  const base = getBaseProperty(item);
  return [base?.city, base?.zone, base?.province].filter(Boolean).join(", ");
}

function buildSpecs(item) {
  const base = getBaseProperty(item);

  const totalArea = base?.total_area ?? base?.covered_area ?? null;
  const bedrooms = base?.bedrooms ?? null;
  const bathrooms = base?.bathrooms ?? null;
  const garages = base?.garages ?? null;

  return [
    {
      icon: "ruler",
      value:
        totalArea !== null && totalArea !== undefined && totalArea !== ""
          ? `${Number(totalArea)} m²`
          : "—",
    },
    {
      icon: "bed",
      value:
        bedrooms !== null && bedrooms !== undefined && bedrooms !== ""
          ? `${bedrooms}`
          : "—",
    },
    {
      icon: "bath",
      value:
        bathrooms !== null && bathrooms !== undefined && bathrooms !== ""
          ? `${bathrooms}`
          : "—",
    },
    {
      icon: "car",
      value:
        garages !== null && garages !== undefined && garages !== ""
          ? `${garages}`
          : "—",
    },
  ];
}

function getStatusMeta(status) {
  const map = {
    published: {
      label: "Publicada",
      wrapper: "bg-emerald-100/90 text-emerald-800 backdrop-blur-sm",
      dot: "bg-emerald-600",
    },
    draft: {
      label: "Borrador",
      wrapper: "bg-slate-200/90 text-slate-700 backdrop-blur-sm",
      dot: "bg-slate-500",
    },
    paused: {
      label: "Pausada",
      wrapper: "bg-amber-100/90 text-amber-800 backdrop-blur-sm",
      dot: "bg-amber-600",
    },
    archived: {
      label: "Archivada",
      wrapper: "bg-slate-300/90 text-slate-700 backdrop-blur-sm",
      dot: "bg-slate-500",
    },
    closed: {
      label: "Cerrada",
      wrapper: "bg-rose-100/90 text-rose-800 backdrop-blur-sm",
      dot: "bg-rose-600",
    },
    duplicated: {
      label: "Duplicada",
      wrapper: "bg-sky-100/90 text-sky-800 backdrop-blur-sm",
      dot: "bg-sky-600",
    },
  };

  return (
    map[status] || {
      label: status || "—",
      wrapper: "bg-slate-200/90 text-slate-700 backdrop-blur-sm",
      dot: "bg-slate-500",
    }
  );
}

function isTrueFlag(value) {
  return value === true || value === 1 || value === "1";
}

function buildExchangeSummary(item) {
  const req = getRequirementSource(item);

  const mode = req?.criteria_mode || item?.criteria_mode || "";

  const acceptsTotalSwap = isTrueFlag(
    req?.accepts_total_swap ?? item?.accepts_total_swap
  );

  const acceptsSwapPlusCash = isTrueFlag(
    req?.accepts_swap_plus_cash ?? item?.accepts_swap_plus_cash
  );

  const acceptsCashOnly = isTrueFlag(
    req?.accepts_cash_only ?? item?.accepts_cash_only
  );

  const acceptsOpenProposals = isTrueFlag(
    req?.accepts_open_proposals ?? item?.accepts_open_proposals
  );

  let eyebrow = "Condiciones de permuta";
  let title = "Sin especificar";

  if (mode === "criteria") {
    eyebrow = "Busco con criterios";
  } else if (acceptsOpenProposals) {
    eyebrow = "Escucho propuestas";
  }

  if (acceptsCashOnly) {
    title = "Solo efectivo";
  } else if (acceptsSwapPlusCash && acceptsTotalSwap) {
    title = "Permuta total o parcial";
  } else if (acceptsSwapPlusCash) {
    title = "Permuta + diferencia";
  } else if (acceptsTotalSwap) {
    title = "Permuta total";
  } else if (acceptsOpenProposals) {
    title = "Abierto a propuestas";
  }

  const notes =
    req?.notes ||
    item?.requirement_notes ||
    item?.requirements?.notes ||
    item?.notes ||
    item?.property?.notes ||
    "Sin observaciones cargadas.";

  const isCriteria = mode === "criteria";

  return {
    eyebrow,
    title,
    notes,
    isCriteria,
  };
}

function resolveImageUrl(rawUrl) {
  if (!rawUrl) return null;

  const value = String(rawUrl).trim();
  if (!value) return null;

  if (/^https?:\/\//i.test(value)) return value;

  const base = getApiBaseUrl().replace(/\/+$/, "");
  return value.startsWith("/") ? `${base}${value}` : `${base}/${value}`;
}

function getGallery(item) {
  const base = getBaseProperty(item);

  const rawImages = [
    ...(Array.isArray(item?.images) ? item.images : []),
    ...(Array.isArray(base?.images) ? base.images : []),
    ...(Array.isArray(item?.property_images) ? item.property_images : []),
    ...(Array.isArray(base?.property_images) ? base.property_images : []),
  ];

  const normalized = rawImages
    .map((img) => {
      if (typeof img === "string") {
        return resolveImageUrl(img);
      }

      return resolveImageUrl(
        img?.view_url ||
          img?.image_url ||
          img?.web_path ||
          img?.url ||
          img?.path ||
          img?.file_path ||
          img?.archive_path
      );
    })
    .filter(Boolean);

  const cover =
    resolveImageUrl(item?.cover_image_url) ||
    resolveImageUrl(base?.cover_image_url);

  const result = [];

  if (cover) result.push(cover);

  normalized.forEach((url) => {
    if (!result.includes(url)) result.push(url);
  });

  return result;
}

function StatusBadge({ status }) {
  const meta = getStatusMeta(status);

  return (
    <div
      className={`absolute top-3 left-3 sm:top-4 sm:left-4 inline-flex items-center gap-1.5 sm:gap-2 rounded-full px-2.5 py-1 sm:px-3 sm:py-1 text-[10px] sm:text-xs font-bold uppercase tracking-wider ${meta.wrapper}`}
    >
      <span className={`h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full ${meta.dot}`} />
      {meta.label}
    </div>
  );
}

function SpecItem({ icon, value }) {
  return (
    <div className="flex items-center gap-1.5 sm:gap-2 min-w-[70px]">
      <Icon name={icon} size={15} className="text-slate-400 shrink-0" />
      <span className="text-xs sm:text-sm font-semibold text-slate-700">
        {value}
      </span>
    </div>
  );
}

export default function PropertyCard({
  item,
  onOpenDetail,
  onEdit,
  onDelete,
  variant = "owned",
  detailHref,
  detailState,
}) {
  const navigate = useNavigate();
  const base = getBaseProperty(item);

  const typeLabel = formatPropertyType(base?.property_type);
  const location = buildLocation(item);
  const specs = buildSpecs(item);
  const exchange = buildExchangeSummary(item);
  const price = formatMoney(base?.price, base?.currency || "USD");
  const propertyId = base?.id ? `#${base.id}` : "—";

  const isDashboard = variant === "dashboard";
  const canOpen = isDashboard && !!detailHref;

  const gallery = useMemo(() => getGallery(item), [item]);
  const [imageIndex, setImageIndex] = useState(0);

  const currentImage = gallery[imageIndex] || null;
  const hasMultipleImages = gallery.length > 1;

  function goToDetail() {
    if (!canOpen) return;
    navigate(detailHref, {
      state: detailState,
    });
  }

  function handlePrevImage(e) {
    e.preventDefault();
    e.stopPropagation();

    if (!hasMultipleImages) return;

    setImageIndex((prev) => (prev === 0 ? gallery.length - 1 : prev - 1));
  }

  function handleNextImage(e) {
    e.preventDefault();
    e.stopPropagation();

    if (!hasMultipleImages) {
      goToDetail();
      return;
    }

    if (imageIndex >= gallery.length - 1) {
      goToDetail();
      return;
    }

    setImageIndex((prev) => prev + 1);
  }

  console.log("PROPERTY CARD GALLERY", {
  id: base?.id,
  cover_image_url: item?.cover_image_url || base?.cover_image_url,
  images: item?.images,
  property_images: item?.property_images,
  gallery,
  galleryLength: gallery.length,
  isDashboard,
});

  return (
    <article className="group bg-white rounded-xl overflow-hidden flex flex-col md:flex-row shadow-sm hover:shadow-md transition-all duration-300 border border-slate-200 md:h-[336px]">
      <div
        className={`md:w-80 h-52 sm:h-60 md:h-full relative overflow-hidden shrink-0 bg-slate-100 ${
          canOpen ? "cursor-pointer" : ""
        }`}
        onClick={canOpen ? goToDetail : undefined}
      >
        {currentImage ? (
          <img
            src={currentImage}
            alt={base?.title || "Propiedad"}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-xs sm:text-sm font-semibold text-slate-400">
            Sin imagen
          </div>
        )}

        {!isDashboard && (
          <StatusBadge status={base?.status || item?.status} />
        )}

        {isDashboard && hasMultipleImages && (
          <>
            <button
              type="button"
              onClick={handlePrevImage}
              className="absolute left-3 top-1/2 -translate-y-1/2 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/85 text-slate-900 shadow-sm backdrop-blur hover:bg-white"
              aria-label="Imagen anterior"
            >
              <Icon name="chevronLeft" size={18} />
            </button>

            <button
              type="button"
              onClick={handleNextImage}
              className="absolute right-3 top-1/2 -translate-y-1/2 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/85 text-slate-900 shadow-sm backdrop-blur hover:bg-white"
              aria-label="Siguiente imagen"
            >
              <Icon name="chevronRight" size={18} />
            </button>

            <div className="absolute bottom-3 left-1/2 -translate-x-1/2 z-10 inline-flex items-center gap-1.5 rounded-full bg-black/45 px-2.5 py-1 text-[10px] font-bold text-white backdrop-blur">
              <span>{imageIndex + 1}</span>
              <span>/</span>
              <span>{gallery.length}</span>
            </div>
          </>
        )}
      </div>

      <div className="flex-1 p-4 sm:p-5 md:p-5 flex flex-col min-h-0 h-full">
        <div className="flex-1 min-h-0 overflow-hidden">
          <div className="flex flex-col sm:flex-row justify-between items-start gap-2 sm:gap-3 mb-2">
            <div className="min-w-0">
              <span className="text-emerald-700 font-bold text-[10px] sm:text-xs uppercase tracking-widest">
                {typeLabel}
              </span>

              {canOpen ? (
                <button
                  type="button"
                  onClick={goToDetail}
                  className="block text-left"
                >
                  <h3 className="text-lg sm:text-[20px] font-extrabold text-slate-900 leading-tight break-words line-clamp-2 hover:text-primary transition-colors">
                    {base?.title || "Sin título"}
                  </h3>
                </button>
              ) : (
                <h3 className="text-lg sm:text-[20px] font-extrabold text-slate-900 leading-tight break-words line-clamp-2">
                  {base?.title || "Sin título"}
                </h3>
              )}
            </div>

            <div className="text-left sm:text-right shrink-0">
              <span className="block text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                {price}
              </span>
              <span className="text-[11px] sm:text-xs text-slate-400 font-medium">
                ID: {propertyId}
              </span>
            </div>
          </div>

          <div className="flex items-center gap-2 text-slate-500 text-xs sm:text-sm mb-3">
            <Icon name="mapPin" size={15} className="text-slate-400 shrink-0" />
            <span className="break-words line-clamp-1">
              {location || "Ubicación no informada"}
            </span>
          </div>

          <div className="flex flex-wrap gap-x-4 gap-y-2 sm:gap-6 mb-3 py-3 border-y border-slate-200">
            {specs.map((spec, index) => (
              <SpecItem
                key={`${spec.icon}-${index}`}
                icon={spec.icon}
                value={spec.value}
              />
            ))}
          </div>

          <div
            className={`rounded-lg p-4 border-l-4 min-h-[104px] max-h-[104px] overflow-hidden ${
              exchange.isCriteria
                ? "bg-slate-50 border-emerald-300"
                : "bg-slate-50 border-slate-300"
            }`}
          >
            <div
              className={`flex items-center gap-2 mb-1.5 text-[10px] sm:text-xs font-bold uppercase ${
                exchange.isCriteria ? "text-emerald-700" : "text-slate-500"
              }`}
            >
              <Icon
                name={exchange.isCriteria ? "refresh" : "messagesSquare"}
                size={14}
              />
              {exchange.eyebrow}
            </div>

            <p className="text-xs sm:text-sm font-bold text-slate-900 mb-1 line-clamp-1">
              {exchange.title}
            </p>

            <p className="text-[11px] sm:text-xs text-slate-500 italic leading-relaxed line-clamp-2">
              {exchange.notes}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-x-4 gap-y-2 mt-3 pt-3 border-t border-slate-100 shrink-0">
          {isDashboard ? (
            canOpen ? (
              <button
                type="button"
                onClick={goToDetail}
                className="text-blue-900 font-bold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 hover:underline underline-offset-4"
              >
                <Icon name="arrowRight" size={15} />
                Ver más
              </button>
            ) : null
          ) : (
            <>
              <button
                type="button"
                onClick={onEdit}
                className="text-blue-900 font-bold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 hover:underline underline-offset-4"
              >
                <Icon name="edit" size={15} />
                Editar
              </button>

              <button
                type="button"
                onClick={onOpenDetail}
                className="text-blue-900 font-bold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 hover:underline underline-offset-4"
              >
                <Icon name="chart" size={15} />
                Gestionar
              </button>

              <button
                type="button"
                onClick={onDelete}
                className="text-rose-700 font-bold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 hover:underline underline-offset-4"
              >
                <Icon name="close" size={15} />
                Eliminar
              </button>
            </>
          )}
        </div>
      </div>
    </article>
  );
}