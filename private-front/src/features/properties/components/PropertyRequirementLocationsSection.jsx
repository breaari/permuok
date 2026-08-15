import PlacesAddressInput from "./PlacesAddressInput";
import { Icon } from "../../../ui/icons/Index";

function RequirementLocationItem({
  location,
  index,
  onUpdate,
  onRemove,
  googleMapsLoaded,
}) {
  function updateField(field, value) {
    onUpdate(index, field, value);
  }

  function handleSearchValueChange(value) {
    updateField("formatted_address", value);

    updateField("place_id", "");
    updateField("latitude", "");
    updateField("longitude", "");
  }

  function handlePlaceSelect(selectedLocation) {
    updateField(
      "formatted_address",
      selectedLocation.formatted_address,
    );

    updateField(
      "place_id",
      selectedLocation.place_id,
    );

    updateField(
      "latitude",
      selectedLocation.latitude,
    );

    updateField(
      "longitude",
      selectedLocation.longitude,
    );

    updateField(
      "country",
      selectedLocation.country,
    );

    updateField(
      "country_code",
      selectedLocation.country_code,
    );

    updateField(
      "province",
      selectedLocation.province,
    );

    updateField(
      "city",
      selectedLocation.city,
    );

    updateField(
      "zone",
      selectedLocation.zone,
    );
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="flex items-start justify-between gap-4 mb-5">
        <div>
          <h4 className="text-base font-bold text-slate-900">
            Ubicación deseada {index + 1}
          </h4>

          <p className="mt-1 text-sm text-slate-500">
            Buscá una dirección, localidad o zona y ajustá los campos manualmente.
          </p>
        </div>

        <button
          type="button"
          onClick={() => onRemove(index)}
          className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50"
        >
          <Icon name="trash" size={15} />
          Eliminar
        </button>
      </div>

      <div className="space-y-5">
        <PlacesAddressInput
          value={
            location.formatted_address ||
            [
              location.zone,
              location.city,
              location.province,
              location.country,
            ]
              .filter(Boolean)
              .join(", ")
          }
          onValueChange={handleSearchValueChange}
          onPlaceSelect={handlePlaceSelect}
          googleMapsLoaded={googleMapsLoaded}
          label="Dirección, localidad o zona"
          placeholder="Buscar ubicación con Google Maps"
        />

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              País
            </label>

            <input
              type="text"
              value={location.country || ""}
              onChange={(event) =>
                updateField(
                  "country",
                  event.target.value,
                )
              }
              placeholder="País"
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Código de país
            </label>

            <input
              type="text"
              value={location.country_code || ""}
              onChange={(event) =>
                updateField(
                  "country_code",
                  event.target.value
                    .trim()
                    .toUpperCase(),
                )
              }
              placeholder="AR"
              maxLength={10}
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm uppercase text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Provincia / Estado
            </label>

            <input
              type="text"
              value={location.province || ""}
              onChange={(event) =>
                updateField(
                  "province",
                  event.target.value,
                )
              }
              placeholder="Provincia"
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Ciudad
            </label>

            <input
              type="text"
              value={location.city || ""}
              onChange={(event) =>
                updateField(
                  "city",
                  event.target.value,
                )
              }
              placeholder="Ciudad"
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div className="md:col-span-2">
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Zona / Barrio
            </label>

            <input
              type="text"
              value={location.zone || ""}
              onChange={(event) =>
                updateField(
                  "zone",
                  event.target.value,
                )
              }
              placeholder="Zona / Barrio"
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>
        </div>
      </div>
    </section>
  );
}

export default function PropertyRequirementLocationsSection({
  locations = [],
  onAdd,
  onUpdate,
  onRemove,
  googleMapsLoaded,
}) {
  return (
    <div className="space-y-4">
      {locations.map((location, index) => (
        <RequirementLocationItem
          key={
            location.id ||
            `requirement-location-${index}`
          }
          location={location}
          index={index}
          onUpdate={onUpdate}
          onRemove={onRemove}
          googleMapsLoaded={googleMapsLoaded}
        />
      ))}

      <button
        type="button"
        onClick={() => onAdd()}
        className="inline-flex items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-700"
      >
        <Icon name="plus" size={17} />
        Agregar ubicación
      </button>

      {locations.length === 0 && (
        <p className="text-xs text-slate-500">
          Agregá al menos una ubicación cuando uses criterios específicos.
        </p>
      )}
    </div>
  );
}