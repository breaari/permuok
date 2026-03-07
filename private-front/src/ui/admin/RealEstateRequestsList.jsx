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

function StatusBadge({ tab, item }) {
  const profileStatus = Number(item?.profile_status ?? 0);

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

  if (profileStatus === 4) {
    return (
      <span className="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider">
        Cambios pendientes
      </span>
    );
  }

  return (
    <span className="px-3 py-1 bg-sky-100 text-sky-700 rounded-full text-xs font-bold uppercase tracking-wider">
      Revisión inicial
    </span>
  );
}

function getReceivedLabel(item, tab) {
  const profileStatus = Number(item?.profile_status ?? 0);

  if (tab === "pending" && profileStatus === 4) {
    return "Cambios solicitados";
  }

  if (tab === "approved") {
    return "Aprobada";
  }

  if (tab === "rejected") {
    return "Rechazada";
  }

  return "Recibido";
}

function getReceivedDate(item, tab) {
  const profileStatus = Number(item?.profile_status ?? 0);

  if (tab === "pending" && profileStatus === 4) {
    return item?.changes_requested_at || item?.review_requested_at || item?.created_at;
  }

  if (tab === "approved") {
    return item?.approved_at || item?.validated_at || item?.review_requested_at || item?.created_at;
  }

  if (tab === "rejected") {
    return item?.validated_at || item?.review_requested_at || item?.created_at;
  }

  return item?.review_requested_at || item?.created_at;
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
        const receivedLabel = getReceivedLabel(re, tab);
        const receivedDate = getReceivedDate(re, tab);

        return (
          <div
            key={re.id}
            className="bg-white border border-slate-200 rounded-lg p-5 flex items-center justify-between shadow-sm hover:shadow-md transition-shadow"
          >
            <div className="min-w-0">
              <h3 className="font-bold text-lg text-slate-900 truncate">
                {re.name || "—"}
              </h3>

              <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
                <span className="flex items-center gap-1.5 text-sm text-slate-500">
                  <Icon name="mail" size={16} className="opacity-70" />
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

            <div className="flex items-center gap-4 sm:gap-6 shrink-0">
              <StatusBadge tab={tab} item={re} />

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
        );
      })}
    </div>
  );
}
