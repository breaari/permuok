export default function RealEstateTabs({ tabs, value, onChange, counts }) {
  return (
    <div className="border-b border-slate-200 mb-6 overflow-x-auto">
      <div className="flex gap-6 min-w-max">
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
                  ? "pb-4 text-sm font-bold text-primary border-b-2 border-primary whitespace-nowrap flex items-center gap-2"
                  : "pb-4 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors whitespace-nowrap flex items-center gap-2"
              }
            >
              <span>{t.label}</span>

              <span
                className={
                  active
                    ? "bg-primary/10 text-primary px-2 py-0.5 rounded-full text-xs font-bold"
                    : "bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full text-xs font-bold"
                }
              >
                {Number.isFinite(n) ? n : 0}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}