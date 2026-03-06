// layout/Navbar.jsx
import { useMemo, useState, useRef, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
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
  const boxRef = useRef(null);

  const initials = useMemo(() => initialsFromUser(user), [user]);
  const label = useMemo(() => roleLabel(user?.role), [user?.role]);

  useEffect(() => {
    function onDoc(e) {
      if (!boxRef.current) return;
      if (!boxRef.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  return (
    <header className="h-16 flex items-center justify-between px-6 bg-white shadow-sm border-b border-slate-200 sticky top-0 z-10">
      <div className="flex items-center gap-3 min-w-0">
        <button
          className="md:hidden text-slate-600 rounded-lg p-2 hover:bg-slate-100"
          onClick={onOpenSidebar}
          type="button"
        >
          <Icon name="menu" />
        </button>

        <h2 className="text-lg font-bold text-slate-800 truncate">{title}</h2>
      </div>

      <div className="flex items-center gap-2 sm:gap-4">
        {/* Mensajes */}
        <button
          className="relative p-2 text-slate-500 hover:bg-slate-100 rounded-full transition-colors"
          type="button"
          onClick={() => nav("/messages")}
          title="Mensajes"
        >
          <Icon name="messagesSquare" />
        </button>

        {/* Notificaciones */}
        <button
          className="relative p-2 text-slate-500 hover:bg-slate-100 rounded-full transition-colors"
          type="button"
          onClick={() => nav("/notifications")}
          title="Notificaciones"
        >
          <Icon name="bell" />
          {/* badge opcional */}
          <span className="absolute top-2 right-2 inline-flex h-2 w-2 rounded-full bg-red-500" />
        </button>

        {/* Perfil */}
        <div ref={boxRef} className="relative flex items-center gap-3 border-l border-slate-200 pl-4">
          <div className="text-right hidden sm:block leading-tight">
            <div className="text-sm font-semibold text-slate-900">
              {(user?.first_name || user?.email || "Usuario")}
            </div>
            <div className="text-xs text-slate-500">{label}</div>
          </div>

          <button
            type="button"
            className="h-10 w-10 rounded-full border border-slate-200 bg-slate-50 flex items-center justify-center font-bold text-slate-700"
            onClick={() => setOpen((s) => !s)}
            aria-label="Abrir menú de usuario"
          >
            {initials}
          </button>

          {open && (
            <div className="absolute right-0 top-full mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-2">
              <button
                className="w-full flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
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
                className="w-full flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
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
                className="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                type="button"
                onClick={logout}
              >
                <Icon name="logOut" className="opacity-80" />
                Cerrar sesión
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}