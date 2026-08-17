import { Icon } from "../../../ui/icons/Index";

function getQualityMeta(level) {
  const map = {
    poor: {
      label: "Necesita mejoras",
      badge: "bg-rose-50 text-rose-700 border-rose-200",
    },
    basic: {
      label: "Publicación básica",
      badge: "bg-amber-50 text-amber-700 border-amber-200",
    },
    good: {
      label: "Buena publicación",
      badge: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },
    excellent: {
      label: "Publicación excelente",
      badge: "bg-emerald-100 text-emerald-800 border-emerald-300",
    },
  };

  return map[level] || map.basic;
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
          {safeScore} / {safeMax}
        </span>
      </div>

      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
        <div
          className="h-full rounded-full bg-emerald-500 transition-all duration-300"
          style={{ width: `${percent}%` }}
        />
      </div>
    </div>
  );
}

export default function PropertyQualityOptimizer({
  quality,
  aiAnalysis,
  aiAnalysisLoading = false,
  aiAnalysisRequesting = false,
  onRequestAIAnalysis,
}) {
  if (!quality) {
    return null;
  }

  const score = Number(quality?.score || 0);
  const meta = getQualityMeta(quality?.quality_level);

  const sections = quality?.sections || {};

  const suggestions = Array.isArray(quality?.suggestions)
    ? quality.suggestions
    : [];

  const priorityOrder = {
    high: 1,
    medium: 2,
    low: 3,
  };

  const prioritizedSuggestions = [...suggestions]
    .sort(
      (a, b) =>
        (priorityOrder[a?.priority] || 99) - (priorityOrder[b?.priority] || 99),
    )
    .slice(0, 4);

  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div className="border-b border-slate-100 bg-slate-50/70 px-5 py-5 sm:px-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
              <Icon name="sparkles" size={20} />
            </div>

            <div>
              <h2 className="text-base font-bold text-slate-900">
                Optimizador de publicación
              </h2>

              <p className="mt-1 text-sm text-slate-500">
                Revisamos qué tan completa y preparada está tu publicación.
              </p>
            </div>
          </div>

          <span
            className={`inline-flex w-fit items-center rounded-full border px-3 py-1.5 text-xs font-bold ${meta.badge}`}
          >
            {meta.label}
          </span>
        </div>
      </div>

      <div className="space-y-7 px-5 py-6 sm:px-6">
        <div>
          <div className="mb-3 flex items-end justify-between gap-4">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                Calidad de publicación
              </p>

              <p className="mt-1 text-3xl font-black tracking-tight text-slate-900">
                {score}
                <span className="text-base font-semibold text-slate-400">
                  /100
                </span>
              </p>
            </div>
          </div>

          <div className="h-3 overflow-hidden rounded-full bg-slate-100">
            <div
              className="h-full rounded-full bg-emerald-500 transition-all duration-500"
              style={{
                width: `${Math.max(0, Math.min(100, score))}%`,
              }}
            />
          </div>
        </div>

        <div className="grid gap-5 sm:grid-cols-2">
          <SectionScore
            label="Datos básicos"
            score={sections?.basic?.score}
            maxScore={sections?.basic?.max_score}
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
            label="Imágenes"
            score={sections?.media?.score}
            maxScore={sections?.media?.max_score}
          />

          <SectionScore
            label="Potencial de matching"
            score={sections?.matchability?.score}
            maxScore={sections?.matchability?.max_score}
          />
        </div>

        {prioritizedSuggestions.length > 0 && (
          <div className="border-t border-slate-100 pt-6">
            <div className="mb-4">
              <h3 className="text-sm font-bold text-slate-900">
                Mejoras recomendadas
              </h3>

              <p className="mt-1 text-xs text-slate-500">
                Completá estos puntos para mejorar la calidad y precisión de la
                publicación.
              </p>
            </div>

            <div className="space-y-3">
              {prioritizedSuggestions.map((suggestion, index) => (
                <div
                  key={`${suggestion?.field || "suggestion"}-${index}`}
                  className="flex gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                >
                  <div className="mt-0.5 text-amber-600">
                    <Icon name="warning" size={18} />
                  </div>

                  <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-900">
                      {suggestion?.title || "Mejorá este dato"}
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
        <div className="border-t border-slate-100 pt-6">
          <div className="rounded-xl border border-violet-200 bg-violet-50/60 p-4">
            <div className="flex items-start gap-3">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                <Icon name="sparkles" size={18} />
              </div>

              <div className="min-w-0 flex-1">
                <p className="text-sm font-bold text-slate-900">
                  Análisis inteligente
                </p>

                {!aiAnalysis && (
                  <p className="mt-1 text-sm leading-5 text-slate-600">
                    La IA puede revisar la ficha, descripción e imágenes para
                    detectar mejoras y datos que conviene confirmar.
                  </p>
                )}

                {aiAnalysis?.status === "pending" && (
                  <p className="mt-1 text-sm text-slate-600">
                    El análisis está en espera para ser procesado.
                  </p>
                )}

                {aiAnalysis?.status === "processing" && (
                  <p className="mt-1 text-sm text-slate-600">
                    Estamos analizando la publicación.
                  </p>
                )}

                <button
                  type="button"
                  onClick={onRequestAIAnalysis}
                  disabled={
                    aiAnalysisLoading ||
                    aiAnalysisRequesting ||
                    ["pending", "processing"].includes(aiAnalysis?.status)
                  }
                  className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Icon
                    name={
                      aiAnalysis?.status === "completed"
                        ? "refresh"
                        : "sparkles"
                    }
                    size={17}
                  />

                  {aiAnalysisRequesting
                    ? "Solicitando..."
                    : aiAnalysis?.status === "pending"
                      ? "En espera..."
                      : aiAnalysis?.status === "processing"
                        ? "Analizando..."
                        : aiAnalysis?.status === "completed"
                          ? "Actualizar análisis"
                          : aiAnalysis?.status === "failed"
                            ? "Reintentar análisis"
                            : "Analizar con IA"}
                </button>
              </div>
            </div>

            {aiAnalysis?.status === "completed" && (
              <div className="space-y-4">
                {aiAnalysis?.suggested_title && (
                  <div className="rounded-xl border border-slate-200 bg-white p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="text-xs font-bold uppercase tracking-wide text-violet-600">
                          Título sugerido
                        </p>

                        <p className="mt-2 text-sm font-bold leading-5 text-slate-900">
                          {aiAnalysis.suggested_title}
                        </p>
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={() => onApplyTitle?.(aiAnalysis.suggested_title)}
                      className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm font-bold text-violet-700 transition hover:bg-violet-100"
                    >
                      <Icon name="check" size={16} />
                      Usar este título
                    </button>
                  </div>
                )}

                {aiAnalysis?.suggested_description && (
                  <div className="rounded-xl border border-slate-200 bg-white p-4">
                    <p className="text-xs font-bold uppercase tracking-wide text-violet-600">
                      Descripción sugerida
                    </p>

                    <p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">
                      {aiAnalysis.suggested_description}
                    </p>

                    <button
                      type="button"
                      onClick={() =>
                        onApplyDescription?.(aiAnalysis.suggested_description)
                      }
                      className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm font-bold text-violet-700 transition hover:bg-violet-100"
                    >
                      <Icon name="check" size={16} />
                      Usar esta descripción
                    </button>
                  </div>
                )}
              </div>
            )}

            {aiAnalysis?.status === "completed" &&
              Array.isArray(aiAnalysis?.questions) &&
              aiAnalysis.questions.length > 0 && (
                <div className="border-t border-slate-100 pt-5">
                  <div className="mb-3">
                    <h3 className="text-sm font-bold text-slate-900">
                      Datos para confirmar
                    </h3>

                    <p className="mt-1 text-xs leading-5 text-slate-500">
                      La IA detectó información que podría mejorar o precisar la
                      publicación.
                    </p>
                  </div>

                  <div className="space-y-3">
                    {aiAnalysis.questions.map((item, index) => (
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
          </div>
        </div>
        <div className="flex items-center gap-2 border-t border-slate-100 pt-4 text-xs text-slate-400">
          <Icon name="clock" size={14} />

          <span>
            Último análisis:{" "}
            {quality?.analyzed_at || "pendiente de actualización"}
          </span>
        </div>
      </div>
    </section>
  );
}
