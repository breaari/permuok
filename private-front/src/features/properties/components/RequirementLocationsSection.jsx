import { ALLOWED_COUNTRIES } from "../utils/PropertyFormHelpers";

export default function RequirementLocationsSection({
  locations = [],
  onAdd,
  onUpdate,
  onRemove,
}) {
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Ubicaciones buscadas
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Indicá una o varias zonas donde aceptarías la propiedad buscada.
          No hace falta una dirección exacta.
        </p>
      </div>

      <div className="space-y-4">
        {!locations.length && (
          <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">
            Todavía no cargaste ubicaciones buscadas.
          </div>
        )}

        {locations.map((loc, i) => (
          <div
            key={i}
            className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4"
          >
            <div className="flex items-center justify-between gap-3">
              <p className="text-sm font-semibold text-slate-800">
                Ubicación {i + 1}
              </p>

              <button
                type="button"
                onClick={() => onRemove(i)}
                className="text-xs sm:text-sm font-medium text-rose-600 hover:text-rose-700"
              >
                Eliminar
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">
                  País
                </label>
                <select
                  value={loc.country || ""}
                  onChange={(e) => onUpdate(i, "country", e.target.value)}
                  className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                >
                  <option value="">Seleccionar</option>
                  {ALLOWED_COUNTRIES.map((country) => (
                    <option key={country.value} value={country.value}>
                      {country.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">
                  Provincia / Estado
                </label>
                <input
                  type="text"
                  placeholder="Ej. Buenos Aires"
                  value={loc.province || ""}
                  onChange={(e) => onUpdate(i, "province", e.target.value)}
                  className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">
                  Ciudad
                </label>
                <input
                  type="text"
                  placeholder="Ej. Mar del Plata"
                  value={loc.city || ""}
                  onChange={(e) => onUpdate(i, "city", e.target.value)}
                  className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">
                  Zona / Barrio
                </label>
                <input
                  type="text"
                  placeholder="Ej. Güemes / Playa Grande / Palermo"
                  value={loc.zone || ""}
                  onChange={(e) => onUpdate(i, "zone", e.target.value)}
                  className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                />
              </div>
            </div>
          </div>
        ))}

        <button
          type="button"
          onClick={onAdd}
          className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors"
        >
          + Agregar ubicación buscada
        </button>
      </div>
    </section>
  );
}