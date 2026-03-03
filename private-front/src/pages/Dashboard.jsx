import { useAuth } from "../auth/AuthContext";

export default function Dashboard() {
  const { user, access, logout } = useAuth();

  return (
    <div className="p-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">Dashboard</h1>
          <p className="mt-1 text-sm text-gray-600">
            Sesión activa: {user?.email}
          </p>
        </div>

        <button
          className="rounded-lg border px-4 py-2"
          onClick={logout}
        >
          Cerrar sesión
        </button>
      </div>

      <div className="mt-6 rounded-2xl border p-4">
        <h2 className="font-semibold">Access</h2>
        <pre className="mt-3 overflow-auto rounded-lg bg-gray-50 p-3 text-sm">
          {JSON.stringify(access, null, 2)}
        </pre>
      </div>
    </div>
  );
}