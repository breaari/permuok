export default function AdminDetailLoading({
  message = "Cargando detalle...",
}) {
  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-sm text-slate-500">
      {message}
    </div>
  );
}