function roleLabel(role) {
  if (Number(role) === 3) return "Agente";
  if (Number(role) === 4) return "Inversor";
  return "Usuario";
}

function statusBadge(isActive) {
  return Number(isActive) === 1
    ? "bg-emerald-100 text-emerald-700"
    : "bg-slate-100 text-slate-600";
}

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

export function UsersList({
  loading,
  items,
  updatingUserId,
  onToggleStatus,
}) {
  if (loading) {
    return (
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-sm text-slate-500">
        Cargando usuarios...
      </div>
    );
  }

  if (!items?.length) {
    return (
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center">
        <p className="text-lg font-bold text-slate-900">
          Todavía no tenés usuarios creados
        </p>
        <p className="text-sm text-slate-500 mt-1">
          Podés crear agentes e inversores para trabajar dentro de la plataforma.
        </p>
      </div>
    );
  }

  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-6 border-b border-slate-100">
        <h2 className="text-xl font-bold text-slate-900">Listado</h2>
      </div>

      <div className="divide-y divide-slate-100">
        {items.map((user) => (
          <div
            key={user.id}
            className="p-4 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
          >
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-bold text-slate-900 break-words">
                  {user.first_name} {user.last_name}
                </p>

                <span className="px-2.5 py-1 rounded-full bg-primary/10 text-primary text-[11px] font-bold uppercase tracking-wider">
                  {roleLabel(user.role)}
                </span>

                <span
                  className={`px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider ${statusBadge(
                    user.is_active
                  )}`}
                >
                  {Number(user.is_active) === 1 ? "Activo" : "Inactivo"}
                </span>
              </div>

              <div className="mt-2 space-y-1 text-sm text-slate-500">
                <p className="break-all">{user.email}</p>
                <p>{user.phone || "—"}</p>
                <p>Creado: {formatDate(user.created_at)}</p>
              </div>
            </div>

            <div className="w-full md:w-auto">
              <button
                type="button"
                disabled={updatingUserId === user.id}
                onClick={() => onToggleStatus(user)}
                className="w-full md:w-auto px-5 py-3 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-colors disabled:opacity-60"
              >
                {updatingUserId === user.id
                  ? "Procesando..."
                  : Number(user.is_active) === 1
                    ? "Desactivar"
                    : "Activar"}
              </button>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}