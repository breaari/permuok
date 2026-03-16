import { Icon } from "../../icons/Index";
import { AdminDetailField } from "./AdminDetailField";


export default function AdminUserSection({
  fullName,
  detail,
  roleLabel,
  formatDate,
}) {
  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex items-center gap-3">
        <span className="text-primary">
          <Icon name="badge" size={24} />
        </span>
        <h2 className="text-xl font-bold text-slate-900">Datos del usuario</h2>
      </div>

      <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
        <AdminDetailField label="Nombre y apellido" value={fullName} strong />
        <AdminDetailField label="Rol de sistema" value={roleLabel(detail.role)} />
        <AdminDetailField label="Email" value={detail.email} />
        <AdminDetailField label="Teléfono de contacto" value={detail.phone} />
        <AdminDetailField label="Fecha de registro" value={formatDate(detail.created_at)} />
        <AdminDetailField label="Último acceso" value={formatDate(detail.last_login)} />
      </div>
    </section>
  );
}