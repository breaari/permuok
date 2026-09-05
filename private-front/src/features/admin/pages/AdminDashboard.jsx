import { useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";
import { useNavigate } from "react-router-dom";

const BUSINESS_CARDS = [
  {
    key: "active_real_estates",
    title: "Inmobiliarias",
    description: "Cuentas registradas en la plataforma",
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
    description: "Contenido temporalmente fuera de circulación",
    icon: "pause",
  },
  {
    key: "active_conversations",
    title: "Conversaciones",
    description: "Conversaciones creadas en la plataforma",
    icon: "messagesSquare",
  },
  {
    key: "pending_requests",
    title: "Altas pendientes",
    description: "Inmobiliarias esperando revisión",
    icon: "clock",
  },
];

function formatNumber(value) {
  return Number(value || 0).toLocaleString("es-AR");
}

function formatPercent(value) {
  return `${Number(value || 0).toLocaleString("es-AR", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 1,
  })}%`;
}

function formatUsd(value) {
  const amount = Number(value || 0);

  if (amount === 0) {
    return "US$ 0";
  }

  if (amount < 0.01) {
    return `US$ ${amount.toLocaleString("en-US", {
      minimumFractionDigits: 4,
      maximumFractionDigits: 8,
    })}`;
  }

  return `US$ ${amount.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  })}`;
}

function StatCard({
  title,
  value,
  description,
  icon,
  alert = false,
  onClick = null,
}) {
  return (
    <div
      onClick={onClick || undefined}
      className={[
        "rounded-2xl border bg-white p-5 shadow-sm",
        alert ? "border-rose-200" : "border-slate-200",
        onClick
          ? "cursor-pointer transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
          : "",
      ].join(" ")}
    >
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-bold text-slate-500">{title}</p>

          <p
            className={[
              "mt-2 text-3xl font-black tracking-tight",
              alert ? "text-rose-700" : "text-slate-900",
            ].join(" ")}
          >
            {value}
          </p>
        </div>

        <div
          className={[
            "flex h-11 w-11 items-center justify-center rounded-xl",
            alert ? "bg-rose-50 text-rose-700" : "bg-slate-100 text-slate-700",
          ].join(" ")}
        >
          <Icon name={icon} size={21} />
        </div>
      </div>

      {description ? (
        <p className="mt-4 text-xs font-medium leading-relaxed text-slate-400">
          {description}
        </p>
      ) : null}
    </div>
  );
}

function SectionHeader({ title, description }) {
  return (
    <div>
      <h2 className="text-lg font-black tracking-tight text-slate-900">
        {title}
      </h2>

      {description ? (
        <p className="mt-1 text-sm font-medium text-slate-500">{description}</p>
      ) : null}
    </div>
  );
}

export default function AdminDashboard() {
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [stats, setStats] = useState(null);

  const navigate = useNavigate();

  const businessCards = useMemo(() => BUSINESS_CARDS, []);

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

  const ai = stats?.ai || {};

  const matching = stats?.matching || {};

  const activity = stats?.activity || {};

  const users = stats?.users || {};

  const systemHealth = stats?.system_health || {};

  const hasSystemAlerts = Boolean(systemHealth?.has_alerts);

  return (
    <div className="space-y-8">
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-xs font-black uppercase tracking-[0.18em] text-slate-400">
          Panel administrativo
        </p>

        <h1 className="mt-2 text-2xl font-black tracking-tight text-slate-900">
          Resumen general
        </h1>

        <p className="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-500">
          Estado comercial, matching, consumo de inteligencia artificial y salud
          operativa de PermuOK.
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
        <>
          <section className="space-y-4">
            <SectionHeader
              title="Negocio"
              description="Estado general de la actividad comercial de la plataforma."
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
              {businessCards.map((item) => (
                <StatCard
                  key={item.key}
                  title={item.title}
                  value={formatNumber(stats?.[item.key])}
                  description={item.description}
                  icon={item.icon}
                />
              ))}
            </div>
          </section>

          <section className="space-y-4">
            <SectionHeader
              title="Actividad este mes"
              description="Movimiento generado durante el mes actual."
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
              <StatCard
                title="Nuevas inmobiliarias"
                value={formatNumber(activity.new_real_estates_month)}
                description="Cuentas creadas durante este mes."
                icon="building2"
              />

              <StatCard
                title="Nuevas publicaciones"
                value={formatNumber(activity.new_publications_month)}
                description={`Propiedades: ${formatNumber(
                  activity.new_properties_month,
                )} · Búsquedas: ${formatNumber(
                  activity.new_search_requests_month,
                )} · Desarrollos: ${formatNumber(
                  activity.new_developments_month,
                )}`}
                icon="layoutGrid"
              />

              <StatCard
                title="Nuevos matches"
                value={formatNumber(activity.new_matches_month)}
                description="Compatibilidades detectadas durante este mes."
                icon="badgeCheck"
              />

              <StatCard
                title="Nuevas conversaciones"
                value={formatNumber(activity.new_conversations_month)}
                description="Conversaciones iniciadas durante este mes."
                icon="messagesSquare"
              />
            </div>
          </section>

          <section className="space-y-4">
            <SectionHeader
              title="Usuarios"
              description="Distribución y actividad de los usuarios habilitados en la plataforma."
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
              <StatCard
                title="Usuarios activos"
                value={formatNumber(users.active_total)}
                description="Usuarios activos, excluyendo administradores."
                icon="badgeCheck"
                onClick={() => navigate("/admin/users")}
              />

              <StatCard
                title="Inmobiliarias"
                value={formatNumber(users.active_real_estate)}
                description="Usuarios inmobiliaria activos."
                icon="building2"
                onClick={() => navigate("/admin/users")}
              />

              <StatCard
                title="Agentes"
                value={formatNumber(users.active_agents)}
                description="Agentes activos en la plataforma."
                icon="badgeCheck"
                onClick={() => navigate("/admin/users")}
              />

              <StatCard
                title="Inversores"
                value={formatNumber(users.active_investors)}
                description="Inversores activos en la plataforma."
                icon="badgeCheck"
                onClick={() => navigate("/admin/users")}
              />

              <StatCard
                title="Activos últimos 30 días"
                value={formatNumber(users.logged_in_last_30_days)}
                description="Usuarios activos que iniciaron sesión en los últimos 30 días."
                icon="clock"
                onClick={() => navigate("/admin/users")}
              />
            </div>
          </section>

          <section className="space-y-4">
            <SectionHeader
              title="Matching"
              description="Rendimiento de las compatibilidades detectadas por PermuOK."
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
              <StatCard
                title="Matches activos"
                value={formatNumber(matching.active_matches)}
                description="Compatibilidades actualmente vigentes."
                icon="layoutGrid"
              />

              <StatCard
                title="Matches +90"
                value={formatNumber(matching.high_quality_matches)}
                description="Compatibilidades de alta calidad."
                icon="badgeCheck"
              />

              <StatCard
                title="Con interés"
                value={formatNumber(matching.matches_with_interest)}
                description="Matches donde al menos una parte manifestó interés."
                icon="messagesSquare"
              />

              <StatCard
                title="Chat habilitado"
                value={formatNumber(matching.chat_enabled_matches)}
                description="Matches que avanzaron hasta habilitar contacto."
                icon="messagesSquare"
              />

              <StatCard
                title="Tasa de interés"
                value={formatPercent(matching.interest_rate_pct)}
                description="Porcentaje de matches con interés."
                icon="badgeCheck"
              />

              <StatCard
                title="Conversión a chat"
                value={formatPercent(matching.chat_enablement_rate_pct)}
                description="Porcentaje de matches que llegaron a chat habilitado."
                icon="badgeCheck"
              />
            </div>
          </section>

          <section className="space-y-4">
            <SectionHeader
              title="Inteligencia artificial"
              description="Consumo y costo estimado de las funciones IA."
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
              <StatCard
                title="Costo de hoy"
                value={formatUsd(ai.cost_today_usd)}
                description="Consumo estimado de IA durante el día."
                icon="badgeCheck"
              />

              <StatCard
                title="Costo del mes"
                value={formatUsd(ai.cost_month_usd)}
                description="Consumo acumulado del mes actual."
                icon="badgeCheck"
              />

              <StatCard
                title="Costo histórico"
                value={formatUsd(ai.cost_total_usd)}
                description="Costo registrado desde que comenzó el tracking."
                icon="badgeCheck"
              />

              <StatCard
                title="Llamadas este mes"
                value={formatNumber(ai.calls_month)}
                description="Solicitudes registradas a servicios de IA."
                icon="layoutGrid"
              />

              <StatCard
                title="Tokens este mes"
                value={formatNumber(ai.tokens_month)}
                description="Tokens procesados en llamadas exitosas."
                icon="layoutGrid"
              />

              <StatCard
                title="Fallos este mes"
                value={formatNumber(ai.failed_calls_month)}
                description="Intentos de IA registrados como fallidos."
                icon="clock"
                alert={Number(ai.failed_calls_month) > 0}
              />
            </div>
          </section>

          <section className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <SectionHeader
                title="Salud del sistema"
                description="Estado de las colas y procesos internos."
              />

              <div
                className={[
                  "rounded-full px-3 py-1 text-xs font-black",
                  hasSystemAlerts
                    ? "bg-rose-50 text-rose-700"
                    : "bg-emerald-50 text-emerald-700",
                ].join(" ")}
              >
                {hasSystemAlerts
                  ? "Requiere atención"
                  : "Sin errores pendientes"}
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
              <StatCard
                title="Matching pendientes"
                value={formatNumber(systemHealth.compatibility_jobs_pending)}
                description="Trabajos esperando ser procesados. Ver detalle."
                icon="clock"
                onClick={() =>
                  navigate("/admin/system/compatibility-jobs?status=pending")
                }
              />

              <StatCard
                title="Matching fallidos"
                value={formatNumber(systemHealth.compatibility_jobs_failed)}
                description="Trabajos que agotaron sus intentos. Ver detalle."
                icon="clock"
                alert={Number(systemHealth.compatibility_jobs_failed) > 0}
                onClick={() =>
                  navigate("/admin/system/compatibility-jobs?status=failed")
                }
              />
              <StatCard
                title="Emails pendientes"
                value={formatNumber(systemHealth.email_jobs_pending)}
                description="Emails esperando procesamiento. Ver detalle."
                icon="clock"
                onClick={() =>
                  navigate("/admin/system/email-jobs?status=pending")
                }
              />

              <StatCard
                title="Emails fallidos"
                value={formatNumber(systemHealth.email_jobs_failed)}
                description="Emails que no pudieron enviarse. Ver detalle."
                icon="clock"
                alert={Number(systemHealth.email_jobs_failed) > 0}
                onClick={() =>
                  navigate("/admin/system/email-jobs?status=failed")
                }
              />
            </div>
          </section>
        </>
      )}
    </div>
  );
}
