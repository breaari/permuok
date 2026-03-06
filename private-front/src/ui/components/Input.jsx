export default function Input({
  label,
  value,
  onChange,
  type = "text",
  placeholder,
  iconLeft,
  rightSlot,
  className = "",
  inputClassName = "",
  ...props
}) {
  return (
    <div className={className}>
      {label && <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>}

      <div className="relative">
        {iconLeft && (
          <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
            {iconLeft}
          </span>
        )}

        <input
          type={type}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className={[
            "block w-full py-2.5 border border-slate-200 rounded-lg bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all",
            iconLeft ? "pl-10" : "pl-3",
            rightSlot ? "pr-10" : "pr-3",
            inputClassName,
          ].join(" ")}
          {...props}
        />

        {rightSlot && (
          <span className="absolute inset-y-0 right-0 pr-3 flex items-center">{rightSlot}</span>
        )}
      </div>
    </div>
  );
}