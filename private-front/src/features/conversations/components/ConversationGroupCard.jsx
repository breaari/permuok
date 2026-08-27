import { Icon } from "../../../ui/icons/Index";

import {
  formatConversationDate,
  getOpportunityLabel,
} from "../detail/conversationCard.helpers";

export default function ConversationGroupCard({
  group,
  onOpen,
}) {
  const count =
    Number(
      group?.conversation_count || 0,
    );

  const unread =
    Number(
      group?.unread_count || 0,
    );

  return (
    <article
      className={`group overflow-hidden rounded-2xl border bg-white p-4 transition-all sm:rounded-3xl sm:p-5 ${
        unread > 0
          ? "border-emerald-200 shadow-md shadow-emerald-100/40"
          : "border-slate-200 shadow-sm"
      } hover:border-slate-300 hover:shadow-lg`}
    >
      <button
        type="button"
        onClick={onOpen}
        className="w-full text-left"
      >
        <div className="flex items-start gap-3 sm:gap-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
            <Icon
              name="messagesSquare"
              size={21}
            />
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">
                  {getOpportunityLabel(
                    group?.opportunity_type,
                  )}
                </span>

                <h3 className="mt-3 line-clamp-2 text-base font-black text-slate-900">
                  {group?.subject ||
                    "Publicación"}
                </h3>
              </div>

              <p className="text-xs font-bold text-slate-400">
                {formatConversationDate(
                  group?.last_activity_at,
                )}
              </p>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
              <span className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-700">
                {count}{" "}
                {count === 1
                  ? "conversación"
                  : "conversaciones"}
              </span>

              {unread > 0 ? (
                <span className="rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-black text-emerald-700">
                  {unread} sin leer
                </span>
              ) : null}
            </div>

            <p className="mt-4 text-sm font-bold text-emerald-700">
              Ver conversaciones →
            </p>
          </div>
        </div>
      </button>
    </article>
  );
}