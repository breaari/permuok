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
} from "../api/properties.api.js";

export function usePropertyForm({ googleMapsLoaded }) {
  const navigate = useNavigate();
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
  });

  const [requirements, setRequirements] = useState(emptyRequirements());
  const [images, setImages] = useState([]);
  const [existingImages, setExistingImages] = useState([]);
  const [initialExistingImageIds, setInitialExistingImageIds] = useState([]);
  const [isLocationValid, setIsLocationValid] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitMessage, setSubmitMessage] = useState("");
  const [submitError, setSubmitError] = useState("");
  const [initialLoading, setInitialLoading] = useState(isEditMode);
  const [initialError, setInitialError] = useState("");

  useEffect(() => {
    if (!isEditMode) return;

    let cancelled = false;

    async function loadProperty() {
      try {
        setInitialLoading(true);
        setInitialError("");
        setSubmitError("");
        setSubmitMessage("");

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

        setForm(mapPropertyToForm(property));
        setPropertyStatus(property?.status || "draft");

        setRequirements(
          mapRequirementsToState(
            requirementsData,
            requirementPropertyTypes,
            requirementLocations,
          ),
        );

        setExistingImages(serverImages);
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
    const detailRes = await api.get(`/properties/${propertyId}`);
    const detail = unwrap(detailRes);

    const property = detail?.property || {};
    const refreshedImages = Array.isArray(detail?.images) ? detail.images : [];

    setForm(mapPropertyToForm(property));
    setPropertyStatus(property?.status || propertyStatus);

    setExistingImages(refreshedImages);
    setInitialExistingImageIds(
      refreshedImages
        .map((img) => Number(img?.id))
        .filter((imgId) => Number.isFinite(imgId) && imgId > 0),
    );
    setImages([]);

    return detail;
  }

  async function syncImagesForEdit(propertyId) {
    const currentExistingIds = existingImages
      .map((img) => Number(img?.id))
      .filter((imgId) => Number.isFinite(imgId) && imgId > 0);

    const deletedExistingIds = initialExistingImageIds.filter(
      (imgId) => !currentExistingIds.includes(imgId),
    );

    for (const imageId of deletedExistingIds) {
      await api.del(`/properties/images/${imageId}`);
    }

    let latestDetail = null;

    if (images.length) {
      latestDetail = await uploadPropertyImages(propertyId, images);
    }

    if (!latestDetail) {
      const detailRes = await api.get(`/properties/${propertyId}`);
      latestDetail = unwrap(detailRes);
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
      throw new Error("La propiedad debe conservar al menos una imagen.");
    }

    const reorderPayload = finalOrderedIds.map((imageId, index) => ({
      id: imageId,
      is_cover: index === 0,
    }));

    await reorderPropertyImages(propertyId, reorderPayload);
  }

  async function syncImagesForCreate(propertyId) {
    const uploaded = await uploadPropertyImages(propertyId, images);
    const uploadedImages = Array.isArray(uploaded?.images)
      ? uploaded.images
      : [];

    if (!uploadedImages.length) {
      throw new Error(
        "El servidor no devolvió correctamente las imágenes subidas.",
      );
    }

    const reorderPayload = uploadedImages.map((img, index) => ({
      id: img.id,
      is_cover: index === 0,
    }));

    await reorderPropertyImages(propertyId, reorderPayload);
  }

  async function submitProperty({
    publishNow = false,
    redirectAfterSave = true,
    successMessageOverride = "",
  } = {}) {
    setSubmitError("");
    setSubmitMessage("");

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
        propertyId = created?.property?.id;
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

      let message = "";

      if (successMessageOverride) {
        message = successMessageOverride;
      } else if (publishNow) {
        message = isEditMode
          ? "La propiedad fue actualizada y publicada correctamente."
          : "La propiedad fue publicada correctamente.";
      } else {
        message = isEditMode
          ? "Los datos de la propiedad se actualizaron correctamente."
          : "La propiedad se guardó como borrador.";
      }

      setSubmitMessage(message);
      setPublishChoiceOpen(false);

      if (redirectAfterSave) {
        setTimeout(() => {
          navigate("/properties");
        }, 600);
      } else if (isEditMode) {
        await refreshPropertyState(propertyId);
      }
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo finalizar la publicación."),
      );
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
      setSubmitError("");
      setSubmitMessage("");

      await archiveProperty(Number(id));
      await refreshPropertyState(Number(id));

      setPropertyStatus("archived");
      setSubmitMessage("La publicación fue archivada correctamente.");
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo archivar la publicación."),
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handlePause() {
    if (!isEditMode || isSubmitting) return;

    try {
      setIsSubmitting(true);
      setSubmitError("");
      setSubmitMessage("");

      await api.post(`/properties/${id}/pause`, {});
      await refreshPropertyState(Number(id));

      setPropertyStatus("paused");
      setSubmitMessage("La publicación fue pausada correctamente.");
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo pausar la publicación."),
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!isEditMode || isSubmitting) return;

    try {
      setIsSubmitting(true);
      setSubmitError("");
      setSubmitMessage("");

      await deleteProperty(Number(id));
      setPropertyStatus("deleted");
      setSubmitMessage("La publicación fue eliminada correctamente.");

      setTimeout(() => {
        navigate("/properties");
      }, 600);
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo eliminar la publicación."),
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  function handleContinueToStepTwo() {
    setSubmitError("");
    setSubmitMessage("");

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
      setSubmitError(error.message || "No se pudo avanzar al paso 2.");
    }
  }

  function handleBackToStepOne() {
    setSubmitError("");
    setSubmitMessage("");
    setPublishChoiceOpen(false);
    setCurrentStep(1);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function handleOpenPublishChoice() {
    setSubmitError("");
    setSubmitMessage("");

    try {
      validateBeforeSubmit();
      setPublishChoiceOpen(true);
    } catch (error) {
      setSubmitError(error.message || "No se pudo continuar.");
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

  function handlePreview() {
    console.log("Vista previa");
  }

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
    submitMessage,
    submitError,
    setSubmitMessage,
    setSubmitError,

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
    handlePreview,
    submitProperty,
    navigate,
  };
}
