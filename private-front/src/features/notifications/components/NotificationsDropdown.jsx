import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";

import { Icon } from "../../../ui/icons/Index";

import {
  getNotifications,
  markAllNotificationsAsRead,
  markNotificationAsRead,
} from "../api/notifications.api";

function formatDate(value) {
  if (!value) return "";

  try {
    return new Date(value).toLocaleString("es-AR", {
      day: "2-digit",
      month: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return "";
  }
}

function getNotificationPath(item) {
  if (item?.related_type === "conversation" && item?.related_id) {
    return `/conversations/${item.related_id}`;
  }

  return null;
}

function getNotificationIcon(type) {
  switch (type) {
    case "new_message":
      return "messagesSquare";

    case "new_conversation":
      return "inbox";

    case "contact_share_requested":
      return "shieldCheck";

    case "contact_share_accepted":
      return "badgeCheck";

    case "contact_share_rejected":
      return "xCircle";

    case "conversation_status_changed":
      return "refreshCcw";

    default:
      return "bell";
  }
}

function getNotificationColor(unread) {
  if (unread) {
    return "bg-emerald-100 text-emerald-700";
  }

  return "bg-slate-100 text-slate-500";
}

export default function NotificationsDropdown({
  onUnreadChange,
  onClose,
}) {
  const navigate = useNavigate();

  const [items, setItems] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [markingAll, setMarkingAll] = useState(false);

  async function loadData() {
    try {
      const res = await getNotifications({
        page: 1,
        limit: 8,
      });

      const nextItems = Array.isArray(res?.items)
        ? res.items
        : [];

      const nextUnread = Number(
        res?.unread_count?.count || 0,
      );

      setItems(nextItems);
      setUnreadCount(nextUnread);

      onUnreadChange?.(nextUnread);
    } catch (err) {
      console.error("[NOTIFICATIONS] load error", err);
    } finally {
      setLoading(false);
    }
  }

  async function handleOpen(item) {
    const path = getNotificationPath(item);

    try {
      if (Number(item?.is_read || 0) === 0) {
        await markNotificationAsRead(item.id);

        setItems((prev) =>
          prev.map((n) =>
            Number(n.id) === Number(item.id)
              ? {
                  ...n,
                  is_read: 1,
                }
              : n,
          ),
        );

        const nextUnread = Math.max(0, unreadCount - 1);

        setUnreadCount(nextUnread);
        onUnreadChange?.(nextUnread);
      }

      onClose?.();

      if (path) {
        navigate(path);
      }
    } catch (err) {
      console.error("[NOTIFICATIONS] open error", err);
    }
  }

  async function handleMarkAll() {
    try {
      setMarkingAll(true);

      await markAllNotificationsAsRead();

      setItems((prev) =>
        prev.map((item) => ({
          ...item,
          is_read: 1,
        })),
      );

      setUnreadCount(0);
      onUnreadChange?.(0);
    } catch (err) {
      console.error("[NOTIFICATIONS] mark all error", err);
    } finally {
      setMarkingAll(false);
    }
  }

  useEffect(() => {
    loadData();
  }, []);

  return (
    <div className="absolute right-0 top-full mt-3 w-[380px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
      <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
        <div>
          <p className="text-sm font-black text-slate-900">
            Notificaciones
          </p>

          <p className="text-xs font-semibold text-slate-400">
            {unreadCount > 0
              ? `${unreadCount} sin leer`
              : "Todo leído"}
          </p>
        </div>

        {unreadCount > 0 ? (
          <button
            type="button"
            disabled={markingAll}
            onClick={handleMarkAll}
            className="text-xs font-bold text-emerald-600 transition hover:text-emerald-700 disabled:opacity-50"
          >
            Marcar todo
          </button>
        ) : null}
      </div>

      <div className="max-h-[420px] overflow-y-auto">
        {loading ? (
          <div className="p-5 text-sm font-semibold text-slate-400">
            Cargando...
          </div>
        ) : !items.length ? (
          <div className="p-6 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
              <Icon name="bell" size={22} />
            </div>

            <p className="mt-3 text-sm font-bold text-slate-700">
              No tenés notificaciones
            </p>

            <p className="mt-1 text-xs text-slate-400">
              Cuando ocurra actividad, aparecerá acá.
            </p>
          </div>
        ) : (
          items.map((item) => {
            const unread =
              Number(item?.is_read || 0) === 0;

            return (
              <button
                key={item.id}
                type="button"
                onClick={() => handleOpen(item)}
                className={`flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-slate-50 ${
                  unread
                    ? "bg-emerald-50/40"
                    : "bg-white"
                }`}
              >
                <div
                  className={`mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${getNotificationColor(
                    unread,
                  )}`}
                >
                  <Icon
                    name={getNotificationIcon(item?.type)}
                    size={18}
                  />
                </div>

                <div className="min-w-0 flex-1">
                  <div className="flex items-start justify-between gap-3">
                    <p className="line-clamp-1 text-sm font-black text-slate-900">
                      {item?.title || "Notificación"}
                    </p>

                    {unread ? (
                      <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                    ) : null}
                  </div>

                  {item?.body ? (
                    <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">
                      {item.body}
                    </p>
                  ) : null}

                  <p className="mt-2 text-[11px] font-bold text-slate-400">
                    {formatDate(item?.created_at)}
                  </p>
                </div>
              </button>
            );
          })
        )}
      </div>
    </div>
  );
}