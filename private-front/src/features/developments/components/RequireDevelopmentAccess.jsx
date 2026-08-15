import { Navigate } from "react-router-dom";
import { useAuth } from "../../auth/components/AuthContext";
import DevelopmentUpgradeRequired from "../pages/DevelopmentUpgradeRequired";

export default function RequireDevelopmentAccess({
  children,
  mode = "publish", // "publish" | "view"
}) {
  const { loading, permissions } = useAuth();

  if (loading) return null;

  if (mode === "publish") {
    if (!permissions.canUseDevelopments) {
      return <DevelopmentUpgradeRequired mode="publish" />;
    }
  }

  if (mode === "view") {
    if (!permissions.canViewDevelopments) {
      return <DevelopmentUpgradeRequired mode="view" />;
    }
  }

  return children;
}