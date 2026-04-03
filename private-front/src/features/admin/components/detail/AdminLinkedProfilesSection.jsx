import { Icon } from "../../../../ui/icons/Index";

function MiniStatusBadge({ isActive }) {
  return Number(isActive) === 1 ? (
    <span className="text-emerald-600 font-extrabold uppercase tracking-widest">
      Activo
    </span>
  ) : (
    <span className="text-rose-500 font-extrabold uppercase tracking-widest">
      Inactivo
    </span>
  );
}

export default function AdminLinkedProfilesSection({
  children,
  childrenSummary,
  navigate,
  roleLabel,
}) {
  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div className="flex items-center gap-3">
          <span className="text-primary">
            <Icon name="users" size={24} />
          </span>
          <h2 className="text-xl font-bold text-slate-900">Perfiles creados</h2>
        </div>

        <div className="flex flex-wrap gap-2 text-xs font-bold uppercase tracking-tight">
          <span className="bg-primary/10 text-primary px-3 py-1.5 rounded-full">
            Total: {childrenSummary.total}
          </span>
          <span className="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-full">
            Agentes: {childrenSummary.agents}
          </span>
          <span className="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-full">
            Inversores: {childrenSummary.investors}
          </span>
        </div>
      </div>

      <div className="p-6">
        {children.length === 0 ? (
          <div className="text-sm text-slate-500">
            Esta inmobiliaria todavía no tiene perfiles creados.
          </div>
        ) : (
          <div className="space-y-4">
            {children.map((child) => {
              const childName =
                `${child.first_name || ""} ${child.last_name || ""}`.trim() || "—";

              return (
                <div
                  key={child.id}
                  className="flex items-center justify-between p-4 bg-background-light rounded-lg border border-slate-100 hover:border-primary/30 transition-colors"
                >
                  <div className="flex items-center gap-4 min-w-0">
                    <div>
                      <p className="font-bold text-slate-900">{childName}</p>

                      <div className="flex items-center gap-2 text-xs">
                        <span className="font-bold text-slate-500">
                          {roleLabel(Number(child.role) === 3 ? "agent" : "investor")}
                        </span>
                        <span className="text-slate-300">•</span>
                        <MiniStatusBadge isActive={child.is_active} />
                      </div>
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={() => navigate(`/admin/users/${child.id}`)}
                    className="text-primary font-bold text-sm hover:underline flex items-center gap-1 shrink-0"
                  >
                    Ver perfil
                    <span>↗</span>
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}