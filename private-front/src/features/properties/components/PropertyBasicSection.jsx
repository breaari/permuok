export default function PropertyBasicSection({ form, setField }) {
  const descriptionLength = String(form.description || "").length;

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">

        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Información básica
        </h2>

        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Definí un título claro y una descripción atractiva para la publicación.
          Esto es lo primero que van a ver otros usuarios al revisar la propiedad.
        </p>
      </div>

      <div className="space-y-5">
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1.5">
            Título de la publicación
          </label>

          <input
            type="text"
            placeholder="Ej. Casa moderna con pileta en barrio privado"
            value={form.title}
            onChange={(e) => setField("title", e.target.value)}
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          />

          <p className="mt-1.5 text-xs text-slate-400">
            Intentá resumir lo más importante en una sola frase.
          </p>
        </div>

        <div>
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <label className="block text-sm font-medium text-slate-700">
              Descripción
            </label>

            <span className="text-xs text-slate-400">
              {descriptionLength} caracteres
            </span>
          </div>

          <textarea
            placeholder="Contá los puntos fuertes de la propiedad, ubicación, estado general y cualquier detalle importante."
            value={form.description}
            onChange={(e) => setField("description", e.target.value)}
            rows={6}
            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none resize-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
          />

          <p className="mt-1.5 text-xs text-slate-400">
            Ejemplo: orientación, amenities, refacciones, entorno o potencial de permuta.
          </p>
        </div>
      </div>
    </section>
  );
}