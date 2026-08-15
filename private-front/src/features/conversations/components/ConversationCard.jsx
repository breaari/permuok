import { Icon } from "../../../ui/icons/Index";
import {
  buildConversationPreview,
  formatConversationDate,
  getConversationStatusMeta,
  getOpportunityLabel,
} from "../detail/conversationCard.helpers";
import ConversationCardActions from "./ConversationCardActions";

export default function ConversationCard({
  item,
  mode,
  archived = false,
  onOpen,
  onArchive,
  onUnarchive,
}) {
  const unread = Number(item?.unread_count || 0);
  const isOwn = mode === "own";
  const statusMeta = getConversationStatusMeta(item?.status || "open");

  return (
    <article
      className={`group relative overflow-hidden rounded-2xl border bg-white p-4 transition-all sm:rounded-3xl sm:p-5 ${
        unread > 0
          ? "border-emerald-200 shadow-md shadow-emerald-100/40"
          : "border-slate-200 shadow-sm"
      } hover:border-slate-300 hover:shadow-lg sm:hover:-translate-y-0.5`}
    >
      {unread > 0 ? (
        <div className="absolute left-0 top-0 h-full w-1 bg-emerald-500" />
      ) : null}

      <button type="button" onClick={onOpen} className="w-full text-left">
        <div className="flex items-start gap-3 sm:gap-4">
          <div
            className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl sm:h-14 sm:w-14 ${
              isOwn
                ? "bg-emerald-100 text-emerald-700"
                : "bg-blue-100 text-blue-700"
            }`}
          >
            <Icon name="messagesSquare" size={20} />
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                  <Badge>{getOpportunityLabel(item?.opportunity_type)}</Badge>

                  <Badge
                    className={
                      isOwn
                        ? "bg-emerald-100 text-emerald-700"
                        : "bg-blue-100 text-blue-700"
                    }
                  >
                    {isOwn ? "Publicación propia" : "Publicación externa"}
                  </Badge>

                  <Badge className={statusMeta.className}>
                    {statusMeta.label}
                  </Badge>

                  {Number(item?.contact_shared) === 1 ? (
                    <Badge className="bg-violet-100 text-violet-700">
                      Contacto habilitado
                    </Badge>
                  ) : null}
                </div>

                <h3 className="mt-3 line-clamp-2 text-sm font-black leading-snug text-slate-900 sm:text-base">
                  {item?.subject || "Consulta"}
                </h3>
              </div>

              <div className="flex shrink-0 items-center justify-between gap-3 sm:block sm:text-right">
                <p className="text-[11px] font-bold text-slate-400 sm:text-xs">
                  {formatConversationDate(item?.last_message_created_at)}
                </p>

                {unread > 0 ? (
                  <div className="inline-flex min-w-[24px] items-center justify-center rounded-full bg-emerald-500 px-2 py-1 text-[11px] font-black text-white sm:mt-2">
                    {unread > 99 ? "99+" : unread}
                  </div>
                ) : null}
              </div>
            </div>

            <p
              className={`mt-3 line-clamp-2 text-xs leading-relaxed sm:mt-4 sm:text-sm ${
                unread > 0 ? "font-medium text-slate-700" : "text-slate-500"
              }`}
            >
              {buildConversationPreview(item)}
            </p>
          </div>
        </div>
      </button>

      <div className="mt-4 border-t border-slate-100 pt-3 sm:mt-0 sm:border-t-0 sm:pt-0">
        <ConversationCardActions
          archived={archived}
          onArchive={onArchive}
          onUnarchive={onUnarchive}
        />
      </div>
    </article>
  );
}

function Badge({ children, className = "bg-slate-100 text-slate-600" }) {
  return (
    <span
      className={`rounded-full px-2 py-1 text-[9px] font-black uppercase tracking-[0.12em] sm:px-2.5 sm:text-[10px] sm:tracking-[0.14em] ${className}`}
    >
      {children}
    </span>
  );
}