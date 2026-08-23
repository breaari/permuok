import { useEffect, useMemo, useState } from "react";
import {
  Navigate,
  useLocation,
  useNavigate,
  useParams,
} from "react-router-dom";

import { Icon } from "../../../ui/icons/Index";
import { useToast } from "../../../ui/toast/ToastProvider";
import { getErrorMessage } from "../../../api/http";
import { useAuth } from "../../auth/components/AuthContext";
import { useGoogleMaps } from "../../../ui/maps/UseGoogleMaps";
import { normalizeAmenities as normalizeAmenityList } from "../../shared/helpers/amenities";
import DevelopmentQualityOptimizer from "../components/DevelopmentQualityOptimizer";

import {
  createDevelopmentDraft,
  deleteDevelopment,
  deleteDevelopmentImage,
  getDevelopmentDetail,
  reorderDevelopmentImages,
  updateDevelopmentDraft,
  uploadDevelopmentImages,
  publishDevelopment,
  pauseDevelopment,
  archiveDevelopment,
  replaceDevelopmentAmenities,
  getDevelopmentQuality,
  requestDevelopmentAIAnalysis,
} from "../api/developments.api";

import DevelopmentFormProgress from "../components/DevelopmentFormProgress";
import DevelopmentFormHeaderActions from "../components/DevelopmentFormHeaderActions";
import DevelopmentBasicSection from "../components/DevelopmentBasicSection";
import DevelopmentCommercialSection from "../components/DevelopmentCommercialSection";
import DevelopmentImagesSection from "../components/DevelopmentImagesSection";
import DevelopmentUnitTypesSection from "../components/DevelopmentUnitTypesSection";
import DevelopmentAmenitiesSection from "../components/DevelopmentAmenitiesSection";
import PropertyLocationSection from "../../properties/components/PropertyLocationSection";

const initialForm = {
  title: "",
  slug: "",
  description: "",
  short_description: "",
  developer_name: "",
  construction_company: "",
  development_stage: "",
  delivery_date_estimated: "",
  country_code: "",
  country: "",
  province: "",
  city: "",
  zone: "",
  address: "",
  formatted_address: "",
  postal_code: "",
  place_id: "",
  latitude: "",
  longitude: "",
  price_from: "",
  price_to: "",
  currency: "USD",
  total_units: "",
  available_units: "",
  whatsapp_url: "",
  brochure_url: "",
  video_url: "",
};

function normalizeDetailToForm(detail) {
  const development = detail?.development || {};

  return {
    title: development?.title ?? "",
    slug: development?.slug ?? "",
    description: development?.description ?? "",
    short_description: development?.short_description ?? "",
    developer_name: development?.developer_name ?? "",
    construction_company: development?.construction_company ?? "",
    development_stage: development?.development_stage ?? "",
    delivery_date_estimated: development?.delivery_date_estimated ?? "",
    country_code: development?.country_code ?? "",
    country: development?.country ?? "",
    province: development?.province ?? "",
    city: development?.city ?? "",
    zone: development?.zone ?? "",
    address: development?.address ?? "",
    formatted_address: development?.formatted_address ?? "",
    postal_code: development?.postal_code ?? "",
    place_id: development?.place_id ?? "",
    latitude: development?.latitude ?? "",
    longitude: development?.longitude ?? "",
    price_from: development?.price_from ?? "",
    price_to: development?.price_to ?? "",
    currency: development?.currency ?? "USD",
    total_units: development?.total_units ?? "",
    available_units: development?.available_units ?? "",
    whatsapp_url: development?.whatsapp_url ?? "",
    brochure_url: development?.brochure_url ?? "",
    video_url: development?.video_url ?? "",
  };
}

function normalizeExistingImages(detail) {
  const images = Array.isArray(detail?.images) ? detail.images : [];

  return images.map((img, index) => ({
    ...img,
    is_cover: index === 0 || Number(img?.is_cover) === 1,
    sort_order: Number.isFinite(Number(img?.sort_order))
      ? Number(img.sort_order)
      : index,
  }));
}

function normalizeUnitTypes(detail) {
  return Array.isArray(detail?.unit_types) ? detail.unit_types : [];
}

function normalizeAmenities(detail) {
  return normalizeAmenityList(detail?.amenities);
}

function hasValidLocation(form) {
  return (
    !!form?.place_id &&
    form?.latitude !== null &&
    form?.latitude !== "" &&
    form?.longitude !== null &&
    form?.longitude !== "" &&
    !!form?.formatted_address
  );
}

function getDevelopmentIdFromResult(result) {
  return result?.development?.id || result?.item?.id || result?.id || null;
}

export default function DevelopmentForm() {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const { id } = useParams();
  const { isLoaded: googleMapsLoaded, loadError } = useGoogleMaps();

  const role = Number(user?.role || 0);
  const canAccess = role === 2 || role === 3;
  const isEditMode = Boolean(id);

  const [currentStep, setCurrentStep] = useState(
    location.state?.startStep === 2 ? 2 : 1,
  );

  const [form, setForm] = useState(initialForm);
  const [detail, setDetail] = useState(null);

  const [images, setImages] = useState([]);
  const [existingImages, setExistingImages] = useState([]);
  const [initialExistingImageIds, setInitialExistingImageIds] = useState([]);
  const [unitTypes, setUnitTypes] = useState([]);
  const [amenities, setAmenities] = useState([]);

  const [isLocationValid, setIsLocationValid] = useState(false);

  const [initialLoading, setInitialLoading] = useState(isEditMode);
  const [initialError, setInitialError] = useState("");

  const [isSubmitting, setIsSubmitting] = useState(false);

  const [quality, setQuality] = useState(null);
  const [qualityLoading, setQualityLoading] = useState(false);
  const [aiAnalysisRequesting, setAIAnalysisRequesting] = useState(false);

  const backPath = useMemo(() => {
    return location.state?.from || "/developments";
  }, [location.state]);

  const developmentStatus = detail?.development?.status || "draft";
  const totalImages = existingImages.length + images.length;

  const canPreview =
    isEditMode &&
    unitTypes.length > 0 &&
    amenities.length > 0 &&
    totalImages > 0 &&
    !!form.title.trim() &&
    !!form.description.trim() &&
    isLocationValid;

  function setField(field, value) {
    setForm((prev) => ({
      ...prev,
      [field]: value,
    }));
  }

  function showError(message) {
    if (message) toast.error(message);
  }

  function showSuccess(message) {
    if (message) toast.success(message);
  }

  function showWarning(message) {
    if (message) toast.warning(message);
  }

  function handlePreview() {
    if (!id || !canPreview) return;
    window.open(`/developments/${id}`, "_blank");
  }

  function getPublishValidationError() {
    if (!isEditMode) {
      return "Primero guardá el desarrollo para poder publicarlo.";
    }

    if (!form.title.trim()) {
      return "Completá el título del desarrollo.";
    }

    if (!form.description.trim()) {
      return "Completá la descripción del desarrollo.";
    }

    if (!isLocationValid) {
      return "Seleccioná una dirección válida desde Google Maps.";
    }

    if (!totalImages) {
      return "Cargá al menos una imagen para poder publicar.";
    }

    if (!unitTypes.length) {
      return "Cargá al menos una tipología para poder publicar.";
    }

    if (!amenities.length) {
      return "Seleccioná al menos una amenity para poder publicar.";
    }

    return "";
  }

  async function refreshDevelopmentState(developmentId) {
    const fresh = await getDevelopmentDetail(developmentId, false);
    setDetail(fresh);
    setForm(normalizeDetailToForm(fresh));

    const normalizedImages = normalizeExistingImages(fresh);
    setExistingImages(normalizedImages);
    setInitialExistingImageIds(
      normalizedImages
        .map((img) => Number(img?.id))
        .filter((imgId) => Number.isFinite(imgId) && imgId > 0),
    );

    setUnitTypes(normalizeUnitTypes(fresh));
    setAmenities(normalizeAmenities(fresh));
    setIsLocationValid(hasValidLocation(fresh?.development || {}));

    return fresh;
  }

  async function refreshQualityState(developmentId = Number(id)) {
    if (!developmentId) {
      return null;
    }

    try {
      setQualityLoading(true);

      const result = await getDevelopmentQuality(developmentId);

      setQuality(result);

      return result;
    } catch (error) {
      console.error(
        "[DEVELOPMENT QUALITY] No se pudo refrescar el índice:",
        error,
      );

      return null;
    } finally {
      setQualityLoading(false);
    }
  }

  async function syncImagesForEdit(developmentId) {
    const currentExistingIds = existingImages
      .map((img) => Number(img?.id))
      .filter((imgId) => Number.isFinite(imgId) && imgId > 0);

    const deletedExistingIds = initialExistingImageIds.filter(
      (imgId) => !currentExistingIds.includes(imgId),
    );

    for (const imageId of deletedExistingIds) {
      await deleteDevelopmentImage(imageId);
    }

    let latestDetail = null;

    if (images.length) {
      latestDetail = await uploadDevelopmentImages(developmentId, images);
    }

    if (!latestDetail) {
      latestDetail = await getDevelopmentDetail(developmentId, false);
    }

    const latestImages = Array.isArray(latestDetail?.images)
      ? latestDetail.images
      : [];

    const latestIds = latestImages
      .map((img) => Number(img?.id))
      .filter((imgId) => Number.isFinite(imgId) && imgId > 0);

    const newUploadedIds = latestIds.filter(
      (imgId) => !currentExistingIds.includes(imgId),
    );

    const finalOrderedIds = [...currentExistingIds, ...newUploadedIds];

    if (!finalOrderedIds.length) {
      setImages([]);
      return;
    }

    const reorderPayload = finalOrderedIds.map((imageId, index) => ({
      id: imageId,
      is_cover: index === 0,
    }));

    await reorderDevelopmentImages(developmentId, reorderPayload);
    setImages([]);
  }

  async function syncAmenitiesForEdit(developmentId) {
    const selectedAmenities = Array.isArray(amenities) ? amenities : [];

    const response = await replaceDevelopmentAmenities(
      developmentId,
      selectedAmenities,
    );

    setAmenities(Array.isArray(response?.items) ? response.items : []);
  }

  async function syncRelatedData(developmentId) {
    await syncImagesForEdit(developmentId);
    await syncAmenitiesForEdit(developmentId);
  }

  async function createOrUpdateDraft({
    successMessage = "",
    navigateAfterCreate = false,
    nextStepAfterCreate = 1,
    refreshAfterSave = true,
  } = {}) {
    if (isEditMode) {
      const developmentId = Number(id);

      await updateDevelopmentDraft(developmentId, form);
      await syncRelatedData(developmentId);

      if (refreshAfterSave) {
        await refreshDevelopmentState(developmentId);
      }

      if (successMessage) {
        showSuccess(successMessage);
      }

      return developmentId;
    }

    const result = await createDevelopmentDraft(form);
    const developmentId = getDevelopmentIdFromResult(result);

    if (!developmentId) {
      throw new Error("No se pudo obtener el ID del desarrollo.");
    }

    if (images.length) {
      await uploadDevelopmentImages(developmentId, images);
      setImages([]);
    }

    if (Array.isArray(amenities) && amenities.length) {
      await replaceDevelopmentAmenities(developmentId, amenities);
    }

    if (successMessage) {
      showSuccess(successMessage);
    }

    if (navigateAfterCreate) {
      navigate(`/developments/${developmentId}/edit`, {
        replace: true,
        state: {
          from: backPath,
          startStep: nextStepAfterCreate,
        },
      });
    }

    return developmentId;
  }

  useEffect(() => {
    if (!isEditMode) return;

    let cancelled = false;

    async function loadDetail() {
      try {
        setInitialLoading(true);
        setInitialError("");

        const data = await getDevelopmentDetail(id, false);

        if (cancelled) return;

        setDetail(data);
        setForm(normalizeDetailToForm(data));

        const normalizedImages = normalizeExistingImages(data);
        setExistingImages(normalizedImages);
        setInitialExistingImageIds(
          normalizedImages
            .map((img) => Number(img?.id))
            .filter((imgId) => Number.isFinite(imgId) && imgId > 0),
        );

        setUnitTypes(normalizeUnitTypes(data));
        setAmenities(normalizeAmenities(data));
        setIsLocationValid(hasValidLocation(data?.development || {}));
        try {
          const currentQuality = await getDevelopmentQuality(Number(id));

          if (!cancelled) {
            setQuality(currentQuality);
          }
        } catch (error) {
          console.error(
            "[DEVELOPMENT QUALITY] No se pudo cargar el índice:",
            error,
          );

          if (!cancelled) {
            setQuality(null);
          }
        }

        if (location.state?.startStep === 2) {
          setCurrentStep(2);
        }
      } catch (err) {
        if (cancelled) return;
        setInitialError(
          getErrorMessage(err, "No se pudo cargar el desarrollo."),
        );
      } finally {
        if (!cancelled) {
          setInitialLoading(false);
        }
      }
    }

    loadDetail();

    return () => {
      cancelled = true;
    };
  }, [id, isEditMode, location.state]);

  useEffect(() => {
    if (!isEditMode || !id || quality?.status !== "waiting_ai") {
      return;
    }

    let cancelled = false;
    let timeoutId = null;

    const startedAt = Date.now();

    const POLL_INTERVAL_MS = 4000;
    const MAX_POLLING_TIME_MS = 120000;

    async function checkQuality() {
      if (cancelled) {
        return;
      }

      if (Date.now() - startedAt > MAX_POLLING_TIME_MS) {
        return;
      }

      const result = await refreshQualityState(Number(id));

      if (cancelled) {
        return;
      }

      if (result?.status === "waiting_ai") {
        timeoutId = window.setTimeout(checkQuality, POLL_INTERVAL_MS);
      }
    }

    timeoutId = window.setTimeout(checkQuality, POLL_INTERVAL_MS);

    return () => {
      cancelled = true;

      if (timeoutId) {
        window.clearTimeout(timeoutId);
      }
    };
  }, [id, isEditMode, quality?.status]);

  if (!canAccess) {
    return <Navigate to="/" replace />;
  }

  if (loadError) {
    return (
      <div className="max-w-4xl mx-auto py-10 px-4">
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          No se pudo cargar Google Maps. Revisá la API key y la configuración.
        </div>
      </div>
    );
  }

  async function handleSaveDraft() {
    if (isSubmitting) return;

    try {
      setIsSubmitting(true);

      await createOrUpdateDraft({
        successMessage: isEditMode
          ? "Borrador actualizado correctamente."
          : "Borrador creado correctamente.",
        navigateAfterCreate: !isEditMode,
        nextStepAfterCreate: currentStep,
      });
    } catch (err) {
      showError(getErrorMessage(err, "No se pudo guardar el borrador."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handlePublish() {
    if (isSubmitting) return;

    const validationError = getPublishValidationError();

    if (validationError) {
      showError(validationError);
      return;
    }

    try {
      setIsSubmitting(true);

      const developmentId = Number(id);

      await updateDevelopmentDraft(developmentId, form);
      await syncRelatedData(developmentId);
      await publishDevelopment(developmentId);
      await refreshDevelopmentState(developmentId);

      showSuccess("Desarrollo publicado correctamente.");
    } catch (err) {
      showError(getErrorMessage(err, "No se pudo publicar el desarrollo."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handlePause() {
    if (isSubmitting || !isEditMode) return;

    try {
      setIsSubmitting(true);

      await pauseDevelopment(Number(id));
      await refreshDevelopmentState(Number(id));

      showSuccess("Desarrollo pausado correctamente.");
    } catch (err) {
      showError(getErrorMessage(err, "No se pudo pausar el desarrollo."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleArchive() {
    if (isSubmitting || !isEditMode) return;

    try {
      setIsSubmitting(true);

      await archiveDevelopment(Number(id));
      await refreshDevelopmentState(Number(id));

      showSuccess("Desarrollo archivado correctamente.");
    } catch (err) {
      showError(getErrorMessage(err, "No se pudo archivar el desarrollo."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (isSubmitting || !isEditMode) return;

    try {
      setIsSubmitting(true);

      await deleteDevelopment(Number(id));
      showSuccess("Desarrollo eliminado correctamente.");
      navigate("/developments", { replace: true });
    } catch (err) {
      showError(getErrorMessage(err, "No se pudo eliminar el desarrollo."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleContinueToStepTwo() {
    if (isSubmitting) return;

    try {
      if (!googleMapsLoaded) {
        throw new Error("Google Maps todavía se está cargando.");
      }

      if (!isLocationValid) {
        throw new Error("Seleccioná una dirección válida desde Google Maps.");
      }

      if (!form.title.trim()) {
        throw new Error("Completá el título del desarrollo.");
      }

      if (!form.description.trim()) {
        throw new Error("Completá la descripción del desarrollo.");
      }

      setIsSubmitting(true);

      if (isEditMode) {
        await createOrUpdateDraft({
          successMessage: "",
          refreshAfterSave: true,
        });

        setCurrentStep(2);
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
      }

      await createOrUpdateDraft({
        successMessage: "",
        navigateAfterCreate: true,
        nextStepAfterCreate: 2,
      });
    } catch (error) {
      showError(error.message || "No se pudo avanzar al paso 2.");
    } finally {
      setIsSubmitting(false);
    }
  }

  function handleBackToStepOne() {
    setCurrentStep(1);

    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function handleQuickUpdateStepOne() {
    await handleSaveDraft();
  }

  async function handleFinalPrimaryAction() {
    await handleSaveDraft();
  }

  if (initialLoading) {
    return (
      <div className="max-w-4xl mx-auto py-10 px-4">
        <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
          Cargando desarrollo...
        </div>
      </div>
    );
  }

  if (initialError) {
    return (
      <div className="max-w-4xl mx-auto py-10 px-4 space-y-4">
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
          {initialError}
        </div>

        <button
          type="button"
          onClick={() => navigate("/developments")}
          className="px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700"
        >
          Volver
        </button>
      </div>
    );
  }

  async function handleRequestAIAnalysis() {
    if (!isEditMode || aiAnalysisRequesting) {
      return;
    }

    try {
      setAIAnalysisRequesting(true);

      const result = await requestDevelopmentAIAnalysis(Number(id));

      if (result?.status === "completed" && result?.quality) {
        setQuality(result.quality);

        if (result?.reused === true) {
          showSuccess(
            "El desarrollo no tiene cambios desde el último análisis.",
          );
        }

        return;
      }

      await refreshQualityState(Number(id));
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo iniciar el análisis IA."));
    } finally {
      setAIAnalysisRequesting(false);
    }
  }

  return (
    <>
      <form
        onSubmit={(e) => e.preventDefault()}
        className="py-8 sm:py-10 md:py-12 px-4 sm:px-6 md:px-10 w-full max-w-4xl mx-auto space-y-6 sm:space-y-8"
      >
        {currentStep === 1 && (
          <>
            <DevelopmentFormProgress
              currentStep={currentStep}
              isEditMode={isEditMode}
            />

            <DevelopmentFormHeaderActions
              status={developmentStatus}
              isEditMode={isEditMode}
              isSubmitting={isSubmitting}
              onSaveDraft={handleSaveDraft}
              onPublish={handlePublish}
              onPause={handlePause}
              onArchive={handleArchive}
              onDelete={handleDelete}
              onPreview={handlePreview}
              canPreview={canPreview}
            />

            {isEditMode && quality && (
              <DevelopmentQualityOptimizer
                quality={quality}
                qualityLoading={qualityLoading}
                aiAnalysisRequesting={aiAnalysisRequesting}
                onRequestAIAnalysis={handleRequestAIAnalysis}
              />
            )}

            <DevelopmentBasicSection form={form} setField={setField} />

            <PropertyLocationSection
              form={form}
              setField={setField}
              googleMapsLoaded={googleMapsLoaded}
              onLocationValidityChange={setIsLocationValid}
            />

            <DevelopmentCommercialSection form={form} setField={setField} />

            <DevelopmentImagesSection
              onWarning={showWarning}
              images={images}
              setImages={setImages}
              existingImages={existingImages}
              setExistingImages={setExistingImages}
              onError={showError}
              onSuccess={showSuccess}
            />

            <div className="pt-2 flex flex-col sm:flex-row gap-3">
              {isEditMode && (
                <button
                  type="button"
                  disabled={isSubmitting}
                  onClick={handleQuickUpdateStepOne}
                  className="w-full sm:w-auto sm:min-w-[220px] bg-emerald-600 text-white py-4 px-6 rounded-lg font-bold text-base flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 active:scale-95 transition-all hover:opacity-95 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                  <Icon name="checkCircle" size={18} />
                  {isSubmitting ? "Actualizando..." : "Actualizar datos"}
                </button>
              )}

              <button
                type="button"
                disabled={isSubmitting}
                onClick={handleContinueToStepTwo}
                className="w-full bg-slate-900 text-white py-4 rounded-lg font-bold text-base sm:text-lg flex items-center justify-center gap-2 shadow-lg active:scale-95 transition-all hover:opacity-95 disabled:opacity-60 disabled:cursor-not-allowed"
              >
                Continuar al Paso 2
                <Icon name="arrowRight" size={18} />
              </button>
            </div>
          </>
        )}

        {currentStep === 2 && (
          <>
            <DevelopmentFormProgress
              currentStep={currentStep}
              isEditMode={isEditMode}
            />

            <DevelopmentFormHeaderActions
              status={developmentStatus}
              isEditMode={isEditMode}
              isSubmitting={isSubmitting}
              onSaveDraft={handleSaveDraft}
              onPublish={handlePublish}
              onPause={handlePause}
              onArchive={handleArchive}
              onDelete={handleDelete}
              onPreview={handlePreview}
              canPreview={canPreview}
            />

            <DevelopmentUnitTypesSection
              developmentId={
                detail?.development?.id || (isEditMode ? Number(id) : null)
              }
              unitTypes={unitTypes}
              setUnitTypes={setUnitTypes}
              onError={showError}
              onSuccess={showSuccess}
            />

            <DevelopmentAmenitiesSection
              amenities={amenities}
              setAmenities={setAmenities}
            />

            <div className="sticky bottom-4">
              <div className="rounded-2xl border border-slate-200 bg-white/95 backdrop-blur px-4 sm:px-5 py-4 shadow-lg">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">
                      Último paso
                    </p>
                    <p className="text-xs sm:text-sm text-slate-500 mt-1">
                      Revisá las tipologías y amenities antes de finalizar.
                    </p>
                  </div>

                  <div className="flex flex-col-reverse sm:flex-row gap-3">
                    <button
                      type="button"
                      disabled={isSubmitting}
                      onClick={handleBackToStepOne}
                      className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                      <Icon name="chevronLeft" size={18} />
                      Volver al Paso 1
                    </button>

                    <button
                      type="button"
                      disabled={isSubmitting}
                      onClick={handleFinalPrimaryAction}
                      className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 hover:opacity-90 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                      <Icon name="checkCircle" size={18} />
                      {isSubmitting
                        ? isEditMode
                          ? "Actualizando..."
                          : "Guardando..."
                        : isEditMode
                          ? "Actualizar datos"
                          : "Finalizar desarrollo"}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </>
        )}
      </form>
    </>
  );
}
