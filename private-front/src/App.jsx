import { Routes, Route, Navigate } from "react-router-dom";

import Login from "./pages/Login";
import Register from "./pages/Register";
import Gate from "./pages/Gate";
import ProtectedRoute from "./auth/ProtectedRoute";

import MyProfile from "./pages/MyProfile";
import Review from "./pages/Review";
import Billing from "./pages/Billing";
import AppHome from "./pages/AppHome";

import AdminPanel from "./pages/AdminPanel";
import AdminRealEstates from "./pages/AdminRealEstates";
import AdminRealEstateDetail from "./pages/AdminRealEstateDetail";

import AppLayout from "./layout/AppLayout";

import BillingSuccess from "./pages/BillingSuccess";
import BillingPending from "./pages/BillingPending";
import BillingFailure from "./pages/BillingFailure";

export default function App() {
  return (
    <Routes>
      {/* PUBLIC */}
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />

      {/* PROTECTED + LAYOUT */}
      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/" element={<Gate />} />

        {/* Inmobiliaria */}
        <Route path="my-profile" element={<MyProfile />} />
        <Route path="status/review" element={<Review />} />
        <Route path="billing" element={<Billing />} />
        <Route path="billing/success" element={<BillingSuccess />} />
        <Route path="billing/pending" element={<BillingPending />} />
        <Route path="billing/failure" element={<BillingFailure />} />

        <Route path="app" element={<AppHome />} />

        {/* Admin */}
        <Route path="admin" element={<AdminPanel />}>
          <Route index element={<Navigate to="real-estates" replace />} />
          <Route path="real-estates" element={<AdminRealEstates />} />
          <Route path="real-estates/:id" element={<AdminRealEstateDetail />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>

      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}