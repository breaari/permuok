import { useEffect, useMemo, useState } from "react";
import { getApiBaseUrl } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";

const MAX_IMAGES = 5;
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 MB
const MIN_IMAGE_WIDTH = 800;
const MIN_IMAGE_HEIGHT = 600;
const ALLOWED_IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp"];

function resolveImageUrl(path) {
  if (!path) return "";
  if (String(path).startsWith("http")) return path;
  return `${getApiBaseUrl()}${path}`;
}

function fileSignature(file) {
  return `${file.name}-${file.size}-${file.lastModified}`;
}

function getImageDimensions(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();

    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve({
        width: img.naturalWidth,
        height: img.naturalHeight,
      });
    };

    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("No se pudo leer la imagen."));
    };

    img.src = url;
  });
}

export default function DevelopmentImagesSection({
  images,
  setImages,
  existingImages = [],
  setExistingImages = () => {},
  onError,
  onSuccess,
  onWarning,
}) {
  const [newPreviews, setNewPreviews] = useState([]);
  const [checkingImages, setCheckingImages] = useState(false);

  function clearMessages() {
    onError?.("");
    onSuccess?.("");
    onWarning?.("");
  }

  function normalizeIncomingFiles(files) {
    return files.map((file, index) => ({
      id:
        typeof crypto !== "undefined" && crypto.randomUUID
          ? `${file.name}-${file.lastModified}-${index}-${crypto.randomUUID()}`
          : `${file.name}-${file.lastModified}-${index}-${Date.now()}`,
      file,
    }));
  }

  async function handleFiles(e) {
    clearMessages();

    const selectedFiles = Array.from(e.target.files || []);
    if (!selectedFiles.length) return;

    try {
      setCheckingImages(true);

      const totalCurrent = existingImages.length + images.length;
      const availableSlots = MAX_IMAGES - totalCurrent;

      if (availableSlots <= 0) {
        onError?.(`Solo podés cargar hasta ${MAX_IMAGES} imágenes.`);
        e.target.value = "";
        return;
      }

      const currentNewSignatures = images
        .map((item) => item?.file)
        .filter(Boolean)
        .map(fileSignature);

      const acceptedFiles = [];
      const rejectedMessages = [];

      for (const file of selectedFiles) {
        const signature = fileSignature(file);

        if (acceptedFiles.length >= availableSlots) {
          rejectedMessages.push(
            `"${file.name}" no se agregó porque alcanzaste el máximo de ${MAX_IMAGES} imágenes.`,
          );
          continue;
        }

        if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
          rejectedMessages.push(
            `"${file.name}" no tiene un formato válido. Usá JPG, PNG o WebP.`,
          );
          continue;
        }

        if (file.size > MAX_IMAGE_SIZE) {
          rejectedMessages.push(`"${file.name}" supera los 5 MB.`);
          continue;
        }

        if (
          currentNewSignatures.includes(signature) ||
          acceptedFiles.map(fileSignature).includes(signature)
        ) {
          rejectedMessages.push(`"${file.name}" ya fue seleccionada.`);
          continue;
        }

        try {
          const dimensions = await getImageDimensions(file);

          if (
            dimensions.width < MIN_IMAGE_WIDTH ||
            dimensions.height < MIN_IMAGE_HEIGHT
          ) {
            rejectedMessages.push(
              `La imagen "${file.name}" mide ${dimensions.width} × ${dimensions.height}px. Recomendamos usar imágenes de al menos ${MIN_IMAGE_WIDTH} × ${MIN_IMAGE_HEIGHT}px para mejor calidad.`,
            );
          }
        } catch {
          rejectedMessages.push(`"${file.name}" no pudo leerse como imagen.`);
          continue;
        }

        acceptedFiles.push(file);
      }

      if (!acceptedFiles.length) {
        onError?.(rejectedMessages.join(" "));
        e.target.value = "";
        return;
      }

      const normalized = normalizeIncomingFiles(acceptedFiles);
      setImages((prev) => [...prev, ...normalized]);

      if (rejectedMessages.length) {
        onWarning?.(
          `Se agregaron ${acceptedFiles.length} imagen${
            acceptedFiles.length !== 1 ? "es" : ""
          } correctamente. ${rejectedMessages.join(" ")}`,
        );
      } else {
        onSuccess?.(
          `${acceptedFiles.length} imagen${
            acceptedFiles.length !== 1 ? "es" : ""
          } agregada${acceptedFiles.length !== 1 ? "s" : ""} correctamente.`,
        );
      }

      e.target.value = "";
    } finally {
      setCheckingImages(false);
    }
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

  function setExistingAsCover(index) {
    clearMessages();

    setExistingImages((prev) => {
      if (index < 0 || index >= prev.length) return prev;

      const selected = prev[index];
      const rest = prev.filter((_, i) => i !== index);

      return [selected, ...rest].map((item, idx) => ({
        ...item,
        is_cover: idx === 0,
        sort_order: idx,
      }));
    });
  }

  function setNewAsCover(index) {
    clearMessages();

    setImages((prev) => {
      if (index < 0 || index >= prev.length) return prev;

      const selected = prev[index];
      const rest = prev.filter((_, i) => i !== index);

      return [selected, ...rest];
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
    [existingImages.length, images.length],
  );

  const remaining = useMemo(() => MAX_IMAGES - totalImages, [totalImages]);

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Imágenes
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Sumá fotos o renders claros y bien presentados. La primera imagen será
          la portada principal del desarrollo.
        </p>
      </div>

      <div className="space-y-5">
        <label className="block">
          <input
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp"
            onChange={handleFiles}
            className="hidden"
            disabled={totalImages >= MAX_IMAGES || checkingImages}
          />

          <div
            className={`rounded-2xl border-2 border-dashed px-5 py-8 sm:px-6 sm:py-10 text-center transition-colors ${
              totalImages >= MAX_IMAGES || checkingImages
                ? "border-slate-200 bg-slate-50 cursor-not-allowed opacity-70"
                : "border-slate-300 bg-slate-50 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/40"
            }`}
          >
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white border border-slate-200 text-slate-500">
              <Icon name="image" size={20} />
            </div>

            <p className="text-sm sm:text-base font-semibold text-slate-800">
              {checkingImages
                ? "Validando imágenes..."
                : totalImages >= MAX_IMAGES
                  ? "Límite de imágenes alcanzado"
                  : "Hacé click para subir imágenes"}
            </p>

            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              {totalImages >= MAX_IMAGES
                ? `Ya cargaste el máximo de ${MAX_IMAGES} imágenes`
                : `JPG, PNG o WebP. Máximo 5 MB. Recomendado ${MIN_IMAGE_WIDTH} × ${MIN_IMAGE_HEIGHT}px. (${remaining} disponibles)`}
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
                      img?.view_url || img?.url || img?.file_path || "",
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

                        {!isPrimary && (
                          <button
                            type="button"
                            onClick={() => setExistingAsCover(i)}
                            className="absolute top-2 left-2 rounded-full bg-white/85 px-2.5 py-1 text-[10px] font-semibold text-slate-700 shadow-sm transition hover:bg-white"
                          >
                            Usar portada
                          </button>
                        )}

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

                        {!isPrimary && !existingImages.length && (
                          <button
                            type="button"
                            onClick={() => setNewAsCover(i)}
                            className="absolute top-2 left-2 rounded-full bg-white/85 px-2.5 py-1 text-[10px] font-semibold text-slate-700 shadow-sm transition hover:bg-white"
                          >
                            Usar portada
                          </button>
                        )}

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
              La primera imagen visible será la portada principal. Para
              publicar, recomendamos cargar al menos una imagen.
            </p>
          </div>
        )}
      </div>
    </section>
  );
}
