import { Icon } from "../../../ui/icons/Index";

function getQualityMeta(level) {
  const map = {
    poor: {
      label: "Búsqueda deficiente",
      badge: "bg-rose-50 text-rose-700 border-rose-200",
    },

    needs_improvement: {
      label: "Necesita mejoras",
      badge: "bg-amber-50 text-amber-700 border-amber-200",
    },

    good: {
      label: "Buena búsqueda",
      badge: "bg-sky-50 text-sky-700 border-sky-200",
    },

    very_good: {
      label: "Muy buena búsqueda",
      badge: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },

    excellent: {
      label: "Búsqueda excelente",
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

function SectionScore({
  label,
  score,
  maxScore,
}) {
  const safeScore =
    Number(score || 0);

  const safeMax =
    Number(maxScore || 1);

  const percent =
    Math.max(
      0,
      Math.min(
        100,
        (safeScore / safeMax) * 100,
      ),
    );

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between gap-4">
        <span className="text-sm font-medium text-slate-700">
          {label}
        </span>

        <span className="text-sm font-bold text-slate-900">
          {formatScore(safeScore)}
          <span className="font-semibold text-slate-400">
            {" "}
            / {safeMax}
          </span>
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

function SuggestionItem({ item }) {
  const priority =
    item?.priority || "medium";

  const priorityClass = {
    high:
      "border-rose-200 bg-rose-50/70",
    medium:
      "border-amber-200 bg-amber-50/70",
    low:
      "border-slate-200 bg-slate-50",
  }[priority];

  return (
    <div
      className={`rounded-xl border p-3 ${priorityClass}`}
    >
      <div className="flex items-start gap-2">
        <Icon
          name="sparkles"
          size={16}
          className="mt-0.5 shrink-0"
        />

        <div className="min-w-0">
          <p className="text-sm font-semibold text-slate-800">
            {item?.action ||
              item?.title ||
              "Mejorar búsqueda"}
          </p>

          <p className="mt-1 text-sm leading-5 text-slate-600">
            {item?.message || ""}
          </p>
        </div>
      </div>
    </div>
  );
}

export default function SearchRequestQualityOptimizer({
  quality,
  qualityLoading = false,
  aiAnalysisRequesting = false,
  onRequestAIAnalysis,
}) {
  if (!quality) {
    return null;
  }

  const completed =
    quality?.status === "completed";

  const waitingAI =
    quality?.status === "waiting_ai";

  const score =
    completed
      ? Number(quality?.score || 0)
      : null;

  const meta =
    completed
      ? getQualityMeta(
          quality?.quality_level,
        )
      : null;

  const sections =
    quality?.sections || {};

  const suggestions =
    Array.isArray(quality?.suggestions)
      ? quality.suggestions.slice(0, 5)
      : [];

  const contradictions =
    Array.isArray(
      quality?.contradictions,
    )
      ? quality.contradictions
      : [];

  const disabled =
    qualityLoading ||
    aiAnalysisRequesting ||
    waitingAI;

  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div className="border-b border-slate-100 bg-slate-50/70 px-5 py-5">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
            <Icon
              name="sparkles"
              size={20}
            />
          </div>

          <div className="min-w-0">
            <p className="text-xs font-bold uppercase tracking-wider text-emerald-700">
              Optimizador IA
            </p>

            <h2 className="mt-0.5 text-lg font-bold text-slate-900">
              Calidad de búsqueda
            </h2>

            <p className="mt-1 text-sm leading-5 text-slate-500">
              Analizamos qué tan clara,
              completa y útil es esta
              búsqueda para generar
              matches.
            </p>
          </div>
        </div>
      </div>

      <div className="space-y-6 p-5">
        {completed ? (
          <>
            <div>
              <div className="flex items-end justify-between gap-4">
                <div>
                  <p className="text-sm font-medium text-slate-500">
                    Índice de calidad
                  </p>

                  <p className="mt-1 text-4xl font-black tracking-tight text-slate-900">
                    {formatScore(score)}
                    <span className="ml-1 text-lg font-semibold text-slate-400">
                      /100
                    </span>
                  </p>
                </div>

                <span
                  className={`rounded-full border px-3 py-1 text-xs font-bold ${meta.badge}`}
                >
                  {meta.label}
                </span>
              </div>

              <div className="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                <div
                  className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                  style={{
                    width: `${Math.max(
                      0,
                      Math.min(
                        100,
                        score,
                      ),
                    )}%`,
                  }}
                />
              </div>
            </div>

            <div className="space-y-4">
              <SectionScore
                label="Criterios"
                score={
                  sections?.criteria
                    ?.score
                }
                maxScore={
                  sections?.criteria
                    ?.max_score
                }
              />

              <SectionScore
                label="Ubicación"
                score={
                  sections?.location
                    ?.score
                }
                maxScore={
                  sections?.location
                    ?.max_score
                }
              />

              <SectionScore
                label="Presupuesto y pago"
                score={
                  sections?.payment
                    ?.score
                }
                maxScore={
                  sections?.payment
                    ?.max_score
                }
              />

              <SectionScore
                label="Título"
                score={
                  sections?.title?.score
                }
                maxScore={
                  sections?.title
                    ?.max_score
                }
              />

              <SectionScore
                label="Descripción"
                score={
                  sections
                    ?.description
                    ?.score
                }
                maxScore={
                  sections
                    ?.description
                    ?.max_score
                }
              />

              <SectionScore
                label="Coherencia"
                score={
                  sections
                    ?.consistency
                    ?.score
                }
                maxScore={
                  sections
                    ?.consistency
                    ?.max_score
                }
              />

              <SectionScore
                label="Matching"
                score={
                  sections
                    ?.matchability
                    ?.score
                }
                maxScore={
                  sections
                    ?.matchability
                    ?.max_score
                }
              />
            </div>
          </>
        ) : (
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div className="flex gap-3">
              <Icon
                name="clock"
                size={18}
                className="mt-0.5 shrink-0 text-amber-700"
              />

              <div>
                <p className="text-sm font-bold text-amber-800">
                  Falta actualizar el análisis IA
                </p>

                <p className="mt-1 text-sm leading-5 text-amber-700">
                  La búsqueda cambió desde
                  el último análisis. Generá
                  uno nuevo para obtener el
                  índice oficial actualizado.
                </p>
              </div>
            </div>
          </div>
        )}

        {contradictions.length > 0 && (
          <div>
            <p className="mb-3 text-sm font-bold text-slate-900">
              Inconsistencias detectadas
            </p>

            <div className="space-y-2">
              {contradictions.map(
                (item, index) => (
                  <div
                    key={`${index}-${item}`}
                    className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm leading-5 text-rose-700"
                  >
                    {item}
                  </div>
                ),
              )}
            </div>
          </div>
        )}

        {suggestions.length > 0 && (
          <div>
            <p className="mb-3 text-sm font-bold text-slate-900">
              Recomendaciones
            </p>

            <div className="space-y-2.5">
              {suggestions.map(
                (item, index) => (
                  <SuggestionItem
                    key={`${item?.field || "suggestion"}-${index}`}
                    item={item}
                  />
                ),
              )}
            </div>
          </div>
        )}

        <button
          type="button"
          disabled={disabled}
          onClick={
            onRequestAIAnalysis
          }
          className="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <Icon
            name="sparkles"
            size={17}
          />

          {aiAnalysisRequesting
            ? "Solicitando análisis..."
            : waitingAI
              ? "Analizando búsqueda..."
              : "Analizar nuevamente"}
        </button>

        {waitingAI && (
          <p className="text-center text-xs leading-5 text-slate-500">
            El análisis se está procesando
            en segundo plano. El índice se
            actualizará automáticamente.
          </p>
        )}
      </div>
    </section>
  );
}