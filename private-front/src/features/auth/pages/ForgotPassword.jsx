import { useEffect, useState } from "react";

import { Link } from "react-router-dom";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Input from "../../../ui/components/Input";
import Button from "../../../ui/components/Button";
import { Icon } from "../../../ui/icons/Index";

import { api, getErrorMessage, unwrap } from "../../../api/http.js";

function formatRemainingTime(totalSeconds) {
  const seconds = Math.max(0, Math.ceil(Number(totalSeconds) || 0));

  const minutes = Math.floor(seconds / 60);

  const remainingSeconds = seconds % 60;

  if (minutes === 0) {
    return `${remainingSeconds} s`;
  }

  if (remainingSeconds === 0) {
    return `${minutes} min`;
  }

  return `${minutes} min ${remainingSeconds} s`;
}

export default function ForgotPassword() {
  const [email, setEmail] = useState("");

  const [submitting, setSubmitting] = useState(false);

  const [message, setMessage] = useState("");

  const [error, setError] = useState("");

  const [blockedSeconds, setBlockedSeconds] = useState(0);

  const isBlocked = blockedSeconds > 0;

  useEffect(() => {
    if (!isBlocked) {
      return undefined;
    }

    const intervalId = window.setInterval(() => {
      setBlockedSeconds((current) => Math.max(0, current - 1));
    }, 1000);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [isBlocked]);

  async function onSubmit(event) {
    event.preventDefault();

    if (submitting || isBlocked) {
      return;
    }

    setSubmitting(true);
    setMessage("");
    setError("");

    try {
      const response = await api.post(
        "/auth/forgot-password",
        {
          email: email.trim().toLowerCase(),
        },
        {
          skipAuth: true,
        },
      );

      const data = unwrap(response);

      setMessage(
        data?.message ||
          "Si hay una cuenta asociada a esa dirección, recibirás un enlace para restablecer tu contraseña.",
      );
    } catch (requestError) {
      const errorDetails =
        requestError?.data?.errors &&
        typeof requestError.data.errors === "object"
          ? requestError.data.errors
          : {};

      const errorCode = errorDetails.code || null;

      const retryAfter = Math.max(0, Number(errorDetails.retry_after) || 0);

      if (errorCode === "RATE_LIMIT_EXCEEDED" && retryAfter > 0) {
        setBlockedSeconds(Math.ceil(retryAfter));

        return;
      }

      setError(
        getErrorMessage(
          requestError,
          "No se pudo procesar la solicitud. Intentá nuevamente.",
        ),
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthLayout
      title={message ? "Revisá tu correo" : "Recuperar contraseña"}
      subtitle={
        message
          ? "Te enviamos las instrucciones para continuar"
          : "Ingresá el email asociado a tu cuenta"
      }
    >
      {message ? (
        <div className="space-y-6">
          <div
            className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4"
            role="status"
          >
            <div className="flex items-start gap-3">
              <Icon name="check" className="mt-0.5 shrink-0 text-emerald-600" />

              <div className="space-y-2">
                <p className="text-sm font-medium text-emerald-900">
                  Si hay una cuenta asociada a esa dirección, vas a recibir un
                  enlace para restablecer tu contraseña.
                </p>

                <p className="text-sm text-emerald-800">
                  ¿No lo encontrás? Revisá la carpeta de correo no deseado o
                  volvé a intentarlo en unos minutos.
                </p>
              </div>
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
        <form onSubmit={onSubmit} className="space-y-6">
          <Input
            label="Email"
            value={email}
            onChange={(value) => {
              setEmail(value);
              setError("");
            }}
            type="email"
            placeholder="ejemplo@correo.com"
            autoComplete="email"
            iconLeft={<Icon name="mail" className="opacity-80" />}
            required
            disabled={submitting || isBlocked}
          />

          {isBlocked && (
            <div
              className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4"
              role="alert"
              aria-live="polite"
            >
              <div className="flex items-start gap-3">
                <Icon name="clock" className="mt-0.5 shrink-0 text-amber-600" />

                <div>
                  <p className="text-sm font-semibold text-amber-900">
                    Demasiadas solicitudes
                  </p>

                  <p className="mt-1 text-sm text-amber-800">
                    Para proteger tu cuenta, esperá antes de solicitar otro
                    enlace.
                  </p>

                  <p className="mt-2 text-sm font-semibold text-amber-900">
                    Podrás volver a intentarlo en{" "}
                    {formatRemainingTime(blockedSeconds)}
                  </p>
                </div>
              </div>
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

          <Button type="submit" disabled={submitting || isBlocked}>
            {submitting
              ? "ENVIANDO..."
              : isBlocked
                ? `ESPERÁ ${formatRemainingTime(blockedSeconds)}`
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
