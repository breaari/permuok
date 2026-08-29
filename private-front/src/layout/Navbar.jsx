import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import useRealtimeStream from "../features/realtime/hooks/useRealtimeStream";
import { useAuth } from "../features/auth/components/AuthContext";
import useNavbarBadges from "../features/layout/hooks/useNavbarBadges";
import NotificationsDropdown from "../features/notifications/components/NotificationsDropdown";
import { Icon } from "../ui/icons/Index";

function roleLabel(role) {
  const r = Number(role || 0);

  if (r === 1) return "Administrador";
  if (r === 2) return "Inmobiliaria";
  if (r === 3) return "Agente";
  if (r === 4) return "Inversor";

  return "Usuario";
}

function initialsFromUser(user) {
  const first = (user?.first_name || "").trim();
  const last = (user?.last_name || "").trim();

  const a = first ? first[0] : "";
  const b = last ? last[0] : "";

  const init = (a + b).toUpperCase();

  return init || (user?.email ? user.email[0].toUpperCase() : "U");
}

export default function Navbar({ title = "Panel", onOpenSidebar }) {
  const { user, logout } = useAuth();
  const nav = useNavigate();

  const [open, setOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);

  const boxRef = useRef(null);
  const notificationsRef = useRef(null);

  const initials = useMemo(() => initialsFromUser(user), [user]);
  const label = useMemo(() => roleLabel(user?.role), [user?.role]);
  const role = Number(user?.role || 0);
  const canSeeMessages = role === 2 || role === 3;
  const canSeeNotifications = role === 1 || role === 2 || role === 3;

  const shouldLoadBadges = canSeeMessages || canSeeNotifications;

  const {
    notificationsCount,
    setNotificationsCount,
    conversationsCount,
    setConversationsCount,
  } = useNavbarBadges(shouldLoadBadges ? user?.id : null);

  const handleRealtimeNotification = useCallback(() => {
    setNotificationsCount((prev) => Number(prev || 0) + 1);
  }, [setNotificationsCount]);

  const handleRealtimeConversationUnread = useCallback(
    (data) => {
      setConversationsCount(Number(data?.count || 0));
    },
    [setConversationsCount],
  );

  useRealtimeStream({
    enabled: shouldLoadBadges && Boolean(user?.id),
    onNotification: handleRealtimeNotification,
    onConversationUnread: handleRealtimeConversationUnread,
  });

  useEffect(() => {
    function onDoc(e) {
      if (boxRef.current && !boxRef.current.contains(e.target)) {
        setOpen(false);
      }

      if (
        notificationsRef.current &&
        !notificationsRef.current.contains(e.target)
      ) {
        setNotificationsOpen(false);
      }
    }

    document.addEventListener("mousedown", onDoc);

    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  return (
    <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6 shadow-sm">
      <div className="flex min-w-0 items-center gap-3">
        <button
          className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden"
          onClick={onOpenSidebar}
          type="button"
        >
          <Icon name="menu" />
        </button>

        <h2 className="truncate text-lg font-bold text-slate-800">{title}</h2>
      </div>

      <div className="flex items-center gap-2 sm:gap-4">
        {canSeeMessages ? (
          <button
            className="relative rounded-full p-2 text-slate-500 transition-colors hover:bg-slate-100"
            type="button"
            onClick={() => nav("/conversations")}
            title="Conversaciones"
          >
            <Icon name="messagesSquare" />

            {conversationsCount > 0 ? (
              <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-black text-white">
                {conversationsCount > 99 ? "99+" : conversationsCount}
              </span>
            ) : null}
          </button>
        ) : null}

        {canSeeNotifications ? (
          <div ref={notificationsRef} className="relative">
            <button
              className={`relative rounded-full p-2 transition-colors ${
                notificationsOpen
                  ? "bg-slate-100 text-slate-900"
                  : "text-slate-500 hover:bg-slate-100"
              }`}
              type="button"
              onClick={() => {
                setNotificationsOpen((value) => !value);
                setOpen(false);
              }}
              title="Notificaciones"
            >
              <Icon name="bell" />

              {notificationsCount > 0 ? (
                <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-black text-white">
                  {notificationsCount > 99 ? "99+" : notificationsCount}
                </span>
              ) : null}
            </button>

            {notificationsOpen ? (
              <NotificationsDropdown
                onUnreadChange={setNotificationsCount}
                onClose={() => setNotificationsOpen(false)}
              />
            ) : null}
          </div>
        ) : null}

        <div
          ref={boxRef}
          className="relative flex items-center gap-3 border-l border-slate-200 pl-4"
        >
          <div className="hidden text-right leading-tight sm:block">
            <div className="text-sm font-semibold text-slate-900">
              {user?.first_name || user?.email || "Usuario"}
            </div>

            <div className="text-xs text-slate-500">{label}</div>
          </div>

          <button
            type="button"
            className="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-slate-50 font-bold text-slate-700"
            onClick={() => {
              setOpen((s) => !s);
              setNotificationsOpen(false);
            }}
            aria-label="Abrir menú de usuario"
          >
            {initials}
          </button>

          {open ? (
            <div className="absolute right-0 top-full mt-2 w-52 rounded-xl border border-slate-200 bg-white py-2 shadow-xl">
              <button
                className="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                type="button"
                onClick={() => {
                  setOpen(false);
                  nav("/profile");
                }}
              >
                <Icon name="user" className="opacity-80" />
                Ver perfil
              </button>

              <button
                className="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                type="button"
                onClick={() => {
                  setOpen(false);
                  nav("/settings");
                }}
              >
                <Icon name="settings" className="opacity-80" />
                Configuración
              </button>

              <div className="my-2 border-t border-slate-200" />

              <button
                className="flex w-full items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                type="button"
                onClick={logout}
              >
                <Icon name="logOut" className="opacity-80" />
                Cerrar sesión
              </button>
            </div>
          ) : null}
        </div>
      </div>
    </header>
  );
}
