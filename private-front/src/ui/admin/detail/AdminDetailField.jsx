export function AdminDetailField({ label, value, strong = false }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
        {label}
      </p>
      <p className={strong ? "text-slate-900 font-bold text-lg" : "text-slate-700"}>
        {value || "—"}
      </p>
    </div>
  );
}