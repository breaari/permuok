import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { getErrorMessage } from "../api/http";

export default function Login() {
  const { login } = useAuth();
  const nav = useNavigate();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [err, setErr] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(e) {
    e.preventDefault();
    setErr("");

    try {
      setSubmitting(true);
      await login(email.trim().toLowerCase(), password);
      nav("/", { replace: true });
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo iniciar sesión"));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-6">
      <div className="w-full max-w-md rounded-2xl border p-6 shadow-sm">
        <h1 className="text-xl font-semibold">Ingresar</h1>

        {err && (
          <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
            {err}
          </div>
        )}

        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <div>
            <label className="text-sm">Email</label>
            <input
              className="mt-1 w-full rounded-lg border p-2 disabled:opacity-60"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="username"
              disabled={submitting}
            />
          </div>

          <div>
            <label className="text-sm">Contraseña</label>
            <input
              className="mt-1 w-full rounded-lg border p-2 disabled:opacity-60"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              disabled={submitting}
            />
          </div>

          <button
            className="w-full rounded-lg bg-black px-4 py-2 text-white disabled:opacity-60"
            type="submit"
            disabled={submitting}
          >
            {submitting ? "Ingresando..." : "Entrar"}
          </button>
        </form>

        <div className="mt-4 text-sm">
          ¿No tenés cuenta?{" "}
          <Link className="underline" to="/register">
            Crear cuenta
          </Link>
        </div>
      </div>
    </div>
  );
}