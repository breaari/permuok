import { Navigate, Outlet } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";

export default function AdminPanel() {
  const { user } = useAuth();
  const isAdmin = Number(user?.role) === 1;
  if (!isAdmin) return <Navigate to="/" replace />;
  return <Outlet />;
}