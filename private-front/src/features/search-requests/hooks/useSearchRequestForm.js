import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { getErrorMessage } from "../../../api/http";
import {
  archiveSearchRequest,
  createSearchRequestDraft,
  deleteSearchRequest,
  getSearchRequestDetail,
  pauseSearchRequest,
  publishSearchRequest,
  updateSearchRequestDraft,
} from "../api/searchRequests.api";
import {
  buildSearchRequestPayload,
  emptySearchRequestForm,
  mapSearchRequestToForm,
  resolveCountryCode,
  validateSearchRequestForm,
} from "../utils/SearchRequestHelpers";

function resolveSearchRequestId(response) {
  const candidates = [
    response?.search_request?.id,
    response?.searchRequest?.id,
    response?.item?.id,
    response?.id,
  ];

  for (const candidate of candidates) {
    const numeric = Number(candidate);
    if (Number.isFinite(numeric) && numeric > 0) {
      return numeric;
    }
  }

  return null;
}

export function useSearchRequestForm() {
  const navigate = useNavigate();
  const { id } = useParams();

  const isEditMode = useMemo(() => !!id, [id]);

  const [currentStep, setCurrentStep] = useState(1);
  const [publishChoiceOpen, setPublishChoiceOpen] = useState(false);

  const [form, setForm] = useState(emptySearchRequestForm());
  const [requestStatus, setRequestStatus] = useState("draft");
  const [access, setAccess] = useState({
    can_edit: true,
    can_publish: true,
    can_pause: false,
    can_archive: true,
    can_delete: true,
  });

  const [loading, setLoading] = useState(isEditMode);
  const [initialError, setInitialError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitMessage, setSubmitMessage] = useState("");
  const [submitError, setSubmitError] = useState("");

  useEffect(() => {
    if (!isEditMode) return;

    let cancelled = false;

    async function loadDetail() {
      try {
        setLoading(true);
        setInitialError("");

        const detail = await getSearchRequestDetail(id);

        if (cancelled) return;

        const mapped = mapSearchRequestToForm(detail);
        setForm(mapped);
        setRequestStatus(mapped.status || "draft");
        setAccess(
          detail?.access || {
            can_edit: true,
            can_publish: true,
            can_pause: false,
            can_archive: true,
            can_delete: true,
          }
        );
      } catch (error) {
        if (cancelled) return;
        setInitialError(
          getErrorMessage(error, "No se pudo cargar la búsqueda.")
        );
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadDetail();

    return () => {
      cancelled = true;
    };
  }, [id, isEditMode]);

  function setField(name, value) {
    setForm((prev) => {
      const next = { ...prev, [name]: value };

      if (name === "country") {
        next.country_code = resolveCountryCode(value) || prev.country_code;
      }

      return next;
    });
  }

  function togglePropertyType(type) {
    setForm((prev) => {
      const current = Array.isArray(prev.property_types)
        ? prev.property_types
        : [];

      const exists = current.includes(type);

      return {
        ...prev,
        property_types: exists
          ? current.filter((item) => item !== type)
          : [...current, type],
      };
    });
  }

  function toggleAmenity(code) {
    setForm((prev) => {
      const current = Array.isArray(prev.amenities) ? prev.amenities : [];
      const exists = current.includes(code);

      return {
        ...prev,
        amenities: exists
          ? current.filter((item) => item !== code)
          : [...current, code],
      };
    });
  }

  function validateForStepOne() {
    if (!String(form.title || "").trim()) {
      throw new Error("Completá el título de la búsqueda.");
    }

    if (!String(form.description || "").trim()) {
      throw new Error("Completá la descripción.");
    }

    if (!String(form.country || "").trim()) {
      throw new Error("Seleccioná un país.");
    }

    if (!String(form.province || "").trim()) {
      throw new Error("Completá la provincia.");
    }

    if (!Array.isArray(form.property_types) || !form.property_types.length) {
      throw new Error("Seleccioná al menos un tipo de propiedad buscada.");
    }
  }

  function handleContinueToStepTwo() {
    setSubmitError("");
    setSubmitMessage("");

    try {
      validateForStepOne();
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

  async function refreshDetail(requestId) {
    const detail = await getSearchRequestDetail(requestId);
    const mapped = mapSearchRequestToForm(detail);

    setForm(mapped);
    setRequestStatus(mapped.status || "draft");
    setAccess(
      detail?.access || {
        can_edit: true,
        can_publish: true,
        can_pause: false,
        can_archive: true,
        can_delete: true,
      }
    );
  }

  async function submitRequest({
    publishNow = false,
    redirectAfterSave = true,
    successMessageOverride = "",
  } = {}) {
    setSubmitError("");
    setSubmitMessage("");

    try {
      validateSearchRequestForm(form, { requireFull: true });
      setIsSubmitting(true);

      const payload = buildSearchRequestPayload(form);
      console.log("CREATE SEARCH REQUEST PAYLOAD", payload);

      let requestId;

      if (isEditMode) {
        await updateSearchRequestDraft(id, payload);
        requestId = Number(id);
      } else {
        const created = await createSearchRequestDraft(payload);
        console.log("CREATE SEARCH REQUEST RESPONSE", created);

        requestId = resolveSearchRequestId(created);

        if (!requestId) {
          console.error("Respuesta sin ID usable:", created);
          throw new Error("No se pudo obtener el ID de la búsqueda.");
        }
      }

      if (publishNow) {
        await publishSearchRequest(requestId);
        setRequestStatus("published");
      } else if (!isEditMode) {
        setRequestStatus("draft");
      }

      const message = successMessageOverride
        ? successMessageOverride
        : publishNow
          ? isEditMode
            ? "La búsqueda fue actualizada y publicada."
            : "La búsqueda fue publicada correctamente."
          : isEditMode
            ? "La búsqueda fue actualizada correctamente."
            : "La búsqueda se guardó como borrador.";

      setSubmitMessage(message);
      setPublishChoiceOpen(false);

      if (redirectAfterSave) {
        setTimeout(() => {
          navigate("/search-requests");
        }, 600);
      } else if (isEditMode) {
        await refreshDetail(requestId);
      }
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo guardar la búsqueda.")
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  function handleOpenPublishChoice() {
    setSubmitError("");
    setSubmitMessage("");

    try {
      validateSearchRequestForm(form, { requireFull: true });
      setPublishChoiceOpen(true);
    } catch (error) {
      setSubmitError(error.message || "No se pudo continuar.");
    }
  }

  function handleQuickUpdateStepOne() {
    submitRequest({
      publishNow: false,
      redirectAfterSave: false,
      successMessageOverride:
        "Los datos del paso 1 se actualizaron correctamente.",
    });
  }

  function handleFinalPrimaryAction() {
    if (isEditMode) {
      submitRequest({
        publishNow: false,
        redirectAfterSave: true,
        successMessageOverride: "La búsqueda se actualizó correctamente.",
      });
      return;
    }

    handleOpenPublishChoice();
  }

  async function handleSaveDraft() {
    if (isSubmitting) return;

    await submitRequest({
      publishNow: false,
      redirectAfterSave: false,
      successMessageOverride: isEditMode
        ? "Cambios guardados como borrador."
        : "La búsqueda se guardó como borrador.",
    });
  }

  async function handlePublish() {
    if (isSubmitting) return;

    if (isEditMode) {
      await submitRequest({
        publishNow: true,
        redirectAfterSave: false,
        successMessageOverride: "La búsqueda fue publicada correctamente.",
      });
      return;
    }

    handleOpenPublishChoice();
  }

  async function handlePause() {
    if (!isEditMode || isSubmitting || !access?.can_pause) return;

    try {
      setIsSubmitting(true);
      setSubmitError("");
      setSubmitMessage("");

      await pauseSearchRequest(id);
      await refreshDetail(Number(id));

      setRequestStatus("paused");
      setSubmitMessage("La búsqueda fue pausada correctamente.");
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo pausar la búsqueda.")
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleArchive() {
    if (!isEditMode || isSubmitting || !access?.can_archive) return;

    try {
      setIsSubmitting(true);
      setSubmitError("");
      setSubmitMessage("");

      await archiveSearchRequest(id);
      await refreshDetail(Number(id));

      setRequestStatus("archived");
      setSubmitMessage("La búsqueda fue archivada correctamente.");
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo archivar la búsqueda.")
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!isEditMode || isSubmitting || !access?.can_delete) return;

    try {
      setIsSubmitting(true);
      setSubmitError("");
      setSubmitMessage("");

      await deleteSearchRequest(id);
      setRequestStatus("deleted");
      setSubmitMessage("La búsqueda fue eliminada correctamente.");

      setTimeout(() => {
        navigate("/search-requests");
      }, 600);
    } catch (error) {
      setSubmitError(
        getErrorMessage(error, "No se pudo eliminar la búsqueda.")
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  function handlePreview() {
    console.log("Vista previa búsqueda", form);
  }

  return {
    id,
    isEditMode,
    currentStep,
    setCurrentStep,
    publishChoiceOpen,
    setPublishChoiceOpen,

    form,
    setForm,
    setField,
    togglePropertyType,
    toggleAmenity,

    requestStatus,
    access,

    loading,
    initialError,
    isSubmitting,
    submitMessage,
    submitError,
    setSubmitMessage,
    setSubmitError,

    handleContinueToStepTwo,
    handleBackToStepOne,
    handleOpenPublishChoice,
    handleQuickUpdateStepOne,
    handleFinalPrimaryAction,

    handleSaveDraft,
    handlePublish,
    handlePause,
    handleArchive,
    handleDelete,
    handlePreview,

    submitRequest,
    navigate,
  };
}