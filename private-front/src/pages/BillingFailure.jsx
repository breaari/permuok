import { useNavigate } from "react-router-dom";

export default function BillingFailure() {
  const nav = useNavigate();

  return (
    <div className="min-h-screen p-6">
      <div className="mx-auto max-w-xl rounded-2xl border p-6 shadow-sm space-y-4">
        <h1 className="text-2xl font-semibold">No se pudo procesar el pago</h1>

        <p className="text-sm text-gray-600">
          Mercado Pago informó un error o rechazo. Podés volver a Membresía y reintentar con otro
          medio de pago.
        </p>

        <div className="flex gap-2">
          <button
            className="rounded-lg bg-black px-4 py-2 text-white"
            onClick={() => nav("/billing")}
          >
            Volver a Membresía
          </button>

          <button className="rounded-lg border px-4 py-2 text-sm" onClick={() => nav("/app")}>
            Ir al Panel
          </button>
        </div>
      </div>
    </div>
  );
}