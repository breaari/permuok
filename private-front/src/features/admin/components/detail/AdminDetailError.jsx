import { Icon } from "../../../../ui/icons/Index";

export default function AdminDetailError({
  message = "Ocurrió un error al cargar la información.",
}) {
  return (
    <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4">
      <div className="flex items-start gap-3">
        <Icon
          name="alertCircle"
          size={18}
          className="mt-0.5 shrink-0 text-rose-600"
        />

        <div>
          <p className="font-bold text-rose-800">
            No se pudo cargar la información
          </p>

          <p className="mt-1 text-sm text-rose-700">
            {message}
          </p>
        </div>
      </div>
    </div>
  );
}