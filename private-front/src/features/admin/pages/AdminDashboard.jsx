import { useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";

const STAT_CARDS = [
  {
    key: "active_real_estates",
    title: "Inmobiliarias activas",
    description: "Cuentas aprobadas y operativas",
    icon: "building2",
  },
  {
    key: "active_memberships",
    title: "Membresías activas",
    description: "Planes vigentes actualmente",
    icon: "badgeCheck",
  },
  {
    key: "active_publications",
    title: "Publicaciones activas",
    description: "Propiedades, búsquedas y desarrollos publicados",
    icon: "layoutGrid",
  },
  {
    key: "paused_publications",
    title: "Publicaciones pausadas",
    description: "Contenido oculto temporalmente",
    icon: "pause",
  },
  {
    key: "active_conversations",
    title: "Conversaciones activas",
    description: "Conversaciones creadas en la plataforma",
    icon: "messagesSquare",
  },
  {
    key: "pending_requests",
    title: "Solicitudes pendientes",
    description: "Inmobiliarias esperando revisión",
    icon: "clock",
  },
];

function StatCard({ item, value }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-bold text-slate-500">{item.title}</p>
          <p className="mt-2 text-3xl font-black tracking-tight text-slate-900">
            {Number(value || 0).toLocaleString("es-AR")}
          </p>
        </div>

        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
          <Icon name={item.icon} size={21} />
        </div>
      </div>

      <p className="mt-4 text-xs font-medium leading-relaxed text-slate-400">
        {item.description}
      </p>
    </div>
  );
}

export default function AdminDashboard() {
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [stats, setStats] = useState(null);

  const cards = useMemo(() => STAT_CARDS, []);

  useEffect(() => {
    async function loadStats() {
      try {
        setLoading(true);
        setErr("");
        setStats(null);

        const res = await api.get("/admin/dashboard/stats");
        const payload = unwrap(res);

        setStats(payload?.stats || {});
      } catch (error) {
        setStats(null);

        setErr(getErrorMessage(error, "No se pudieron cargar las métricas."));
      } finally {
        setLoading(false);
      }
    }

    loadStats();
  }, []);
  
  return (
    <div className="space-y-8">
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-xs font-black uppercase tracking-[0.18em] text-slate-400">
          Panel administrativo
        </p>

        <h1 className="mt-2 text-2xl font-black tracking-tight text-slate-900">
          Resumen general
        </h1>

        <p className="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
          Estado operativo de la plataforma: usuarios, membresías, publicaciones
          y actividad comercial.
        </p>
      </div>

      {err ? (
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">
          {err}
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm font-semibold text-slate-500 shadow-sm">
          Cargando métricas...
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          {cards.map((item) => (
            <StatCard key={item.key} item={item} value={stats?.[item.key]} />
          ))}
        </div>
      )}
    </div>
  );
}
