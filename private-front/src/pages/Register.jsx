import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { getErrorMessage } from "../api/http";


export default function Register() {
  const nav = useNavigate();
  const { register } = useAuth();

  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e) {
    e.preventDefault();
    setErr("");
    setOk("");

    try {
      setSubmitting(true);

      await register({
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim().toLowerCase(),
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
    <div className="min-h-screen flex items-center justify-center p-6">
      <div className="w-full max-w-md rounded-2xl border p-6 shadow-sm">
        <h1 className="text-xl font-semibold">Crear cuenta</h1>

        {err && (
          <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
            {err}
          </div>
        )}
        {ok && (
          <div className="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm">
            {ok}
          </div>
        )}

        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <div>
            <label className="text-sm">Nombre</label>
            <input
              className="mt-1 w-full rounded-lg border p-2 disabled:opacity-60"
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              required
              disabled={submitting}
            />
          </div>

          <div>
            <label className="text-sm">Apellido</label>
            <input
              className="mt-1 w-full rounded-lg border p-2 disabled:opacity-60"
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
              required
              disabled={submitting}
            />
          </div>

          <div>
            <label className="text-sm">Email</label>
            <input
              className="mt-1 w-full rounded-lg border p-2 disabled:opacity-60"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="username"
              required
              disabled={submitting}
            />
          </div>

          <div>
            <label className="text-sm">Contraseña</label>
            <input
              className="mt-1 w-full rounded-lg border p-2 disabled:opacity-60"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="new-password"
              required
              minLength={6}
              disabled={submitting}
            />
          </div>

          <button
            className="w-full rounded-lg bg-black px-4 py-2 text-white disabled:opacity-60"
            type="submit"
            disabled={submitting}
          >
            {submitting ? "Creando..." : "Crear cuenta"}
          </button>
        </form>

        <div className="mt-4 text-sm">
          ¿Ya tenés cuenta?{" "}
          <Link className="underline" to="/login">
            Iniciar sesión
          </Link>
        </div>
      </div>
    </div>
  );
}