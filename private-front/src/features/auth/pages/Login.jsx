// src/pages/Login.jsx
import { useEffect, useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { useAuth } from "../components/AuthContext";
import { getErrorMessage } from "../../../api/http.js";
import { useToast } from "../../../ui/toast/ToastProvider";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Input from "../../../ui/components/Input";
import Button from "../../../ui/components/Button";
import { Icon } from "../../../ui/icons/Index";

function normalizeEmail(value) {
  return String(value || "")
    .trim()
    .toLowerCase();
}

function formatRemainingTime(totalSeconds) {
  const seconds = Math.max(0, Math.ceil(Number(totalSeconds) || 0));

  if (seconds < 60) {
    return `${seconds} ${seconds === 1 ? "segundo" : "segundos"}`;
  }

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;

  if (remainingSeconds === 0) {
    return `${minutes} ${minutes === 1 ? "minuto" : "minutos"}`;
  }

  return `${minutes}:${
    remainingSeconds < 10 ? "0" : ""
  }${remainingSeconds} min`;
}

export default function Login() {
  const { login } = useAuth();
  const nav = useNavigate();
  const toast = useToast();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPass, setShowPass] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [blockedSeconds, setBlockedSeconds] = useState(0);

  const [blockedEmail, setBlockedEmail] = useState("");

  const currentEmail = normalizeEmail(email);

  const isBlocked = blockedSeconds > 0 && blockedEmail === currentEmail;

  useEffect(() => {
    if (blockedSeconds <= 0) {
      return undefined;
    }

    const intervalId = window.setInterval(() => {
      setBlockedSeconds((current) => Math.max(0, current - 1));
    }, 1000);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [blockedSeconds > 0]);

  useEffect(() => {
    if (blockedSeconds !== 0) {
      return;
    }

    setBlockedEmail("");
  }, [blockedSeconds]);

  async function onSubmit(e) {
    e.preventDefault();

    if (isBlocked) {
      return;
    }

    try {
      setSubmitting(true);

      await login(currentEmail, password);

      toast.success("Sesión iniciada correctamente.");

      nav("/gate", {
        replace: true,
      });
    } catch (error) {
      const message = getErrorMessage(error, "No se pudo iniciar sesión");

      const errorDetails =
        error?.data?.errors && typeof error.data.errors === "object"
          ? error.data.errors
          : {};

      const errorCode = errorDetails.code || null;

      const retryAfter = Math.max(0, Number(errorDetails.retry_after) || 0);

      if (errorCode === "RATE_LIMIT_EXCEEDED" && retryAfter > 0) {
        setBlockedEmail(currentEmail);
        setBlockedSeconds(Math.ceil(retryAfter));

        return;
      }

      if (String(message).toLowerCase().includes("suspendido")) {
        toast.error(
          "Cuenta suspendida. El acceso de esta inmobiliaria fue suspendido por el administrador.",
        );

        return;
      }

      if (String(message).toLowerCase().includes("desactivado")) {
        toast.error(
          "Usuario desactivado. Esta cuenta no tiene acceso habilitado a PermuOK.",
        );

        return;
      }

      toast.error(message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthLayout
      title="Bienvenido de nuevo"
      subtitle="Ingresá tus credenciales para acceder"
    >
      <form onSubmit={onSubmit} className="space-y-6">
        <Input
          label="Email"
          value={email}
          onChange={setEmail}
          type="email"
          placeholder="ejemplo@correo.com"
          autoComplete="email"
          iconLeft={<Icon name="mail" className="opacity-80" />}
          required
          disabled={submitting}
        />

        <div>
          <div className="flex items-center justify-between mb-1">
            <label className="block text-sm font-medium text-slate-700">
              Contraseña
            </label>

            <button
              type="button"
              className="text-xs font-semibold text-primary hover:underline"
              onClick={() =>
                toast.info("Función pendiente: recuperación de contraseña")
              }
              disabled={submitting}
            >
              ¿Olvidaste tu contraseña?
            </button>
          </div>

          <Input
            label=""
            value={password}
            onChange={setPassword}
            type={showPass ? "text" : "password"}
            placeholder="••••••••"
            autoComplete="current-password"
            iconLeft={<Icon name="lock" className="opacity-80" />}
            rightSlot={
              <button
                type="button"
                className="text-slate-400 hover:text-slate-600"
                onClick={() => setShowPass((current) => !current)}
                tabIndex={-1}
                aria-label={
                  showPass ? "Ocultar contraseña" : "Mostrar contraseña"
                }
                disabled={submitting}
              >
                <Icon name={showPass ? "eyeOff" : "eye"} />
              </button>
            }
            required
            disabled={submitting}
          />
        </div>

        {isBlocked && (
          <div
            className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
            role="alert"
            aria-live="polite"
          >
            <div className="flex items-start gap-3">
              <Icon name="clock" className="mt-0.5 text-amber-600" />

              <div>
                <p className="text-sm font-semibold text-amber-900">
                  Esperá un momento antes de volver a intentar
                </p>

                <p className="mt-1 text-sm text-amber-800">
                  Podrás probar nuevamente en{" "}
                  <strong>{formatRemainingTime(blockedSeconds)}</strong>.
                </p>
              </div>
            </div>
          </div>
        )}

        {!isBlocked && (
          <Button type="submit" disabled={submitting || isBlocked}>
            {submitting ? "Ingresando..." : "INICIAR SESIÓN"}
          </Button>
        )}
      </form>

      <div className="mt-10 pt-6 border-t border-slate-100 text-center">
        <p className="text-sm text-slate-600">
          ¿No tenés una cuenta?
          <Link
            className="font-bold text-primary hover:underline ml-1"
            to="/register"
          >
            Registrate ahora
          </Link>
        </p>
      </div>
    </AuthLayout>
  );
}
