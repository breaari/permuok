import { Icon } from "../../../../ui/icons/Index";

export default function AdminDetailBackButton({ label, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex items-center gap-2 px-2 py-2 text-slate-500 hover:text-slate-900 transition-colors font-semibold text-sm"
    >
      <Icon name="arrowLeft" size={16} />
      {label}
    </button>
  );
}