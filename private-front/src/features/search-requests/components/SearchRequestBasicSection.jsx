import {
  SEARCH_REQUEST_AMENITIES,
  SEARCH_REQUEST_CONDITION_OPTIONS,
  SEARCH_REQUEST_PROPERTY_TYPES,
  SEARCH_REQUEST_URGENCY_OPTIONS,
} from "../utils";

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
        <Field label="Título">
          <input
            type="text"
            value={form.title}
            onChange={(e) => setField("title", e.target.value)}
            placeholder="Ej. Busco lote en zona sur para desarrollo"
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          />
        </Field>

        <Field label="Descripción">
          <textarea
            rows={4}
            value={form.description}
            onChange={(e) => setField("description", e.target.value)}
            placeholder="Contá el contexto de la búsqueda, perfil del cliente y detalles relevantes."
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition resize-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          />
        </Field>

        <div className="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-4">
          <p className="text-sm font-semibold text-emerald-900">
            Cuantos menos filtros agregues, más amplia será la búsqueda.
          </p>
          <p className="mt-1 text-xs sm:text-sm text-emerald-800">
            No es necesario completar todo. Dejar campos abiertos puede favorecer
            más coincidencias y mejores oportunidades de match.
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
            {SEARCH_REQUEST_AMENITIES.map((item) => {
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
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Superficie cubierta mínima">
              <input
                type="number"
                min="0"
                value={form.min_covered_area}
                onChange={(e) => setField("min_covered_area", e.target.value)}
                placeholder="m²"
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Antigüedad máxima">
              <input
                type="number"
                min="0"
                value={form.max_antiquity}
                onChange={(e) => setField("max_antiquity", e.target.value)}
                placeholder="Años"
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Dormitorios mínimos">
              <input
                type="number"
                min="0"
                value={form.min_bedrooms}
                onChange={(e) => setField("min_bedrooms", e.target.value)}
                placeholder="Ej. 2"
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Baños mínimos">
              <input
                type="number"
                min="0"
                value={form.min_bathrooms}
                onChange={(e) => setField("min_bathrooms", e.target.value)}
                placeholder="Ej. 2"
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Cocheras mínimas">
              <input
                type="number"
                min="0"
                value={form.min_garages}
                onChange={(e) => setField("min_garages", e.target.value)}
                placeholder="Ej. 1"
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>
          </div>
        </SectionBlock>
      </div>
    </section>
  );
}