import { useMemo } from "react";
import { Icon } from "../../../ui/icons/Index";

const TITLE_MAX = 180;
const SHORT_DESCRIPTION_MAX = 500;
const DESCRIPTION_MAX = 3000;

function getCounterClass(length, max) {
  if (length >= max) return "text-rose-500";
  if (length >= max * 0.85) return "text-amber-500";
  return "text-slate-400";
}

function inputClass(hasError) {
  return `w-full rounded-xl border px-4 py-3 text-sm outline-none transition ${
    hasError
      ? "border-rose-400 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10"
      : "border-slate-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
  } bg-white text-slate-900`;
}

export default function DevelopmentBasicSection({
  form,
  setField,

  isEditMode = false,

  aiTitleSuggestion = "",
  aiDescriptionSuggestion = "",

  aiTitleLoading = false,
  aiDescriptionLoading = false,

  onGenerateAITitle,
  onGenerateAIDescription,

  onApplyAITitle,
  onApplyAIDescription,
}) {
  const title = String(form.title || "");
  const shortDescription = String(form.short_description || "");
  const description = String(form.description || "");

  const errors = useMemo(() => {
    return {
      title:
        title.trim().length === 0
          ? "El título es obligatorio"
          : title.length > TITLE_MAX
            ? `Máximo ${TITLE_MAX} caracteres`
            : null,

      short_description:
        shortDescription.length > SHORT_DESCRIPTION_MAX
          ? `Máximo ${SHORT_DESCRIPTION_MAX} caracteres`
          : null,

      description:
        description.trim().length === 0
          ? "La descripción es obligatoria"
          : description.length > DESCRIPTION_MAX
            ? `Máximo ${DESCRIPTION_MAX} caracteres`
            : null,
    };
  }, [title, shortDescription, description]);

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Información básica
        </h2>

        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Definí el nombre del proyecto y una descripción clara para que otros
          usuarios entiendan rápidamente de qué desarrollo se trata.
        </p>
      </div>

      <div className="space-y-5">
        {/* TITLE */}
        <div>
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <label className="text-sm font-medium text-slate-700">
              Título del desarrollo *
            </label>

            <span
              className={`text-xs ${getCounterClass(title.length, TITLE_MAX)}`}
            >
              {title.length}/{TITLE_MAX}
            </span>
          </div>

          <input
            type="text"
            maxLength={TITLE_MAX}
            value={title}
            onChange={(e) => setField("title", e.target.value)}
            className={inputClass(errors.title)}
            placeholder="Ej. Torres del Golf"
          />

          {errors.title && (
            <p className="mt-1 text-xs text-rose-500">{errors.title}</p>
          )}
        </div>

        {isEditMode && (
          <div className="mt-3">
            {!aiTitleSuggestion ? (
              <button
                type="button"
                onClick={onGenerateAITitle}
                disabled={aiTitleLoading}
                className="inline-flex items-center gap-2 text-sm font-semibold text-violet-700 hover:text-violet-800 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Icon name="sparkles" size={16} />

                {aiTitleLoading
                  ? "Generando título..."
                  : "Generar título con IA"}
              </button>
            ) : (
              <div className="rounded-xl border border-violet-200 bg-violet-50/60 p-4">
                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-violet-700">
                  <Icon name="sparkles" size={14} />
                  Sugerencia IA
                </div>

                <p className="mt-2 text-sm font-semibold leading-6 text-slate-900">
                  {aiTitleSuggestion}
                </p>

                <div className="mt-3 flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={onApplyAITitle}
                    className="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-violet-700"
                  >
                    <Icon name="check" size={15} />
                    Usar sugerencia
                  </button>

                  <button
                    type="button"
                    onClick={onGenerateAITitle}
                    disabled={aiTitleLoading}
                    className="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-3 py-2 text-sm font-bold text-violet-700 transition hover:bg-violet-50 disabled:opacity-50"
                  >
                    <Icon name="refresh" size={15} />

                    {aiTitleLoading ? "Generando..." : "Generar otra opción"}
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* SHORT DESCRIPTION */}
        <div>
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <label className="text-sm font-medium text-slate-700">
              Descripción corta
            </label>

            <span
              className={`text-xs ${getCounterClass(
                shortDescription.length,
                SHORT_DESCRIPTION_MAX,
              )}`}
            >
              {shortDescription.length}/{SHORT_DESCRIPTION_MAX}
            </span>
          </div>

          <textarea
            maxLength={SHORT_DESCRIPTION_MAX}
            rows={3}
            value={shortDescription}
            onChange={(e) => setField("short_description", e.target.value)}
            className={inputClass(errors.short_description) + " resize-none"}
            placeholder="Resumen breve para cards y vistas rápidas."
          />

          {errors.short_description && (
            <p className="mt-1 text-xs text-rose-500">
              {errors.short_description}
            </p>
          )}
        </div>

        {/* DESCRIPTION */}
        <div>
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <label className="text-sm font-medium text-slate-700">
              Descripción *
            </label>

            <span
              className={`text-xs ${getCounterClass(
                description.length,
                DESCRIPTION_MAX,
              )}`}
            >
              {description.length}/{DESCRIPTION_MAX}
            </span>
          </div>

          <textarea
            maxLength={DESCRIPTION_MAX}
            rows={6}
            value={description}
            onChange={(e) => setField("description", e.target.value)}
            className={inputClass(errors.description) + " resize-none"}
            placeholder="Contá la propuesta del proyecto, ubicación, etapa, ventajas comerciales y cualquier detalle importante."
          />

          {errors.description && (
            <p className="mt-1 text-xs text-rose-500">{errors.description}</p>
          )}

          {isEditMode && (
            <div className="mt-3">
              {!aiDescriptionSuggestion ? (
                <button
                  type="button"
                  onClick={onGenerateAIDescription}
                  disabled={aiDescriptionLoading}
                  className="inline-flex items-center gap-2 text-sm font-semibold text-violet-700 hover:text-violet-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Icon name="sparkles" size={16} />

                  {aiDescriptionLoading
                    ? "Generando descripción..."
                    : "Generar descripción con IA"}
                </button>
              ) : (
                <div className="rounded-xl border border-violet-200 bg-violet-50/60 p-4">
                  <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-violet-700">
                    <Icon name="sparkles" size={14} />
                    Sugerencia IA
                  </div>

                  <p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">
                    {aiDescriptionSuggestion}
                  </p>

                  <div className="mt-3 flex flex-wrap gap-2">
                    <button
                      type="button"
                      onClick={onApplyAIDescription}
                      className="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-violet-700"
                    >
                      <Icon name="check" size={15} />
                      Usar descripción
                    </button>

                    <button
                      type="button"
                      onClick={onGenerateAIDescription}
                      disabled={aiDescriptionLoading}
                      className="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-3 py-2 text-sm font-bold text-violet-700 transition hover:bg-violet-50 disabled:opacity-50"
                    >
                      <Icon name="refresh" size={15} />

                      {aiDescriptionLoading
                        ? "Generando..."
                        : "Generar otra opción"}
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
