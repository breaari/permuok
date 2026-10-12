import { Navigate } from "react-router-dom";
import { useAuth } from "../../auth/components/AuthContext";

export default function Gate() {
  const { loading, user, access } = useAuth();

  if (loading) {
    return <div className="p-6 text-sm text-slate-500">Cargando...</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  const role = Number(user?.role || 0);
  const level = access?.level;

  /* =========================================================
     ADMIN
  ========================================================= */

  if (role === 1) {
    return <Navigate to="/admin" replace />;
  }

  /* =========================================================
     INMOBILIARIA

     El Panel ahora funciona también como espacio de onboarding.
     No enviamos automáticamente al usuario al perfil ni a
     facturación: desde el Panel le mostramos su estado y el
     próximo paso.
  ========================================================= */

  if (role === 2) {
    return <Navigate to="/app" replace />;
  }

  /* =========================================================
     AGENTES / INVERSORES
  ========================================================= */

  if (role === 3 || role === 4) {
    return <Navigate to="/app" replace />;
  }

  /* =========================================================
     FALLBACK
  ========================================================= */

  if (
    level === "real_estate_active" ||
    level === "real_estate_active_changes_pending"
  ) {
    return <Navigate to="/app" replace />;
  }

  return <Navigate to="/app" replace />;
}
