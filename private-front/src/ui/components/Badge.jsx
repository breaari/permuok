export default function Badge({ kind = "neutral", className = "", children }) {
  const map = {
    ok: "bg-green-50 text-green-700 border-green-200",
    warn: "bg-yellow-50 text-yellow-800 border-yellow-200",
    bad: "bg-red-50 text-red-700 border-red-200",
    neutral: "bg-slate-50 text-slate-700 border-slate-200",
  };

  return (
    <span className={["text-xs border rounded-full px-2 py-1", map[kind], className].join(" ")}>
      {children}
    </span>
  );
}