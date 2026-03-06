export default function RealEstateTabs({ tabs, value, onChange, counts }) {
  return (
    <div className="border-b border-slate-200 mb-6 flex gap-8">
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
  );
}