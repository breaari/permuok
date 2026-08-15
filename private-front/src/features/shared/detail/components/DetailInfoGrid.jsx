function InfoItem({ label, value, dark = false }) {
  return (
    <div className="space-y-1">
      <p
        className={`text-[10px] uppercase font-bold tracking-[0.18em] ${
          dark ? "text-white/50" : "text-slate-400"
        }`}
      >
        {label}
      </p>
      <p className={`text-sm font-semibold ${dark ? "text-white" : "text-slate-900"}`}>
        {value || "—"}
      </p>
    </div>
  );
}

export default function DetailInfoGrid({ items = [], dark = false }) {
  return (
    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {items.map((item) => (
        <InfoItem
          key={item.label}
          label={item.label}
          value={item.value}
          dark={dark}
        />
      ))}
    </div>
  );
}