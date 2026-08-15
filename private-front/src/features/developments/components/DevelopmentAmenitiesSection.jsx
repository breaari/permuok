import { useMemo } from "react";
import { Icon } from "../../../ui/icons/Index";
import { AMENITIES } from "../../shared/helpers/amenities";


export default function DevelopmentAmenitiesSection({
  amenities = [],
  setAmenities,
}) {
  const selectedAmenities = useMemo(
    () => (Array.isArray(amenities) ? amenities : []),
    [amenities],
  );

  function toggleAmenity(value) {
    setAmenities((prev) => {
      const current = Array.isArray(prev) ? prev : [];

      if (current.includes(value)) {
        return current.filter((item) => item !== value);
      }

      return [...current, value];
    });
  }

  const selectedCount = selectedAmenities.length;

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Amenities
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Seleccioná las características generales del desarrollo que querés
          destacar en la publicación.
        </p>
      </div>

      <div className="space-y-6">
        {/* Selector */}
        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:p-5">
          <h3 className="text-sm font-semibold text-slate-800 mb-4">
            Selección de amenities
          </h3>

          <div className="flex flex-wrap gap-2.5">
            {AMENITIES.map((item) => {
              const active = selectedAmenities.includes(item.value);

              return (
                <button
                  key={item.value}
                  type="button"
                  onClick={() => toggleAmenity(item.value)}
                  className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-semibold transition ${
                    active
                      ? "border-emerald-600 bg-emerald-600 text-white shadow-sm shadow-emerald-600/20"
                      : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                  }`}
                >
                  {active && <Icon name="check" size={14} />}
                  {item.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Feedback (sin botón) */}
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
          <p className="text-sm text-slate-600">
            {selectedCount > 0
              ? `${selectedCount} amenity${selectedCount !== 1 ? "ies" : ""} seleccionada${selectedCount !== 1 ? "s" : ""}.`
              : "Todavía no seleccionaste amenities."}
          </p>
        </div>
      </div>
    </section>
  );
}
