import { Navigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";

export default function Gate() {
  const { loading, user, access } = useAuth();

  if (loading) return <div className="p-6">Cargando...</div>;
  if (!user) return <Navigate to="/login" replace />;

  const level = access?.level;

  if (Number(user?.role) === 1) return <Navigate to="/admin" replace />;
  if (level === "real_estate_not_linked")
    return <Navigate to="/my-profile" replace />;
  if (level === "real_estate_draft")
    return <Navigate to="/my-profile" replace />;
  if (level === "real_estate_review")
    return <Navigate to="/my-profile" replace />;
  if (level === "real_estate_unpaid") return <Navigate to="/billing" replace />;
  if (level === "real_estate_active") return <Navigate to="/app" replace />;

  return <Navigate to="/app" replace />;
}
