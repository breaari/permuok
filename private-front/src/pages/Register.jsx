// src/pages/Register.jsx
import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { getErrorMessage } from "../api/http";

import AuthLayout from "../layout/AuthLayout";
import Input from "../ui/components/Input";
import Button from "../ui/components/Button";
import PhoneField from "../ui/components/PhoneField";
import { Icon } from "../ui/icons/Index";
import { isValidPhoneNumber } from "react-phone-number-input";

export default function Register() {
  const nav = useNavigate();
  const { register } = useAuth();

  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");

  // ✅ ahora guardamos el teléfono como E.164: +549...
  const [phone, setPhone] = useState("");

  const [password, setPassword] = useState("");
  const [showPass, setShowPass] = useState(false);

  const [terms, setTerms] = useState(false);

  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e) {
    e.preventDefault();
    setErr("");
    setOk("");

    if (!terms) {
      setErr("Debés aceptar los términos para registrarte.");
      return;
    }

    if (!phone) {
      setErr("Ingresá un número de teléfono.");
      return;
    }

    if (!isValidPhoneNumber(phone)) {
      setErr("El número de teléfono no es válido.");
      return;
    }

    try {
      setSubmitting(true);

      await register({
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim().toLowerCase(),
        phone, // ✅ ya viene con prefijo + bandera
        password,
      });

      setOk("Cuenta creada. Ahora iniciá sesión.");
      setTimeout(() => nav("/login", { replace: true }), 700);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo crear la cuenta"));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthLayout title="Creá tu cuenta" subtitle="Empezá tu camino en el intercambio inmobiliario">
      {err && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
          {err}
        </div>
      )}
      {ok && (
        <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
          {ok}
        </div>
      )}

      <form onSubmit={onSubmit} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

        {/* ✅ Teléfono profesional con banderas */}
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
              aria-label={showPass ? "Ocultar contraseña" : "Mostrar contraseña"}
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
              className="text-primary font-semibold hover:underline"
              onClick={() => setErr("Pendiente: página de Términos")}
            >
              Términos de Servicio
            </button>{" "}
            y la{" "}
            <button
              type="button"
              className="text-primary font-semibold hover:underline"
              onClick={() => setErr("Pendiente: página de Privacidad")}
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

      <div className="mt-8 pt-6 border-t border-slate-100 text-center">
        <p className="text-sm text-slate-600">
          ¿Ya tenés una cuenta?
          <Link className="font-bold text-primary hover:underline ml-1" to="/login">
            Iniciá sesión
          </Link>
        </p>
      </div>
    </AuthLayout>
  );
}