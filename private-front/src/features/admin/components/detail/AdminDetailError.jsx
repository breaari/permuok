export default function AdminDetailError({
  message = "Ocurrió un error al cargar la información.",
}) {
  return (
    <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
      {message}
    </div>
  );
}