import RequirementTypeSelector from "./RequirementTypeSelector";
import RequirementLocationsSection from "./PropertyLocationSection";
import {
  CURRENCIES,
  CASH_DIFFERENCE_DIRECTIONS,
  PROPERTY_CONDITIONS,
} from "../utils/PropertyFormHelpers";
import { Icon } from "../../../ui/icons/Index";

export default function PropertyExchangeSection({
  requirements,
  setRequirementField,
  onAddLocation,
  onUpdateLocation,
  onRemoveLocation,
  googleMapsLoaded,
}) {
  const isCriteriaMode = requirements.criteria_mode === "criteria";
  const showCashDifference =
    requirements.accepts_swap_plus_cash || requirements.accepts_cash_only;

  function togglePropertyType(type) {
    const currentTypes = requirements.property_types || [];
    const exists = currentTypes.includes(type);

    setRequirementField(
      "property_types",
      exists ? currentTypes.filter((t) => t !== type) : [...currentTypes, type],
    );
  }

  return (
    <div className="space-y-6">
      {/* Condiciones de permuta */}
      <section className="bg-white p-6 sm:p-8 rounded-lg shadow-sm border border-slate-200 space-y-6">
        <div className="flex items-center gap-3">
          <Icon name="refresh" size={18} className="text-slate-400" />
          <h3 className="text-lg font-bold text-slate-900">
            Condiciones de permuta
          </h3>
        </div>

        <div className="space-y-6">
          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">
              Modalidad de búsqueda
            </p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label className="flex items-center p-4 rounded-lg bg-slate-50 border border-transparent cursor-pointer hover:bg-slate-100 transition-colors">
                <input
                  checked={requirements.criteria_mode === "open"}
                  onChange={() => setRequirementField("criteria_mode", "open")}
                  className="w-5 h-5 text-slate-900 focus:ring-slate-900 border-slate-300"
                  name="criteria_mode"
                  type="radio"
                />
                <span className="ml-3 font-medium text-slate-800">
                  Abierto a propuestas
                </span>
              </label>

              <label className="flex items-center p-4 rounded-lg bg-slate-50 border border-transparent cursor-pointer hover:bg-slate-100 transition-colors">
                <input
                  checked={requirements.criteria_mode === "criteria"}
                  onChange={() =>
                    setRequirementField("criteria_mode", "criteria")
                  }
                  className="w-5 h-5 text-slate-900 focus:ring-slate-900 border-slate-300"
                  name="criteria_mode"
                  type="radio"
                />
                <span className="ml-3 font-medium text-slate-800">
                  Con criterios específicos
                </span>
              </label>
            </div>
          </div>

          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">
              Opciones de flexibilidad
            </p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label className="flex items-center group cursor-pointer">
                <input
                  className="w-5 h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                  type="checkbox"
                  checked={requirements.accepts_open_proposals}
                  onChange={(e) =>
                    setRequirementField(
                      "accepts_open_proposals",
                      e.target.checked,
                    )
                  }
                />
                <span className="ml-3 text-sm text-slate-600 group-hover:text-slate-900">
                  Abierto a propuestas
                </span>
              </label>

              <label className="flex items-center group cursor-pointer">
                <input
                  className="w-5 h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                  type="checkbox"
                  checked={requirements.accepts_total_swap}
                  onChange={(e) =>
                    setRequirementField("accepts_total_swap", e.target.checked)
                  }
                />
                <span className="ml-3 text-sm text-slate-600 group-hover:text-slate-900">
                  Permuta total
                </span>
              </label>

              <label className="flex items-center group cursor-pointer">
                <input
                  className="w-5 h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                  type="checkbox"
                  checked={requirements.accepts_swap_plus_cash}
                  onChange={(e) =>
                    setRequirementField(
                      "accepts_swap_plus_cash",
                      e.target.checked,
                    )
                  }
                />
                <span className="ml-3 text-sm text-slate-600 group-hover:text-slate-900">
                  Permuta + efectivo
                </span>
              </label>

              <label className="flex items-center group cursor-pointer">
                <input
                  className="w-5 h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                  type="checkbox"
                  checked={requirements.accepts_multiple_swap}
                  onChange={(e) =>
                    setRequirementField(
                      "accepts_multiple_swap",
                      e.target.checked,
                    )
                  }
                />
                <span className="ml-3 text-sm text-slate-600 group-hover:text-slate-900">
                  Acepta múltiples inmuebles
                </span>
              </label>

              <label className="flex items-center group cursor-pointer">
                <input
                  className="w-5 h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                  type="checkbox"
                  checked={requirements.accepts_cash_only}
                  onChange={(e) =>
                    setRequirementField("accepts_cash_only", e.target.checked)
                  }
                />
                <span className="ml-3 text-sm text-slate-600 group-hover:text-slate-900">
                  Solo efectivo
                </span>
              </label>
            </div>
          </div>

          {showCashDifference && (
            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:p-5">
              <h4 className="text-sm font-semibold text-slate-800 mb-4">
                Diferencia en efectivo
              </h4>

              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                    Dirección de la diferencia
                  </label>
                  <select
                    value={requirements.cash_difference_direction}
                    onChange={(e) =>
                      setRequirementField(
                        "cash_difference_direction",
                        e.target.value,
                      )
                    }
                    className="w-full rounded-lg border-none bg-white px-4 py-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                  >
                    <option value="">Seleccionar</option>
                    {CASH_DIFFERENCE_DIRECTIONS.map((item) => (
                      <option key={item.value} value={item.value}>
                        {item.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                      Monto mín.
                    </label>
                    <input
                      type="number"
                      min="0"
                      inputMode="decimal"
                      value={requirements.cash_difference_min}
                      onChange={(e) =>
                        setRequirementField(
                          "cash_difference_min",
                          e.target.value,
                        )
                      }
                      className="no-spinner w-full rounded-lg border-none bg-white px-4 py-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                      placeholder="0"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                      Monto máx.
                    </label>
                    <input
                      type="number"
                      min="0"
                      inputMode="decimal"
                      value={requirements.cash_difference_max}
                      onChange={(e) =>
                        setRequirementField(
                          "cash_difference_max",
                          e.target.value,
                        )
                      }
                      className="no-spinner w-full rounded-lg border-none bg-white px-4 py-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                      placeholder="Máximo"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                      Moneda
                    </label>
                    <select
                      value={requirements.cash_difference_currency}
                      onChange={(e) =>
                        setRequirementField(
                          "cash_difference_currency",
                          e.target.value,
                        )
                      }
                      className="w-full rounded-lg border-none bg-white px-4 py-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                    >
                      {CURRENCIES.map((currency) => (
                        <option key={currency.value} value={currency.value}>
                          {currency.label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </section>

      {/* Qué propiedad está buscando */}
      {isCriteriaMode && (
        <section className="bg-white p-6 sm:p-8 rounded-lg shadow-sm border border-slate-200 space-y-8">
          <div className="flex items-center gap-3">
            <Icon name="search" size={18} className="text-slate-400" />
            <h3 className="text-lg font-bold text-slate-900">
              Qué propiedad estás buscando
            </h3>
          </div>

          <div className="space-y-6">
            <div>
              <p className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">
                Tipos de propiedad aceptados
              </p>

              <RequirementTypeSelector
                selected={requirements.property_types}
                onToggle={togglePropertyType}
              />
            </div>

            <div>
              <p className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">
                Ubicaciones deseadas
              </p>

              <RequirementLocationsSection
                locations={requirements.locations || []}
                onAdd={onAddLocation}
                onUpdate={onUpdateLocation}
                onRemove={onRemoveLocation}
                googleMapsLoaded={googleMapsLoaded}
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-bold uppercase tracking-widest text-slate-500">
                  Presupuesto mín ({requirements.price_currency || "USD"})
                </label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 font-bold">
                    $
                  </span>
                  <input
                    type="number"
                    min="0"
                    inputMode="decimal"
                    value={requirements.price_min}
                    onChange={(e) =>
                      setRequirementField("price_min", e.target.value)
                    }
                    className="no-spinner w-full rounded-lg border-none bg-slate-50 p-3 pl-8 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                    placeholder="0"
                  />
                </div>
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-bold uppercase tracking-widest text-slate-500">
                  Presupuesto máx ({requirements.price_currency || "USD"})
                </label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 font-bold">
                    $
                  </span>
                  <input
                    type="number"
                    min="0"
                    inputMode="decimal"
                    value={requirements.price_max}
                    onChange={(e) =>
                      setRequirementField("price_max", e.target.value)
                    }
                    className="no-spinner w-full rounded-lg border-none bg-slate-50 p-3 pl-8 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                    placeholder="Máximo"
                  />
                </div>
              </div>
            </div>

            <div className="md:w-1/2">
              <label className="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                Moneda del presupuesto
              </label>
              <select
                value={requirements.price_currency}
                onChange={(e) =>
                  setRequirementField("price_currency", e.target.value)
                }
                className="w-full rounded-lg border-none bg-slate-50 p-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
              >
                {CURRENCIES.map((currency) => (
                  <option key={currency.value} value={currency.value}>
                    {currency.label}
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                  Sup. mín (m²)
                </label>
                <input
                  type="number"
                  min="0"
                  inputMode="decimal"
                  value={requirements.min_total_area}
                  onChange={(e) =>
                    setRequirementField("min_total_area", e.target.value)
                  }
                  className="no-spinner w-full rounded-lg border-none bg-slate-50 p-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                  Antig. máx.
                </label>
                <input
                  type="number"
                  min="0"
                  inputMode="numeric"
                  value={requirements.max_antiquity}
                  onChange={(e) =>
                    setRequirementField("max_antiquity", e.target.value)
                  }
                  className="no-spinner w-full rounded-lg border-none bg-slate-50 p-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                  Dormitorios
                </label>
                <input
                  type="number"
                  min="0"
                  inputMode="numeric"
                  value={requirements.min_bedrooms}
                  onChange={(e) =>
                    setRequirementField("min_bedrooms", e.target.value)
                  }
                  className="no-spinner w-full rounded-lg border-none bg-slate-50 p-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                  Baños mín
                </label>
                <input
                  type="number"
                  min="0"
                  inputMode="numeric"
                  value={requirements.min_bathrooms}
                  onChange={(e) =>
                    setRequirementField("min_bathrooms", e.target.value)
                  }
                  className="no-spinner w-full rounded-lg border-none bg-slate-50 p-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                  Cocheras
                </label>
                <input
                  type="number"
                  min="0"
                  inputMode="numeric"
                  value={requirements.min_garages}
                  onChange={(e) =>
                    setRequirementField("min_garages", e.target.value)
                  }
                  className="no-spinner w-full rounded-lg border-none bg-slate-50 p-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
                />
              </div>
            </div>

            <div className="md:w-1/2">
              <label className="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                Estado de la propiedad
              </label>
              <select
                value={requirements.property_condition}
                onChange={(e) =>
                  setRequirementField("property_condition", e.target.value)
                }
                className="w-full rounded-lg border-none bg-slate-50 p-3 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all"
              >
                <option value="">Cualquiera</option>
                {PROPERTY_CONDITIONS.map((condition) => (
                  <option key={condition.value} value={condition.value}>
                    {condition.label}
                  </option>
                ))}
              </select>
            </div>

            <div className="md:w-1/2">
              <label className="flex items-center group cursor-pointer">
                <input
                  className="w-5 h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                  type="checkbox"
                  checked={requirements.open_to_other_zones}
                  onChange={(e) =>
                    setRequirementField("open_to_other_zones", e.target.checked)
                  }
                />
                <span className="ml-3 text-sm text-slate-600 group-hover:text-slate-900">
                  Abierto a otras zonas
                </span>
              </label>
            </div>
          </div>
        </section>
      )}

      {/* Observaciones */}
      <section className="bg-white p-6 sm:p-8 rounded-lg shadow-sm border border-slate-200 space-y-4">
        <div className="flex items-center gap-3">
          <Icon name="clipboardList" size={18} className="text-slate-400" />
          <h3 className="text-lg font-bold text-slate-900">
            Observaciones adicionales
          </h3>
        </div>

        <p className="text-sm text-slate-500">
          Describe detalles adicionales que no estén en los filtros superiores
          para ayudar a encontrar la permuta ideal.
        </p>

        <textarea
          value={requirements.notes}
          onChange={(e) => setRequirementField("notes", e.target.value)}
          className="w-full rounded-lg border-none bg-slate-50 p-4 text-sm focus:ring-0 focus:border-b-2 focus:border-slate-900 transition-all min-h-[120px] resize-none"
          placeholder="Ej: Prefiero zonas tranquilas, cerca de colegios o parques..."
        />
      </section>
    </div>
  );
}