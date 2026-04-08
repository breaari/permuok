import { Routes, Route, Navigate } from "react-router-dom";

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

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />

      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/" element={<Gate />} />

        <Route path="my-profile" element={<MyProfile />} />
        <Route path="billing" element={<Billing />} />
        <Route path="billing/change-plan" element={<ChangePlan />} />
        <Route path="users" element={<Users />} />
        <Route path="app" element={<Dashboard />} />

        <Route path="properties" element={<Properties />} />
        <Route path="properties/new" element={<PropertyForm />} />
        <Route path="properties/:id/edit" element={<PropertyForm />} />
        <Route path="properties/:id" element={<PropertyDetail />} />

        <Route path="explore/properties" element={<Properties />} />
        <Route path="explore/properties/:id" element={<PropertyDetail />} />

        <Route path="search-requests" element={<SearchRequests />} />
        <Route path="search-requests/new" element={<SearchRequestForm />} />
        <Route
          path="search-requests/:id/edit"
          element={<SearchRequestForm />}
        />
        <Route path="search-requests/:id" element={<SearchRequestDetail />} />

        <Route path="explore/search-requests" element={<SearchRequests />} />
        <Route
          path="explore/search-requests/:id"
          element={<SearchRequestDetail />}
        />

        <Route path="admin" element={<AdminPanel />}>
          <Route index element={<Navigate to="real-estates" replace />} />
          <Route path="real-estates" element={<AdminRealEstates />} />
          <Route path="real-estates/:id" element={<AdminRealEstateDetail />} />
          <Route path="users" element={<AdminUsers />} />
          <Route path="users/:id" element={<AdminUserDetail />} />
          <Route path="billing" element={<AdminBilling />} />
          <Route path="billing/:id" element={<AdminBillingDetail />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>

      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}