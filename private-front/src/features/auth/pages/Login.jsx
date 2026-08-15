// src/pages/Login.jsx
import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { useAuth } from "../components/AuthContext";
import { getErrorMessage } from "../../../api/http.js";
import { useToast } from "../../../ui/toast/ToastProvider";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Input from "../../../ui/components/Input";
import Button from "../../../ui/components/Button";
import { Icon } from "../../../ui/icons/Index";

export default function Login() {
  const { login } = useAuth();
  const nav = useNavigate();
  const toast = useToast();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPass, setShowPass] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e) {
    e.preventDefault();

    try {
      setSubmitting(true);

      await login(email.trim().toLowerCase(), password);

      toast.success("Sesión iniciada correctamente.");
      nav("/gate", { replace: true });
    } catch (e) {
      toast.error(getErrorMessage(e, "No se pudo iniciar sesión"));
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
                onClick={() => setShowPass((s) => !s)}
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

        <Button type="submit" disabled={submitting}>
          {submitting ? "Ingresando..." : "INICIAR SESIÓN"}
        </Button>
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
