export default function InboxHeader({ polling = false }) {
  return (
    <div className="mb-6 sm:mb-8">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-600 sm:text-[11px] sm:tracking-[0.18em]">
          Inbox
        </p>

        {polling ? (
          <span className="text-[10px] font-bold text-slate-400 sm:text-[11px]">
            Actualizando...
          </span>
        ) : null}
      </div>

      <h1 className="mt-1 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
        Conversaciones
      </h1>

      <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
        Alterná entre consultas sobre tus publicaciones y consultas que
        iniciaste sobre publicaciones externas.
      </p>
    </div>
  );
}
