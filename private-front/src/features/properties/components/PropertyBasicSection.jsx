import { Icon } from "../../../ui/icons/Index";

export default function PropertyBasicSection({
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
  const descriptionLength = String(form.description || "").length;

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Información básica
        </h2>

        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Definí un título claro y una descripción atractiva para la
          publicación. Esto es lo primero que van a ver otros usuarios al
          revisar la propiedad.
        </p>
      </div>

      <div className="space-y-6">
        {/* TÍTULO */}
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1.5">
            Título de la publicación
          </label>

          <input
            type="text"
            placeholder="Ej. Casa moderna con pileta en barrio privado"
            value={form.title}
            onChange={(e) => setField("title", e.target.value)}
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          />

          <p className="mt-1.5 text-xs text-slate-400">
            Intentá resumir lo más importante en una sola frase.
          </p>

          {isEditMode && (
            <div className="mt-3">
              {!aiTitleSuggestion ? (
                <button
                  type="button"
                  onClick={onGenerateAITitle}
                  disabled={aiTitleLoading}
                  className="inline-flex items-center gap-2 text-sm font-semibold text-violet-700 hover:text-violet-800 disabled:opacity-50 disabled:cursor-not-allowed"
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
        </div>

        {/* DESCRIPCIÓN */}
        <div>
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <label className="block text-sm font-medium text-slate-700">
              Descripción
            </label>

            <span className="text-xs text-slate-400">
              {descriptionLength} caracteres
            </span>
          </div>

          <textarea
            placeholder="Contá los puntos fuertes de la propiedad, ubicación, estado general y cualquier detalle importante."
            value={form.description}
            onChange={(e) => setField("description", e.target.value)}
            rows={6}
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none resize-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          />

          <p className="mt-1.5 text-xs text-slate-400">
            Ejemplo: orientación, amenities, refacciones, entorno o potencial de
            permuta.
          </p>

          {isEditMode && (
            <div className="mt-3">
              {!aiDescriptionSuggestion ? (
                <button
                  type="button"
                  onClick={onGenerateAIDescription}
                  disabled={aiDescriptionLoading}
                  className="inline-flex items-center gap-2 text-sm font-semibold text-violet-700 hover:text-violet-800 disabled:opacity-50 disabled:cursor-not-allowed"
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
