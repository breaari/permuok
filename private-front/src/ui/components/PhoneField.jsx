import PhoneInput from "react-phone-number-input";
import es from "react-phone-number-input/locale/es.json";

export default function PhoneField({
  label = "Teléfono",
  value,
  onChange,
  disabled,
  defaultCountry = "AR",
  error,
  helperText,
  variant = "profile", // "profile" | "auth"
}) {
  const labelMargin = variant === "auth" ? "mb-2" : "mb-1";

  return (
    <div>
      {label && (
        <label
          className={`block text-sm font-semibold text-slate-700 ${labelMargin}`}
        >
          {label}
        </label>
      )}

      <div
        className={[
          "phone-field-shell",
          `phone-field-shell--${variant}`,
          error ? "phone-field-shell--error" : "",
          disabled ? "opacity-60" : "",
        ].join(" ")}
      >
        <PhoneInput
          international
          countryCallingCodeEditable={false}
          defaultCountry={defaultCountry}
          labels={es}
          value={value || ""}
          onChange={(v) => onChange?.(v || "")}
          disabled={disabled}
          placeholder="11 1234 5678"
          className={`phone-field phone-field--${variant}`}
        />
      </div>

      {(error || helperText) && (
        <div
          className={[
            "mt-1 text-xs",
            error ? "text-red-700" : "text-slate-500",
          ].join(" ")}
        >
          {error || helperText}
        </div>
      )}
    </div>
  );
}