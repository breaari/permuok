import { useEffect, useMemo, useRef, useState } from "react";
import { Icon } from "../../../ui/icons/Index";

function extractAddressComponent(components, type, short = false) {
  const item = components?.find((c) => c.types?.includes(type));
  if (!item) return "";

  return (
    (short ? item.shortText || item.short_name : item.longText || item.long_name) ||
    ""
  );
}

function normalizeProvinceName(value) {
  return String(value || "")
    .replace(/\s+Province$/i, "")
    .replace(/^Provincia de /i, "")
    .trim();
}

function extractZone(components, fallbackAddress = "") {
  return (
    extractAddressComponent(components, "sublocality_level_1") ||
    extractAddressComponent(components, "sublocality") ||
    extractAddressComponent(components, "neighborhood") ||
    extractAddressComponent(components, "administrative_area_level_2") ||
    fallbackAddress
  );
}

export default function SearchRequestLocationAutocomplete({
  label = "Ubicación buscada",
  value,
  disabled,
  isLoaded,
  regionCodes = [],
  onChangeLocation,
}) {
  const [inputValue, setInputValue] = useState(value || "");
  const [suggestions, setSuggestions] = useState([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);

  const wrapperRef = useRef(null);
  const requestIdRef = useRef(0);
  const sessionTokenRef = useRef(null);
  const placesLibRef = useRef(null);

  useEffect(() => {
    setInputValue(value || "");
  }, [value]);

  useEffect(() => {
    if (!isLoaded || !window.google) return;

    let mounted = true;

    async function boot() {
      const placesLib = await window.google.maps.importLibrary("places");
      if (!mounted) return;

      placesLibRef.current = placesLib;

      if (!sessionTokenRef.current) {
        sessionTokenRef.current = new placesLib.AutocompleteSessionToken();
      }
    }

    boot();

    return () => {
      mounted = false;
    };
  }, [isLoaded]);

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

  const canSearch = useMemo(() => {
    return isLoaded && !disabled && inputValue.trim().length >= 3;
  }, [isLoaded, disabled, inputValue]);

  useEffect(() => {
    if (!canSearch || !placesLibRef.current) {
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
            includedRegionCodes: regionCodes,
            language: "es",
          });

        if (requestIdRef.current !== currentRequestId) return;

        const nextSuggestions = response?.suggestions ?? [];
        setSuggestions(nextSuggestions);
        setOpen(nextSuggestions.length > 0);
        setActiveIndex(-1);
      } catch {
        if (requestIdRef.current !== currentRequestId) return;
        setSuggestions([]);
        setOpen(false);
      } finally {
        if (requestIdRef.current === currentRequestId) {
          setLoading(false);
        }
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [canSearch, inputValue, regionCodes]);

  async function selectSuggestion(suggestion) {
    try {
      const place = suggestion.placePrediction?.toPlace?.();
      if (!place) return;

      await place.fetchFields({
        fields: [
          "formattedAddress",
          "location",
          "id",
          "addressComponents",
        ],
      });

      const components = place.addressComponents ?? [];
      const lat = place.location?.lat?.();
      const lng = place.location?.lng?.();

      const address = place.formattedAddress || "";
      const locality =
        extractAddressComponent(components, "locality") ||
        extractAddressComponent(components, "administrative_area_level_2");

      const province = normalizeProvinceName(
        extractAddressComponent(components, "administrative_area_level_1")
      );

      const countryCode = extractAddressComponent(components, "country", true);
      const country = extractAddressComponent(components, "country");
      const zone = extractZone(components, address);

      const payload = {
        address,
        place_id: place.id || "",
        lat: typeof lat === "number" ? lat : null,
        lng: typeof lng === "number" ? lng : null,
        locality,
        province,
        zone,
        country,
        country_code: countryCode,
        postal_code: extractAddressComponent(components, "postal_code"),
      };

      setInputValue(payload.address);
      setSuggestions([]);
      setOpen(false);
      setActiveIndex(-1);

      onChangeLocation?.(payload);

      const { AutocompleteSessionToken } = placesLibRef.current;
      sessionTokenRef.current = new AutocompleteSessionToken();
    } catch {
      // opcional: error visual
    }
  }

  function handleInputChange(e) {
    const nextValue = e.target.value;
    setInputValue(nextValue);

    onChangeLocation?.({
      address: nextValue,
      place_id: "",
      lat: null,
      lng: null,
      locality: "",
      province: "",
      zone: "",
      country: "",
      country_code: "",
      postal_code: "",
    });
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

  return (
    <div className="space-y-1" ref={wrapperRef}>
      {label && (
        <label className="block text-sm font-medium text-slate-700 mb-1.5">
          {label}
        </label>
      )}

      <div className="relative">
        <input
          type="text"
          value={inputValue}
          onChange={handleInputChange}
          onFocus={() => {
            if (suggestions.length > 0) setOpen(true);
          }}
          onKeyDown={handleKeyDown}
          disabled={disabled || !isLoaded}
          placeholder={
            isLoaded
              ? "Buscá ciudad, barrio o zona..."
              : "Cargando direcciones..."
          }
          autoComplete="off"
          className="w-full rounded-xl border border-slate-300 bg-white px-4 pr-10 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 disabled:bg-slate-100 disabled:text-slate-400"
        />

        <div className="absolute inset-y-0 right-3 flex items-center text-slate-400">
          {loading ? (
            <span className="text-xs">...</span>
          ) : (
            <Icon name="search" size={16} />
          )}
        </div>

        {open && (
          <div className="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
            {suggestions.map((suggestion, index) => {
              const prediction = suggestion.placePrediction;
              const mainText =
                prediction?.mainText?.text ||
                prediction?.text?.text ||
                "Sin descripción";
              const secondaryText =
                prediction?.secondaryText?.text || "";

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
                  <div className="flex items-start gap-3">
                    <span className="mt-0.5 text-slate-400">
                      <Icon name="search" size={15} />
                    </span>
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
                  </div>
                </button>
              );
            })}

            {!loading && suggestions.length === 0 && (
              <div className="px-4 py-3 text-sm text-slate-500">
                No se encontraron resultados.
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}