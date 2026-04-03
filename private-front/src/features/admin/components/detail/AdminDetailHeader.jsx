import AdminDetailBackButton from "./AdminDetailBackButton";

export default function AdminDetailHeader({
  backLabel,
  onBack,
  badge,
  subtitle,
  rightSlot = null,
}) {
  return (
    <div className="space-y-4">
      <AdminDetailBackButton label={backLabel} onClick={onBack} />

      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div className="flex flex-wrap items-center gap-3 text-sm min-w-0">
          {badge}
          {subtitle ? <span className="text-slate-400">/</span> : null}
          {subtitle ? <span className="text-slate-600">{subtitle}</span> : null}
        </div>

        {rightSlot ? <div className="shrink-0">{rightSlot}</div> : null}
      </div>
    </div>
  );
}