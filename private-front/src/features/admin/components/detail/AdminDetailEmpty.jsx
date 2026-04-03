export default function AdminDetailEmpty({
  message = "No se encontró información.",
}) {
  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-sm text-slate-500">
      {message}
    </div>
  );
}