import { useEffect, useRef, useState } from "react";

function extractAddressComponent(
  components,
  type,
  useShortName = false,
) {
  const item = components?.find((component) =>
    component.types?.includes(type),
  );

  if (!item) {
    return "";
  }

  if (useShortName) {
    return item.shortText || item.short_name || "";
  }

  return (
    item.longText ||
    item.long_name ||
    item.shortText ||
    item.short_name ||
    ""
  );
}

function normalizeProvinceName(value) {
  return String(value || "")
    .replace(/\s+Province$/i, "")
    .replace(/^Provincia de /i, "")
    .trim();
}

export default function PlacesAddressInput({
  value = "",
  onValueChange,
  onPlaceSelect,
  googleMapsLoaded,
  label = "Dirección",
  placeholder = "Buscar dirección con Google Maps",
}) {
  const [inputValue, setInputValue] = useState(value);
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
    setInputValue(value || "");
  }, [value]);

  useEffect(() => {
    if (!googleMapsLoaded || !window.google?.maps) {
      return;
    }

    let mounted = true;

    async function bootPlaces() {
      try {
        const placesLib =
          await window.google.maps.importLibrary("places");

        if (!mounted) {
          return;
        }

        placesLibRef.current = placesLib;

        if (!sessionTokenRef.current) {
          sessionTokenRef.current =
            new placesLib.AutocompleteSessionToken();
        }
      } catch (error) {
        console.error("No se pudo cargar Places:", error);

        if (mounted) {
          setAddressError(
            "No se pudo cargar el buscador de ubicaciones.",
          );
        }
      }
    }

    bootPlaces();

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

    document.addEventListener(
      "mousedown",
      handleClickOutside,
    );

    return () => {
      document.removeEventListener(
        "mousedown",
        handleClickOutside,
      );
    };
  }, []);

  useEffect(() => {
    const normalizedInput = inputValue.trim();

    if (
      !googleMapsLoaded ||
      !placesLibRef.current ||
      normalizedInput.length < 3
    ) {
      setSuggestions([]);
      setOpen(false);
      setLoading(false);
      return;
    }

    const currentRequestId = ++requestIdRef.current;

    setLoading(true);

    const timer = setTimeout(async () => {
      try {
        const {
          AutocompleteSuggestion,
          AutocompleteSessionToken,
        } = placesLibRef.current;

        if (!sessionTokenRef.current) {
          sessionTokenRef.current =
            new AutocompleteSessionToken();
        }

        const response =
          await AutocompleteSuggestion.fetchAutocompleteSuggestions(
            {
              input: normalizedInput,
              sessionToken: sessionTokenRef.current,
              includedRegionCodes: ["ar", "us", "it"],
              language: "es",
            },
          );

        if (requestIdRef.current !== currentRequestId) {
          return;
        }

        const nextSuggestions =
          response?.suggestions ?? [];

        setSuggestions(nextSuggestions);
        setOpen(nextSuggestions.length > 0);
        setActiveIndex(-1);
        setAddressError("");
      } catch (error) {
        if (requestIdRef.current !== currentRequestId) {
          return;
        }

        console.error(
          "Error buscando ubicación:",
          error,
        );

        setSuggestions([]);
        setOpen(false);
        setAddressError(
          "No se pudieron obtener sugerencias.",
        );
      } finally {
        if (requestIdRef.current === currentRequestId) {
          setLoading(false);
        }
      }
    }, 250);

    return () => {
      clearTimeout(timer);
    };
  }, [googleMapsLoaded, inputValue]);

  async function selectSuggestion(suggestion) {
    try {
      const place =
        suggestion.placePrediction?.toPlace?.();

      if (!place) {
        throw new Error(
          "La sugerencia seleccionada no es válida.",
        );
      }

      await place.fetchFields({
        fields: [
          "formattedAddress",
          "location",
          "id",
          "addressComponents",
        ],
      });

      const components =
        place.addressComponents ?? [];

      const country = extractAddressComponent(
        components,
        "country",
      );

      const countryCode = extractAddressComponent(
        components,
        "country",
        true,
      )
        .trim()
        .toUpperCase();

      const province = normalizeProvinceName(
        extractAddressComponent(
          components,
          "administrative_area_level_1",
        ),
      );

      const city =
        extractAddressComponent(
          components,
          "locality",
        ) ||
        extractAddressComponent(
          components,
          "postal_town",
        ) ||
        extractAddressComponent(
          components,
          "administrative_area_level_2",
        );

      const zone =
        extractAddressComponent(
          components,
          "sublocality_level_1",
        ) ||
        extractAddressComponent(
          components,
          "sublocality",
        ) ||
        extractAddressComponent(
          components,
          "neighborhood",
        );

      const latitude =
        place.location?.lat?.();

      const longitude =
        place.location?.lng?.();

      const formattedAddress =
        place.formattedAddress || inputValue;

      const selectedLocation = {
        address: formattedAddress,
        formatted_address: formattedAddress,
        place_id: place.id || "",
        latitude:
          typeof latitude === "number"
            ? latitude
            : "",
        longitude:
          typeof longitude === "number"
            ? longitude
            : "",
        country: country || "",
        country_code: countryCode || "",
        province: province || "",
        city: city || "",
        zone: zone || "",
      };

      setInputValue(formattedAddress);
      setSuggestions([]);
      setOpen(false);
      setActiveIndex(-1);
      setAddressError("");

      onValueChange?.(formattedAddress);
      onPlaceSelect?.(selectedLocation);

      const { AutocompleteSessionToken } =
        placesLibRef.current;

      sessionTokenRef.current =
        new AutocompleteSessionToken();
    } catch (error) {
      console.error(
        "Error validando ubicación:",
        error,
      );

      setAddressError(
        "No se pudo validar la ubicación seleccionada.",
      );
    }
  }

  function handleInputChange(event) {
    const nextValue = event.target.value;

    setInputValue(nextValue);
    setAddressError("");
    onValueChange?.(nextValue);
  }

  function handleKeyDown(event) {
    if (!open || suggestions.length === 0) {
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();

      setActiveIndex((previous) =>
        previous < suggestions.length - 1
          ? previous + 1
          : 0,
      );
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();

      setActiveIndex((previous) =>
        previous > 0
          ? previous - 1
          : suggestions.length - 1,
      );
    }

    if (
      event.key === "Enter" &&
      activeIndex >= 0
    ) {
      event.preventDefault();

      selectSuggestion(
        suggestions[activeIndex],
      );
    }

    if (event.key === "Escape") {
      setOpen(false);
      setActiveIndex(-1);
    }
  }

  return (
    <div ref={wrapperRef}>
      <label className="block text-sm font-medium text-slate-700 mb-1.5">
        {label}
      </label>

      <div className="relative">
        <input
          type="text"
          value={inputValue}
          onChange={handleInputChange}
          onFocus={() => {
            if (suggestions.length > 0) {
              setOpen(true);
            }
          }}
          onKeyDown={handleKeyDown}
          disabled={!googleMapsLoaded}
          placeholder={
            googleMapsLoaded
              ? placeholder
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
            {suggestions.map(
              (suggestion, index) => {
                const prediction =
                  suggestion.placePrediction;

                const mainText =
                  prediction?.mainText?.text ||
                  prediction?.text?.text ||
                  "Sin descripción";

                const secondaryText =
                  prediction?.secondaryText?.text ||
                  "";

                return (
                  <button
                    key={`${
                      prediction?.placeId ||
                      prediction?.text?.text ||
                      index
                    }-${index}`}
                    type="button"
                    onClick={() =>
                      selectSuggestion(suggestion)
                    }
                    className={`w-full px-4 py-3 text-left transition-colors ${
                      index === activeIndex
                        ? "bg-emerald-50"
                        : "bg-white hover:bg-slate-50"
                    }`}
                  >
                    <p className="truncate text-sm font-semibold text-slate-800">
                      {mainText}
                    </p>

                    {secondaryText && (
                      <p className="truncate text-xs text-slate-500">
                        {secondaryText}
                      </p>
                    )}
                  </button>
                );
              },
            )}

            {loading && (
              <div className="px-4 py-3 text-sm text-slate-500">
                Buscando...
              </div>
            )}
          </div>
        )}
      </div>

      {addressError ? (
        <p className="mt-1.5 text-sm text-red-600">
          {addressError}
        </p>
      ) : !googleMapsLoaded ? (
        <p className="mt-1.5 text-xs text-slate-400">
          Esperá mientras se carga Google Maps.
        </p>
      ) : (
        <p className="mt-1.5 text-xs text-slate-400">
          Elegí una sugerencia para completar los datos automáticamente.
        </p>
      )}
    </div>
  );
}