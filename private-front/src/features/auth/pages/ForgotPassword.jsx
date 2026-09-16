import { useState } from "react";
import { Link } from "react-router-dom";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Input from "../../../ui/components/Input";
import Button from "../../../ui/components/Button";
import { Icon } from "../../../ui/icons/Index";
import {
  api,
  getErrorMessage,
  unwrap,
} from "../../../api/http.js";

export default function ForgotPassword() {
  const [email, setEmail] = useState("");
  const [submitting, setSubmitting] =
    useState(false);
  const [message, setMessage] =
    useState("");
  const [error, setError] =
    useState("");

  async function onSubmit(event) {
    event.preventDefault();

    setSubmitting(true);
    setMessage("");
    setError("");

    try {
      const response = await api.post(
        "/auth/forgot-password",
        {
          email: email
            .trim()
            .toLowerCase(),
        },
        {
          skipAuth: true,
        },
      );

      const data = unwrap(response);

      setMessage(
        data?.message ||
          "Si el email corresponde a una cuenta activa, recibirás las instrucciones.",
      );
    } catch (requestError) {
      setError(
        getErrorMessage(
          requestError,
          "No se pudo procesar la solicitud.",
        ),
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthLayout
      title="Recuperar contraseña"
      subtitle="Te enviaremos un enlace para crear una nueva contraseña"
    >
      {message ? (
        <div className="space-y-6">
          <div
            className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4"
            role="status"
          >
            <div className="flex items-start gap-3">
              <Icon
                name="check"
                className="mt-0.5 text-emerald-600"
              />

              <p className="text-sm text-emerald-800">
                {message}
              </p>
            </div>
          </div>

          <Link
            to="/login"
            className="block text-center text-sm font-semibold text-primary hover:underline"
          >
            Volver al inicio de sesión
          </Link>
        </div>
      ) : (
        <form
          onSubmit={onSubmit}
          className="space-y-6"
        >
          <Input
            label="Email"
            value={email}
            onChange={setEmail}
            type="email"
            placeholder="ejemplo@correo.com"
            autoComplete="email"
            iconLeft={
              <Icon
                name="mail"
                className="opacity-80"
              />
            }
            required
            disabled={submitting}
          />

          {error && (
            <div
              className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
              role="alert"
            >
              {error}
            </div>
          )}

          <Button
            type="submit"
            disabled={submitting}
          >
            {submitting
              ? "ENVIANDO..."
              : "ENVIAR INSTRUCCIONES"}
          </Button>

          <Link
            to="/login"
            className="block text-center text-sm font-semibold text-primary hover:underline"
          >
            Volver al inicio de sesión
          </Link>
        </form>
      )}
    </AuthLayout>
  );
}