export default function ConversationManagementCard({
  archived = false,
  loading = false,
  onArchive,
  onUnarchive,
}) {
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:rounded-3xl sm:p-5">
      <p className="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400 sm:text-[11px] sm:tracking-[0.18em]">
        Gestión
      </p>

      <h2 className="mt-2 text-base font-black leading-tight text-slate-900 sm:text-lg">
        {archived ? "Conversación archivada" : "Archivar conversación"}
      </h2>

      <p className="mt-2 text-sm leading-relaxed text-slate-500">
        {archived
          ? "Esta conversación está oculta de activas. Podés devolverla al inbox principal."
          : "La conversación se ocultará de activas y quedará disponible en archivadas."}
      </p>

      <button
        type="button"
        disabled={loading}
        onClick={archived ? onUnarchive : onArchive}
        className="mt-4 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 sm:mt-5"
      >
        {archived ? "Desarchivar conversación" : "Archivar conversación"}
      </button>
    </section>
  );
}