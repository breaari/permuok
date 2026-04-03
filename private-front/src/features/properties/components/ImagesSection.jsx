import { useEffect, useMemo, useState } from "react";
import { getApiBaseUrl } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";

const MAX_IMAGES = 5;
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 MB

function resolveImageUrl(path) {
  if (!path) return "";
  if (String(path).startsWith("http")) return path;
  return `${getApiBaseUrl()}${path}`;
}

export default function ImagesSection({
  images,
  setImages,
  existingImages = [],
  setExistingImages = () => {},
  onError,
  onSuccess,
}) {
  const [newPreviews, setNewPreviews] = useState([]);

  function clearMessages() {
    onError?.("");
    onSuccess?.("");
  }

  function normalizeIncomingFiles(files) {
    return files.map((file, index) => ({
      id: `${file.name}-${file.lastModified}-${index}-${crypto.randomUUID()}`,
      file,
    }));
  }

  function handleFiles(e) {
    clearMessages();

    const selectedFiles = Array.from(e.target.files || []);
    if (!selectedFiles.length) return;

    const tooLarge = selectedFiles.find((file) => file.size > MAX_IMAGE_SIZE);

    if (tooLarge) {
      onError?.(`La imagen "${tooLarge.name}" supera los 5 MB.`);
      e.target.value = "";
      return;
    }

    const totalCurrent = existingImages.length + images.length;
    const availableSlots = MAX_IMAGES - totalCurrent;

    if (availableSlots <= 0) {
      onError?.(`Solo podés cargar hasta ${MAX_IMAGES} imágenes.`);
      e.target.value = "";
      return;
    }

    const filesToAdd = selectedFiles.slice(0, availableSlots);
    const normalized = normalizeIncomingFiles(filesToAdd);

    setImages((prev) => [...prev, ...normalized]);

    if (selectedFiles.length > availableSlots) {
      onError?.(
        `Solo se agregaron ${availableSlots} imágenes. El máximo es ${MAX_IMAGES}.`
      );
    } else {
      onSuccess?.(
        `${filesToAdd.length} imagen${
          filesToAdd.length !== 1 ? "es" : ""
        } agregada${filesToAdd.length !== 1 ? "s" : ""} correctamente.`
      );
    }

    e.target.value = "";
  }

  function removeNewImage(indexToRemove) {
    clearMessages();
    setImages((prev) => prev.filter((_, i) => i !== indexToRemove));
  }

  function removeExistingImage(indexToRemove) {
    clearMessages();
    setExistingImages((prev) => prev.filter((_, i) => i !== indexToRemove));
  }

  function moveExistingImage(index, direction) {
    clearMessages();

    setExistingImages((prev) => {
      const newIndex = direction === "left" ? index - 1 : index + 1;
      if (newIndex < 0 || newIndex >= prev.length) return prev;

      const next = [...prev];
      const temp = next[index];
      next[index] = next[newIndex];
      next[newIndex] = temp;

      return next.map((item, idx) => ({
        ...item,
        is_cover: idx === 0,
        sort_order: idx,
      }));
    });
  }

  function moveNewImage(index, direction) {
    clearMessages();

    setImages((prev) => {
      const newIndex = direction === "left" ? index - 1 : index + 1;
      if (newIndex < 0 || newIndex >= prev.length) return prev;

      const next = [...prev];
      const temp = next[index];
      next[index] = next[newIndex];
      next[newIndex] = temp;

      return next;
    });
  }

  useEffect(() => {
    const nextPreviews = images.map((item) => URL.createObjectURL(item.file));

    setNewPreviews(nextPreviews);

    return () => {
      nextPreviews.forEach((url) => URL.revokeObjectURL(url));
    };
  }, [images]);

  const totalImages = useMemo(
    () => existingImages.length + images.length,
    [existingImages.length, images.length]
  );

  const remaining = useMemo(() => MAX_IMAGES - totalImages, [totalImages]);

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Imágenes
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Sumá fotos claras y bien iluminadas. La primera imagen será la portada
          principal de la publicación.
        </p>
      </div>

      <div className="space-y-5">
        <label className="block">
          <input
            type="file"
            multiple
            accept="image/*"
            onChange={handleFiles}
            className="hidden"
            disabled={totalImages >= MAX_IMAGES}
          />

          <div
            className={`rounded-2xl border-2 border-dashed px-5 py-8 sm:px-6 sm:py-10 text-center transition-colors ${
              totalImages >= MAX_IMAGES
                ? "border-slate-200 bg-slate-50 cursor-not-allowed opacity-70"
                : "border-slate-300 bg-slate-50 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/40"
            }`}
          >
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white border border-slate-200 text-slate-500">
              <Icon name="image" size={20} />
            </div>

            <p className="text-sm sm:text-base font-semibold text-slate-800">
              {totalImages >= MAX_IMAGES
                ? "Límite de imágenes alcanzado"
                : "Hacé click para subir imágenes"}
            </p>

            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              {totalImages >= MAX_IMAGES
                ? `Ya cargaste el máximo de ${MAX_IMAGES} imágenes`
                : `Podés seleccionar hasta ${MAX_IMAGES} imágenes (${remaining} disponibles)`}
            </p>
          </div>
        </label>

        {!totalImages ? (
          <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            Todavía no cargaste imágenes.
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <p className="text-sm font-semibold text-slate-800">
                Imágenes seleccionadas
              </p>

              <span className="text-xs text-slate-400">
                {totalImages} / {MAX_IMAGES}
              </span>
            </div>

            {!!existingImages.length && (
              <div className="space-y-2">
                <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  Ya guardadas
                </p>

                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                  {existingImages.map((img, i) => {
                    const isPrimary = i === 0;
                    const imageUrl = resolveImageUrl(
                      img?.view_url || img?.url || img?.file_path || ""
                    );

                    return (
                      <div
                        key={img.id || `existing-${i}`}
                        className="relative rounded-xl overflow-hidden border border-slate-200 bg-slate-100 group"
                      >
                        {imageUrl ? (
                          <img
                            src={imageUrl}
                            alt={img.name || `Imagen ${i + 1}`}
                            className="w-full h-28 object-cover"
                          />
                        ) : (
                          <div className="w-full h-28 flex items-center justify-center text-slate-400">
                            <Icon name="image" size={18} />
                          </div>
                        )}

                        <button
                          type="button"
                          onClick={() => removeExistingImage(i)}
                          className="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/85 text-slate-500 shadow-sm transition hover:bg-white hover:text-rose-600"
                          aria-label="Quitar imagen existente"
                        >
                          <Icon name="close" size={14} />
                        </button>

                        <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent p-2">
                          <div className="flex items-center justify-between gap-1">
                            <button
                              type="button"
                              onClick={() => moveExistingImage(i, "left")}
                              disabled={i === 0}
                              className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white"
                              aria-label="Mover a la izquierda"
                            >
                              <Icon name="chevronLeft" size={14} />
                            </button>

                            <div
                              className={`rounded-full px-2.5 py-1 text-[10px] font-semibold ${
                                isPrimary
                                  ? "bg-emerald-600/90 text-white"
                                  : "bg-white/90 text-slate-700"
                              }`}
                            >
                              {isPrimary ? "Principal" : `Posición ${i + 1}`}
                            </div>

                            <button
                              type="button"
                              onClick={() => moveExistingImage(i, "right")}
                              disabled={i === existingImages.length - 1}
                              className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white"
                              aria-label="Mover a la derecha"
                            >
                              <Icon name="chevronRight" size={14} />
                            </button>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {!!images.length && (
              <div className="space-y-2">
                <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  Nuevas
                </p>

                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                  {images.map((img, i) => {
                    const isPrimary = !existingImages.length && i === 0;

                    return (
                      <div
                        key={img.id}
                        className="relative rounded-xl overflow-hidden border border-slate-200 bg-slate-100 group"
                      >
                        <img
                          src={newPreviews[i]}
                          alt={img.file?.name || `Imagen nueva ${i + 1}`}
                          className="w-full h-28 object-cover"
                        />

                        <button
                          type="button"
                          onClick={() => removeNewImage(i)}
                          className="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/85 text-slate-500 shadow-sm transition hover:bg-white hover:text-rose-600"
                          aria-label="Quitar imagen nueva"
                        >
                          <Icon name="close" size={14} />
                        </button>

                        <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent p-2">
                          <div className="flex items-center justify-between gap-1">
                            <button
                              type="button"
                              onClick={() => moveNewImage(i, "left")}
                              disabled={i === 0}
                              className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white"
                              aria-label="Mover a la izquierda"
                            >
                              <Icon name="chevronLeft" size={14} />
                            </button>

                            <div
                              className={`rounded-full px-2.5 py-1 text-[10px] font-semibold ${
                                isPrimary
                                  ? "bg-emerald-600/90 text-white"
                                  : "bg-white/90 text-slate-700"
                              }`}
                            >
                              {isPrimary ? "Principal" : `Nueva ${i + 1}`}
                            </div>

                            <button
                              type="button"
                              onClick={() => moveNewImage(i, "right")}
                              disabled={i === images.length - 1}
                              className="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white"
                              aria-label="Mover a la derecha"
                            >
                              <Icon name="chevronRight" size={14} />
                            </button>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            <p className="text-xs text-slate-400">
              La primera imagen visible será la portada principal.
            </p>
          </div>
        )}
      </div>
    </section>
  );
}