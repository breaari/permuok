import { useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";

function pillClass(kind) {
  switch (kind) {
    case "ok":
      return "bg-green-50 text-green-700 border-green-200";
    case "warn":
      return "bg-yellow-50 text-yellow-800 border-yellow-200";
    case "bad":
      return "bg-red-50 text-red-700 border-red-200";
    default:
      return "bg-gray-50 text-gray-700 border-gray-200";
  }
}

function statusToPill(level) {
  if (!level) return { label: "—", kind: "neutral" };
  if (level === "real_estate_active") return { label: "Activa", kind: "ok" };
  if (level === "real_estate_unpaid") return { label: "Pendiente de pago", kind: "warn" };
  if (level === "real_estate_review") return { label: "En revisión", kind: "warn" };
  if (level === "real_estate_draft" || level === "real_estate_not_linked")
    return { label: "Perfil incompleto", kind: "bad" };
  return { label: level, kind: "neutral" };
}

export default function Topbar() {
  const { user, access, logout } = useAuth();
  const nav = useNavigate();

  const role = Number(user?.role || 0);
  const isAdmin = role === 1;

  const pill = statusToPill(access?.level);

  return (
    <header className="sticky top-0 z-10 border-b bg-white/90 backdrop-blur">
      <div className="flex items-center justify-between px-6 py-3 gap-3">
        <div className="min-w-0">
          <div className="text-sm font-medium truncate">
            {isAdmin ? "Panel Administrador" : "Panel Inmobiliaria"}
          </div>
          <div className="text-xs text-gray-500 truncate">
            {user?.email || "—"}
          </div>
        </div>

        <div className="flex items-center gap-2">
          {!isAdmin && (
            <span className={"text-xs border rounded-full px-2 py-1 " + pillClass(pill.kind)}>
              {pill.label}
            </span>
          )}

          <button
            className="rounded-lg border px-3 py-2 text-sm"
            onClick={() => nav("/", { replace: true })}
          >
            Inicio
          </button>

          <button
            className="rounded-lg bg-black px-3 py-2 text-sm text-white"
            onClick={logout}
          >
            Salir
          </button>
        </div>
      </div>
    </header>
  );
}