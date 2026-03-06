import { Icon } from "../icons/Index";

export default function Pagination({ meta, onChange }) {
  if (!meta || meta.pages <= 1) return null;

  const { page, pages } = meta;

  const go = (p) => {
    if (p < 1 || p > pages || p === page) return;
    onChange(p);
  };

  const numbers = [];
  for (let i = 1; i <= pages; i++) {
    numbers.push(i);
  }

  return (
    <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-6">
      <p className="text-sm text-slate-500">
        Mostrando{" "}
        <span className="font-bold text-slate-900">
          {meta.from} - {meta.to}
        </span>{" "}
        de{" "}
        <span className="font-bold text-slate-900">
          {meta.total}
        </span>
      </p>

      <div className="flex gap-2">

        {/* previous */}
        <button
          onClick={() => go(page - 1)}
          disabled={page === 1}
          className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50"
        >
          <Icon name="chevronLeft" size={18} />
        </button>

        {/* pages */}
        {numbers.map((n) => (
          <button
            key={n}
            onClick={() => go(n)}
            className={`w-10 h-10 rounded-lg font-bold ${
              n === page
                ? "bg-primary text-white"
                : "border border-slate-200 hover:bg-slate-50"
            }`}
          >
            {n}
          </button>
        ))}

        {/* next */}
        <button
          onClick={() => go(page + 1)}
          disabled={page === pages}
          className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50"
        >
          <Icon name="chevronRight" size={18} />
        </button>
      </div>
    </div>
  );
}