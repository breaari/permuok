import { useState } from "react";

import PropertyBasicSection from "../components/PropertyBasicSection";
import PropertyLocationSection from "../components/PropertyLocationSection";
import PropertyFeaturesSection from "../components/PropertyFeaturesSection";
import PropertyExchangeSection from "../components/PropertyExchangeSection";
import PropertyImagesSection from "../components/PropertyImagesSection";
import PropertyPublishChoiceModal from "../components/PropertyPublishChoiceModal";
import PropertyDeleteModal from "../components/PropertyDeleteModal";
import PropertyFormHeaderActions from "../components/PropertyFormHeaderActions";
import PropertyFormProgress from "../components/PropertyFormProgress";
import PropertyQualityOptimizer from "../components/PropertyQualityOptimizer";
import { Icon } from "../../../ui/icons/Index";
import { useGoogleMaps } from "../../../ui/maps/UseGoogleMaps";
import { usePropertyForm } from "../hooks/usePropertyForm";

export default function PropertyForm() {
  const { isLoaded: googleMapsLoaded, loadError } = useGoogleMaps();
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);

  const {
    isEditMode,
    currentStep,
    publishChoiceOpen,
    setPublishChoiceOpen,

    propertyStatus,

    form,
    setField,

    requirements,
    setRequirementField,
    addRequirementLocation,
    updateRequirementLocation,
    removeRequirementLocation,

    images,
    setImages,
    existingImages,
    setExistingImages,

    setIsLocationValid,
    isSubmitting,

    initialLoading,
    initialError,

    handleContinueToStepTwo,
    handleBackToStepOne,
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
    quality,
    showError,
    showSuccess,
  } = usePropertyForm({ googleMapsLoaded });

  function handleOpenDeleteModal() {
    if (!isEditMode || isSubmitting) return;
    setDeleteModalOpen(true);
  }

  function handleCloseDeleteModal() {
    if (isSubmitting) return;
    setDeleteModalOpen(false);
  }

  async function handleConfirmDelete() {
    await handleDelete();
    setDeleteModalOpen(false);
  }

  const propertyImageUrl =
    existingImages?.[0]?.view_url ||
    existingImages?.[0]?.url ||
    existingImages?.[0]?.file_path ||
    "";

  if (loadError) {
    return (
      <div className="max-w-4xl mx-auto py-10 px-4">
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          No se pudo cargar Google Maps. Revisá la API key y la configuración.
        </div>
      </div>
    );
  }

  if (initialLoading) {
    return (
      <div className="max-w-4xl mx-auto py-10 px-4">
        <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
          Cargando propiedad...
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
          onClick={() => navigate("/properties")}
          className="px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700"
        >
          Volver
        </button>
      </div>
    );
  }

  return (
    <>
      <form
        onSubmit={(e) => e.preventDefault()}
        className="py-8 sm:py-10 md:py-12 px-4 sm:px-6 md:px-10 w-full"
      >
        {currentStep === 1 && (
          <div className="w-full max-w-[1240px] mx-auto">
            {/* 
            Desktop:
            - formulario: 768px
            - optimizador: 360px
            - separación: 24px

            En pantallas menores se apilan.
          */}
            <div className="grid gap-6 xl:grid-cols-[minmax(0,768px)_360px] 2xl:grid-cols-[minmax(0,768px)_390px] xl:justify-center xl:items-start">
              {/* COLUMNA IZQUIERDA: FORMULARIO */}
              <div className="space-y-6 sm:space-y-8 min-w-0">
                <PropertyFormProgress
                  currentStep={currentStep}
                  isEditMode={isEditMode}
                />

                <PropertyFormHeaderActions
                  status={propertyStatus}
                  isEditMode={isEditMode}
                  isSubmitting={isSubmitting}
                  onSaveDraft={handleSaveDraft}
                  onPublish={handlePublish}
                  onPause={handlePause}
                  onArchive={handleArchive}
                  onDelete={handleOpenDeleteModal}
                  onPreview={handlePreview}
                />

                <PropertyBasicSection form={form} setField={setField} />

                <PropertyLocationSection
                  form={form}
                  setField={setField}
                  onLocationValidityChange={setIsLocationValid}
                  googleMapsLoaded={googleMapsLoaded}
                />

                <PropertyFeaturesSection form={form} setField={setField} />

                <PropertyImagesSection
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
              </div>

              {/* COLUMNA DERECHA: OPTIMIZADOR */}
              {isEditMode && quality ? (
                <aside className="self-start min-w-0">
                  <PropertyQualityOptimizer quality={quality} />
                </aside>
              ) : (
                <div className="hidden xl:block" />
              )}
            </div>
          </div>
        )}

        {currentStep === 2 && (
          <div className="w-full max-w-4xl mx-auto space-y-6 sm:space-y-8">
            <PropertyFormProgress
              currentStep={currentStep}
              isEditMode={isEditMode}
            />

            <PropertyFormHeaderActions
              status={propertyStatus}
              isEditMode={isEditMode}
              isSubmitting={isSubmitting}
              onSaveDraft={handleSaveDraft}
              onPublish={handlePublish}
              onPause={handlePause}
              onArchive={handleArchive}
              onDelete={handleOpenDeleteModal}
              onPreview={handlePreview}
            />

            <PropertyExchangeSection
              requirements={requirements}
              setRequirementField={setRequirementField}
              onAddLocation={addRequirementLocation}
              onUpdateLocation={updateRequirementLocation}
              onRemoveLocation={removeRequirementLocation}
              googleMapsLoaded={googleMapsLoaded}
            />

            <div className="sticky bottom-4">
              <div className="rounded-2xl border border-slate-200 bg-white/95 backdrop-blur px-4 sm:px-5 py-4 shadow-lg">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">
                      Último paso
                    </p>

                    <p className="text-xs sm:text-sm text-slate-500 mt-1">
                      Revisá las condiciones antes de finalizar la publicación.
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
                          : "Finalizar publicación"}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </form>

      {!isEditMode && (
        <PropertyPublishChoiceModal
          open={publishChoiceOpen}
          busy={isSubmitting}
          onClose={() => setPublishChoiceOpen(false)}
          onSaveDraft={() =>
            submitProperty({
              publishNow: false,
            })
          }
          onPublishNow={() =>
            submitProperty({
              publishNow: true,
            })
          }
        />
      )}

      {isEditMode && (
        <PropertyDeleteModal
          open={deleteModalOpen}
          busy={isSubmitting}
          propertyTitle={form.title}
          propertyImageUrl={propertyImageUrl}
          onClose={handleCloseDeleteModal}
          onConfirm={handleConfirmDelete}
        />
      )}
    </>
  );
}
