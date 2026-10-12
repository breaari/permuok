export function ProfileProgress({
  progress,
}) {
  if (!progress) return null;

  return (
    <section className="mb-8 rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900 sm:text-xl">
            Prepará tu inmobiliaria para la verificación
          </h2>

          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
            Completá los datos requeridos y cargá al menos una matrícula.
            Cuando esté todo listo vas a poder enviar tu perfil a revisión.
          </p>
        </div>

        <div className="shrink-0">
          <span className="text-sm font-bold text-slate-900">
            {progress.completed}/{progress.total} completos
          </span>
        </div>
      </div>

      <div className="mt-5 h-2 overflow-hidden rounded-full bg-slate-100">
        <div
          className="h-full rounded-full bg-slate-900 transition-all duration-300"
          style={{
            width: `${progress.percentage}%`,
          }}
        />
      </div>

      <div className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
        {progress.steps.map((step) => (
          <div
            key={step.key}
            className="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3"
          >
            <div className="flex items-center gap-3">
              <span
                className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-black ${
                  step.complete
                    ? "bg-slate-900 text-white"
                    : "border border-slate-300 bg-white text-slate-400"
                }`}
              >
                {step.complete ? "✓" : ""}
              </span>

              <span className="text-sm font-semibold text-slate-700">
                {step.label}
              </span>
            </div>

            <span
              className={`text-xs font-semibold ${
                step.complete
                  ? "text-slate-700"
                  : "text-slate-400"
              }`}
            >
              {step.complete
                ? "Completo"
                : "Pendiente"}
            </span>
          </div>
        ))}
      </div>
    </section>
  );
}