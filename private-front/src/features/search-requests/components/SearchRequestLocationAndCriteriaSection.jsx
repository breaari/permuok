import SearchRequestLocationAutocomplete from "./SearchRequestLocationAutocomplete";

const COUNTRIES = [
  { value: "argentina", label: "Argentina", code: "AR" },
  { value: "italia", label: "Italia", code: "IT" },
  { value: "usa", label: "Estados Unidos", code: "US" },
];

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

function resolveCountryFromPayload(data) {
  const code = String(data?.country_code || "").toUpperCase();
  const countryText = String(data?.country || "").trim().toLowerCase();

  if (code === "AR" || countryText === "argentina") {
    return { value: "argentina", code: "AR", label: "Argentina" };
  }

  if (code === "IT" || countryText === "italia" || countryText === "italy") {
    return { value: "italia", code: "IT", label: "Italia" };
  }

  if (
    code === "US" ||
    countryText === "estados unidos" ||
    countryText === "united states" ||
    countryText === "usa"
  ) {
    return { value: "usa", code: "US", label: "Estados Unidos" };
  }

  return null;
}

function getCountryLabel(value) {
  return COUNTRIES.find((c) => c.value === value)?.label || "";
}

export default function SearchRequestLocationAndCriteriaSection({
  form,
  setField,
  mapsLoaded,
  mapsError,
}) {
  const selectedCountry = COUNTRIES.find((c) => c.value === form.country);

  function clearLocationFields() {
    setField("country", "");
    setField("country_code", "");
    setField("province", "");
    setField("city", "");
    setField("zone", "");
    setField("formatted_address", "");
    setField("place_id", "");
    setField("latitude", "");
    setField("longitude", "");
  }

  function handleLocationChange(data) {
    const address = data?.address || "";
    const placeId = data?.place_id || "";
    const locality = data?.locality || "";
    const province = data?.province || "";
    const zone = data?.zone || "";
    const lat = data?.lat ?? "";
    const lng = data?.lng ?? "";

    if (!placeId) {
      setField("formatted_address", address);
      setField("place_id", "");
      setField("country", "");
      setField("country_code", "");
      setField("province", "");
      setField("city", "");
      setField("zone", "");
      setField("latitude", "");
      setField("longitude", "");
      return;
    }

    const detectedCountry = resolveCountryFromPayload(data);

    if (!detectedCountry) {
      alert("La ubicación seleccionada no pertenece a un país permitido.");
      clearLocationFields();
      return;
    }

    if (detectedCountry.value === "usa") {
      const isMiami =
        String(locality).trim().toLowerCase() === "miami" ||
        String(address).toLowerCase().includes("miami");

      if (!isMiami) {
        alert("Para Estados Unidos solo se permite Miami.");
        clearLocationFields();
        return;
      }
    }

    setField("country", detectedCountry.value);
    setField("country_code", detectedCountry.code);
    setField("province", province);
    setField("city", locality);
    setField("zone", zone);
    setField("formatted_address", address);
    setField("place_id", placeId);
    setField("latitude", lat);
    setField("longitude", lng);
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Ubicación buscada
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Buscá directamente una ubicación y el sistema completará país,
          provincia, ciudad y zona automáticamente.
        </p>
      </div>

      <div className="space-y-5">
        <div className="grid grid-cols-1 gap-4">
          <SearchRequestLocationAutocomplete
            label="Ubicación"
            value={form.formatted_address || ""}
            disabled={false}
            isLoaded={mapsLoaded}
            regionCodes={
              selectedCountry?.code ? [selectedCountry.code.toLowerCase()] : []
            }
            onChangeLocation={handleLocationChange}
          />
        </div>

        {mapsError && (
          <p className="text-xs text-red-700">
            No se pudo cargar Google Maps.
          </p>
        )}

        {form.country === "usa" && (
          <p className="text-xs text-slate-500">
            En Estados Unidos solo se acepta Miami.
          </p>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
          <Field
            label="País"
            hint="Se completa automáticamente a partir de la ubicación seleccionada."
          >
            <input
              type="text"
              value={getCountryLabel(form.country)}
              readOnly
              className="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none cursor-not-allowed"
              placeholder="Se completa automáticamente"
            />
          </Field>

          <Field label="Provincia / Estado">
            <input
              type="text"
              value={form.province || ""}
              readOnly
              className="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none cursor-not-allowed"
              placeholder="Se completa automáticamente"
            />
          </Field>

          <Field label="Ciudad">
            <input
              type="text"
              value={form.city || ""}
              readOnly
              className="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none cursor-not-allowed"
              placeholder="Se completa automáticamente"
            />
          </Field>

          <Field
            label="Zona / Barrio"
            hint="Si Google no detecta un barrio específico, puede quedar vacío."
          >
            <input
              type="text"
              value={form.zone || ""}
              readOnly
              className="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none cursor-not-allowed"
              placeholder="Se completa automáticamente"
            />
          </Field>
        </div>

        <label className="inline-flex items-center gap-3 text-sm font-medium text-slate-700">
          <input
            type="checkbox"
            checked={!!form.open_to_other_zones}
            onChange={(e) => setField("open_to_other_zones", e.target.checked)}
            className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
          />
          Abierto a otras zonas similares
        </label>
      </div>
    </section>
  );
}