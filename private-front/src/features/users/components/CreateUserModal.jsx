import { useEffect, useMemo, useState } from "react";
import { Icon } from "../../../ui/icons/Index";
import PasswordRequirements, {
  isPasswordValid,
} from "../../auth/components/PasswordRequirements.jsx";

const ROLE_AGENT = 3;
const ROLE_INVESTOR = 4;

function getAvailableMessage(role, summary) {
  const agentsUsed = Number(summary?.agents_used ?? 0);
  const agentsLimit = Number(summary?.agents_limit ?? 0);
  const investorsUsed = Number(summary?.investors_used ?? 0);
  const investorsLimit = Number(summary?.investors_limit ?? 0);

  if (Number(role) === ROLE_AGENT) {
    return `${agentsUsed}/${agentsLimit} agentes en uso`;
  }

  if (Number(role) === ROLE_INVESTOR) {
    return `${investorsUsed}/${investorsLimit} inversores en uso`;
  }

  return "";
}

export function CreateUserModal({
  open,
  busy = false,
  summary,
  onClose,
  onSubmit,
}) {
  const [form, setForm] = useState({
    role: ROLE_AGENT,
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
  });
  const [localError, setLocalError] = useState("");

  useEffect(() => {
    if (!open) {
      setForm({
        role: ROLE_AGENT,
        first_name: "",
        last_name: "",
        email: "",
        phone: "",
        password: "",
        password_confirmation: "",
      });
      setLocalError("");
    }
  }, [open]);

  const availabilityText = useMemo(() => {
    return getAvailableMessage(form.role, summary);
  }, [form.role, summary]);

  const passwordIsValid = isPasswordValid(form.password);

  const passwordsMatch =
    form.password_confirmation.length > 0 &&
    form.password === form.password_confirmation;

  const canSubmit = passwordIsValid && passwordsMatch && !busy;

  if (!open) return null;

  async function handleSubmit(e) {
    e.preventDefault();

    setLocalError("");

    if (!passwordIsValid) {
      setLocalError("La contraseña no cumple todos los requisitos.");

      return;
    }

    if (!passwordsMatch) {
      setLocalError("Las contraseñas no coinciden.");

      return;
    }

    try {
      const { password_confirmation, ...payload } = form;

      await onSubmit({
        ...payload,
        role: Number(payload.role),
      });
    } catch (error) {
      setLocalError(error?.message || "No se pudo crear el usuario");
    }
  }

  function updateField(name, value) {
    setForm((prev) => ({ ...prev, [name]: value }));
  }

  return (
    <div className="fixed inset-0 z-50">
      <div
        className="absolute inset-0 bg-black/40"
        onClick={busy ? undefined : onClose}
      />

      <div className="absolute left-1/2 top-1/2 w-[92%] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white border border-slate-200 shadow-xl">
        <div className="p-5 border-b border-slate-100 flex items-start justify-between gap-4">
          <div>
            <h3 className="text-lg font-extrabold text-slate-900">
              Crear usuario
            </h3>
            <p className="text-sm text-slate-500 mt-1">
              Podés crear agentes o inversores para tu inmobiliaria.
            </p>
          </div>

          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 disabled:opacity-60"
            aria-label="Cerrar"
          >
            <Icon name="x" size={18} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          {localError && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
              {localError}
            </div>
          )}

          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">
              Tipo de usuario
            </label>
            <select
              value={form.role}
              onChange={(e) => updateField("role", Number(e.target.value))}
              className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary"
              disabled={busy}
            >
              <option value={ROLE_AGENT}>Agente</option>
              <option value={ROLE_INVESTOR}>Inversor</option>
            </select>
            <p className="mt-2 text-xs text-slate-500">{availabilityText}</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-bold text-slate-700 mb-2">
                Nombre
              </label>
              <input
                type="text"
                value={form.first_name}
                onChange={(e) => updateField("first_name", e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary"
                disabled={busy}
              />
            </div>

            <div>
              <label className="block text-sm font-bold text-slate-700 mb-2">
                Apellido
              </label>
              <input
                type="text"
                value={form.last_name}
                onChange={(e) => updateField("last_name", e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary"
                disabled={busy}
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">
              Email
            </label>
            <input
              type="email"
              value={form.email}
              onChange={(e) => updateField("email", e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary"
              disabled={busy}
            />
          </div>

          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">
              Teléfono
            </label>
            <input
              type="text"
              value={form.phone}
              onChange={(e) => updateField("phone", e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary"
              disabled={busy}
            />
          </div>

          <div>
            <label className="block text-sm font-bold text-slate-700 mb-2">
              Contraseña inicial
            </label>
            <input
              type="password"
              minLength={8}
              maxLength={72}
              autoComplete="new-password"
              value={form.password}
              onChange={(e) => updateField("password", e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary"
              disabled={busy}
            />

            <div>
              <label className="mb-2 block text-sm font-bold text-slate-700">
                Repetir contraseña
              </label>

              <input
                type="password"
                value={form.password_confirmation}
                onChange={(e) =>
                  updateField("password_confirmation", e.target.value)
                }
                minLength={8}
                maxLength={72}
                autoComplete="new-password"
                className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-primary"
                disabled={busy}
              />
            </div>

            <PasswordRequirements
              password={form.password}
              confirmation={form.password_confirmation}
              showMatch
            />
          </div>

          <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              disabled={busy}
              className="rounded-lg border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={!canSubmit}
              className="rounded-lg bg-primary px-4 py-3 text-sm font-bold text-white hover:bg-primary/90 disabled:opacity-60"
            >
              {busy ? "Creando..." : "Crear usuario"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
