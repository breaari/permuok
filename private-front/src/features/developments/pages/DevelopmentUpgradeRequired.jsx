import { useNavigate } from "react-router-dom";
import { Icon } from "../../../ui/icons/Index";

export default function DevelopmentUpgradeRequired({
  mode = "publish",
}) {
  const navigate = useNavigate();

  const title =
    mode === "view"
      ? "Tu plan actual no incluye acceso a desarrollos"
      : "Tu plan actual no permite publicar desarrollos";

  const description =
    mode === "view"
      ? "Para explorar proyectos en desarrollo necesitás un plan que tenga esta función habilitada."
      : "Para publicar y administrar proyectos en desarrollo necesitás un plan que tenga esta función habilitada.";

  return (
    <main className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-10 py-10">
      <div className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <div className="flex items-start gap-4">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
            <Icon name="lock" size={22} />
          </div>

          <div className="flex-1">
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">
              Función no disponible
            </p>

            <h1 className="mt-2 text-2xl font-black tracking-tight text-slate-900">
              {title}
            </h1>

            <p className="mt-3 text-sm sm:text-base leading-relaxed text-slate-500 max-w-2xl">
              {description}
            </p>

            <div className="mt-6 flex flex-col sm:flex-row gap-3">
              <button
                type="button"
                onClick={() => navigate("/billing/change-plan")}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white hover:opacity-90"
              >
                <Icon name="creditCard" size={16} />
                Ver planes
              </button>

              <button
                type="button"
                onClick={() => navigate("/app")}
                className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
              >
                Volver al panel
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}