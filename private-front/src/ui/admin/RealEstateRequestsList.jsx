// ui/admin/RealEstateRequestsList.jsx
import { Icon } from "../icons/Index";

function formatDate(value) {
  if (!value) return "—";

  try {
    return new Date(value).toLocaleDateString("es-AR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

function StatusBadge({ tab }) {
  if (tab === "draft") {
    return (
      <span className="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Borrador
      </span>
    );
  }

  if (tab === "initial_review") {
    return (
      <span className="px-3 py-1 bg-sky-100 text-sky-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Revisión inicial
      </span>
    );
  }

  if (tab === "changes_pending") {
    return (
      <span className="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Cambios pendientes
      </span>
    );
  }

  if (tab === "approved") {
    return (
      <span className="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Aprobada
      </span>
    );
  }

  return (
    <span className="px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold uppercase tracking-wider">
      Rechazada
    </span>
  );
}

function getReceivedLabel(tab) {
  if (tab === "draft") return "Creado";
  if (tab === "initial_review") return "Solicitado";
  if (tab === "changes_pending") return "Cambios solicitados";
  if (tab === "approved") return "Aprobada";
  if (tab === "rejected") return "Rechazada";
  return "Fecha";
}

function getReceivedDate(item, tab) {
  if (tab === "draft") {
    return item?.created_at;
  }

  if (tab === "initial_review") {
    return item?.review_requested_at || item?.created_at;
  }

  if (tab === "changes_pending") {
    return (
      item?.changes_requested_at || item?.review_requested_at || item?.created_at
    );
  }

  if (tab === "approved") {
    return (
      item?.approved_at ||
      item?.validated_at ||
      item?.review_requested_at ||
      item?.created_at
    );
  }

  if (tab === "rejected") {
    return item?.validated_at || item?.review_requested_at || item?.created_at;
  }

  return item?.created_at;
}

export default function RealEstateRequestsList({
  loading,
  items,
  tab,
  onOpenDetail,
}) {
  if (loading) {
    return <div className="text-sm text-slate-500">Cargando...</div>;
  }

  if (!items?.length) {
    return <div className="text-sm text-slate-500">Sin resultados</div>;
  }

  return (
    <div className="space-y-4">
      {items.map((re) => {
        const receivedLabel = getReceivedLabel(tab);
        const receivedDate = getReceivedDate(re, tab);

        return (
          <div
            key={re.id}
            className="bg-white border border-slate-200 rounded-lg p-4 md:p-5 shadow-sm hover:shadow-md transition-shadow"
          >
            {/* MOBILE */}
            <div className="flex flex-col gap-4 md:hidden">
              <div className="min-w-0">
                <h3 className="font-bold text-base text-slate-900 break-words">
                  {re.name || "—"}
                </h3>

                <div className="mt-2 flex items-start gap-1.5 text-sm text-slate-500">
                  <Icon name="mail" size={16} className="opacity-70 mt-0.5 shrink-0" />
                  <span className="break-all">{re.email || "—"}</span>
                </div>

                {tab === "rejected" && re.validation_note && (
                  <p className="mt-3 text-sm text-rose-700">
                    <span className="font-semibold">Motivo:</span>{" "}
                    {re.validation_note}
                  </p>
                )}
              </div>

              <div className="flex flex-col items-start gap-3">
                <StatusBadge tab={tab} />

                <div className="space-y-2 text-sm text-slate-500 w-full">
                  <div className="flex items-start gap-2">
                    <span className="opacity-70 mt-[2px]">•</span>
                    <span>
                      {receivedLabel}: {formatDate(receivedDate)}
                    </span>
                  </div>

                  {!!re.cuit && (
                    <div className="flex items-start gap-2">
                      <span className="opacity-70 mt-[2px]">•</span>
                      <span>CUIT: {re.cuit}</span>
                    </div>
                  )}
                </div>

                <button
                  type="button"
                  onClick={() => onOpenDetail(re.id)}
                  className="w-full bg-primary hover:bg-primary/90 text-white px-5 py-3 rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition-colors"
                >
                  Ver detalles
                  <span className="opacity-90">›</span>
                </button>
              </div>
            </div>

            {/* DESKTOP */}
            <div className="hidden md:flex items-center justify-between gap-6">
              <div className="min-w-0">
                <h3 className="font-bold text-lg text-slate-900 truncate">
                  {re.name || "—"}
                </h3>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
                  <span className="flex items-center gap-1.5 text-sm text-slate-500 min-w-0">
                    <Icon name="mail" size={16} className="opacity-70 shrink-0" />
                    <span className="truncate">{re.email || "—"}</span>
                  </span>

                  <span className="flex items-center gap-1.5 text-sm text-slate-500 border-l border-slate-200 pl-4">
                    <span className="opacity-70">•</span>
                    {receivedLabel}: {formatDate(receivedDate)}
                  </span>

                  {!!re.cuit && (
                    <span className="flex items-center gap-1.5 text-sm text-slate-500 border-l border-slate-200 pl-4">
                      <span className="opacity-70">•</span>
                      CUIT: {re.cuit}
                    </span>
                  )}
                </div>

                {tab === "rejected" && re.validation_note && (
                  <p className="mt-3 text-sm text-rose-700 line-clamp-2">
                    <span className="font-semibold">Motivo:</span>{" "}
                    {re.validation_note}
                  </p>
                )}
              </div>

              <div className="flex items-center gap-4 shrink-0">
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
          </div>
        );
      })}
    </div>
  );
}