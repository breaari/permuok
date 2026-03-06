// layout/MobileSidebar.jsx
export default function MobileSidebar({ open, onClose, children }) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 md:hidden">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className="absolute left-0 top-0 h-full w-72 bg-slate-900 border-r border-slate-800 p-4 overflow-y-auto">
        <button
          className="mb-4 rounded-lg px-3 py-2 text-sm text-slate-200 hover:bg-white/10"
          onClick={onClose}
          type="button"
        >
          Cerrar
        </button>
        {children}
      </div>
    </div>
  );
}