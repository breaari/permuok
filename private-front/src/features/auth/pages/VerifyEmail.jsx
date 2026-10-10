import { useEffect, useRef, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import { Icon } from "../../../ui/icons/Index";
import {
  api,
  getErrorMessage,
  unwrap,
} from "../../../api/http.js";

export default function VerifyEmail() {
  const [searchParams] = useSearchParams();

  const token =
    String(searchParams.get("token") || "").trim();

  const requestedRef = useRef(false);

  const [status, setStatus] = useState(
    /^[a-f0-9]{64}$/.test(token)
      ? "loading"
      : "error",
  );

  const [message, setMessage] = useState(
    /^[a-f0-9]{64}$/.test(token)
      ? ""
      : "El enlace de verificación no es válido.",
  );

  useEffect(() => {
    if (
      !/^[a-f0-9]{64}$/.test(token) ||
      requestedRef.current
    ) {
      return;
    }

    requestedRef.current = true;

    async function verifyEmail() {
      try {
        const response = await api.post(
          "/auth/verify-email",
          {
            token,
          },
          {
            skipAuth: true,
          },
        );

        const payload = unwrap(response);

        setStatus("success");

        setMessage(
          payload?.message ||
            "Tu email fue verificado correctamente.",
        );
      } catch (requestError) {
        setStatus("error");

        setMessage(
          getErrorMessage(
            requestError,
            "No se pudo verificar el email.",
          ),
        );
      }
    }

    verifyEmail();
  }, [token]);

  if (status === "loading") {
    return (
      <AuthLayout
        title="Verificando tu email"
        subtitle="Esperá unos segundos mientras confirmamos tu cuenta"
      >
        <div className="py-6 text-center">
          <div className="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-primary" />

          <p className="text-sm text-slate-600">
            Estamos verificando tu dirección de correo.
          </p>
        </div>
      </AuthLayout>
    );
  }

  if (status === "success") {
    return (
      <AuthLayout
        title="Email verificado"
        subtitle="Tu cuenta ya está lista para continuar"
      >
        <div className="space-y-6">
          <div
            className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4"
            role="status"
          >
            <div className="flex items-start gap-3">
              <Icon
                name="check"
                className="mt-0.5 shrink-0 text-emerald-600"
              />

              <div>
                <p className="text-sm font-semibold text-emerald-900">
                  Verificación completada
                </p>

                <p className="mt-1 text-sm text-emerald-800">
                  {message}
                </p>
              </div>
            </div>
          </div>

          <Link
            to="/login"
            className="block rounded-lg bg-primary px-4 py-3 text-center text-sm font-semibold text-white transition hover:opacity-90"
          >
            INICIAR SESIÓN
          </Link>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title="No pudimos verificar el email"
      subtitle="El enlace puede haber vencido o ya haber sido utilizado"
    >
      <div className="space-y-6">
        <div
          className="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
          role="alert"
        >
          <div className="flex items-start gap-3">
            <Icon
              name="clock"
              className="mt-0.5 shrink-0 text-amber-600"
            />

            <p className="text-sm text-amber-800">
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
    </AuthLayout>
  );
}