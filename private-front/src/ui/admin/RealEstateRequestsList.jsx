import { Icon } from "../icons/Index";

function StatusBadge({ tab }) {
  if (tab === "approved") {
    return (
      <span className="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Aprobada
      </span>
    );
  }
  if (tab === "rejected") {
    return (
      <span className="px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Rechazada
      </span>
    );
  }
  return (
    <span className="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider">
      Pendiente
    </span>
  );
}

export default function RealEstateRequestsList({
  loading,
  items,
  tab,
  onOpenDetail,
}) {
  if (loading) return <div className="text-sm text-slate-500">Cargando...</div>;
  if (!items?.length)
    return <div className="text-sm text-slate-500">Sin resultados</div>;

  return (
    <div className="space-y-4">
      {items.map((re) => (
        <div
          key={re.id}
          className="bg-white border border-slate-200 rounded-lg p-5 flex items-center justify-between shadow-sm hover:shadow-md transition-shadow"
        >
          {/* LEFT */}
          <div className="min-w-0">
            <h3 className="font-bold text-lg text-slate-900 truncate">
              {re.name || "—"}
            </h3>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
              <span className="flex items-center gap-1.5 text-sm text-slate-500">
                <Icon name="mail" size={16} className="opacity-70" />
                <span className="truncate">{re.email || "—"}</span>
              </span>

              {re.created_at && (
                <span className="flex items-center gap-1.5 text-sm text-slate-500 border-l border-slate-200 pl-4">
                  <span className="opacity-70">•</span>
                  Recibido:{" "}
                  {new Date(re.created_at).toLocaleDateString("es-AR")}
                </span>
              )}
            </div>
          </div>

          {/* RIGHT */}
          <div className="flex items-center gap-4 sm:gap-6 shrink-0">
            <StatusBadge tab={tab} />

            <button
              type="button"
              onClick={() => onOpenDetail(re.id)}
              className="bg-primary hover:bg-primary/90 text-white px-5 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors"
            >
              Ver detalles
              <span className="opacity-90">›</span>
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}
