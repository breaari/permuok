export default function Button({ className = "", variant = "primary", ...props }) {
  const base =
    "w-full flex justify-center items-center gap-2 py-3 px-4 rounded-lg text-sm font-bold transition-all active:translate-y-0 disabled:opacity-60 disabled:cursor-not-allowed";

  const variants = {
    primary: "bg-primary text-white hover:bg-blue-700",
    outline: "border border-slate-200 bg-white text-slate-900 hover:bg-slate-50",
    ghost: "text-slate-900 hover:bg-slate-100",
  };

  return <button className={[base, variants[variant], className].join(" ")} {...props} />;
}