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

function roleLabel(tab) {
  if (tab === "real_estate") return "Inmobiliaria";
  if (tab === "agent") return "Agente";
  if (tab === "investor") return "Inversor";
  return "Usuario";
}

function RoleBadge({ tab }) {
  return (
    <span className="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider">
      {roleLabel(tab)}
    </span>
  );
}

function AccountStatusBadge({ isActive }) {
  return Number(isActive) === 1 ? (
    <span className="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider">
      Cuenta activa
    </span>
  ) : (
    <span className="px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold uppercase tracking-wider">
      Cuenta inactiva
    </span>
  );
}

function InfoItem({ label, value }) {
  if (!value || value === "—") return null;

  return (
    <p>
      <span className="font-semibold text-slate-900">{label}:</span> {value}
    </p>
  );
}

export default function AdminUsersList({
  loading,
  items,
  tab,
  onOpenDetail,
  onToggleStatus,
}) {
  if (loading) {
    return <div className="text-sm text-slate-500">Cargando...</div>;
  }

  if (!items?.length) {
    return <div className="text-sm text-slate-500">Sin resultados</div>;
  }

  return (
    <div className="space-y-4">
      {items.map((user) => {
        const fullName = `${user.first_name || ""} ${user.last_name || ""}`.trim();

        const title =
          tab === "real_estate"
            ? user.real_estate_name || user.real_estate_legal_name || "—"
            : fullName || "—";

        const actionButtonClass =
          Number(user.is_active) === 1
            ? "bg-slate-200 text-slate-700 hover:bg-red-100 hover:text-red-700"
            : "bg-primary text-white hover:bg-primary/90";

        const actionButtonLabel =
          Number(user.is_active) === 1 ? "Desactivar" : "Activar";

        return (
          <div
            key={user.id}
            className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm"
          >
            <div className="flex flex-col md:flex-row justify-between items-start gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex flex-wrap items-center gap-3 mb-4">
                  <h3 className="text-xl font-bold text-slate-900 break-words">
                    {title}
                  </h3>

                  <RoleBadge tab={tab} />
                  <AccountStatusBadge isActive={user.is_active} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-y-2 gap-x-8 text-sm text-slate-600">
                  {tab === "real_estate" && (
                    <InfoItem label="Responsable" value={fullName || "—"} />
                  )}

                  <InfoItem label="Email" value={user.email || "—"} />
                  <InfoItem label="Tel" value={user.phone || "—"} />
                  <InfoItem label="Alta" value={formatDate(user.created_at)} />
                  <InfoItem
                    label="Último acceso"
                    value={formatDate(user.last_login)}
                  />

                  {tab !== "real_estate" && user.real_estate_name && (
                    <InfoItem
                      label="Inmobiliaria"
                      value={user.real_estate_name}
                    />
                  )}
                </div>

                {Number(user.is_active) !== 1 && user.deactivation_reason && (
                  <div className="mt-4 p-3 bg-slate-50 rounded-lg text-sm italic text-slate-500">
                    Motivo: "{user.deactivation_reason}"
                  </div>
                )}
              </div>

              <div className="flex flex-row md:flex-col items-center md:items-end gap-3 w-full md:w-auto shrink-0">
                <button
                  type="button"
                  onClick={() => onOpenDetail(user.id)}
                  className="text-primary font-bold text-sm hover:underline"
                >
                  Ver detalles
                </button>

                <button
                  type="button"
                  onClick={() => onToggleStatus(user)}
                  className={`px-6 py-2 rounded-lg font-bold text-sm w-full md:w-32 transition-colors ${actionButtonClass}`}
                >
                  {actionButtonLabel}
                </button>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}