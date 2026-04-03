function UsageBar({ used, limit }) {
  const safeLimit = Math.max(0, Number(limit) || 0);
  const safeUsed = Math.max(0, Number(used) || 0);
  const percentage =
    safeLimit > 0 ? Math.min(100, Math.round((safeUsed / safeLimit) * 100)) : 0;

  return (
    <div className="space-y-2">
      <div className="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
        <div
          className="h-full rounded-full bg-primary"
          style={{ width: `${percentage}%` }}
        />
      </div>

      <p className="text-xs text-slate-500">
        {safeUsed} / {safeLimit}
      </p>
    </div>
  );
}

export function UsersSummaryCards({ summary }) {
  const agentsUsed = Number(summary?.agents_used ?? 0);
  const agentsLimit = Number(summary?.agents_limit ?? 0);
  const investorsUsed = Number(summary?.investors_used ?? 0);
  const investorsLimit = Number(summary?.investors_limit ?? 0);

  return (
    <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
          Cupo de agentes
        </p>
        <p className="mt-2 text-2xl font-black text-slate-900">
          {agentsUsed}/{agentsLimit}
        </p>
        <p className="text-sm text-slate-500 mt-1">
          Usuarios tipo agente en tu membresía.
        </p>

        <div className="mt-4">
          <UsageBar used={agentsUsed} limit={agentsLimit} />
        </div>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
          Cupo de inversores
        </p>
        <p className="mt-2 text-2xl font-black text-slate-900">
          {investorsUsed}/{investorsLimit}
        </p>
        <p className="text-sm text-slate-500 mt-1">
          Usuarios tipo inversor en tu membresía.
        </p>

        <div className="mt-4">
          <UsageBar used={investorsUsed} limit={investorsLimit} />
        </div>
      </div>
    </section>
  );
}