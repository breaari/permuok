import {
  SEARCH_REQUEST_CONDITION_OPTIONS,
  SEARCH_REQUEST_PROPERTY_TYPES,
  SEARCH_REQUEST_URGENCY_OPTIONS,
} from "../utils";

import { AMENITIES } from "../../shared/helpers/amenities";

function Field({ label, children, hint = "" }) {
  return (
    <div>
      <label className="block text-sm font-medium text-slate-700 mb-1.5">
        {label}
      </label>
      {children}
      {hint ? <p className="mt-1 text-xs text-slate-500">{hint}</p> : null}
    </div>
  );
}

function SectionBlock({ title, description = "", children }) {
  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-sm font-bold text-slate-900">{title}</h3>
        {description ? (
          <p className="mt-1 text-xs text-slate-500">{description}</p>
        ) : null}
      </div>
      {children}
    </div>
  );
}

export default function SearchRequestBasicSection({
  form,
  setField,
  togglePropertyType,
  toggleAmenity,

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
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Datos de la búsqueda
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Definí qué está buscando tu cliente y qué tipologías podrían encajar.
        </p>
      </div>

      <div className="space-y-6">
        <div className="space-y-4">
          <div>
            <div className="mb-1.5 flex items-center justify-between gap-3">
              <label className="block text-sm font-medium text-slate-700">
                Título
              </label>

              {isEditMode && (
                <button
                  type="button"
                  onClick={onGenerateAITitle}
                  disabled={aiTitleLoading}
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 transition hover:text-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {aiTitleLoading
                    ? "Generando..."
                    : aiTitleSuggestion
                      ? "Generar otra opción"
                      : "Mejorar con IA"}
                </button>
              )}
            </div>

            <input
              type="text"
              value={form.title}
              onChange={(e) => setField("title", e.target.value)}
              placeholder="Ej. Departamento de 2 ambientes en Lanús"
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />

            {aiTitleSuggestion && (
              <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                <div className="flex items-start gap-3">
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                    <span className="text-sm">✦</span>
                  </div>

                  <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">
                      Sugerencia IA
                    </p>

                    <p className="mt-1 text-sm font-semibold leading-5 text-slate-900">
                      {aiTitleSuggestion}
                    </p>

                    <div className="mt-3 flex flex-wrap gap-2">
                      <button
                        type="button"
                        onClick={onApplyAITitle}
                        className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700"
                      >
                        Usar este título
                      </button>

                      <button
                        type="button"
                        onClick={onGenerateAITitle}
                        disabled={aiTitleLoading}
                        className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                      >
                        {aiTitleLoading
                          ? "Generando..."
                          : "Generar otra opción"}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>

          <div>
            <div className="mb-1.5 flex items-center justify-between gap-3">
              <label className="block text-sm font-medium text-slate-700">
                Descripción
              </label>

              {isEditMode && (
                <button
                  type="button"
                  onClick={onGenerateAIDescription}
                  disabled={aiDescriptionLoading}
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 transition hover:text-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {aiDescriptionLoading
                    ? "Generando..."
                    : aiDescriptionSuggestion
                      ? "Generar otra opción"
                      : "Mejorar con IA"}
                </button>
              )}
            </div>

            <textarea
              rows={4}
              value={form.description}
              onChange={(e) => setField("description", e.target.value)}
              placeholder="Contá el contexto de la búsqueda y los criterios realmente importantes."
              className="w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />

            {aiDescriptionSuggestion && (
              <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                <div className="flex items-start gap-3">
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                    <span className="text-sm">✦</span>
                  </div>

                  <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">
                      Sugerencia IA
                    </p>

                    <p className="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">
                      {aiDescriptionSuggestion}
                    </p>

                    <div className="mt-3 flex flex-wrap gap-2">
                      <button
                        type="button"
                        onClick={onApplyAIDescription}
                        className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700"
                      >
                        Usar esta descripción
                      </button>

                      <button
                        type="button"
                        onClick={onGenerateAIDescription}
                        disabled={aiDescriptionLoading}
                        className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                      >
                        {aiDescriptionLoading
                          ? "Generando..."
                          : "Generar otra opción"}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        <div className="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-4">
          <p className="text-sm font-semibold text-emerald-900">
            Cuantos menos filtros agregues, más amplia será la búsqueda.
          </p>
          <p className="mt-1 text-xs sm:text-sm text-emerald-800">
            No es necesario completar todo. Dejar campos abiertos puede
            favorecer más coincidencias y mejores oportunidades de match.
          </p>
        </div>

        <SectionBlock
          title="Tipos de propiedad buscada"
          description="Seleccioná una o varias opciones según lo que podría interesarle al cliente."
        >
          <div className="flex flex-wrap gap-2">
            {SEARCH_REQUEST_PROPERTY_TYPES.map((item) => {
              const active = form.property_types.includes(item.value);

              return (
                <button
                  key={item.value}
                  type="button"
                  onClick={() => togglePropertyType(item.value)}
                  className={`px-3 py-2 rounded-xl text-sm font-semibold border transition ${
                    active
                      ? "bg-emerald-600 text-white border-emerald-600"
                      : "bg-white text-slate-700 border-slate-300 hover:bg-slate-50"
                  }`}
                >
                  {item.label}
                </button>
              );
            })}
          </div>
        </SectionBlock>

        <SectionBlock
          title="Amenities deseadas"
          description="Opcional. Elegí solo las que realmente sean importantes."
        >
          <div className="flex flex-wrap gap-2">
            {AMENITIES.map((item) => {
              const active = form.amenities.includes(item.value);

              return (
                <button
                  key={item.value}
                  type="button"
                  onClick={() => toggleAmenity(item.value)}
                  className={`px-3 py-2 rounded-xl text-sm font-semibold border transition ${
                    active
                      ? "bg-emerald-600 text-white border-emerald-600"
                      : "bg-white text-slate-700 border-slate-300 hover:bg-slate-50"
                  }`}
                >
                  {item.label}
                </button>
              );
            })}
          </div>
        </SectionBlock>

        <SectionBlock
          title="Condiciones generales"
          description="Estos campos ayudan a orientar la búsqueda, pero no todos son obligatorios."
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Estado de la propiedad">
              <select
                value={form.property_condition}
                onChange={(e) => setField("property_condition", e.target.value)}
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              >
                {SEARCH_REQUEST_CONDITION_OPTIONS.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </Field>

            <Field label="Urgencia">
              <select
                value={form.urgency}
                onChange={(e) => setField("urgency", e.target.value)}
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              >
                {SEARCH_REQUEST_URGENCY_OPTIONS.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </Field>
          </div>
        </SectionBlock>

        <SectionBlock
          title="Filtros opcionales"
          description="Usalos solo si realmente son excluyentes. Si los dejás vacíos, la búsqueda será más amplia."
        >
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <Field label="Superficie total mínima">
              <input
                type="number"
                min="0"
                value={form.min_total_area}
                onChange={(e) => setField("min_total_area", e.target.value)}
                placeholder="m²"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Superficie cubierta mínima">
              <input
                type="number"
                min="0"
                value={form.min_covered_area}
                onChange={(e) => setField("min_covered_area", e.target.value)}
                placeholder="m²"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Antigüedad máxima">
              <input
                type="number"
                min="0"
                value={form.max_antiquity}
                onChange={(e) => setField("max_antiquity", e.target.value)}
                placeholder="Años"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Dormitorios mínimos">
              <input
                type="number"
                min="0"
                value={form.min_bedrooms}
                onChange={(e) => setField("min_bedrooms", e.target.value)}
                placeholder="Ej. 2"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Baños mínimos">
              <input
                type="number"
                min="0"
                value={form.min_bathrooms}
                onChange={(e) => setField("min_bathrooms", e.target.value)}
                placeholder="Ej. 2"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Cocheras mínimas">
              <input
                type="number"
                min="0"
                value={form.min_garages}
                onChange={(e) => setField("min_garages", e.target.value)}
                placeholder="Ej. 1"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>
          </div>
        </SectionBlock>
      </div>
    </section>
  );
}
