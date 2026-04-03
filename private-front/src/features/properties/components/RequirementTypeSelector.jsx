import { PROPERTY_TYPES } from "../utils/PropertyFormHelpers";

export default function RequirementTypeSelector({ selected, onToggle }) {
  return (
    <div className="flex flex-wrap gap-2">
      {PROPERTY_TYPES.map((type) => {
        const active = selected.includes(type.value);

        return (
          <button
            key={type.value}
            type="button"
            onClick={() => onToggle(type.value)}
            className={`px-5 py-2 rounded-full text-xs font-bold uppercase tracking-wider transition-all ${
              active
                ? "bg-slate-900 text-white shadow-sm"
                : "border border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-900 hover:text-slate-900"
            }`}
          >
            {type.label}
          </button>
        );
      })}
    </div>
  );
}