export default function AdminUsersTabs({ tabs, value, onChange, counts }) {
  return (
    <>
      <div className="hidden md:flex border-b border-slate-200 mb-6 gap-8">
        {tabs.map((t) => {
          const active = t.key === value;
          const n = counts?.[t.key];

          return (
            <button
              key={t.key}
              type="button"
              onClick={() => onChange(t.key)}
              className={
                active
                  ? "pb-4 text-sm font-bold text-primary border-b-2 border-primary"
                  : "pb-4 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors"
              }
            >
              {t.label}
              <span className="ml-2 bg-primary/10 text-primary px-2 py-0.5 rounded-full text-xs">
                {Number.isFinite(n) ? n : 0}
              </span>
            </button>
          );
        })}
      </div>

      <div className="md:hidden mb-6">
        <label className="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
          Tipo de usuario
        </label>

        <select
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-primary"
        >
          {tabs.map((t) => (
            <option key={t.key} value={t.key}>
              {t.label} ({Number.isFinite(counts?.[t.key]) ? counts[t.key] : 0})
            </option>
          ))}
        </select>
      </div>
    </>
  );
}