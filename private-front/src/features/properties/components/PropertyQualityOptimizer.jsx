import { Icon } from "../../../ui/icons/Index";

function getQualityMeta(level) {
  const map = {
    poor: {
      label: "Publicación deficiente",
      badge: "bg-rose-50 text-rose-700 border-rose-200",
    },

    needs_improvement: {
      label: "Necesita mejoras",
      badge: "bg-amber-50 text-amber-700 border-amber-200",
    },

    good: {
      label: "Buena publicación",
      badge: "bg-sky-50 text-sky-700 border-sky-200",
    },

    very_good: {
      label: "Muy buena publicación",
      badge: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },

    excellent: {
      label: "Publicación excelente",
      badge: "bg-emerald-100 text-emerald-800 border-emerald-300",
    },
  };

  return map[level] || map.needs_improvement;
}

function formatScore(value) {
  const number = Number(value || 0);

  if (Number.isInteger(number)) {
    return number;
  }

  return number.toFixed(1);
}

function SectionScore({ label, score, maxScore }) {
  const safeScore = Number(score || 0);
  const safeMax = Number(maxScore || 1);

  const percent = Math.max(0, Math.min(100, (safeScore / safeMax) * 100));

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between gap-4">
        <span className="text-sm font-medium text-slate-700">{label}</span>

        <span className="text-sm font-bold text-slate-900">
          {formatScore(safeScore)}
          <span className="font-semibold text-slate-400"> / {safeMax}</span>
        </span>
      </div>

      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
        <div
          className="h-full rounded-full bg-emerald-500 transition-all duration-300"
          style={{
            width: `${percent}%`,
          }}
        />
      </div>
    </div>
  );
}

function formatAnalysisDate(value) {
  if (!value) {
    return null;
  }

  const raw = String(value).trim();

  const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)
    ? `${raw.replace(" ", "T")}Z`
    : raw;

  const date = new Date(normalized);

  if (Number.isNaN(date.getTime())) {
    return raw;
  }

  return new Intl.DateTimeFormat("es-AR", {
    timeZone: "America/Argentina/Buenos_Aires",

    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function buildUnifiedSuggestions(quality, aiAnalysis) {
  const objectiveSuggestions = Array.isArray(quality?.suggestions)
    ? quality.suggestions
    : [];

  const aiSuggestions = Array.isArray(aiAnalysis?.suggestions)
    ? aiAnalysis.suggestions
    : [];

  const contradictions = Array.isArray(aiAnalysis?.contradictions)
    ? aiAnalysis.contradictions
    : [];

  /*
   * La IA tiene prioridad porque puede evaluar
   * semánticamente el problema y dar una acción
   * más específica.
   */
  const normalized = [
    ...contradictions.map((item) => ({
      field: item?.field || "contradiction",

      priority: "high",

      title: "Corregir una inconsistencia",

      message: item?.message || "",

      source: "ai",
    })),

    ...aiSuggestions.map((item) => ({
      field: item?.field || "general",

      priority: item?.priority || "medium",

      title: item?.action || "Mejorar la publicación",

      message: item?.message || "",

      source: "ai",
    })),

    ...objectiveSuggestions.map((item) => ({
      field: item?.field || "general",

      priority: item?.priority || "medium",

      title: item?.title || "Completar información",

      message: item?.message || "",

      source: "objective",
    })),
  ];

  const priorityOrder = {
    high: 1,
    medium: 2,
    low: 3,
  };

  /*
   * Una única recomendación principal por campo.
   *
   * Como IA está antes que objective,
   * si ambos hablan de amenities conservamos
   * la recomendación IA.
   */
  const byField = new Map();

  for (const item of normalized) {
    const field = String(item?.field || "general")
      .trim()
      .toLowerCase();

    if (!field) {
      continue;
    }

    if (!byField.has(field)) {
      byField.set(field, item);

      continue;
    }

    const current = byField.get(field);

    const currentPriority = priorityOrder[current?.priority] || 99;

    const newPriority = priorityOrder[item?.priority] || 99;

    /*
     * Una recomendación más importante
     * puede reemplazar a la anterior.
     *
     * A igualdad de prioridad conservamos IA.
     */
    if (
      newPriority < currentPriority ||
      (newPriority === currentPriority &&
        current?.source !== "ai" &&
        item?.source === "ai")
    ) {
      byField.set(field, item);
    }
  }

  return Array.from(byField.values())
    .sort(
      (a, b) =>
        (priorityOrder[a?.priority] || 99) - (priorityOrder[b?.priority] || 99),
    )
    .slice(0, 5);
}

export default function PropertyQualityOptimizer({
  quality,
  qualityV2,
  aiAnalysis,
  aiAnalysisLoading = false,
  aiAnalysisRequesting = false,
  onRequestAIAnalysis,
}) {
  if (!quality && !qualityV2) {
    return null;
  }

  const completed = qualityV2?.status === "completed";

  const waitingAI = qualityV2?.status === "waiting_ai";

  const aiPending = aiAnalysis?.status === "pending";

  const aiProcessing = aiAnalysis?.status === "processing";

  const aiFailed = aiAnalysis?.status === "failed";

  const score = completed ? Number(qualityV2?.score || 0) : null;

  const meta = completed ? getQualityMeta(qualityV2?.quality_level) : null;

  const sections = qualityV2?.sections || {};

  const unifiedSuggestions = buildUnifiedSuggestions(quality, aiAnalysis);

  const questions = Array.isArray(aiAnalysis?.questions)
    ? aiAnalysis.questions
    : [];

  const objectiveProgress = Number(qualityV2?.objective_progress || 0);

  const isAnalyzing = aiPending || aiProcessing;

  const buttonDisabled =
    aiAnalysisLoading || aiAnalysisRequesting || isAnalyzing;

  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div className="border-b border-slate-100 bg-slate-50/70 px-5 py-5 sm:px-6">
        <div className="flex flex-col gap-4">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
              <Icon name="sparkles" size={20} />
            </div>

            <div className="min-w-0 flex-1">
              <h2 className="text-base font-bold text-slate-900">
                Optimizador de publicación
              </h2>

              <p className="mt-1 text-sm leading-5 text-slate-500">
                Analizamos la ficha, el contenido y el potencial de matching
                para medir la calidad real de la publicación.
              </p>
            </div>
          </div>

          {completed && meta && (
            <span
              className={`inline-flex w-fit items-center rounded-full border px-3 py-1.5 text-xs font-bold ${meta.badge}`}
            >
              {meta.label}
            </span>
          )}
        </div>
      </div>

      <div className="space-y-7 px-5 py-6 sm:px-6">
        {completed ? (
          <>
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                Calidad de publicación
              </p>

              <div className="mt-1 flex items-end gap-2">
                <p className="text-4xl font-black tracking-tight text-slate-900">
                  {Math.round(score)}
                </p>

                <span className="pb-1 text-base font-semibold text-slate-400">
                  /100
                </span>
              </div>

              <div className="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                <div
                  className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                  style={{
                    width: `${Math.max(0, Math.min(100, score))}%`,
                  }}
                />
              </div>

              <p className="mt-3 text-sm leading-5 text-slate-600">
                El índice combina completitud de la ficha con calidad del
                contenido, imágenes, coherencia y capacidad de generar matches
                relevantes.
              </p>
            </div>

            <div className="grid gap-5">
              <SectionScore
                label="Ficha y datos"
                score={sections?.structure?.score}
                maxScore={sections?.structure?.max_score}
              />

              <SectionScore
                label="Ubicación"
                score={sections?.location?.score}
                maxScore={sections?.location?.max_score}
              />

              <SectionScore
                label="Características"
                score={sections?.features?.score}
                maxScore={sections?.features?.max_score}
              />

              <SectionScore
                label="Título"
                score={sections?.title?.score}
                maxScore={sections?.title?.max_score}
              />

              <SectionScore
                label="Descripción"
                score={sections?.description?.score}
                maxScore={sections?.description?.max_score}
              />

              <SectionScore
                label="Imágenes"
                score={sections?.images?.score}
                maxScore={sections?.images?.max_score}
              />

              <SectionScore
                label="Coherencia"
                score={sections?.consistency?.score}
                maxScore={sections?.consistency?.max_score}
              />

              <SectionScore
                label="Profesionalismo"
                score={sections?.professionalism?.score}
                maxScore={sections?.professionalism?.max_score}
              />

              <SectionScore
                label="Potencial de matching"
                score={sections?.matchability?.score}
                maxScore={sections?.matchability?.max_score}
              />
            </div>
          </>
        ) : (
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
              Preparación de la ficha
            </p>

            <p className="mt-1 text-3xl font-black tracking-tight text-slate-900">
              {Math.round(objectiveProgress)}
              <span className="text-base font-semibold text-slate-400">%</span>
            </p>

            <div className="mt-3 h-3 overflow-hidden rounded-full bg-slate-100">
              <div
                className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                style={{
                  width: `${Math.max(0, Math.min(100, objectiveProgress))}%`,
                }}
              />
            </div>

            <p className="mt-3 text-sm leading-5 text-slate-600">
              {isAnalyzing
                ? "Estamos analizando la calidad del contenido, las imágenes y el potencial de matching."
                : aiFailed
                  ? "El análisis no pudo completarse. Podés volver a intentarlo."
                  : "La ficha ya fue evaluada. Falta completar el análisis inteligente para calcular el índice final."}
            </p>
          </div>
        )}

        {unifiedSuggestions.length > 0 && completed && (
          <div className="border-t border-slate-100 pt-6">
            <div className="mb-4">
              <h3 className="text-sm font-bold text-slate-900">
                Mejoras recomendadas
              </h3>

              <p className="mt-1 text-xs leading-5 text-slate-500">
                Priorizamos los cambios que más pueden mejorar la publicación y
                sus compatibilidades.
              </p>
            </div>

            <div className="space-y-3">
              {unifiedSuggestions.map((suggestion, index) => (
                <div
                  key={`${suggestion?.field || "suggestion"}-${index}`}
                  className="flex gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                >
                  <div className="mt-0.5 text-amber-600">
                    <Icon name="warning" size={18} />
                  </div>

                  <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-900">
                      {suggestion?.title || "Mejorá este aspecto"}
                    </p>

                    {suggestion?.message && (
                      <p className="mt-1 text-sm leading-5 text-slate-600">
                        {suggestion.message}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {completed && questions.length > 0 && (
          <div className="border-t border-slate-100 pt-6">
            <div className="mb-3">
              <h3 className="text-sm font-bold text-slate-900">
                Datos para confirmar
              </h3>

              <p className="mt-1 text-xs leading-5 text-slate-500">
                Detectamos información que podría hacer más precisa la
                publicación.
              </p>
            </div>

            <div className="space-y-3">
              {questions.map((item, index) => (
                <div
                  key={`${item?.field || "question"}-${index}`}
                  className="rounded-xl border border-violet-100 bg-violet-50/40 p-4"
                >
                  <p className="text-sm font-bold leading-5 text-slate-900">
                    {item?.question}
                  </p>

                  {item?.reason && (
                    <p className="mt-1 text-xs leading-5 text-slate-500">
                      {item.reason}
                    </p>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="border-t border-slate-100 pt-6">
          <button
            type="button"
            onClick={onRequestAIAnalysis}
            disabled={buttonDisabled}
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Icon name={completed ? "refresh" : "sparkles"} size={17} />

            {aiAnalysisRequesting
              ? "Solicitando..."
              : aiProcessing
                ? "Analizando publicación..."
                : aiPending
                  ? "Análisis en espera..."
                  : completed
                    ? "Actualizar análisis"
                    : aiFailed
                      ? "Reintentar análisis"
                      : "Analizar publicación"}
          </button>

          {!completed && waitingAI && !isAnalyzing && (
            <p className="mt-2 text-center text-xs text-slate-400">
              El índice final se calculará cuando termine el análisis.
            </p>
          )}
        </div>

        {aiAnalysis?.analyzed_at && (
          <div className="flex items-center gap-2 border-t border-slate-100 pt-4 text-xs text-slate-400">
            <Icon name="clock" size={14} />

            <span>
              Último análisis: {formatAnalysisDate(aiAnalysis.analyzed_at)}
            </span>
          </div>
        )}
      </div>
    </section>
  );
}
