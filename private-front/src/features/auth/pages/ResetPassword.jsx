import { useState } from "react";
import {
  Link,
  useNavigate,
  useSearchParams,
} from "react-router-dom";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Input from "../../../ui/components/Input";
import Button from "../../../ui/components/Button";
import { Icon } from "../../../ui/icons/Index";
import {
  api,
  clearTokens,
  getErrorMessage,
} from "../../../api/http.js";

export default function ResetPassword() {
  const [searchParams] =
    useSearchParams();

  const navigate = useNavigate();

  const token =
    searchParams.get("token") || "";

  const [password, setPassword] =
    useState("");
  const [confirmation, setConfirmation] =
    useState("");
  const [showPassword, setShowPassword] =
    useState(false);
  const [submitting, setSubmitting] =
    useState(false);
  const [error, setError] =
    useState("");

  const invalidToken =
    !/^[a-f0-9]{64}$/.test(token);

  async function onSubmit(event) {
    event.preventDefault();

    setError("");

    if (password.length < 8) {
      setError(
        "La contraseña debe tener al menos 8 caracteres.",
      );

      return;
    }

    if (password !== confirmation) {
      setError(
        "Las contraseñas no coinciden.",
      );

      return;
    }

    try {
      setSubmitting(true);

      await api.post(
        "/auth/reset-password",
        {
          token,
          password,
        },
        {
          skipAuth: true,
        },
      );

      /*
       * El cambio revoca todas las sesiones.
       * También eliminamos el access token local.
       */
      clearTokens();

      navigate("/login", {
        replace: true,
        state: {
          passwordReset: true,
        },
      });
    } catch (requestError) {
      setError(
        getErrorMessage(
          requestError,
          "No se pudo actualizar la contraseña.",
        ),
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (invalidToken) {
    return (
      <AuthLayout
        title="Enlace inválido"
        subtitle="El enlace de recuperación no es válido"
      >
        <div className="space-y-6">
          <div
            className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800"
            role="alert"
          >
            Solicitá un nuevo enlace para restablecer tu contraseña.
          </div>

          <Link
            to="/forgot-password"
            className="block text-center text-sm font-semibold text-primary hover:underline"
          >
            Solicitar otro enlace
          </Link>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title="Crear nueva contraseña"
      subtitle="Ingresá una contraseña de al menos 8 caracteres"
    >
      <form
        onSubmit={onSubmit}
        className="space-y-6"
      >
        <Input
          label="Nueva contraseña"
          value={password}
          onChange={setPassword}
          type={
            showPassword
              ? "text"
              : "password"
          }
          autoComplete="new-password"
          placeholder="••••••••"
          iconLeft={
            <Icon
              name="lock"
              className="opacity-80"
            />
          }
          rightSlot={
            <button
              type="button"
              className="text-slate-400 hover:text-slate-600"
              onClick={() =>
                setShowPassword(
                  (current) => !current,
                )
              }
              aria-label={
                showPassword
                  ? "Ocultar contraseña"
                  : "Mostrar contraseña"
              }
              disabled={submitting}
            >
              <Icon
                name={
                  showPassword
                    ? "eyeOff"
                    : "eye"
                }
              />
            </button>
          }
          required
          disabled={submitting}
        />

        <Input
          label="Repetir contraseña"
          value={confirmation}
          onChange={setConfirmation}
          type={
            showPassword
              ? "text"
              : "password"
          }
          autoComplete="new-password"
          placeholder="••••••••"
          iconLeft={
            <Icon
              name="lock"
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
            ? "ACTUALIZANDO..."
            : "ACTUALIZAR CONTRASEÑA"}
        </Button>

        <Link
          to="/login"
          className="block text-center text-sm font-semibold text-primary hover:underline"
        >
          Volver al inicio de sesión
        </Link>
      </form>
    </AuthLayout>
  );
}