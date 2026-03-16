// pages/AdminBillingDetail.jsx
import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../api/http";

import AdminDetailHeader from "../ui/admin/detail/AdminDetailHeader";
import AdminDetailLoading from "../ui/admin/detail/AdminDetailLoading";
import AdminDetailEmpty from "../ui/admin/detail/AdminDetailEmpty";
import AdminDetailError from "../ui/admin/detail/AdminDetailError";
import AdminRealEstateSection from "../ui/admin/detail/AdminRealEstateSection";
import AdminMembershipCard from "../ui/admin/detail/AdminMembershipCard";
import AdminAdministrativeStatusCard from "../ui/admin/detail/AdminAdministrativeStatusCard";

function formatDate(value) {
  if (!value) return "—";

  try {
    return new Date(value).toLocaleDateString("es-AR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

function formatDateTime(value) {
  if (!value) return "—";

  try {
    return new Date(value).toLocaleString("es-AR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return "—";
  }
}

function formatMoney(value) {
  const amount = Number(value || 0);

  if (!Number.isFinite(amount) || amount <= 0) {
    return "—";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 0,
  }).format(amount);
}

function membershipStatusLabel(status) {
  if (status === "active") return "Activa";
  if (status === "cancel_at_period_end") return "Cancelación programada";
  if (status === "scheduled_change") return "Cambio programado";
  if (status === "pending") return "Pendiente";
  if (status === "expired") return "Vencida";
  if (status === "cancelled") return "Cancelada";
  return "Sin membresía";
}

function membershipStatusClasses(status) {
  if (status === "active") return "bg-emerald-100 text-emerald-700";
  if (status === "cancel_at_period_end") return "bg-amber-100 text-amber-700";
  if (status === "scheduled_change") return "bg-sky-100 text-sky-700";
  if (status === "pending") return "bg-violet-100 text-violet-700";
  if (status === "expired") return "bg-rose-100 text-rose-700";
  if (status === "cancelled") return "bg-slate-200 text-slate-700";
  return "bg-slate-100 text-slate-600";
}

function paymentStatusMeta(payment) {
  const raw = String(payment?.mp_status || payment?.status || "").toLowerCase();

  if (!payment) {
    return {
      label: "Sin pagos",
      className: "bg-slate-100 text-slate-600",
    };
  }

  if (raw.includes("approved") || raw === "paid" || raw === "accredited") {
    return {
      label: "Cobrado",
      className: "bg-emerald-100 text-emerald-700",
    };
  }

  if (raw.includes("pending") || raw.includes("process") || raw === "created") {
    return {
      label: "Pendiente",
      className: "bg-amber-100 text-amber-700",
    };
  }

  if (
    raw.includes("rejected") ||
    raw.includes("cancelled") ||
    raw.includes("refunded")
  ) {
    return {
      label: "Fallido",
      className: "bg-rose-100 text-rose-700",
    };
  }

  return {
    label: payment?.status || "Sin pagos",
    className: "bg-slate-100 text-slate-600",
  };
}

function InfoField({ label, value, strong = false }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
        {label}
      </p>
      <p className={strong ? "text-slate-900 font-bold text-lg" : "text-slate-700"}>
        {value || "—"}
      </p>
    </div>
  );
}

function BillingSummarySection({ summary }) {
  const paymentMeta = paymentStatusMeta(summary?.last_payment);

  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex items-center gap-3">
        <span className="text-primary text-xl">₳</span>
        <h2 className="text-xl font-bold text-slate-900">
          Resumen de cobro
        </h2>
      </div>

      <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
        <InfoField
          label="Último cobro"
          value={formatMoney(summary?.last_payment?.amount_ars)}
          strong
        />
        <div className="space-y-1">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
            Estado de cobro
          </p>
          <span
            className={`inline-flex px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${paymentMeta.className}`}
          >
            {paymentMeta.label}
          </span>
        </div>

        <InfoField
          label="Último pago aprobado"
          value={formatDateTime(summary?.last_payment?.approved_at)}
        />
        <InfoField
          label="Último intento"
          value={formatDateTime(
            summary?.last_payment?.paid_at || summary?.last_payment?.created_at,
          )}
        />

        <InfoField
          label="Provider"
          value={summary?.last_payment?.provider || "Mercado Pago"}
        />
        <InfoField
          label="Próximo vencimiento"
          value={formatDate(summary?.membership?.end_date)}
        />

        {summary?.scheduled_plan?.name && (
          <>
            <InfoField
              label="Próximo plan"
              value={summary.scheduled_plan.name}
            />
            <InfoField
              label="Próximo precio"
              value={formatMoney(summary?.scheduled_plan?.price_ars)}
            />
          </>
        )}
      </div>
    </section>
  );
}

function MercadoPagoCard({ payment }) {
  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-fit">
      <div className="p-6 border-b border-slate-100 flex items-center gap-2">
        <span className="text-primary">MP</span>
        <h2 className="text-xl font-bold text-slate-900">Mercado Pago</h2>
      </div>

      <div className="p-6 space-y-4 text-sm">
        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Payment ID
          </span>
          <span className="text-slate-900 font-bold">
            {payment?.mp_payment_id || "—"}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Preference ID
          </span>
          <span className="text-slate-900 font-bold text-right break-all">
            {payment?.preference_id || "—"}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            External ref.
          </span>
          <span className="text-slate-900 font-bold text-right break-all">
            {payment?.external_reference || "—"}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            MP status
          </span>
          <span className="text-slate-900 font-bold">
            {payment?.mp_status || "—"}
          </span>
        </div>

        <div>
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest block mb-2">
            Detalle
          </span>
          <div className="p-4 rounded border bg-slate-50 border-slate-200 text-xs text-slate-700 italic">
            {payment?.mp_status_detail || "—"}
          </div>
        </div>
      </div>
    </section>
  );
}

function PaymentsHistorySection({ payments }) {
  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 className="text-xl font-bold text-slate-900">Historial de pagos</h2>
        <span className="bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1 rounded-full uppercase">
          {payments.length}
        </span>
      </div>

      <div className="p-6">
        {!payments.length ? (
          <div className="text-sm text-slate-500">
            No hay pagos registrados.
          </div>
        ) : (
          <div className="space-y-4">
            {payments.map((payment) => {
              const meta = paymentStatusMeta(payment);

              return (
                <div
                  key={payment.id}
                  className="rounded-xl border border-slate-200 p-4"
                >
                  <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div className="space-y-3 min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="font-bold text-slate-900">
                          {payment.plan_name || "Pago"}
                        </p>

                        <span
                          className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${meta.className}`}
                        >
                          {meta.label}
                        </span>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-sm text-slate-600">
                        <p>
                          <span className="font-semibold text-slate-900">Importe:</span>{" "}
                          {formatMoney(payment.amount_ars)}
                        </p>
                        <p>
                          <span className="font-semibold text-slate-900">Estado:</span>{" "}
                          {payment.status || "—"}
                        </p>
                        <p>
                          <span className="font-semibold text-slate-900">MP status:</span>{" "}
                          {payment.mp_status || "—"}
                        </p>
                        <p>
                          <span className="font-semibold text-slate-900">Aprobado:</span>{" "}
                          {formatDateTime(payment.approved_at)}
                        </p>
                        <p>
                          <span className="font-semibold text-slate-900">Pagado:</span>{" "}
                          {formatDateTime(payment.paid_at)}
                        </p>
                        <p>
                          <span className="font-semibold text-slate-900">Creado:</span>{" "}
                          {formatDateTime(payment.created_at)}
                        </p>
                      </div>
                    </div>

                    <div className="text-xs text-slate-500 md:text-right">
                      <p>
                        <span className="font-semibold text-slate-900">Ref:</span>{" "}
                        {payment.external_reference || "—"}
                      </p>
                      <p className="mt-1">
                        <span className="font-semibold text-slate-900">Payment ID:</span>{" "}
                        {payment.mp_payment_id || "—"}
                      </p>
                    </div>
                  </div>

                  {payment.mp_status_detail && (
                    <div className="mt-4 rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600 italic">
                      {payment.mp_status_detail}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}

export default function AdminBillingDetail() {
  const navigate = useNavigate();
  const { id } = useParams();

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [data, setData] = useState(null);

  const summary = data?.summary || null;
  const payments = data?.payments || [];

  useEffect(() => {
    async function load() {
      setErr("");
      setLoading(true);

      try {
        const res = await api.get(`/admin/billing/${id}`);
        const payload = unwrap(res);

        setData({
          summary: payload?.summary ?? null,
          payments: Array.isArray(payload?.payments) ? payload.payments : [],
        });
      } catch (e) {
        setErr(
          getErrorMessage(e, "No se pudo cargar el detalle de facturación"),
        );
      } finally {
        setLoading(false);
      }
    }

    load();
  }, [id]);

  const membershipLabel = membershipStatusLabel(summary?.membership_status);
  const membershipTone = membershipStatusClasses(summary?.membership_status);

  const headerSubtitle = useMemo(() => {
    if (!summary) return "Cargando detalle...";

    const name = summary?.real_estate_name || "Inmobiliaria";
    const owner =
      summary?.owner_name || summary?.owner_email || "Sin responsable";

    return `${name} · ${owner}`;
  }, [summary]);

  return (
    <div className="space-y-8">
      {loading ? (
        <AdminDetailHeader
          backLabel="Volver a facturación"
          onBack={() => navigate("/admin/billing")}
          subtitle="Cargando detalle..."
        />
      ) : (
        <AdminDetailHeader
          backLabel="Volver a facturación"
          onBack={() => navigate("/admin/billing")}
          badge={
            <span
              className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${membershipTone}`}
            >
              {membershipLabel}
            </span>
          }
          subtitle={headerSubtitle}
        />
      )}

      {err && <AdminDetailError message={err} />}

      {loading ? (
        <AdminDetailLoading />
      ) : !summary ? (
        <AdminDetailEmpty message="No se encontró el detalle de facturación." />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          <div className="lg:col-span-2 space-y-6">
            <AdminRealEstateSection
              realEstate={{
                name: summary?.real_estate_name,
                legal_name: summary?.real_estate_legal_name,
                cuit: summary?.real_estate_cuit,
                email: summary?.real_estate_email,
                phone: summary?.real_estate_phone,
                address: null,
                website: null,
                instagram: null,
                facebook: null,
              }}
              showLinks={false}
            />

            <BillingSummarySection summary={summary} />
            <PaymentsHistorySection payments={payments} />
          </div>

          <div className="flex flex-col gap-6">
            <AdminMembershipCard
              membership={summary?.membership}
              membershipStatus={summary?.membership_status}
              membershipLabel={membershipLabel}
              membershipTone={membershipTone}
              formatDate={formatDate}
              plan={summary?.plan}
              scheduledPlan={summary?.scheduled_plan}
              hideButton={true}
              emptyMessage="Esta inmobiliaria no tiene una membresía activa asociada."
            />

            <AdminAdministrativeStatusCard
              isActive={summary?.owner_is_active}
              deactivatedByEmail={null}
              deactivatedAt={null}
              deactivationReason={null}
              formatDate={formatDate}
              statusLabel={(value) =>
                Number(value) === 1 ? "Cuenta activa" : "Cuenta inactiva"
              }
              actionLabel={null}
              actionDisabled={true}
              activeNote="La cuenta del responsable se encuentra operativa."
              compact={true}
              showDeactivatedBy={false}
              showDeactivatedAt={false}
              showDeactivationReason={false}
            />

            <MercadoPagoCard payment={summary?.last_payment || payments[0]} />
          </div>
        </div>
      )}
    </div>
  );
}