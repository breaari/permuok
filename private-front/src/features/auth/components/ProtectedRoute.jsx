// src/auth/ProtectedRoute.jsx
import { Navigate } from "react-router-dom";
import { useAuth } from "./AuthContext";

export default function ProtectedRoute({ children }) {
  const { loading, user } = useAuth();

  if (loading) return <div className="p-6">Cargando sesión...</div>;
  if (!user) return <Navigate to="/login" replace />;
  return children;
}