import { useState } from "react";
import { Link, useLocation } from "react-router-dom";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Button from "../../../ui/components/Button";
import { Icon } from "../../../ui/icons/Index";
import {
  api,
  getErrorMessage,
  unwrap,
} from "../../../api/http.js";

export default function VerifyEmailPending() {
  const location = useLocation();

  const email =
    String(location.state?.email || "")
      .trim()
      .toLowerCase();

  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  async function resendVerification() {
    if (!email || submitting) {
      return;
    }

    setSubmitting(true);
    setMessage("");
    setError("");

    try {
      const response = await api.post(
        "/auth/resend-verification",
        {
          email,
        },
        {
          skipAuth: true,
        },
      );

      const payload = unwrap(response);

      setMessage(
        payload?.message ||
          "Si la cuenta todavía necesita verificación, recibirás un nuevo enlace por email.",
      );
    } catch (requestError) {
      setError(
        getErrorMessage(
          requestError,
          "No se pudo reenviar el email de verificación.",
        ),
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthLayout
      title="Revisá tu correo"
      subtitle="Necesitamos verificar tu email antes de que puedas iniciar sesión"
    >
      <div className="space-y-6">
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
          <div className="flex items-start gap-3">
            <Icon
              name="mail"
              className="mt-0.5 shrink-0 text-emerald-600"
            />

            <div className="space-y-2">
              <p className="text-sm font-semibold text-emerald-900">
                Te enviamos un enlace de verificación.
              </p>

              {email && (
                <p className="text-sm text-emerald-800">
                  Revisá la casilla de{" "}
                  <strong>{email}</strong>.
                </p>
              )}

              <p className="text-sm text-emerald-800">
                Hacé clic en “Verificar mi email” para activar tu cuenta.
              </p>
            </div>
          </div>
        </div>

        <p className="text-sm leading-relaxed text-slate-600">
          El enlace vence en 24 horas. Si no encontrás el correo, revisá
          también la carpeta de spam o correo no deseado.
        </p>

        {message && (
          <div
            className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status"
          >
            {message}
          </div>
        )}

        {error && (
          <div
            className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            role="alert"
          >
            {error}
          </div>
        )}

        {email && (
          <Button
            type="button"
            onClick={resendVerification}
            disabled={submitting}
          >
            {submitting
              ? "REENVIANDO..."
              : "REENVIAR EMAIL"}
          </Button>
        )}

        <Link
          to="/login"
          className="block text-center text-sm font-semibold text-primary hover:underline"
        >
          Volver al inicio de sesión
        </Link>
      </div>
    </AuthLayout>
  );
}