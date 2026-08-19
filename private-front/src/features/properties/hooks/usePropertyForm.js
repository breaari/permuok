import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../../../api/http.js";
import {
  emptyRequirements,
  emptyLocation,
} from "../utils/PropertyFormHelpers.js";
import {
  buildPropertyPayload,
  buildRequirementsPayload,
  mapPropertyToForm,
  mapRequirementsToState,
  normalizeCountryCode,
  validatePropertyForSubmit,
} from "../utils/PropertyFormMappers";
import {
  archiveProperty,
  deleteProperty,
  createPropertyDraft,
  updatePropertyDraft,
  savePropertyRequirements,
  uploadPropertyImages,
  publishProperty,
  reorderPropertyImages,
  getPropertyAIAnalysis,
  requestPropertyAIAnalysis,
  generatePropertyAITitle,
  generatePropertyAIDescription,
} from "../api/properties.api.js";
import { useToast } from "../../../ui/toast/ToastProvider";

export function usePropertyForm({ googleMapsLoaded }) {
  const navigate = useNavigate();
  const toast = useToast();
  const { id } = useParams();

  const isEditMode = useMemo(() => !!id, [id]);

  const [currentStep, setCurrentStep] = useState(1);
  const [publishChoiceOpen, setPublishChoiceOpen] = useState(false);

  const [propertyStatus, setPropertyStatus] = useState("draft");

  const [form, setForm] = useState({
    title: "",
    description: "",
    property_type: "",
    price: "",
    currency: "USD",
    total_area: "",
    covered_area: "",
    bedrooms: "",
    bathrooms: "",
    garages: "",
    antiquity: "",
    country: "Argentina",
    province: "",
    city: "",
    zone: "",
    address: "",
    formatted_address: "",
    place_id: "",
    latitude: "",
    longitude: "",
    status: "draft",
    amenities: [],
  });

  const [requirements, setRequirements] = useState(emptyRequirements());
  const [images, setImages] = useState([]);
  const [existingImages, setExistingImages] = useState([]);
  const [initialExistingImageIds, setInitialExistingImageIds] = useState([]);
  const [isLocationValid, setIsLocationValid] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [initialLoading, setInitialLoading] = useState(isEditMode);
  const [initialError, setInitialError] = useState("");
  const [quality, setQuality] = useState(null);
  const [qualityV2, setQualityV2] = useState(null);
  const [aiAnalysis, setAIAnalysis] = useState(null);
  const [aiAnalysisLoading, setAIAnalysisLoading] = useState(false);
  const [aiAnalysisRequesting, setAIAnalysisRequesting] = useState(false);
  const [aiTitleSuggestion, setAITitleSuggestion] = useState("");
  const [aiDescriptionSuggestion, setAIDescriptionSuggestion] = useState("");

  const [aiTitleLoading, setAITitleLoading] = useState(false);
  const [aiDescriptionLoading, setAIDescriptionLoading] = useState(false);
  useEffect(() => {
    if (!isEditMode) return;

    let cancelled = false;

    async function loadProperty() {
      try {
        setInitialLoading(true);
        setInitialError("");

        const res = await api.get(`/properties/${id}`);
        const data = unwrap(res);

        if (cancelled) return;

        const property = data?.property || {};
        const requirementsData = data?.requirements || null;
        const requirementLocations = Array.isArray(data?.requirement_locations)
          ? data.requirement_locations
          : [];
        const requirementPropertyTypes = Array.isArray(
          data?.requirement_property_types,
        )
          ? data.requirement_property_types
          : [];
        const serverImages = Array.isArray(data?.images) ? data.images : [];
        const serverQuality = data?.quality || null;
        const serverQualityV2 = data?.quality_v2 || null;
        const serverAmenities = Array.isArray(data?.amenities)
          ? data.amenities
          : [];

        setForm({
          ...mapPropertyToForm(property),
          amenities: serverAmenities,
        });
        setPropertyStatus(property?.status || "draft");

        setRequirements(
          mapRequirementsToState(
            requirementsData,
            requirementPropertyTypes,
            requirementLocations,
          ),
        );

        setExistingImages(serverImages);
        setQuality(serverQuality);
        setQualityV2(serverQualityV2);
        try {
          let analysis = await getPropertyAIAnalysis(Number(id));

          if (analysis?.status === "pending") {
            const queued = await requestPropertyAIAnalysis(Number(id));

            analysis = {
              ...analysis,
              id: queued?.analysis_id ?? analysis?.id ?? null,
              status: queued?.status || analysis?.status || "pending",
            };
          }

          if (!cancelled) {
            setAIAnalysis(analysis);
          }
        } catch (error) {
          /*
           * No bloqueamos la carga de la propiedad
           * si solamente falla el optimizador IA.
           */
          console.error("[PROPERTY AI] No se pudo cargar el análisis:", error);

          if (!cancelled) {
            setAIAnalysis(null);
          }
        }
        setInitialExistingImageIds(
          serverImages
            .map((img) => Number(img?.id))
            .filter((imgId) => Number.isFinite(imgId) && imgId > 0),
        );
        setImages([]);

        setIsLocationValid(
          !!property?.place_id &&
            !!property?.formatted_address &&
            property?.latitude !== null &&
            property?.latitude !== "" &&
            property?.longitude !== null &&
            property?.longitude !== "",
        );
      } catch (error) {
        if (cancelled) return;
        setInitialError(
          getErrorMessage(error, "No se pudo cargar la propiedad"),
        );
      } finally {
        if (!cancelled) {
          setInitialLoading(false);
        }
      }
    }

    loadProperty();

    return () => {
      cancelled = true;
    };
  }, [id, isEditMode]);

  function setField(name, value) {
    setForm((prev) => ({ ...prev, [name]: value }));
  }

  function setRequirementField(name, value) {
    setRequirements((prev) => ({ ...prev, [name]: value }));
  }

  function addRequirementLocation(location = null) {
    setRequirements((prev) => ({
      ...prev,
      locations: [
        ...(prev.locations || []),
        location || emptyLocation(form.country),
      ],
    }));
  }

  function updateRequirementLocation(index, field, value) {
    setRequirements((prev) => ({
      ...prev,
      locations: (prev.locations || []).map((loc, i) => {
        if (i !== index) return loc;

        const next = { ...loc, [field]: value };

        if (field === "country") {
          next.country_code = normalizeCountryCode(value);
        }

        return next;
      }),
    }));
  }

  function removeRequirementLocation(index) {
    setRequirements((prev) => ({
      ...prev,
      locations: (prev.locations || []).filter((_, i) => i !== index),
    }));
  }

  function validateBeforeSubmit() {
    validatePropertyForSubmit({
      googleMapsLoaded,
      isLocationValid,
      form,
      requirements,
      isEditMode,
      images,
      existingImages,
    });
  }

  async function refreshPropertyState(propertyId) {
    const detailRes = await api.get(
      `/properties/${propertyId}?_=${Date.now()}`,
    );

    const detail = unwrap(detailRes);

    const property = detail?.property || {};
    const refreshedQuality = detail?.quality || null;
    const refreshedQualityV2 = detail?.quality_v2 || null;
    let refreshedAIAnalysis = null;

    try {
      refreshedAIAnalysis = await getPropertyAIAnalysis(propertyId);
    } catch (error) {
      console.error(
        "[PROPERTY AI] No se pudo refrescar el análisis actual:",
        error,
      );
    }
    setQuality(refreshedQuality);
    setQualityV2(refreshedQualityV2);
    setAIAnalysis(refreshedAIAnalysis);
    const refreshedImages = Array.isArray(detail?.images) ? detail.images : [];

    const refreshedAmenities = Array.isArray(detail?.amenities)
      ? detail.amenities
      : [];

    const requirementsData = detail?.requirements || null;

    const requirementLocations = Array.isArray(detail?.requirement_locations)
      ? detail.requirement_locations
      : [];

    const requirementPropertyTypes = Array.isArray(
      detail?.requirement_property_types,
    )
      ? detail.requirement_property_types
      : [];

    setForm({
      ...mapPropertyToForm(property),
      amenities: refreshedAmenities,
    });

    setAITitleSuggestion("");
    setAIDescriptionSuggestion("");

    setRequirements(
      mapRequirementsToState(
        requirementsData,
        requirementPropertyTypes,
        requirementLocations,
      ),
    );

    setPropertyStatus(property?.status || propertyStatus);
    setQuality(refreshedQuality);
    setQualityV2(refreshedQualityV2);
    setExistingImages(refreshedImages);

    setInitialExistingImageIds(
      refreshedImages
        .map((image) => Number(image?.id))
        .filter((imageId) => Number.isFinite(imageId) && imageId > 0),
    );

    setImages([]);

    setIsLocationValid(
      !!property?.place_id &&
        !!property?.formatted_address &&
        property?.latitude !== null &&
        property?.latitude !== "" &&
        property?.longitude !== null &&
        property?.longitude !== "",
    );

    return detail;
  }
  function showError(message) {
    if (message) toast.error(message);
  }

  function showSuccess(message) {
    if (message) toast.success(message);
  }
  async function syncImagesForEdit(propertyId) {
    const currentExistingIds = existingImages
      .map((image) => Number(image?.id))
      .filter((imageId) => Number.isFinite(imageId) && imageId > 0);

    const deletedExistingIds = initialExistingImageIds.filter(
      (imageId) => !currentExistingIds.includes(imageId),
    );

    for (const imageId of deletedExistingIds) {
      await api.del(`/properties/images/${imageId}`);
    }

    let uploadResult = null;

    if (images.length > 0) {
      uploadResult = await uploadPropertyImages(propertyId, images);
    }

    /*
     * Primero intentamos obtener las imágenes desde la respuesta
     * del endpoint de carga.
     */
    const imagesFromUpload = Array.isArray(uploadResult?.images)
      ? uploadResult.images
      : Array.isArray(uploadResult?.data?.images)
        ? uploadResult.data.images
        : Array.isArray(uploadResult?.property?.images)
          ? uploadResult.property.images
          : [];

    /*
     * Luego consultamos el detalle evitando caché.
     */
    const detailResponse = await api.get(
      `/properties/${propertyId}?_=${Date.now()}`,
    );

    const latestDetail = unwrap(detailResponse);

    const imagesFromDetail = Array.isArray(latestDetail?.images)
      ? latestDetail.images
      : [];

    const latestImages =
      imagesFromDetail.length > 0 ? imagesFromDetail : imagesFromUpload;

    const latestIds = latestImages
      .map((image) => Number(image?.id))
      .filter((imageId) => Number.isFinite(imageId) && imageId > 0);

    /*
     * Conservamos solamente imágenes que realmente siguen existiendo.
     */
    const survivingExistingIds = currentExistingIds.filter((imageId) =>
      latestIds.includes(imageId),
    );

    const newUploadedIds = latestIds.filter(
      (imageId) => !currentExistingIds.includes(imageId),
    );

    const finalOrderedIds = [...survivingExistingIds, ...newUploadedIds];

    /*
     * No generamos un error solamente porque el GET inmediato
     * todavía no haya reflejado la nueva imagen.
     * El endpoint de upload ya habría lanzado una excepción real
     * si la carga hubiera fallado.
     */
    if (finalOrderedIds.length > 0) {
      const reorderPayload = finalOrderedIds.map((imageId, index) => ({
        id: imageId,
        is_cover: index === 0,
      }));

      await reorderPropertyImages(propertyId, reorderPayload);
    }

    await refreshPropertyState(propertyId);
  }
  async function syncImagesForCreate(propertyId) {
    const uploadResult = await uploadPropertyImages(propertyId, images);

    const imagesFromUpload = Array.isArray(uploadResult?.images)
      ? uploadResult.images
      : Array.isArray(uploadResult?.data?.images)
        ? uploadResult.data.images
        : Array.isArray(uploadResult?.property?.images)
          ? uploadResult.property.images
          : [];

    const detailResponse = await api.get(
      `/properties/${propertyId}?_=${Date.now()}`,
    );

    const latestDetail = unwrap(detailResponse);

    const imagesFromDetail = Array.isArray(latestDetail?.images)
      ? latestDetail.images
      : [];

    const uploadedImages =
      imagesFromDetail.length > 0 ? imagesFromDetail : imagesFromUpload;

    const reorderPayload = uploadedImages
      .map((image, index) => ({
        id: Number(image?.id),
        is_cover: index === 0,
      }))
      .filter((image) => Number.isFinite(image.id) && image.id > 0);

    if (reorderPayload.length > 0) {
      await reorderPropertyImages(propertyId, reorderPayload);
    }
  }

  async function submitProperty({
    publishNow = false,
    redirectAfterSave = true,
    successMessageOverride = "",
  } = {}) {
    if (isSubmitting) return;

    try {
      validateBeforeSubmit();
      setIsSubmitting(true);

      const propertyPayload = buildPropertyPayload(form);

      let propertyId;

      if (isEditMode) {
        await updatePropertyDraft(id, propertyPayload);
        propertyId = Number(id);
      } else {
        const created = await createPropertyDraft(propertyPayload);
        propertyId = Number(created?.property?.id);
      }

      if (!propertyId) {
        throw new Error("No se pudo obtener el ID de la propiedad.");
      }

      if (isEditMode) {
        await syncImagesForEdit(propertyId);
      } else {
        await syncImagesForCreate(propertyId);
      }

      const requirementsPayload = buildRequirementsPayload(requirements);

      await savePropertyRequirements(propertyId, requirementsPayload);

      if (publishNow) {
        await publishProperty(propertyId);
        setPropertyStatus("published");
      } else if (!isEditMode) {
        setPropertyStatus("draft");
      }

      const successMessage =
        successMessageOverride ||
        (publishNow
          ? isEditMode
            ? "La propiedad fue actualizada y publicada correctamente."
            : "La propiedad fue publicada correctamente."
          : isEditMode
            ? "Los datos de la propiedad se actualizaron correctamente."
            : "La propiedad se guardó como borrador.");

      showSuccess(successMessage);
      setPublishChoiceOpen(false);

      if (redirectAfterSave) {
        setTimeout(() => {
          navigate("/properties");
        }, 600);
      } else if (isEditMode) {
        await refreshPropertyState(propertyId);
      }
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo finalizar la publicación."));
    } finally {
      setIsSubmitting(false);
    }
  }
  async function handlePublish() {
    if (isSubmitting) return;

    if (isEditMode) {
      await submitProperty({
        publishNow: true,
        redirectAfterSave: false,
        successMessageOverride: "La propiedad fue actualizada y publicada.",
      });
      return;
    }

    handleOpenPublishChoice();
  }

  async function handleSaveDraft() {
    if (isSubmitting) return;

    await submitProperty({
      publishNow: false,
      redirectAfterSave: false,
      successMessageOverride: isEditMode
        ? "Los cambios se guardaron correctamente."
        : "La propiedad se guardó como borrador.",
    });

    if (!isEditMode) {
      setPropertyStatus("draft");
    }
  }

  async function handleArchive() {
    if (!isEditMode || isSubmitting) return;

    try {
      setIsSubmitting(true);

      await archiveProperty(Number(id));
      await refreshPropertyState(Number(id));

      setPropertyStatus("archived");
      showSuccess("La publicación fue archivada correctamente.");
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo archivar la publicación."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handlePause() {
    if (!isEditMode || isSubmitting) return;

    try {
      setIsSubmitting(true);

      await api.post(`/properties/${id}/pause`, {});
      await refreshPropertyState(Number(id));

      setPropertyStatus("paused");
      showSuccess("La publicación fue pausada correctamente.");
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo pausar la publicación."));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!isEditMode || isSubmitting) return;

    try {
      setIsSubmitting(true);

      await deleteProperty(Number(id));
      setPropertyStatus("deleted");
      showSuccess("La publicación fue eliminada correctamente.");

      setTimeout(() => {
        navigate("/properties");
      }, 600);
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo eliminar la publicación."));
    } finally {
      setIsSubmitting(false);
    }
  }

  function handleContinueToStepTwo() {
    try {
      if (!googleMapsLoaded) {
        throw new Error("Google Maps todavía se está cargando.");
      }

      if (!isLocationValid) {
        throw new Error("Seleccioná una dirección válida desde Google Maps.");
      }

      if (!form.title.trim()) {
        throw new Error("Completá el título de la publicación.");
      }

      if (!form.description.trim()) {
        throw new Error("Completá la descripción de la propiedad.");
      }

      if (!form.property_type) {
        throw new Error("Seleccioná el tipo de propiedad.");
      }

      setCurrentStep(2);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo avanzar al paso 2."));
    }
  }

  function handleBackToStepOne() {
    setPublishChoiceOpen(false);
    setCurrentStep(1);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function handleOpenPublishChoice() {
    try {
      validateBeforeSubmit();
      setPublishChoiceOpen(true);
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo continuar."));
    }
  }

  function handleQuickUpdateStepOne() {
    submitProperty({
      publishNow: false,
      redirectAfterSave: false,
      successMessageOverride:
        "Los datos del paso 1 se actualizaron correctamente.",
    });
  }

  function handleFinalPrimaryAction() {
    if (isEditMode) {
      submitProperty({
        publishNow: false,
        redirectAfterSave: true,
        successMessageOverride: "La propiedad se actualizó correctamente.",
      });
      return;
    }

    handleOpenPublishChoice();
  }

  async function refreshQualityState(propertyId = Number(id)) {
    if (!propertyId) {
      return null;
    }

    try {
      const response = await api.get(
        `/properties/${propertyId}?_=${Date.now()}`,
      );

      const detail = unwrap(response);

      setQuality(detail?.quality || null);

      setQualityV2(detail?.quality_v2 || null);

      return detail?.quality_v2 || null;
    } catch (error) {
      console.error(
        "[PROPERTY QUALITY] No se pudo refrescar el índice:",
        error,
      );

      return null;
    }
  }
  async function refreshAIAnalysis(propertyId = Number(id)) {
    if (!propertyId) {
      return null;
    }

    try {
      setAIAnalysisLoading(true);

      const analysis = await getPropertyAIAnalysis(propertyId);

      setAIAnalysis(analysis);

      return analysis;
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo consultar el análisis IA."));

      return null;
    } finally {
      setAIAnalysisLoading(false);
    }
  }

  async function handleRequestAIAnalysis() {
    if (!isEditMode || aiAnalysisRequesting) {
      return;
    }

    try {
      setAIAnalysisRequesting(true);

      const result = await requestPropertyAIAnalysis(Number(id));

      /*
       * Si el análisis ya estaba terminado
       * solamente volvemos a consultarlo.
       */
      if (result?.status === "completed") {
        await refreshAIAnalysis(Number(id));

        await refreshQualityState(Number(id));

        return;
      }

      /*
       * Si se inició un análisis nuevo,
       * dejamos de mostrar inmediatamente
       * los resultados del análisis anterior.
       */
      setAIAnalysis({
        id: result?.analysis_id ?? null,
        status: result?.status || "pending",
      });

      /*
       * Actualizamos quality_v2 para que pase
       * de completed a waiting_ai.
       *
       * Así no mostramos como vigente un índice
       * calculado con el análisis anterior.
       */
      await refreshQualityState(Number(id));
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo iniciar el análisis IA."));
    } finally {
      setAIAnalysisRequesting(false);
    }
  }

  async function handleGenerateAITitle() {
    if (!isEditMode || aiTitleLoading) {
      return;
    }

    try {
      setAITitleLoading(true);

      const result = await generatePropertyAITitle(Number(id));

      const content = String(result?.content || "").trim();

      if (!content) {
        throw new Error("La IA no generó un título.");
      }

      setAITitleSuggestion(content);
    } catch (error) {
      showError(getErrorMessage(error, "No se pudo generar el título con IA."));
    } finally {
      setAITitleLoading(false);
    }
  }

  async function handleGenerateAIDescription() {
    if (!isEditMode || aiDescriptionLoading) {
      return;
    }

    try {
      setAIDescriptionLoading(true);

      const result = await generatePropertyAIDescription(Number(id));

      const content = String(result?.content || "").trim();

      if (!content) {
        throw new Error("La IA no generó una descripción.");
      }

      setAIDescriptionSuggestion(content);
    } catch (error) {
      showError(
        getErrorMessage(error, "No se pudo generar la descripción con IA."),
      );
    } finally {
      setAIDescriptionLoading(false);
    }
  }

  function handleApplyAITitle() {
    if (!aiTitleSuggestion) {
      return;
    }

    setField("title", aiTitleSuggestion);
    setAITitleSuggestion("");

    showSuccess("Se aplicó el título sugerido.");
  }

  function handleApplyAIDescription() {
    if (!aiDescriptionSuggestion) {
      return;
    }

    setField("description", aiDescriptionSuggestion);
    setAIDescriptionSuggestion("");

    showSuccess("Se aplicó la descripción sugerida.");
  }

  useEffect(() => {
    if (
      !isEditMode ||
      !id ||
      !["pending", "processing"].includes(aiAnalysis?.status)
    ) {
      return;
    }

    let cancelled = false;
    let timeoutId = null;

    const startedAt = Date.now();

    const POLL_INTERVAL_MS = 4000;
    const MAX_POLLING_TIME_MS = 120000;

    async function checkAnalysis() {
      if (cancelled) {
        return;
      }

      const elapsed = Date.now() - startedAt;

      if (elapsed >= MAX_POLLING_TIME_MS) {
        console.warn(
          "[PROPERTY AI] Se detuvo el polling por superar el tiempo máximo.",
        );

        return;
      }

      try {
        const analysis = await getPropertyAIAnalysis(Number(id));

        if (cancelled) {
          return;
        }

        setAIAnalysis(analysis);

        if (analysis && ["pending", "processing"].includes(analysis.status)) {
          timeoutId = window.setTimeout(checkAnalysis, POLL_INTERVAL_MS);

          return;
        }

        if (analysis?.status === "completed") {
          await refreshQualityState(Number(id));
        }
      } catch (error) {
        console.error("[PROPERTY AI] Error consultando estado:", error);

        if (!cancelled) {
          timeoutId = window.setTimeout(checkAnalysis, POLL_INTERVAL_MS);
        }
      }
    }

    timeoutId = window.setTimeout(checkAnalysis, 1000);

    return () => {
      cancelled = true;

      if (timeoutId) {
        window.clearTimeout(timeoutId);
      }
    };
  }, [id, isEditMode, aiAnalysis?.status]);

  return {
    id,
    isEditMode,
    currentStep,
    setCurrentStep,
    publishChoiceOpen,
    setPublishChoiceOpen,
    propertyStatus,
    setPropertyStatus,

    form,
    setForm,
    setField,

    requirements,
    setRequirements,
    setRequirementField,
    addRequirementLocation,
    updateRequirementLocation,
    removeRequirementLocation,

    images,
    setImages,
    existingImages,
    setExistingImages,

    isLocationValid,
    setIsLocationValid,
    isSubmitting,

    initialLoading,
    initialError,

    handleContinueToStepTwo,
    handleBackToStepOne,
    handleOpenPublishChoice,
    handleQuickUpdateStepOne,
    handleFinalPrimaryAction,
    handleSaveDraft,
    handlePublish,
    handleArchive,
    handlePause,
    handleDelete,
    submitProperty,
    navigate,
    quality,
    qualityV2,
    aiAnalysis,
    aiAnalysisLoading,
    aiAnalysisRequesting,
    refreshAIAnalysis,
    handleRequestAIAnalysis,
    aiTitleSuggestion,
    aiDescriptionSuggestion,
    aiTitleLoading,
    aiDescriptionLoading,
    handleGenerateAITitle,
    handleGenerateAIDescription,
    handleApplyAITitle,
    handleApplyAIDescription,
    showError,
    showSuccess,
  };
}
