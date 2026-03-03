// src/layout/Sidebar.jsx
import { NavLink } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";

function Item({ to, children, disabled = false }) {
  if (disabled) {
    return (
      <div className="block rounded-lg px-3 py-2 text-sm text-gray-400 cursor-not-allowed">
        {children}
      </div>
    );
  }

  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        [
          "block rounded-lg px-3 py-2 text-sm",
          isActive ? "bg-black text-white" : "text-gray-700 hover:bg-gray-100",
        ].join(" ")
      }
      end
    >
      {children}
    </NavLink>
  );
}

export default function Sidebar() {
  const { user, access } = useAuth();
  const role = Number(user?.role || 0);
  const isAdmin = role === 1;

  const level = access?.level;

  const needsOnboarding =
    level === "real_estate_not_linked" || level === "real_estate_draft";

  const isReview = level === "real_estate_review";
  const isUnpaid = level === "real_estate_unpaid";
  const isActive = level === "real_estate_active";

  return (
    <aside className="w-64 shrink-0 border-r min-h-screen p-4">
      <div className="text-lg font-semibold">permuok</div>
      <div className="mt-1 text-xs text-gray-500">
        {isAdmin ? "Admin" : "Inmobiliaria"} {level ? `• ${level}` : ""}
      </div>

      <nav className="mt-6 space-y-1">
        {isAdmin ? (
          <>
            <Item to="/admin">Panel</Item>
            <Item to="/admin/real-estates">Revisiones</Item>
          </>
        ) : (
          <>
            {/* Panel base */}
            <Item to="/app">Panel</Item>

            {/* Flujos */}
            <Item to="/onboarding" disabled={!needsOnboarding}>
              Onboarding
            </Item>

            <Item to="/status/review" disabled={!isReview}>
              Revisión
            </Item>

            <Item to="/billing" disabled={!isUnpaid}>
              Membresía
            </Item>

            {/* Sección de “funcionalidades” reales (ruta distinta a /app) */}
            <Item to="/app/features" disabled={!isActive}>
              Funciones
            </Item>
          </>
        )}
      </nav>

      <div className="mt-8 border-t pt-4 text-xs text-gray-500">
        {user?.email || "—"}
      </div>
    </aside>
  );
}