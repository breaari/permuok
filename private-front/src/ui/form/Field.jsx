export function Field({
  label,
  value,
  onChange,
  placeholder,
  type = "text",
  leftIcon,
  disabled = false,
  readOnly = false,
  helperText = "",
}) {
  return (
    <div className="space-y-2">
      <label className="text-sm font-semibold text-slate-700">{label}</label>

      <div className="relative">
        <input
          type={type}
          value={value}
          onChange={(e) => onChange?.(e.target.value)}
          placeholder={placeholder}
          disabled={disabled}
          readOnly={readOnly}
          className={`w-full ${
            leftIcon ? "pl-10" : "pl-4"
          } pr-4 py-3 rounded-lg border border-slate-200 bg-slate-50 focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none disabled:opacity-60 read-only:text-slate-500 read-only:cursor-not-allowed`}
        />
      </div>

      {!!helperText && <p className="text-xs text-slate-500">{helperText}</p>}
    </div>
  );
}
