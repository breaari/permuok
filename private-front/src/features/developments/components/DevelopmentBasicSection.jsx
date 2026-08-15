import { useMemo } from "react";

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

export default function DevelopmentBasicSection({ form, setField }) {
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
              className={`text-xs ${getCounterClass(
                title.length,
                TITLE_MAX
              )}`}
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

        {/* SHORT DESCRIPTION */}
        <div>
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <label className="text-sm font-medium text-slate-700">
              Descripción corta
            </label>

            <span
              className={`text-xs ${getCounterClass(
                shortDescription.length,
                SHORT_DESCRIPTION_MAX
              )}`}
            >
              {shortDescription.length}/{SHORT_DESCRIPTION_MAX}
            </span>
          </div>

          <textarea
            maxLength={SHORT_DESCRIPTION_MAX}
            rows={3}
            value={shortDescription}
            onChange={(e) =>
              setField("short_description", e.target.value)
            }
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
                DESCRIPTION_MAX
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
            <p className="mt-1 text-xs text-rose-500">
              {errors.description}
            </p>
          )}
        </div>
      </div>
    </section>
  );
}