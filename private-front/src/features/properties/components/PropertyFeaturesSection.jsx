import { CURRENCIES, PROPERTY_TYPES } from "../utils/PropertyFormHelpers";
import { AMENITIES } from "../../shared/helpers/amenities";

export default function PropertyFeaturesSection({ form, setField }) {
  function handleNumberChange(name, value) {
    setField(name, value);
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Características de la propiedad
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Completá la información principal del inmueble para que la publicación
          sea más clara y fácil de comparar.
        </p>
      </div>

      <div className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Tipo de propiedad
            </label>
            <select
              value={form.property_type || ""}
              onChange={(e) => setField("property_type", e.target.value)}
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            >
              <option value="">Seleccionar</option>
              {PROPERTY_TYPES.map((type) => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Precio
            </label>
            <input
              type="number"
              min="0"
              placeholder="Ej. 120000"
              value={form.price ?? ""}
              onChange={(e) => handleNumberChange("price", e.target.value)}
              className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Moneda
            </label>
            <select
              value={form.currency || "USD"}
              onChange={(e) => setField("currency", e.target.value)}
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            >
              {CURRENCIES.map((currency) => (
                <option key={currency.value} value={currency.value}>
                  {currency.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:p-5">
          <h3 className="text-sm font-semibold text-slate-800 mb-4">
            Superficie
          </h3>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">
                Superficie total (m²)
              </label>
              <input
                type="number"
                min="0"
                step="0.01"
                placeholder="Ej. 120"
                value={form.total_area ?? ""}
                onChange={(e) =>
                  handleNumberChange("total_area", e.target.value)
                }
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">
                Superficie cubierta (m²)
              </label>
              <input
                type="number"
                min="0"
                step="0.01"
                placeholder="Ej. 95"
                value={form.covered_area ?? ""}
                onChange={(e) =>
                  handleNumberChange("covered_area", e.target.value)
                }
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:p-5">
          <h3 className="text-sm font-semibold text-slate-800 mb-4">
            Ambientes y detalles
          </h3>

          <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:p-5">
            <h3 className="text-sm font-semibold text-slate-800 mb-4">
              Amenities
            </h3>

            <div className="flex flex-wrap gap-2">
              {AMENITIES.map((item) => {
                const active = Array.isArray(form.amenities)
                  ? form.amenities.includes(item.value)
                  : false;

                return (
                  <button
                    key={item.value}
                    type="button"
                    onClick={() => {
                      const current = Array.isArray(form.amenities)
                        ? form.amenities
                        : [];

                      const next = current.includes(item.value)
                        ? current.filter((v) => v !== item.value)
                        : [...current, item.value];

                      setField("amenities", next);
                    }}
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
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">
                Dormitorios
              </label>
              <input
                type="number"
                min="0"
                placeholder="Ej. 3"
                value={form.bedrooms ?? ""}
                onChange={(e) => handleNumberChange("bedrooms", e.target.value)}
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">
                Baños
              </label>
              <input
                type="number"
                min="0"
                placeholder="Ej. 2"
                value={form.bathrooms ?? ""}
                onChange={(e) =>
                  handleNumberChange("bathrooms", e.target.value)
                }
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">
                Cocheras
              </label>
              <input
                type="number"
                min="0"
                placeholder="Ej. 1"
                value={form.garages ?? ""}
                onChange={(e) => handleNumberChange("garages", e.target.value)}
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">
                Antigüedad
              </label>
              <input
                type="number"
                min="0"
                placeholder="Ej. 8"
                value={form.antiquity ?? ""}
                onChange={(e) =>
                  handleNumberChange("antiquity", e.target.value)
                }
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
