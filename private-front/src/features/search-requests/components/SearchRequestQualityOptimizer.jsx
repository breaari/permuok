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

function normalizeTopic(value) {
  const text = String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");

  if (
    text.includes("payment") ||
    text.includes("pago") ||
    text.includes("permuta") ||
    text.includes("diferencia") ||
    text.includes("efectivo") ||
    text.includes("dinero") ||
    text.includes("moneda")
  ) {
    return "payment";
  }

  if (text.includes("title") || text.includes("titulo")) {
    return "title";
  }

  if (text.includes("description") || text.includes("descripcion")) {
    return "description";
  }

  if (text.includes("ambiente") || text.includes("dormitorio")) {
    return "rooms";
  }

  if (
    text.includes("surface") ||
    text.includes("superficie") ||
    text.includes("area")
  ) {
    return "surface";
  }

  if (
    text.includes("condition") ||
    text.includes("estado") ||
    text.includes("nueva") ||
    text.includes("usada")
  ) {
    return "condition";
  }

  if (
    text.includes("budget") ||
    text.includes("presupuesto") ||
    text.includes("valor")
  ) {
    return "budget";
  }

  if (
    text.includes("location") ||
    text.includes("ubicacion") ||
    text.includes("zona") ||
    text.includes("ciudad")
  ) {
    return "location";
  }

  return text.slice(0, 80);
}

function buildUnifiedSuggestions(quality) {
  const suggestions = Array.isArray(quality?.suggestions)
    ? quality.suggestions
    : [];

  const contradictions = Array.isArray(quality?.contradictions)
    ? quality.contradictions
    : [];

  const result = [];
  const usedTopics = new Set();

  /*
   * Las contradicciones tienen prioridad.
   *
   * Si un conflicto ya fue explicado acá,
   * no mostramos después otra sugerencia
   * que hable esencialmente del mismo tema.
   */
  for (const message of contradictions) {
    const topic = normalizeTopic(message);

    if (!topic || usedTopics.has(topic)) {
      continue;
    }

    usedTopics.add(topic);

    result.push({
      field: topic,
      priority: "high",
      title:
        topic === "payment"
          ? "Revisá la modalidad de pago"
          : topic === "rooms" || topic === "title"
            ? "Aclará qué propiedad está buscando el cliente"
            : "Revisá esta inconsistencia",
      message,
      source: "contradiction",
    });
  }

  /*
   * Después agregamos sugerencias,
   * únicamente cuando aportan un tema
   * nuevo.
   */
  for (const item of suggestions) {
    const topic = normalizeTopic(
      `${item?.field || ""} ${item?.title || ""} ${item?.message || ""}`,
    );

    if (!topic || usedTopics.has(topic)) {
      continue;
    }

    usedTopics.add(topic);

    result.push({
      field: item?.field || topic,

      priority: item?.priority || "medium",

      title: item?.title || "Mejorá este aspecto",

      message: item?.message || "",

      source: "suggestion",
    });
  }

  const priorityOrder = {
    high: 1,
    medium: 2,
    low: 3,
  };

  return result
    .sort(
      (a, b) =>
        (priorityOrder[a?.priority] || 99) - (priorityOrder[b?.priority] || 99),
    )
    .slice(0, 5);
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

  const completed = quality?.status === "completed";

  const waitingAI = quality?.status === "waiting_ai";

  const score = completed ? Number(quality?.score || 0) : null;

  const meta = completed ? getQualityMeta(quality?.quality_level) : null;

  const sections = quality?.sections || {};

  const unifiedSuggestions = buildUnifiedSuggestions(quality);

  const qualityFlags = Array.isArray(quality?.flags) ? quality.flags : [];

  const buttonDisabled = qualityLoading || aiAnalysisRequesting || waitingAI;

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
                Optimizador de búsqueda
              </h2>

              <p className="mt-1 text-sm leading-5 text-slate-500">
                Analizamos los criterios, el contenido y el potencial de
                matching para medir la calidad real de la búsqueda.
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
                Calidad de búsqueda
              </p>

              <div className="mt-1 flex items-end gap-2">
                <p className="text-4xl font-black tracking-tight text-slate-900">
                  {formatScore(score)}
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
                El índice combina la calidad de los criterios, ubicación,
                presupuesto, contenido, coherencia y capacidad de generar
                matches relevantes.
              </p>
            </div>

            {qualityFlags.length > 0 && (
              <div className="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <div className="flex items-start gap-3">
                  <div className="mt-0.5 text-rose-600">
                    <Icon name="warning" size={18} />
                  </div>

                  <div className="min-w-0">
                    <p className="text-sm font-bold text-rose-900">
                      El puntaje está limitado por un problema crítico
                    </p>

                    <div className="mt-2 space-y-2">
                      {qualityFlags.map((flag, index) => (
                        <div key={`${flag?.code || "flag"}-${index}`}>
                          {flag?.message && (
                            <p className="text-sm leading-5 text-rose-800">
                              {flag.message}
                            </p>
                          )}

                          {flag?.max_score && (
                            <p className="mt-1 text-xs font-medium text-rose-700">
                              El índice no puede superar {flag.max_score}
                              /100 hasta corregirlo.
                            </p>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            )}

            <div className="grid gap-5">
              <SectionScore
                label="Criterios"
                score={sections?.criteria?.score}
                maxScore={sections?.criteria?.max_score}
              />

              <SectionScore
                label="Ubicación"
                score={sections?.location?.score}
                maxScore={sections?.location?.max_score}
              />

              <SectionScore
                label="Presupuesto y pago"
                score={sections?.payment?.score}
                maxScore={sections?.payment?.max_score}
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
                label="Coherencia"
                score={sections?.consistency?.score}
                maxScore={sections?.consistency?.max_score}
              />

              <SectionScore
                label="Potencial de matching"
                score={sections?.matchability?.score}
                maxScore={sections?.matchability?.max_score}
              />
            </div>
          </>
        ) : (
          <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <div className="flex items-start gap-3">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-600">
                <Icon name="sparkles" size={18} />
              </div>

              <div className="min-w-0">
                <p className="text-sm font-bold text-slate-900">
                  {waitingAI ? "Analizando búsqueda" : "Análisis pendiente"}
                </p>

                <p className="mt-1 text-sm leading-5 text-slate-600">
                  {waitingAI
                    ? "Estamos evaluando los criterios, el contenido, la coherencia y el potencial de matching."
                    : "La búsqueda cambió o todavía no fue analizada. Ejecutá el análisis para obtener su índice de calidad."}
                </p>

                {waitingAI && (
                  <p className="mt-2 text-xs leading-5 text-slate-500">
                    El nuevo puntaje estará disponible cuando finalice el
                    análisis.
                  </p>
                )}
              </div>
            </div>
          </div>
        )}

        {unifiedSuggestions.length > 0 && completed && (
          <div className="border-t border-slate-100 pt-6">
            <div className="mb-4">
              <h3 className="text-sm font-bold text-slate-900">
                Mejoras recomendadas
              </h3>

              <p className="mt-1 text-xs leading-5 text-slate-500">
                Priorizamos los cambios que más pueden mejorar la búsqueda y sus
                compatibilidades.
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
              : waitingAI
                ? "Analizando búsqueda..."
                : completed
                  ? "Actualizar análisis"
                  : "Analizar búsqueda"}
          </button>
        </div>
      </div>
    </section>
  );
}
