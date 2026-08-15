import { useMemo, useState } from "react";
import { Icon } from "../../../../ui/icons/Index";
import {
  exchangeModeLabel,
  resolveImageUrl,
} from "../propertyDetail.helpers";

export default function PropertyGallery({
  property,
  gallery = [],
  activeImageIndex = 0,
  setActiveImageIndex,
  requirements,
}) {
  const [touchStartX, setTouchStartX] = useState(null);

  const total = gallery.length;
  const mainImage = gallery[activeImageIndex] || gallery[0] || null;
  const canNavigate = total > 1;

  const visibleThumbnails = useMemo(() => {
    if (!total) return [];

    return Array.from({ length: Math.min(4, total) }, (_, offset) => {
      const realIndex = (activeImageIndex + offset) % total;

      return {
        ...gallery[realIndex],
        realIndex,
      };
    });
  }, [gallery, activeImageIndex, total]);

  function goToPrevious() {
    if (!canNavigate) return;

    setActiveImageIndex((prev) => {
      const current = Number(prev || 0);
      return current <= 0 ? total - 1 : current - 1;
    });
  }

  function goToNext() {
    if (!canNavigate) return;

    setActiveImageIndex((prev) => {
      const current = Number(prev || 0);
      return current >= total - 1 ? 0 : current + 1;
    });
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
        goToNext();
      } else {
        goToPrevious();
      }
    }

    setTouchStartX(null);
  }

  return (
    <div className="space-y-4 lg:col-span-8">
      <div
        className="relative aspect-[16/9] w-full overflow-hidden rounded-2xl bg-slate-200"
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
      >
        {mainImage ? (
          <img
            src={resolveImageUrl(mainImage.url)}
            alt={property?.title || "Propiedad"}
            className="h-full w-full object-cover"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-slate-400">
            Sin imágenes
          </div>
        )}

        {canNavigate && (
          <>
            <button
              type="button"
              onClick={goToPrevious}
              className="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-slate-800 shadow-md backdrop-blur transition hover:bg-white"
              aria-label="Imagen anterior"
            >
              <Icon name="chevronLeft" size={20} />
            </button>

            <button
              type="button"
              onClick={goToNext}
              className="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-slate-800 shadow-md backdrop-blur transition hover:bg-white"
              aria-label="Imagen siguiente"
            >
              <Icon name="chevronRight" size={20} />
            </button>

            <div className="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-slate-900/70 px-3 py-1 text-xs font-bold text-white backdrop-blur">
              {activeImageIndex + 1} / {total}
            </div>
          </>
        )}

        {requirements ? (
          <div className="absolute right-4 top-4 rounded-full border border-white/20 bg-white/70 px-4 py-2 backdrop-blur-md">
            <div className="flex items-center gap-2">
              <Icon name="refresh" size={16} className="text-emerald-700" />
              <span className="text-xs font-bold uppercase tracking-wider text-slate-900">
                {exchangeModeLabel(requirements)}
              </span>
            </div>
          </div>
        ) : null}
      </div>

      {gallery.length > 1 ? (
        <div className="grid grid-cols-4 gap-4">
          {visibleThumbnails.map((image, index) => (
            <button
              key={image.id || `${image.realIndex}-${index}`}
              type="button"
              onClick={() => setActiveImageIndex(image.realIndex)}
              className={`relative aspect-square overflow-hidden rounded-xl bg-slate-200 ${
                activeImageIndex === image.realIndex
                  ? "ring-2 ring-emerald-500"
                  : ""
              }`}
            >
              <img
                src={resolveImageUrl(image.url)}
                alt={`Imagen ${image.realIndex + 1}`}
                className="h-full w-full object-cover transition-transform duration-500 hover:scale-105"
              />

              <div className="absolute bottom-2 right-2 rounded-full bg-slate-900/70 px-2 py-0.5 text-[10px] font-bold text-white">
                {image.realIndex + 1}
              </div>
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}