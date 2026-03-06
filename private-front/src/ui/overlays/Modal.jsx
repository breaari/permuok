export function Modal({ title, children, onClose }) {
  return (
    <div className="fixed inset-0 z-[80]">
      <button type="button" aria-label="Cerrar" onClick={onClose} className="absolute inset-0 bg-black/30" />
      <div className="relative z-[81] mx-auto max-w-lg px-4 py-10">
        <div className="bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden">
          <div className="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 className="text-lg font-bold text-slate-900">{title}</h3>
            <button type="button" onClick={onClose} className="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-50">
              ✕
            </button>
          </div>
          <div className="p-5">{children}</div>
        </div>
      </div>
    </div>
  );
}