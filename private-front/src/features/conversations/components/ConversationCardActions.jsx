import { useEffect, useRef, useState } from "react";

import { Icon } from "../../../ui/icons/Index";

export default function ConversationCardActions({
  archived = false,
  onArchive,
  onUnarchive,
}) {
  const [open, setOpen] = useState(false);

  const ref = useRef(null);

  useEffect(() => {
    function handleClickOutside(event) {
      if (ref.current && !ref.current.contains(event.target)) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);

    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, []);

  return (
    <div className="relative flex justify-end sm:mt-4" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700 sm:h-9 sm:w-9"
        aria-label="Abrir acciones de conversación"
      >
        <Icon name="moreVertical" size={18} />
      </button>

      {open ? (
        <div className="absolute right-0 top-12 z-20 min-w-[220px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:top-11 sm:min-w-[180px]">
          {archived ? (
            <button
              type="button"
              onClick={() => {
                setOpen(false);
                onUnarchive?.();
              }}
              className="flex w-full items-center gap-3 px-4 py-3.5 text-left text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 sm:py-3"
            >
              <Icon name="archive" size={16} />
              Desarchivar conversación
            </button>
          ) : (
            <button
              type="button"
              onClick={() => {
                setOpen(false);
                onArchive?.();
              }}
              className="flex w-full items-center gap-3 px-4 py-3.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:py-3"
            >
              <Icon name="archive" size={16} />
              Archivar conversación
            </button>
          )}
        </div>
      ) : null}
    </div>
  );
}