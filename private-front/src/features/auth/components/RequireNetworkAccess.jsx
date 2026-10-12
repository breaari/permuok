import { Navigate } from "react-router-dom";
import { useAuth } from "./AuthContext";
import { canUseNetwork } from "../utils/access";

export default function RequireNetworkAccess({
  children,
  fallback = "/app",
}) {
  const { loading, access } = useAuth();

  if (loading) {
    return null;
  }

  if (!canUseNetwork(access)) {
    return (
      <Navigate
        to={fallback}
        replace
      />
    );
  }

  return children;
}