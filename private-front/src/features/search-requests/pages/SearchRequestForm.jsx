import { useState } from "react";
import { Icon } from "../../../ui/icons/Index";
import { useGoogleMaps } from "../../../ui/maps/UseGoogleMaps";
import {
  PropertyFormProgress,
  PropertyPublishChoiceModal,
} from "../../properties/components";
import { useSearchRequestForm } from "../hooks";
import {
  SearchRequestBasicSection,
  SearchRequestDeleteModal,
  SearchRequestFormFooterActions,
  SearchRequestFormHeaderActions,
  SearchRequestLocationAndCriteriaSection,
  SearchRequestPaymentSection,
} from "../components";


function LoadingState() {
  return (
    <div className="max-w-4xl mx-auto py-10 px-4">
      <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
        Cargando búsqueda...
      </div>
    </div>
  );
}

function ErrorState({ error, onBack }) {
  return (
    <div className="max-w-4xl mx-auto py-10 px-4 space-y-4">
      <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
        {error}
      </div>

      <button
        type="button"
        onClick={onBack}
        className="px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700"
      >
        Volver
      </button>
    </div>
  );
}

export default function SearchRequestForm() {
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);

  const { isLoaded: mapsLoaded, loadError: mapsError } = useGoogleMaps();

  const {
    isEditMode,
    currentStep,
    publishChoiceOpen,
    setPublishChoiceOpen,

    form,
    setField,
    togglePropertyType,
    toggleAmenity,

    requestStatus,
    isSubmitting,
    loading,
    initialError,

    handleContinueToStepTwo,
    handleBackToStepOne,
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
  } = useSearchRequestForm();

  function openDeleteModal() {
    if (!isEditMode || isSubmitting) return;
    setDeleteModalOpen(true);
  }

  function closeDeleteModal() {
    if (isSubmitting) return;
    setDeleteModalOpen(false);
  }

  async function confirmDelete() {
    await handleDelete();
    setDeleteModalOpen(false);
  }

  if (loading) {
    return <LoadingState />;
  }

  if (initialError) {
    return (
      <ErrorState
        error={initialError}
        onBack={() => navigate("/search-requests")}
      />
    );
  }

  return (
    <>
      <form
        onSubmit={(e) => e.preventDefault()}
        className="py-8 sm:py-10 md:py-12 px-4 sm:px-6 md:px-10 w-full max-w-4xl mx-auto space-y-6 sm:space-y-8"
      >
        {currentStep === 1 && (
          <>
            <PropertyFormProgress
              currentStep={currentStep}
              isEditMode={isEditMode}
              variant="search"
            />

            <SearchRequestFormHeaderActions
              status={requestStatus}
              isEditMode={isEditMode}
              isSubmitting={isSubmitting}
              onSaveDraft={handleSaveDraft}
              onPublish={handlePublish}
              onPause={handlePause}
              onArchive={handleArchive}
              onDelete={openDeleteModal}
              onPreview={handlePreview}
            />

            <SearchRequestBasicSection
              form={form}
              setField={setField}
              togglePropertyType={togglePropertyType}
              toggleAmenity={toggleAmenity}
            />

            <SearchRequestLocationAndCriteriaSection
              form={form}
              setField={setField}
              mapsLoaded={mapsLoaded}
              mapsError={mapsError}
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
            <PropertyFormProgress
              currentStep={currentStep}
              isEditMode={isEditMode}
              variant="search"
            />

            <SearchRequestFormHeaderActions
              status={requestStatus}
              isEditMode={isEditMode}
              isSubmitting={isSubmitting}
              onSaveDraft={handleSaveDraft}
              onPublish={handlePublish}
              onPause={handlePause}
              onArchive={handleArchive}
              onDelete={openDeleteModal}
              onPreview={handlePreview}
            />

            <SearchRequestPaymentSection
              form={form}
              setField={setField}
            />

            <SearchRequestFormFooterActions
              isSubmitting={isSubmitting}
              isEditMode={isEditMode}
              onBack={handleBackToStepOne}
              onSubmit={handleFinalPrimaryAction}
            />
          </>
        )}
      </form>

      {!isEditMode && (
        <PropertyPublishChoiceModal
          open={publishChoiceOpen}
          busy={isSubmitting}
          onClose={() => setPublishChoiceOpen(false)}
          onSaveDraft={() => submitRequest({ publishNow: false })}
          onPublishNow={() => submitRequest({ publishNow: true })}
        />
      )}

      {isEditMode && (
        <SearchRequestDeleteModal
          open={deleteModalOpen}
          busy={isSubmitting}
          title={form.title}
          onClose={closeDeleteModal}
          onConfirm={confirmDelete}
        />
      )}
    </>
  );
}