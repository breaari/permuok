import PlacesAddressInput from "./PlacesAddressInput";

export default function PropertyLocationSection({
  form = {},
  setField = () => {},
  onLocationValidityChange,
  googleMapsLoaded,
}) {
  function handleAddressChange(value) {
    setField("address", value);
    setField("formatted_address", "");
    setField("place_id", "");
    setField("latitude", "");
    setField("longitude", "");

    onLocationValidityChange?.(false);
  }

  function handlePlaceSelect(location) {
    setField("address", location.address);
    setField(
      "formatted_address",
      location.formatted_address,
    );
    setField("place_id", location.place_id);
    setField("latitude", location.latitude);
    setField("longitude", location.longitude);
    setField("country", location.country);
    setField("province", location.province);
    setField("city", location.city);
    setField("zone", location.zone);

    onLocationValidityChange?.(true);
  }

  function clearValidatedAddress() {
    setField("address", "");
    setField("formatted_address", "");
    setField("place_id", "");
    setField("latitude", "");
    setField("longitude", "");
    setField("country", "");
    setField("province", "");
    setField("city", "");
    setField("zone", "");

    onLocationValidityChange?.(false);
  }

  const hasValidatedAddress =
    !!form?.place_id &&
    form?.latitude !== "" &&
    form?.longitude !== "" &&
    !!form?.formatted_address;

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Ubicación
        </h2>

        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Buscá la dirección desde Google Maps y completá manualmente los datos si querés ajustarlos.
        </p>
      </div>

      <div className="space-y-5">
        <div>
          <div className="flex items-center justify-end mb-1">
            {hasValidatedAddress && (
              <button
                type="button"
                onClick={clearValidatedAddress}
                className="text-xs font-medium text-emerald-700 hover:text-emerald-800"
              >
                Limpiar
              </button>
            )}
          </div>

          <PlacesAddressInput
            value={form.address || ""}
            onValueChange={handleAddressChange}
            onPlaceSelect={handlePlaceSelect}
            googleMapsLoaded={googleMapsLoaded}
            label="Dirección"
            placeholder="Buscar dirección con Google Maps"
          />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              País
            </label>

            <input
              placeholder="País"
              value={form.country || ""}
              onChange={(event) =>
                setField(
                  "country",
                  event.target.value,
                )
              }
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Provincia / Estado
            </label>

            <input
              placeholder="Provincia"
              value={form.province || ""}
              onChange={(event) =>
                setField(
                  "province",
                  event.target.value,
                )
              }
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Ciudad
            </label>

            <input
              placeholder="Ciudad"
              value={form.city || ""}
              onChange={(event) =>
                setField(
                  "city",
                  event.target.value,
                )
              }
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Zona / Barrio
            </label>

            <input
              placeholder="Zona / Barrio"
              value={form.zone || ""}
              onChange={(event) =>
                setField(
                  "zone",
                  event.target.value,
                )
              }
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>
        </div>
      </div>
    </section>
  );
}