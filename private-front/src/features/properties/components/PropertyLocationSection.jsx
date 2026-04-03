import { useEffect, useRef, useState } from "react";

function extractAddressComponent(components, type) {
  const item = components?.find((c) => c.types?.includes(type));
  if (!item) return "";

  return item.longText || item.long_name || item.shortText || item.short_name || "";
}

function normalizeProvinceName(value) {
  return String(value || "")
    .replace(/\s+Province$/i, "")
    .replace(/^Provincia de /i, "")
    .trim();
}

export default function PropertyLocationSection({
  form,
  setField,
  onLocationValidityChange,
  googleMapsLoaded,
}) {
  console.log("biribi:" + form.address);
  const [inputValue, setInputValue] = useState(form.address || "");
  const [suggestions, setSuggestions] = useState([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const [addressError, setAddressError] = useState("");

  const wrapperRef = useRef(null);
  const requestIdRef = useRef(0);
  const sessionTokenRef = useRef(null);
  const placesLibRef = useRef(null);

  useEffect(() => {
    setInputValue(form.address || "");
  }, [form.address]);

  useEffect(() => {
    if (!googleMapsLoaded || !window.google) return;

    let mounted = true;

    async function boot() {
      try {
        const placesLib = await window.google.maps.importLibrary("places");
        if (!mounted) return;

        placesLibRef.current = placesLib;

        if (!sessionTokenRef.current) {
          sessionTokenRef.current = new placesLib.AutocompleteSessionToken();
        }
      } catch (error) {
        console.error("No se pudo cargar Places:", error);
      }
    }

    boot();

    return () => {
      mounted = false;
    };
  }, [googleMapsLoaded]);

  useEffect(() => {
    function handleClickOutside(event) {
      if (!wrapperRef.current?.contains(event.target)) {
        setOpen(false);
        setActiveIndex(-1);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  useEffect(() => {
    if (!googleMapsLoaded || !placesLibRef.current || inputValue.trim().length < 3) {
      setSuggestions([]);
      setOpen(false);
      setLoading(false);
      return;
    }

    const currentRequestId = ++requestIdRef.current;
    setLoading(true);

    const timer = setTimeout(async () => {
      try {
        const { AutocompleteSuggestion, AutocompleteSessionToken } = placesLibRef.current;

        if (!sessionTokenRef.current) {
          sessionTokenRef.current = new AutocompleteSessionToken();
        }

        const response =
          await AutocompleteSuggestion.fetchAutocompleteSuggestions({
            input: inputValue,
            sessionToken: sessionTokenRef.current,
            includedRegionCodes: ["ar", "us", "it"],
            language: "es",
          });

        if (requestIdRef.current !== currentRequestId) return;

        const nextSuggestions = response?.suggestions ?? [];
        setSuggestions(nextSuggestions);
        setOpen(nextSuggestions.length > 0);
        setActiveIndex(-1);
        setAddressError("");
      } catch (error) {
        if (requestIdRef.current !== currentRequestId) return;
        console.error("Error buscando dirección:", error);
        setSuggestions([]);
        setOpen(false);
        setAddressError("No se pudieron obtener sugerencias.");
      } finally {
        if (requestIdRef.current === currentRequestId) {
          setLoading(false);
        }
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [googleMapsLoaded, inputValue]);

  async function selectSuggestion(suggestion) {
    try {
      const place = suggestion.placePrediction?.toPlace?.();
      if (!place) return;

      await place.fetchFields({
        fields: ["formattedAddress", "location", "id", "addressComponents"],
      });

      const components = place.addressComponents ?? [];
      const lat = place.location?.lat?.();
      const lng = place.location?.lng?.();

      const country = extractAddressComponent(components, "country");
      const province = normalizeProvinceName(
        extractAddressComponent(components, "administrative_area_level_1")
      );
      const city =
        extractAddressComponent(components, "locality") ||
        extractAddressComponent(components, "administrative_area_level_2");
      const zone =
        extractAddressComponent(components, "sublocality_level_1") ||
        extractAddressComponent(components, "sublocality") ||
        extractAddressComponent(components, "neighborhood");

      const formattedAddress = place.formattedAddress || "";

      setInputValue(formattedAddress);
      setSuggestions([]);
      setOpen(false);
      setActiveIndex(-1);
      setAddressError("");

      setField("address", formattedAddress);
      setField("formatted_address", formattedAddress);
      setField("place_id", place.id || "");
      setField("latitude", typeof lat === "number" ? lat : "");
      setField("longitude", typeof lng === "number" ? lng : "");
      setField("country", country || "");
      setField("province", province || "");
      setField("city", city || "");
      setField("zone", zone || "");

      onLocationValidityChange?.(true);

      const { AutocompleteSessionToken } = placesLibRef.current;
      sessionTokenRef.current = new AutocompleteSessionToken();
    } catch (error) {
      console.error("Error validando dirección:", error);
      setAddressError("No se pudo validar la dirección seleccionada.");
      onLocationValidityChange?.(false);
    }
  }

  function handleInputChange(e) {
    const nextValue = e.target.value;

    setInputValue(nextValue);
    setAddressError("");

    setField("address", nextValue);
    setField("formatted_address", "");
    setField("place_id", "");
    setField("latitude", "");
    setField("longitude", "");

    onLocationValidityChange?.(false);
  }

  function handleKeyDown(e) {
    if (!open || suggestions.length === 0) return;

    if (e.key === "ArrowDown") {
      e.preventDefault();
      setActiveIndex((prev) => (prev < suggestions.length - 1 ? prev + 1 : 0));
    }

    if (e.key === "ArrowUp") {
      e.preventDefault();
      setActiveIndex((prev) => (prev > 0 ? prev - 1 : suggestions.length - 1));
    }

    if (e.key === "Enter" && activeIndex >= 0) {
      e.preventDefault();
      selectSuggestion(suggestions[activeIndex]);
    }

    if (e.key === "Escape") {
      setOpen(false);
      setActiveIndex(-1);
    }
  }

  function clearValidatedAddress() {
    setInputValue("");
    setSuggestions([]);
    setOpen(false);
    setActiveIndex(-1);
    setAddressError("");

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
    !!form.place_id &&
    form.latitude !== "" &&
    form.longitude !== "" &&
    !!form.formatted_address;

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Ubicación
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Buscá la dirección desde Google Maps y completá manualmente los datos
          si querés ajustarlos.
        </p>
      </div>

      <div className="space-y-5" ref={wrapperRef}>
        <div>
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <label className="block text-sm font-medium text-slate-700">
              Dirección
            </label>

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

          <div className="relative">
            <input
              type="text"
              value={inputValue}
              onChange={handleInputChange}
              onFocus={() => {
                if (suggestions.length > 0) setOpen(true);
              }}
              onKeyDown={handleKeyDown}
              disabled={!googleMapsLoaded}
              placeholder={
                googleMapsLoaded
                  ? "Buscar dirección con Google Maps"
                  : "Cargando direcciones..."
              }
              autoComplete="off"
              className={`w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 outline-none transition ${
                addressError
                  ? "border-red-400 focus:border-red-500 focus:ring-4 focus:ring-red-500/10"
                  : "border-slate-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              } disabled:opacity-60`}
            />

            {open && (
              <div className="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                {suggestions.map((suggestion, index) => {
                  const prediction = suggestion.placePrediction;
                  const mainText =
                    prediction?.mainText?.text ||
                    prediction?.text?.text ||
                    "Sin descripción";
                  const secondaryText = prediction?.secondaryText?.text || "";

                  return (
                    <button
                      key={`${prediction?.placeId || prediction?.text?.text || index}-${index}`}
                      type="button"
                      onClick={() => selectSuggestion(suggestion)}
                      className={`w-full px-4 py-3 text-left transition-colors ${
                        index === activeIndex
                          ? "bg-emerald-50"
                          : "bg-white hover:bg-slate-50"
                      }`}
                    >
                      <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-slate-800">
                          {mainText}
                        </p>
                        {secondaryText && (
                          <p className="truncate text-xs text-slate-500">
                            {secondaryText}
                          </p>
                        )}
                      </div>
                    </button>
                  );
                })}

                {loading && (
                  <div className="px-4 py-3 text-sm text-slate-500">
                    Buscando...
                  </div>
                )}
              </div>
            )}
          </div>

          {addressError ? (
            <p className="text-sm text-red-600 mt-1.5">{addressError}</p>
          ) : !googleMapsLoaded ? (
            <p className="mt-1.5 text-xs text-slate-400">
              Esperá un momento mientras se carga Google Maps.
            </p>
          ) : (
            <p className="mt-1.5 text-xs text-slate-400">
              Elegí una sugerencia real para validar correctamente la ubicación.
            </p>
          )}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              País
            </label>
            <input
              placeholder="País"
              value={form.country || ""}
              onChange={(e) => setField("country", e.target.value)}
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
              onChange={(e) => setField("province", e.target.value)}
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
              onChange={(e) => setField("city", e.target.value)}
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
              onChange={(e) => setField("zone", e.target.value)}
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </div>
        </div>
      </div>
    </section>
  );
}