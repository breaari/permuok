import { Icon } from "../../../ui/icons/Index";

export default function ConversationCardActions({
  archived = false,
  onArchive,
  onUnarchive,
}) {
  const handleClick = archived ? onUnarchive : onArchive;

  return (
    <div className="flex justify-end sm:mt-4">
      <button
        type="button"
        onClick={handleClick}
        className={`inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold transition ${
          archived
            ? "text-emerald-700 hover:bg-emerald-50"
            : "text-slate-500 hover:bg-slate-100 hover:text-slate-700"
        }`}
      >
        <Icon name="archive" size={15} />

        {archived ? "Desarchivar" : "Archivar"}
      </button>
    </div>
  );
}
