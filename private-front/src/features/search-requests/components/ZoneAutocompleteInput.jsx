import { useEffect, useRef } from "react";
import { useGoogleMaps } from "../../../ui/maps/UseGoogleMaps";

export default function ZoneAutocompleteInput({
  value = "",
  onSelect,
  countryCode,
  disabled = false,
}) {
  const containerRef = useRef(null);
  const elementRef = useRef(null);
  const { isLoaded } = useGoogleMaps();

  useEffect(() => {
    if (!isLoaded || disabled || !containerRef.current || !window.google?.maps?.places) {
      return;
    }

    containerRef.current.innerHTML = "";

    const element = new window.google.maps.places.PlaceAutocompleteElement({
      includedRegionCodes: countryCode ? [countryCode.toLowerCase()] : undefined,
    });

    elementRef.current = element;
    element.className = "permuok-place-autocomplete";

    const handleSelect = async (event) => {
      try {
        const placePrediction = event?.placePrediction;
        if (!placePrediction) return;

        const place = placePrediction.toPlace();

        await place.fetchFields({
          fields: ["addressComponents", "formattedAddress", "displayName"],
        });

        onSelect?.(place);
      } catch (error) {
        console.error("Error seleccionando ubicación:", error);
      }
    };

    const handleError = (event) => {
      console.error("Google Places error:", event);
    };

    element.addEventListener("gmp-select", handleSelect);
    element.addEventListener("gmp-error", handleError);

    containerRef.current.appendChild(element);

    return () => {
      element.removeEventListener("gmp-select", handleSelect);
      element.removeEventListener("gmp-error", handleError);
      if (containerRef.current) {
        containerRef.current.innerHTML = "";
      }
    };
  }, [isLoaded, disabled, countryCode, onSelect]);

  useEffect(() => {
    if (!elementRef.current) return;
    if (typeof value === "string") {
      elementRef.current.value = value;
    }
  }, [value]);

  return (
    <div
      className={`w-full h-[48px] rounded-xl border border-slate-300 bg-white px-4 flex items-center transition focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-500/10 ${
        disabled ? "bg-slate-100 opacity-70 pointer-events-none" : ""
      }`}
    >
      <div ref={containerRef} className="w-full h-full flex items-center" />
    </div>
  );
}