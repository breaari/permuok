export function ProfileProgress({ progress }) {
  if (!progress) return null;

  return (
    <section className="mb-8 rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900 sm:text-xl">
            Completá tu perfil para solicitar la verificación
          </h2>

          <p className="mt-1 text-sm leading-6 text-slate-500">
            Cada dato requerido suma al avance de tu perfil.
          </p>
        </div>

        <div className="shrink-0 text-left sm:text-right">
          <p className="text-2xl font-black text-slate-900">
            {progress.percentage}%
          </p>

          <p className="text-xs font-medium text-slate-500">
            {progress.completed} de {progress.total} requisitos completos
          </p>
        </div>
      </div>

      <div className="mt-5 h-3 overflow-hidden rounded-full bg-slate-100">
        <div
          className="h-full rounded-full bg-slate-900 transition-all duration-300"
          style={{
            width: `${progress.percentage}%`,
          }}
        />
      </div>

      {progress.percentage === 100 && (
        <p className="mt-3 text-sm font-semibold text-slate-700">
          Tu perfil está listo para ser enviado a revisión.
        </p>
      )}
    </section>
  );
}
