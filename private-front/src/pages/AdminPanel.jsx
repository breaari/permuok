import { useState } from "react";
import { useAuth } from "../auth/AuthContext";
import AdminRealEstates from "./AdminRealEstates";
export default function AdminPanel() {
  const { user, logout } = useAuth();
  const [section, setSection] = useState("real_estates");

  if (user?.role !== 1) return <div className="p-6">No autorizado</div>;

  if (loading) return null;

  const isAdmin = Number(user?.role) === 1;
  if (!isAdmin) return <Navigate to="/" replace />;

  return (
    <div className="min-h-screen flex">
      {/* Sidebar */}
      <aside className="w-64 border-r p-4 space-y-4 bg-gray-50">
        <div>
          <div className="text-lg font-semibold">Administrador</div>
          <div className="text-xs text-gray-600">{user.email}</div>
        </div>

        <nav className="space-y-2">
          <button
            className={`w-full text-left px-3 py-2 rounded-lg ${
              section === "real_estates" ? "bg-black text-white" : ""
            }`}
            onClick={() => setSection("real_estates")}
          >
            Inmobiliarias
          </button>

          {/* Futuras secciones */}
          {/* <button ...>Membresías</button> */}
          {/* <button ...>Pagos</button> */}
        </nav>

        <div className="pt-6">
          <button
            onClick={logout}
            className="w-full border rounded-lg px-3 py-2 text-sm"
          >
            Cerrar sesión
          </button>
        </div>
      </aside>

      {/* Contenido */}
      <main className="flex-1 p-6">
        {section === "real_estates" && <AdminRealEstates />}
      </main>
    </div>
  );
}
