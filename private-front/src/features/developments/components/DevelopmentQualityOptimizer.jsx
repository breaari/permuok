import { Icon } from "../../../ui/icons/Index";

function getQualityMeta(level) {
  const map = {
    poor: {
      label: "Desarrollo deficiente",
      badge: "bg-rose-50 text-rose-700 border-rose-200",
    },

    needs_improvement: {
      label: "Necesita mejoras",
      badge: "bg-amber-50 text-amber-700 border-amber-200",
    },

    good: {
      label: "Buen desarrollo",
      badge: "bg-sky-50 text-sky-700 border-sky-200",
    },

    very_good: {
      label: "Muy buen desarrollo",
      badge: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },

    excellent: {
      label: "Desarrollo excelente",
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

function normalizeDevelopmentTopic(value) {
  const text = String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");

  // Superficies / m² / áreas de tipologías
  if (
    text.includes("superficie") ||
    text.includes(" m²") ||
    text.includes("m2") ||
    text.includes("area_from") ||
    text.includes("area_to")
  ) {
    return "unit_surface";
  }

  // Cantidad/disponibilidad de unidades
  if (
    text.includes("unidad disponible") ||
    text.includes("unidades disponibles") ||
    text.includes("disponibilidad") ||
    text.includes("available_units")
  ) {
    return "availability";
  }

  // Precios
  if (
    text.includes("precio") ||
    text.includes("valor") ||
    text.includes("price_from") ||
    text.includes("price_to")
  ) {
    return "price";
  }

  // Tipología/configuración
  if (
    text.includes("tipologia") ||
    text.includes("ambiente") ||
    text.includes("dormitorio") ||
    text.includes("habitacion")
  ) {
    return "unit_type";
  }

  // Título
  if (text.includes("titulo") || text.includes("nombre del proyecto")) {
    return "title";
  }

  // Descripción
  if (
    text.includes("descripcion") ||
    text.includes("texto comercial") ||
    text.includes("texto de relleno")
  ) {
    return "description";
  }

  // Imágenes
  if (
    text.includes("imagen") ||
    text.includes("foto") ||
    text.includes("material visual")
  ) {
    return "images";
  }

  // Ubicación
  if (
    text.includes("ubicacion") ||
    text.includes("direccion") ||
    text.includes("ciudad") ||
    text.includes("zona")
  ) {
    return "location";
  }

  // Amenities
  if (
    text.includes("amenity") ||
    text.includes("amenities") ||
    text.includes("servicio")
  ) {
    return "amenities";
  }

  // Etapa / entrega
  if (
    text.includes("etapa") ||
    text.includes("entrega") ||
    text.includes("posesion") ||
    text.includes("development_stage")
  ) {
    return "stage";
  }

  return text.slice(0, 100);
}

function buildSuggestions(quality) {
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
   * Si ya detectamos una contradicción sobre superficies,
   * disponibilidad, precio, etc., no repetimos después
   * una sugerencia sobre exactamente ese mismo problema.
   */
  for (const message of contradictions) {
    const topic = normalizeDevelopmentTopic(message);

    if (!topic || usedTopics.has(topic)) {
      continue;
    }

    usedTopics.add(topic);

    result.push({
      field: topic,
      priority: "high",
      title: "Revisá esta inconsistencia",
      message,
      source: "contradiction",
    });
  }

  /*
   * Incorporamos únicamente sugerencias que aporten
   * un tema nuevo.
   */
  for (const item of suggestions) {
    const topic = normalizeDevelopmentTopic(
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

export default function DevelopmentQualityOptimizer({
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
  const needsAI = quality?.status === "needs_ai";

  const score = completed ? Number(quality?.score || 0) : null;

  const meta = completed ? getQualityMeta(quality?.quality_level) : null;

  const sections = quality?.sections || {};
  const suggestions = buildSuggestions(quality);

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
                Optimizador de desarrollo
              </h2>

              <p className="mt-1 text-sm leading-5 text-slate-500">
                Analizamos la calidad de la publicación, la información
                comercial, las tipologías, la coherencia y el potencial de
                matching.
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
                Calidad del desarrollo
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
                El índice combina la calidad objetiva del proyecto con el
                análisis IA del contenido, coherencia y capacidad de generar
                compatibilidades relevantes.
              </p>
            </div>

            <div className="grid gap-5">
              <SectionScore
                label="Datos del proyecto"
                score={sections?.project?.score}
                maxScore={sections?.project?.max_score}
              />

              <SectionScore
                label="Ubicación"
                score={sections?.location?.score}
                maxScore={sections?.location?.max_score}
              />

              <SectionScore
                label="Comercialización"
                score={sections?.commercial?.score}
                maxScore={sections?.commercial?.max_score}
              />

              <SectionScore
                label="Tipologías"
                score={sections?.unit_types?.score}
                maxScore={sections?.unit_types?.max_score}
              />

              <SectionScore
                label="Amenities"
                score={sections?.amenities?.score}
                maxScore={sections?.amenities?.max_score}
              />

              <SectionScore
                label="Imágenes"
                score={sections?.images?.score}
                maxScore={sections?.images?.max_score}
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
                  {waitingAI
                    ? "Analizando desarrollo"
                    : needsAI
                      ? "Necesita un nuevo análisis"
                      : "Análisis pendiente"}
                </p>

                <p className="mt-1 text-sm leading-5 text-slate-600">
                  {waitingAI
                    ? "Estamos evaluando la publicación, las tipologías, la información comercial, la coherencia y el potencial de matching."
                    : needsAI
                      ? "El desarrollo cambió desde el último análisis o todavía no cuenta con un análisis vigente."
                      : "Ejecutá el análisis para obtener el índice de calidad."}
                </p>
              </div>
            </div>
          </div>
        )}

        {completed && suggestions.length > 0 && (
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
              {suggestions.map((suggestion, index) => (
                <div
                  key={index}
                  className="flex gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                >
                  <div className="mt-0.5 text-amber-600">
                    <Icon name="warning" size={18} />
                  </div>

                  <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-900">
                      {suggestion.title}
                    </p>

                    {suggestion.message && (
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
                ? "Analizando desarrollo..."
                : completed
                  ? "Actualizar análisis"
                  : "Analizar desarrollo"}
          </button>
        </div>
      </div>
    </section>
  );
}
