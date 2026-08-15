import { Icon } from "../../../ui/icons/Index";

export const STATUS_FILTERS = [
  { value: "all", label: "Todas" },
  { value: "open", label: "Abiertas" },
  { value: "negotiating", label: "En negociación" },
  { value: "visit_scheduled", label: "Visitas" },
  { value: "closed", label: "Cerradas" },
  { value: "discarded", label: "Descartadas" },
];

export function PillButton({ active, label, count, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`shrink-0 rounded-full border px-3 py-2 text-[11px] font-black transition sm:px-4 sm:text-xs ${
        active
          ? "border-slate-900 bg-slate-900 text-white"
          : "border-slate-200 bg-white text-slate-600 hover:border-slate-300"
      }`}
    >
      {label}
      {count !== undefined ? (
        <span className={active ? "ml-2 text-white/60" : "ml-2 text-slate-400"}>
          {count}
        </span>
      ) : null}
    </button>
  );
}

export function InboxTabs({
  activeTab,
  setActiveTab,
  ownCount,
  ownUnread,
  externalCount,
  externalUnread,
}) {
  return (
    <div className="mb-5 grid grid-cols-1 gap-3 sm:mb-6 md:grid-cols-2 md:gap-4">
      <TabButton
        active={activeTab === "own"}
        title="Mis publicaciones"
        count={ownCount}
        unread={ownUnread}
        onClick={() => setActiveTab("own")}
      />

      <TabButton
        active={activeTab === "external"}
        title="Publicaciones externas"
        count={externalCount}
        unread={externalUnread}
        onClick={() => setActiveTab("external")}
      />
    </div>
  );
}

export default function InboxFilters({
  archiveMode,
  setArchiveMode,
  activeStatus,
  setActiveStatus,
  statusCounts,
  search,
  setSearch,
}) {
  return (
    <>
      <div className="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:mb-5 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
        <PillButton
          active={archiveMode === "active"}
          label="Activas"
          onClick={() => setArchiveMode("active")}
        />

        <PillButton
          active={archiveMode === "archived"}
          label="Archivadas"
          onClick={() => setArchiveMode("archived")}
        />
      </div>

      <div className="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:mb-8 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
        {STATUS_FILTERS.map((filter) => (
          <PillButton
            key={filter.value}
            active={activeStatus === filter.value}
            label={filter.label}
            count={statusCounts?.[filter.value] || 0}
            onClick={() => setActiveStatus(filter.value)}
          />
        ))}
      </div>

      <div className="mb-5">
        <div className="relative">
          <Icon
            name="search"
            size={18}
            className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
          />

          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Buscar conversaciones..."
            className="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-11 pr-4 text-sm font-medium text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          />
        </div>
      </div>
    </>
  );
}

function TabButton({ active, title, count, unread, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-2xl border px-4 py-4 text-left transition sm:px-5 ${
        active
          ? "border-slate-900 bg-slate-900 text-white shadow-lg"
          : "border-slate-200 bg-white text-slate-700 hover:border-slate-300"
      }`}
    >
      <p
        className={`text-[9px] font-black uppercase tracking-[0.14em] sm:text-[10px] sm:tracking-[0.16em] ${
          active ? "text-white/60" : "text-slate-400"
        }`}
      >
        {title}
      </p>

      <div className="mt-2 flex flex-wrap items-end gap-2 sm:gap-3">
        <span className="text-xl font-black sm:text-2xl">{count}</span>

        {unread > 0 ? (
          <span
            className={`mb-0.5 rounded-full px-2 py-0.5 text-[10px] font-black sm:mb-1 ${
              active
                ? "bg-emerald-400 text-slate-950"
                : "bg-emerald-100 text-emerald-700"
            }`}
          >
            {unread > 99 ? "99+" : unread} nuevo/s
          </span>
        ) : null}
      </div>
    </button>
  );
}
