// src/pages/Register.jsx
import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { useAuth } from "../../auth/components/AuthContext";
import { getErrorMessage } from "../../../api/http.js";
import { useToast } from "../../../ui/toast/ToastProvider";

import AuthLayout from "../../../layout/AuthLayout.jsx";
import Input from "../../../ui/components/Input.jsx";
import Button from "../../../ui/components/Button";
import PhoneField from "../../../ui/components/PhoneField";
import { Icon } from "../../../ui/icons/Index";
import { isValidPhoneNumber } from "react-phone-number-input";

export default function Register() {
  const nav = useNavigate();
  const { register } = useAuth();
  const toast = useToast();

  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [showPass, setShowPass] = useState(false);
  const [terms, setTerms] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e) {
    e.preventDefault();

    if (!terms) {
      toast.error("Debés aceptar los términos para registrarte.");
      return;
    }

    if (!phone) {
      toast.error("Ingresá un número de teléfono.");
      return;
    }

    if (!isValidPhoneNumber(phone)) {
      toast.error("El número de teléfono no es válido.");
      return;
    }

    try {
      setSubmitting(true);

      await register({
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim().toLowerCase(),
        phone,
        password,
      });

      toast.success("Cuenta creada correctamente. Ahora iniciá sesión.");

      setTimeout(() => {
        nav("/login", { replace: true });
      }, 700);
    } catch (e) {
      toast.error(getErrorMessage(e, "No se pudo crear la cuenta"));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthLayout
      title="Creá tu cuenta"
      subtitle="Empezá tu camino en el intercambio inmobiliario"
    >
      <form onSubmit={onSubmit} className="space-y-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            label="Nombre"
            value={firstName}
            onChange={setFirstName}
            placeholder="Nombre"
            iconLeft={<Icon name="user" className="opacity-80" />}
            required
            disabled={submitting}
          />

          <Input
            label="Apellido"
            value={lastName}
            onChange={setLastName}
            placeholder="Apellido"
            iconLeft={<Icon name="badge" className="opacity-80" />}
            required
            disabled={submitting}
          />
        </div>

        <Input
          label="Email"
          value={email}
          onChange={setEmail}
          type="email"
          placeholder="ejemplo@correo.com"
          autoComplete="username"
          iconLeft={<Icon name="mail" className="opacity-80" />}
          required
          disabled={submitting}
        />

        <PhoneField
          label="Teléfono"
          value={phone}
          onChange={setPhone}
          defaultCountry="AR"
          required
          disabled={submitting}
          helperText="Elegí tu país y escribí el número sin el 0 inicial."
          variant="auth"
        />

        <Input
          label="Contraseña"
          value={password}
          onChange={setPassword}
          type={showPass ? "text" : "password"}
          placeholder="••••••••"
          autoComplete="new-password"
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
            >
              <Icon name={showPass ? "eyeOff" : "eye"} />
            </button>
          }
          required
          minLength={6}
          disabled={submitting}
        />

        <label className="flex items-start gap-2 text-xs text-slate-600">
          <input
            type="checkbox"
            className="mt-1 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
            checked={terms}
            onChange={(e) => setTerms(e.target.checked)}
            disabled={submitting}
          />

          <span>
            Acepto los{" "}
            <button
              type="button"
              className="font-semibold text-primary hover:underline"
              onClick={() =>
                toast.info("Pendiente: página de Términos de Servicio")
              }
            >
              Términos de Servicio
            </button>{" "}
            y la{" "}
            <button
              type="button"
              className="font-semibold text-primary hover:underline"
              onClick={() =>
                toast.info("Pendiente: página de Política de Privacidad")
              }
            >
              Política de Privacidad
            </button>
            .
          </span>
        </label>

        <Button type="submit" disabled={submitting}>
          {submitting ? "Creando..." : "REGISTRARSE"}
        </Button>
      </form>

      <div className="mt-8 border-t border-slate-100 pt-6 text-center">
        <p className="text-sm text-slate-600">
          ¿Ya tenés una cuenta?
          <Link
            className="ml-1 font-bold text-primary hover:underline"
            to="/login"
          >
            Iniciá sesión
          </Link>
        </p>
      </div>
    </AuthLayout>
  );
}
