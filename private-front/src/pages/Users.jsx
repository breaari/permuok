import { useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../api/http";
import { UsersSummaryCards } from "../ui/users/UsersSummaryCards";
import { UsersList } from "../ui/users/UsersList";
import { CreateUserModal } from "../ui/users/CreateUserModal";
import { useAuth } from "../auth/AuthContext";

function getRoleLabel(role) {
  if (Number(role) === 3) return "Agente";
  if (Number(role) === 4) return "Inversor";
  return "Usuario";
}

export default function Users() {
  const [loading, setLoading] = useState(true);
  const [items, setItems] = useState([]);
  const [summary, setSummary] = useState({
    agents_used: 0,
    agents_limit: 0,
    investors_used: 0,
    investors_limit: 0,
  });
  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [updatingUserId, setUpdatingUserId] = useState(null);

  const { user } = useAuth();

  if (Number(user?.role) !== 2) {
    return <Navigate to="/app" replace />;
  }

  async function loadUsers() {
    setErr("");
    setLoading(true);

    try {
      const res = await api.get("/users");
      const data = unwrap(res);

      setItems(Array.isArray(data?.items) ? data.items : []);
      setSummary(
        data?.summary ?? {
          agents_used: 0,
          agents_limit: 0,
          investors_used: 0,
          investors_limit: 0,
        },
      );
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudieron cargar los usuarios"));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadUsers();
  }, []);

  async function handleCreateUser(payload) {
    setErr("");
    setOk("");
    setCreating(true);

    try {
      const res = await api.post("/users", payload);
      const data = unwrap(res);

      setOk(`${getRoleLabel(payload.role)} creado correctamente.`);
      setModalOpen(false);
      await loadUsers();

      return data;
    } catch (e) {
      throw new Error(getErrorMessage(e, "No se pudo crear el usuario"));
    } finally {
      setCreating(false);
    }
  }

  async function handleToggleStatus(user) {
    setErr("");
    setOk("");
    setUpdatingUserId(user.id);

    try {
      await api.patch("/users/status", {
        user_id: user.id,
        is_active: Number(user.is_active) === 1 ? 0 : 1,
      });

      setOk(
        Number(user.is_active) === 1
          ? "Usuario desactivado correctamente."
          : "Usuario activado correctamente.",
      );

      await loadUsers();
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo actualizar el estado del usuario"));
    } finally {
      setUpdatingUserId(null);
    }
  }

  const usersSorted = useMemo(() => {
    return [...items].sort((a, b) => Number(b.id) - Number(a.id));
  }, [items]);

  return (
    <>
      <div className="bg-background-light min-h-[calc(100vh-64px)]">
        <main className="max-w-7xl mx-auto w-full px-4 md:px-6 py-8 pb-20 space-y-8">
          <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div className="space-y-2 text-left">
              <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
                Mis usuarios
              </h1>
              <p className="text-slate-500 max-w-2xl text-base text-left">
                Administrá los agentes e inversores de tu inmobiliaria según los
                cupos de tu membresía.
              </p>
            </div>

            <button
              type="button"
              onClick={() => setModalOpen(true)}
              className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-primary text-white font-bold hover:bg-primary/90 transition-colors"
            >
              <span>+</span>
              Crear usuario
            </button>
          </div>

          {err && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
              {err}
            </div>
          )}

          {ok && (
            <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
              {ok}
            </div>
          )}

          <UsersSummaryCards summary={summary} />

          <UsersList
            loading={loading}
            items={usersSorted}
            updatingUserId={updatingUserId}
            onToggleStatus={handleToggleStatus}
          />
        </main>
      </div>

      <CreateUserModal
        open={modalOpen}
        busy={creating}
        summary={summary}
        onClose={() => {
          if (!creating) setModalOpen(false);
        }}
        onSubmit={handleCreateUser}
      />
    </>
  );
}
