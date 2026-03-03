import { Routes, Route, Navigate } from "react-router-dom";

import Login from "./pages/Login";
import Register from "./pages/Register";
import Gate from "./pages/Gate";
import ProtectedRoute from "./auth/ProtectedRoute";

import Onboarding from "./pages/Onboarding";
import Review from "./pages/Review";
import Billing from "./pages/Billing";
import AppHome from "./pages/AppHome";

import AdminPanel from "./pages/AdminPanel";
import AdminRealEstates from "./pages/AdminRealEstates";

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
        {/* Gate decide qué hacer con "/" */}
        <Route path="/" element={<Gate />} />

        {/* Inmobiliaria */}
        <Route path="/onboarding" element={<Onboarding />} />
        <Route path="/status/review" element={<Review />} />
        <Route path="/billing" element={<Billing />} />
        <Route path="/billing/success" element={<BillingSuccess />} />
        <Route path="/billing/pending" element={<BillingPending />} />
        <Route path="/billing/failure" element={<BillingFailure />} />

        {/* Home app */}
        <Route path="/app" element={<AppHome />} />

        {/* Admin */}
        <Route path="/admin" element={<AdminPanel />} />
        <Route path="/admin/real-estates" element={<AdminRealEstates />} />

        {/* fallback (protegido): si no matchea nada, volvemos al gate */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>

      {/* fallback público (por si acaso): */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}