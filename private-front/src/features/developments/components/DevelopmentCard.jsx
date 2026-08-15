import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { getApiBaseUrl } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";

function resolveImageUrl(rawUrl) {
  if (!rawUrl) return null;

  const value = String(rawUrl).trim();
  if (!value) return null;

  if (/^https?:\/\//i.test(value)) return value;

  const base = getApiBaseUrl().replace(/\/+$/, "");
  return value.startsWith("/") ? `${base}${value}` : `${base}/${value}`;
}

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

function formatStage(value) {
  const map = {
    land: "Lote / tierra",
    prelaunch: "Prelanzamiento",
    launch: "Lanzamiento",
    presale: "Preventa",
    under_construction: "En construcción",
    finished: "Finalizado",
  };

  return map[value] || value || "Sin etapa";
}

function getStatusMeta(status) {
  const map = {
    published: {
      label: "Publicado",
      wrapper: "bg-emerald-100/90 text-emerald-800 backdrop-blur-sm",
      dot: "bg-emerald-600",
    },
    draft: {
      label: "Borrador",
      wrapper: "bg-slate-200/90 text-slate-700 backdrop-blur-sm",
      dot: "bg-slate-500",
    },
    paused: {
      label: "Pausado",
      wrapper: "bg-amber-100/90 text-amber-800 backdrop-blur-sm",
      dot: "bg-amber-600",
    },
    archived: {
      label: "Archivado",
      wrapper: "bg-slate-300/90 text-slate-700 backdrop-blur-sm",
      dot: "bg-slate-500",
    },
    closed: {
      label: "Cerrado",
      wrapper: "bg-rose-100/90 text-rose-800 backdrop-blur-sm",
      dot: "bg-rose-600",
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

function getGallery(item) {
  const rawImages = [
    ...(Array.isArray(item?.images) ? item.images : []),
    ...(Array.isArray(item?.development_images) ? item.development_images : []),
    ...(Array.isArray(item?.images_preview) ? item.images_preview : []),
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
          img?.archive_path,
      );
    })
    .filter(Boolean);

  const cover =
    resolveImageUrl(item?.cover_image_url) ||
    resolveImageUrl(item?.cover_image_path) ||
    resolveImageUrl(item?.cover_image);

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

function formatPriceRange(item) {
  const priceFrom = item?.price_from
    ? formatMoney(item.price_from, item?.currency || "USD")
    : null;

  const priceTo = item?.price_to
    ? formatMoney(item.price_to, item?.currency || "USD")
    : null;

  if (priceFrom && priceTo) return `${priceFrom} - ${priceTo}`;
  if (priceFrom) return `Desde ${priceFrom}`;
  if (priceTo) return `Hasta ${priceTo}`;
  return "Consultar";
}

function buildLocation(item) {
  return [item?.city, item?.zone, item?.province].filter(Boolean).join(", ");
}

function buildSpecs(item) {
  const stage = formatStage(item?.development_stage);

  const units =
    item?.available_units || item?.total_units
      ? `${item?.available_units ?? "—"}/${item?.total_units ?? "—"}`
      : "—";

  const unitTypesCount =
    item?.unit_types_count !== null &&
    item?.unit_types_count !== undefined &&
    item?.unit_types_count !== ""
      ? `${item.unit_types_count}`
      : "—";

  const delivery =
    item?.delivery_date_estimated && String(item.delivery_date_estimated).trim()
      ? String(item.delivery_date_estimated).slice(0, 7)
      : "—";

  return [
    {
      icon: "building2",
      value: stage,
    },
    {
      icon: "layoutGrid",
      value: units,
    },
    {
      icon: "home",
      value: unitTypesCount,
    },
    {
      icon: "calendar",
      value: delivery,
    },
  ];
}

function buildDevelopmentSummary(item) {
  const available = item?.available_units ?? null;
  const total = item?.total_units ?? null;

  let title = "Unidades sin informar";
  let notes =
    "Cargá unidades y tipologías para completar la información comercial.";

  if (available || total) {
    title = `${available ?? "—"} disponibles · ${total ?? "—"} totales`;
    notes = "Resumen de disponibilidad del desarrollo.";
  }

  if (item?.unit_types_count) {
    notes = `${item.unit_types_count} tipolog${
      Number(item.unit_types_count) === 1 ? "ía" : "ías"
    } cargadas para este desarrollo.`;
  }

  return {
    eyebrow: "Disponibilidad",
    title,
    notes,
  };
}

export default function DevelopmentCard({
  item,
  variant = "owned",
  detailHref,
  detailState,
  onEdit,
  onDelete,
  onPause,
  onArchive,
  onPublish,
  onClose,
}) {
  const navigate = useNavigate();

  const isDashboard = variant === "dashboard" || variant === "explore";
  const canOpen = !!detailHref;

  const gallery = useMemo(() => getGallery(item), [item]);
  const [imageIndex, setImageIndex] = useState(0);
  const [touchStartX, setTouchStartX] = useState(null);
  const currentImage = gallery[imageIndex] || null;
  const hasMultipleImages = gallery.length > 1;

  const title = item?.title || "Sin título";
  const status = item?.status;
  const location = buildLocation(item);
  const specs = buildSpecs(item);
  const summary = buildDevelopmentSummary(item);
  const price = formatPriceRange(item);
  const developmentId = item?.id ? `#${item.id}` : "—";

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

  function handleTouchStart(e) {
    setTouchStartX(e.touches?.[0]?.clientX ?? null);
  }

  function handleTouchEnd(e) {
    if (touchStartX === null) return;

    const touchEndX = e.changedTouches?.[0]?.clientX ?? null;
    if (touchEndX === null) return;

    const diff = touchStartX - touchEndX;

    if (Math.abs(diff) > 45) {
      if (diff > 0) {
        if (imageIndex >= gallery.length - 1) {
          goToDetail();
        } else {
          setImageIndex((prev) => prev + 1);
        }
      } else {
        setImageIndex((prev) => (prev <= 0 ? 0 : prev - 1));
      }
    }

    setTouchStartX(null);
  }

  return (
    <article className="group bg-white rounded-xl overflow-hidden flex flex-col md:flex-row shadow-sm hover:shadow-md transition-all duration-300 border border-slate-200 md:h-[336px]">
      <div
        className={`md:w-80 h-52 sm:h-60 md:h-full relative overflow-hidden shrink-0 bg-slate-100 ${
          canOpen ? "cursor-pointer" : ""
        }`}
        onClick={canOpen ? goToDetail : undefined}
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
      >
        {currentImage ? (
          <img
            src={currentImage}
            alt={title}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-xs sm:text-sm font-semibold text-slate-400">
            Sin imagen
          </div>
        )}

        {!isDashboard && <StatusBadge status={status} />}

        {hasMultipleImages && (
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
                Desarrollo
              </span>

              {canOpen ? (
                <button
                  type="button"
                  onClick={goToDetail}
                  className="block text-left"
                >
                  <h3 className="text-lg sm:text-[20px] font-extrabold text-slate-900 leading-tight break-words line-clamp-2 hover:text-primary transition-colors">
                    {title}
                  </h3>
                </button>
              ) : (
                <h3 className="text-lg sm:text-[20px] font-extrabold text-slate-900 leading-tight break-words line-clamp-2">
                  {title}
                </h3>
              )}
            </div>

            <div className="text-left sm:text-right shrink-0">
              <span className="block text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                {price}
              </span>
              <span className="text-[11px] sm:text-xs text-slate-400 font-medium">
                ID: {developmentId}
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

          <div className="rounded-lg p-4 border-l-4 min-h-[104px] max-h-[104px] overflow-hidden bg-slate-50 border-emerald-300">
            <div className="flex items-center gap-2 mb-1.5 text-[10px] sm:text-xs font-bold uppercase text-emerald-700">
              <Icon name="building2" size={14} />
              {summary.eyebrow}
            </div>

            <p className="text-xs sm:text-sm font-bold text-slate-900 mb-1 line-clamp-1">
              {summary.title}
            </p>

            <p className="text-[11px] sm:text-xs text-slate-500 italic leading-relaxed line-clamp-2">
              {summary.notes}
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
