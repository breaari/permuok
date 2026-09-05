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

export default function App() {
  return (
    <Routes>
      {/* Página pública corporativa */}
      <Route path="/" element={<HomePage />} />

      {/* Auth público */}
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />

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
        <Route path="properties/new" element={<PropertyForm />} />
        <Route path="properties/:id/edit" element={<PropertyForm />} />
        <Route path="properties/:id" element={<PropertyDetail />} />

        <Route path="search-requests" element={<SearchRequests />} />
        <Route path="search-requests/new" element={<SearchRequestForm />} />
        <Route
          path="search-requests/:id/edit"
          element={<SearchRequestForm />}
        />
        <Route path="search-requests/:id" element={<SearchRequestDetail />} />

        <Route
          path="developments"
          element={
            <RequireDevelopmentAccess mode="publish">
              <Developments />
            </RequireDevelopmentAccess>
          }
        />
        <Route
          path="developments/new"
          element={
            <RequireDevelopmentAccess mode="publish">
              <DevelopmentForm />
            </RequireDevelopmentAccess>
          }
        />
        <Route
          path="developments/:id/edit"
          element={
            <RequireDevelopmentAccess mode="publish">
              <DevelopmentForm />
            </RequireDevelopmentAccess>
          }
        />
        <Route
          path="developments/:id"
          element={
            <RequireDevelopmentAccess mode="publish">
              <DevelopmentDetail />
            </RequireDevelopmentAccess>
          }
        />

        <Route path="explore" element={<Explore />} />
        <Route
          path="explore/properties"
          element={<Explore defaultType="property" />}
        />
        <Route path="explore/properties/:id" element={<PropertyDetail />} />

        <Route
          path="explore/search-requests"
          element={<Explore defaultType="search_request" />}
        />
        <Route
          path="explore/search-requests/:id"
          element={<SearchRequestDetail />}
        />

        <Route
          path="explore/developments"
          element={
            <RequireDevelopmentAccess mode="view">
              <Explore defaultType="development" />
            </RequireDevelopmentAccess>
          }
        />
        <Route
          path="explore/developments/:id"
          element={
            <RequireDevelopmentAccess mode="view">
              <DevelopmentDetail />
            </RequireDevelopmentAccess>
          }
        />
        <Route path="compatibilities" element={<Compatibilities />} />

        <Route
          path="compatibilities/multilateral"
          element={<MultilateralCompatibilities />}
        />

        <Route
          path="compatibilities/multilateral/:id"
          element={<MultilateralCompatibilityDetail />}
        />

        <Route path="compatibilities/:id" element={<CompatibilityDetail />} />

        <Route path="conversations" element={<Inbox />} />
        <Route path="conversations/:id" element={<ConversationDetail />} />

        <Route path="admin" element={<AdminPanel />}>
          <Route index element={<AdminDashboard />} />
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
        </Route>

        <Route path="*" element={<Navigate to="/app" replace />} />
      </Route>

      {/* Cualquier ruta inexistente fuera de la app vuelve a la landing */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
