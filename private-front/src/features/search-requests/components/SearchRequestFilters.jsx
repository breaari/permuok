import { Icon } from "../../../ui/icons/Index";
import { SEARCH_REQUEST_STATUS_OPTIONS } from "../utils/SearchRequestHelpers";

export default function SearchRequestFilters({
  status = "",
  searchInput = "",
  onStatusChange,
  onSearchInputChange,
  onSearchSubmit,
  onClearSearch,
  onCreateNew,
}) {
  return (
    <div className="flex flex-col lg:flex-row gap-4 sm:gap-5 lg:gap-6 items-start lg:items-center justify-between mb-6 sm:mb-8">
      <div className="flex flex-wrap gap-2 p-1 bg-slate-100 rounded-xl w-full lg:w-auto">
        {SEARCH_REQUEST_STATUS_OPTIONS.map((option) => {
          const active = status === option.key;

          return (
            <button
              key={option.key}
              type="button"
              onClick={() => onStatusChange(option.key)}
              className={`px-3 sm:px-4 md:px-5 py-2 rounded-lg text-xs sm:text-sm transition-colors ${
                active
                  ? "bg-white shadow-sm text-emerald-700 font-semibold"
                  : "text-slate-600 hover:bg-white/70 font-medium"
              }`}
            >
              {option.label}
            </button>
          );
        })}
      </div>

      <div className="flex flex-col sm:flex-row w-full lg:w-auto gap-3">
        <form
          onSubmit={onSearchSubmit}
          className="flex flex-col sm:flex-row gap-2 w-full lg:w-auto"
        >
          <div className="relative flex-1 lg:w-72">
            <Icon
              name="search"
              size={17}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
            />

            <input
              type="text"
              value={searchInput}
              onChange={(e) => onSearchInputChange(e.target.value)}
              placeholder="Buscar por título o ID..."
              className="w-full bg-white border border-slate-200 rounded-lg pl-10 pr-10 py-2.5 sm:py-3 text-sm shadow-sm outline-none focus:ring-2 focus:ring-slate-900/5 focus:border-slate-300"
            />

            {!!searchInput && (
              <button
                type="button"
                onClick={onClearSearch}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                aria-label="Limpiar búsqueda"
              >
                <Icon name="close" size={16} />
              </button>
            )}
          </div>

          <button
            type="submit"
            className="px-4 sm:px-5 py-2.5 sm:py-3 rounded-lg text-sm font-bold border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition-colors"
          >
            Buscar
          </button>
        </form>

        <button
          type="button"
          onClick={onCreateNew}
          className="bg-emerald-600 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-lg text-sm font-bold flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 hover:opacity-90 active:scale-[0.99] transition-all whitespace-nowrap"
        >
          <Icon name="plusCircle" size={18} />
          Nueva búsqueda
        </button>
      </div>
    </div>
  );
}