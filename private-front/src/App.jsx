import { Routes, Route, Navigate } from "react-router-dom";

import HomePage from "./public-site/pages/HomePage";

import Login from "./features/auth/pages/Login";
import Register from "./features/auth/pages/Register";
import Gate from "./features/gate/pages/Gate";
import ProtectedRoute from "./features/auth/components/ProtectedRoute";

import MyProfile from "./features/real-estate-profile/pages/MyProfile";
import Billing from "./features/billing/pages/Billing";

import AdminPanel from "./features/admin/pages/AdminPanel";
import AdminRealEstates from "./features/admin/pages/AdminRealEstates";
import AdminRealEstateDetail from "./features/admin/pages/AdminRealEstateDetail";
import AdminRealEstateManagement from "./features/admin/pages/AdminRealEstateManagement";

import AppLayout from "./layout/AppLayout";
import ChangePlan from "./features/billing/pages/ChangePlan";
import Users from "./features/users/pages/Users";
import AdminUsers from "./features/admin/pages/AdminUsers";
import AdminUserDetail from "./features/admin/pages/AdminUserDetail";
import AdminBilling from "./features/admin/pages/AdminBilling";
import AdminBillingDetail from "./features/admin/pages/AdminBillingDetail";

import Properties from "./features/properties/pages/Properties";
import Dashboard from "./features/dashboard/pages/Dashboard";
import PropertyForm from "./features/properties/pages/PropertyForm";
import PropertyDetail from "./features/properties/pages/PropertyDetail";

import SearchRequests from "./features/search-requests/pages/SearchRequests";
import SearchRequestForm from "./features/search-requests/pages/SearchRequestForm";
import SearchRequestDetail from "./features/search-requests/pages/SearchRequestDetail";

import Developments from "./features/developments/pages/Developments";
import DevelopmentForm from "./features/developments/pages/DevelopmentForm";
import DevelopmentDetail from "./features/developments/pages/DevelopmentDetail";
import RequireDevelopmentAccess from "./features/developments/components/RequireDevelopmentAccess";

import Explore from "./features/explore/pages/Explore";

import Inbox from "./features/conversations/pages/Inbox";
import ConversationDetail from "./features/conversations/pages/ConversationDetail";
import AdminDashboard from "./features/admin/pages/AdminDashboard";

import Compatibilities from "./features/compatibilities/pages/Compatibilities";
import CompatibilityDetail from "./features/compatibilities/pages/CompatibilityDetail";

import MultilateralCompatibilities from "./features/compatibilities/pages/MultilateralCompatibilities";
import MultilateralCompatibilityDetail from "./features/compatibilities/pages/MultilateralCompatibilityDetail";

import AdminCompatibilityJobs from "./features/admin/pages/AdminCompatibilityJobs";
import AdminEmailJobs from "./features/admin/pages/AdminEmailJobs";

import ForgotPassword from "./features/auth/pages/ForgotPassword";
import ResetPassword from "./features/auth/pages/ResetPassword";

import VerifyEmail from "./features/auth/pages/VerifyEmail";
import VerifyEmailPending from "./features/auth/pages/VerifyEmailPending";

import RequireNetworkAccess from "./features/auth/components/RequireNetworkAccess";

export default function App() {
  return (
    <Routes>
      {/* Página pública corporativa */}
      <Route path="/" element={<HomePage />} />

      {/* Auth público */}
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/forgot-password" element={<ForgotPassword />} />
      <Route path="/reset-password" element={<ResetPassword />} />
      <Route path="/verify-email" element={<VerifyEmail />} />
      <Route path="/verify-email-pending" element={<VerifyEmailPending />} />
      {/* App privada */}
      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route path="gate" element={<Gate />} />

        <Route path="my-profile" element={<MyProfile />} />
        <Route path="billing" element={<Billing />} />
        <Route path="billing/change-plan" element={<ChangePlan />} />
        <Route path="users" element={<Users />} />
        <Route path="app" element={<Dashboard />} />

        <Route path="properties" element={<Properties />} />
        <Route
          path="properties/new"
          element={
            <RequireNetworkAccess fallback="/properties">
              <PropertyForm />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="properties/:id/edit"
          element={
            <RequireNetworkAccess fallback="/properties">
              <PropertyForm />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="properties/:id"
          element={
            <RequireNetworkAccess fallback="/properties">
              <PropertyDetail />
            </RequireNetworkAccess>
          }
        />

        <Route path="search-requests" element={<SearchRequests />} />
        <Route
          path="search-requests/new"
          element={
            <RequireNetworkAccess fallback="/search-requests">
              <SearchRequestForm />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="search-requests/:id/edit"
          element={
            <RequireNetworkAccess fallback="/search-requests">
              <SearchRequestForm />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="search-requests/:id"
          element={
            <RequireNetworkAccess fallback="/search-requests">
              <SearchRequestDetail />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="developments"
          element={
            <RequireNetworkAccess>
              <RequireDevelopmentAccess mode="publish">
                <Developments />
              </RequireDevelopmentAccess>
            </RequireNetworkAccess>
          }
        />
        <Route
          path="developments/new"
          element={
            <RequireNetworkAccess>
              <RequireDevelopmentAccess mode="publish">
                <DevelopmentForm />
              </RequireDevelopmentAccess>
            </RequireNetworkAccess>
          }
        />

        <Route
          path="developments/:id/edit"
          element={
            <RequireNetworkAccess>
              <RequireDevelopmentAccess mode="publish">
                <DevelopmentForm />
              </RequireDevelopmentAccess>
            </RequireNetworkAccess>
          }
        />

        <Route
          path="developments/:id"
          element={
            <RequireNetworkAccess>
              <RequireDevelopmentAccess mode="publish">
                <DevelopmentDetail />
              </RequireDevelopmentAccess>
            </RequireNetworkAccess>
          }
        />
        <Route
          path="explore"
          element={
            <RequireNetworkAccess>
              <Explore />
            </RequireNetworkAccess>
          }
        />
        <Route
          path="explore/properties"
          element={
            <RequireNetworkAccess>
              <Explore defaultType="property" />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="explore/properties/:id"
          element={
            <RequireNetworkAccess>
              <PropertyDetail />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="explore/search-requests"
          element={
            <RequireNetworkAccess>
              <Explore defaultType="search_request" />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="explore/search-requests/:id"
          element={
            <RequireNetworkAccess>
              <SearchRequestDetail />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="explore/developments"
          element={
            <RequireNetworkAccess>
              <RequireDevelopmentAccess mode="view">
                <Explore defaultType="development" />
              </RequireDevelopmentAccess>
            </RequireNetworkAccess>
          }
        />

        <Route
          path="explore/developments/:id"
          element={
            <RequireNetworkAccess>
              <RequireDevelopmentAccess mode="view">
                <DevelopmentDetail />
              </RequireDevelopmentAccess>
            </RequireNetworkAccess>
          }
        />
        <Route path="compatibilities" element={<Compatibilities />} />

        <Route
          path="compatibilities/multilateral"
          element={
            <RequireNetworkAccess fallback="/compatibilities">
              <MultilateralCompatibilities />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="compatibilities/multilateral/:id"
          element={
            <RequireNetworkAccess fallback="/compatibilities">
              <MultilateralCompatibilityDetail />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="compatibilities/:id"
          element={
            <RequireNetworkAccess fallback="/compatibilities">
              <CompatibilityDetail />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="conversations"
          element={
            <RequireNetworkAccess>
              <Inbox />
            </RequireNetworkAccess>
          }
        />

        <Route
          path="conversations/:id"
          element={
            <RequireNetworkAccess>
              <ConversationDetail />
            </RequireNetworkAccess>
          }
        />

        <Route path="admin" element={<AdminPanel />}>
          <Route index element={<AdminDashboard />} />
          <Route
            path="real-estate-management"
            element={<AdminRealEstateManagement />}
          />
          <Route path="real-estates" element={<AdminRealEstates />} />
          <Route path="real-estates/:id" element={<AdminRealEstateDetail />} />
          <Route path="users" element={<AdminUsers />} />
          <Route path="users/:id" element={<AdminUserDetail />} />
          <Route path="billing" element={<AdminBilling />} />
          <Route path="billing/:id" element={<AdminBillingDetail />} />
          <Route
            path="system/compatibility-jobs"
            element={<AdminCompatibilityJobs />}
          />
          <Route path="system/email-jobs" element={<AdminEmailJobs />} />
        </Route>

        <Route path="*" element={<Navigate to="/app" replace />} />
      </Route>

      {/* Cualquier ruta inexistente fuera de la app vuelve a la landing */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
