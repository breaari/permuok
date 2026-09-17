import { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Input from "../../../ui/components/Input";
import Button from "../../../ui/components/Button";
import { Icon } from "../../../ui/icons/Index";
import { api, clearTokens, getErrorMessage } from "../../../api/http.js";

export default function ResetPassword() {
  const [searchParams] = useSearchParams();

  const navigate = useNavigate();

  const token = searchParams.get("token") || "";

  const [password, setPassword] = useState("");

  const [confirmation, setConfirmation] = useState("");

  const [showPassword, setShowPassword] = useState(false);

  const [submitting, setSubmitting] = useState(false);

  const [error, setError] = useState("");

  const invalidToken = !/^[a-f0-9]{64}$/.test(token);

  const passwordRules = {
    length: password.length >= 8 && password.length <= 72,

    uppercase: /[A-Z]/.test(password),

    lowercase: /[a-z]/.test(password),

    number: /[0-9]/.test(password),
  };

  const passwordIsValid = Object.values(passwordRules).every(Boolean);

  const hasConfirmation = confirmation.length > 0;

  const passwordsMatch = hasConfirmation && password === confirmation;

  const canSubmit =
    !invalidToken && passwordIsValid && passwordsMatch && !submitting;

  async function onSubmit(event) {
    event.preventDefault();

    setError("");

    if (!passwordIsValid) {
      setError("La contraseña no cumple todos los requisitos.");

      return;
    }

    if (!passwordsMatch) {
      setError("Las contraseñas no coinciden.");

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
       * El backend revoca todas las sesiones
       * existentes del usuario.
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
        getErrorMessage(requestError, "No se pudo actualizar la contraseña."),
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
            El enlace está incompleto o no es válido. Solicitá uno nuevo para
            restablecer tu contraseña.
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
      subtitle="Creá una contraseña segura para proteger tu cuenta"
    >
      <form onSubmit={onSubmit} className="space-y-6">
        <div className="space-y-3">
          <Input
            label="Nueva contraseña"
            value={password}
            onChange={(value) => {
              setPassword(value);
              setError("");
            }}
            type={showPassword ? "text" : "password"}
            autoComplete="new-password"
            placeholder="••••••••"
            iconLeft={<Icon name="lock" className="opacity-80" />}
            rightSlot={
              <button
                type="button"
                className="text-slate-400 hover:text-slate-600"
                onClick={() => setShowPassword((current) => !current)}
                aria-label={
                  showPassword ? "Ocultar contraseña" : "Mostrar contraseña"
                }
                disabled={submitting}
              >
                <Icon name={showPassword ? "eyeOff" : "eye"} />
              </button>
            }
            required
            disabled={submitting}
          />

          <div className="space-y-1 text-sm">
            <p
              className={
                passwordRules.length ? "text-emerald-600" : "text-slate-500"
              }
            >
              {passwordRules.length ? "✓" : "○"} Entre 8 y 72 caracteres
            </p>

            <p
              className={
                passwordRules.uppercase ? "text-emerald-600" : "text-slate-500"
              }
            >
              {passwordRules.uppercase ? "✓" : "○"} Una letra mayúscula
            </p>

            <p
              className={
                passwordRules.lowercase ? "text-emerald-600" : "text-slate-500"
              }
            >
              {passwordRules.lowercase ? "✓" : "○"} Una letra minúscula
            </p>

            <p
              className={
                passwordRules.number ? "text-emerald-600" : "text-slate-500"
              }
            >
              {passwordRules.number ? "✓" : "○"} Un número
            </p>
          </div>
        </div>

        <div className="space-y-2">
          <Input
            label="Repetir contraseña"
            value={confirmation}
            onChange={(value) => {
              setConfirmation(value);
              setError("");
            }}
            type={showPassword ? "text" : "password"}
            autoComplete="new-password"
            placeholder="••••••••"
            iconLeft={<Icon name="lock" className="opacity-80" />}
            required
            disabled={submitting}
          />

          {hasConfirmation && (
            <p
              className={
                passwordsMatch
                  ? "text-sm text-emerald-600"
                  : "text-sm text-red-600"
              }
              aria-live="polite"
            >
              {passwordsMatch
                ? "✓ Las contraseñas coinciden."
                : "Las contraseñas no coinciden."}
            </p>
          )}
        </div>

        {error && (
          <div
            className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            role="alert"
          >
            {error}
          </div>
        )}

        <Button type="submit" disabled={!canSubmit}>
          {submitting ? "ACTUALIZANDO..." : "ACTUALIZAR CONTRASEÑA"}
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
