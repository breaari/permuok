import { useMemo } from "react";
import { useAuth } from "../auth/AuthContext";

function roleLabel(role) {
  if (Number(role) === 3) return "Agente";
  if (Number(role) === 4) return "Inversor";
  return "Usuario";
}

function Field({ label, value }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
        {label}
      </p>
      <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        {value || "—"}
      </div>
    </div>
  );
}

export default function MyUserProfile() {
  const { user, access } = useAuth();

  const role = Number(user?.role || 0);
  const roleName = useMemo(() => roleLabel(role), [role]);
  const realEstate = access?.real_estate || user?.real_estate || null;

  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <main className="flex-1 max-w-5xl mx-auto w-full px-4 md:px-6 py-8 pb-20 space-y-8">
        <div className="rounded-xl border border-sky-200 bg-sky-50 p-4 md:p-5 flex items-start gap-3">
          <span className="text-sky-600 mt-0.5">ℹ</span>

          <div>
            <p className="text-slate-900 font-bold text-base">Mi perfil</p>
            <p className="text-sm text-slate-700">
              Estás viendo tu información personal y los datos de la inmobiliaria
              asociada en modo solo lectura.
            </p>
          </div>
        </div>

        <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-100">
            <h1 className="text-2xl md:text-3xl font-black tracking-tight text-slate-900">
              Mis datos
            </h1>
            <p className="text-slate-500 text-sm mt-1">
              Información de tu cuenta dentro de la plataforma.
            </p>
          </div>

          <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Rol" value={roleName} />
            <Field label="Email" value={user?.email} />
            <Field label="Nombre" value={user?.first_name} />
            <Field label="Apellido" value={user?.last_name} />
            <Field label="Teléfono" value={user?.phone} />
          </div>
        </section>

        <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-100">
            <h2 className="text-xl font-bold text-slate-900">
              Datos de la inmobiliaria
            </h2>
            <p className="text-slate-500 text-sm mt-1">
              Esta información es solo de consulta.
            </p>
          </div>

          {!realEstate ? (
            <div className="p-6 text-sm text-slate-500">
              No se encontraron datos de la inmobiliaria asociada.
            </div>
          ) : (
            <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Nombre comercial" value={realEstate?.name} />
              <Field label="Razón social" value={realEstate?.legal_name} />
              <Field label="CUIT" value={realEstate?.cuit} />
              <Field label="Email" value={realEstate?.email} />
              <Field label="Teléfono" value={realEstate?.phone} />
              <Field label="Sitio web" value={realEstate?.website} />
              <div className="md:col-span-2">
                <Field label="Dirección" value={realEstate?.address} />
              </div>
            </div>
          )}
        </section>
      </main>
    </div>
  );
}