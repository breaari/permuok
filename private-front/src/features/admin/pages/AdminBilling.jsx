// pages/AdminBilling.jsx
import { useEffect, useMemo, useRef, useState, useCallback } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../../../api/http.js";
import { useAuth } from "../../auth/components/AuthContext";

const TABS = [
  { key: "active", label: "Activas" },
  { key: "none", label: "Sin membresía" },
  { key: "cancel_at_period_end", label: "Cancelación programada" },
  { key: "scheduled_change", label: "Cambio programado" },
  { key: "pending", label: "Pendientes" },
  { key: "expired", label: "Vencidas" },
  { key: "cancelled", label: "Canceladas" },
];

const DEFAULT_PER_PAGE = 10;

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

function getPaginationRange({ totalPages, currentPage, siblingCount = 1 }) {
  const totalPageNumbers = siblingCount * 2 + 5;

  if (totalPages <= totalPageNumbers) {
    return Array.from({ length: totalPages }, (_, i) => i + 1);
  }

  const leftSiblingIndex = Math.max(currentPage - siblingCount, 1);
  const rightSiblingIndex = Math.min(currentPage + siblingCount, totalPages);

  const showLeftDots = leftSiblingIndex > 2;
  const showRightDots = rightSiblingIndex < totalPages - 1;

  const firstPageIndex = 1;
  const lastPageIndex = totalPages;

  if (!showLeftDots && showRightDots) {
    const leftItemCount = 3 + siblingCount * 2;
    const leftRange = Array.from({ length: leftItemCount }, (_, i) => i + 1);
    return [...leftRange, "…", lastPageIndex];
  }

  if (showLeftDots && !showRightDots) {
    const rightItemCount = 3 + siblingCount * 2;
    const start = totalPages - rightItemCount + 1;
    const rightRange = Array.from(
      { length: rightItemCount },
      (_, i) => start + i,
    );
    return [firstPageIndex, "…", ...rightRange];
  }

  const middleRange = Array.from(
    { length: rightSiblingIndex - leftSiblingIndex + 1 },
    (_, i) => leftSiblingIndex + i,
  );

  return [firstPageIndex, "…", ...middleRange, "…", lastPageIndex];
}

function PaginationPro({ page, perPage, total, onPageChange }) {
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  if (totalPages <= 1) return null;

  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end = Math.min(total, page * perPage);

  const range = useMemo(
    () =>
      getPaginationRange({
        totalPages,
        currentPage: page,
        siblingCount: 1,
      }),
    [totalPages, page],
  );

  return (
    <div className="mt-8 flex flex-col md:flex-row md:items-center md:justify-between border-t border-slate-200 pt-6 gap-4">
      <p className="text-sm text-slate-500">
        Mostrando{" "}
        <span className="font-bold text-slate-900">
          {start} - {end}
        </span>{" "}
        de <span className="font-bold text-slate-900">{total}</span> registros
      </p>

      <div className="flex gap-2 items-center flex-wrap">
        <button
          type="button"
          className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
          aria-label="Anterior"
        >
          <span className="text-lg leading-none">‹</span>
        </button>

        {range.map((item, idx) => {
          if (item === "…") {
            return (
              <span
                key={`dots-${idx}`}
                className="w-10 h-10 flex items-center justify-center text-slate-400 select-none"
              >
                …
              </span>
            );
          }

          return (
            <button
              key={item}
              type="button"
              className={
                item === page
                  ? "w-10 h-10 bg-primary text-white rounded-lg font-bold"
                  : "w-10 h-10 border border-slate-200 rounded-lg hover:bg-slate-50"
              }
              onClick={() => onPageChange(item)}
            >
              {item}
            </button>
          );
        })}

        <button
          type="button"
          className="p-2 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50"
          disabled={page >= totalPages}
          onClick={() => onPageChange(page + 1)}
          aria-label="Siguiente"
        >
          <span className="text-lg leading-none">›</span>
        </button>
      </div>
    </div>
  );
}

function BillingTabs({ tabs, value, onChange, counts }) {
  return (
    <div className="border-b border-slate-200 mb-6 flex gap-8 overflow-x-auto">
      {tabs.map((t) => {
        const active = t.key === value;
        const n = counts?.[t.key];

        return (
          <button
            key={t.key}
            type="button"
            onClick={() => onChange(t.key)}
            className={
              active
                ? "pb-4 text-sm font-bold text-primary border-b-2 border-primary whitespace-nowrap"
                : "pb-4 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors whitespace-nowrap"
            }
          >
            {t.label}
            <span className="ml-2 bg-primary/10 text-primary px-2 py-0.5 rounded-full text-xs">
              {Number.isFinite(n) ? n : 0}
            </span>
          </button>
        );
      })}
    </div>
  );
}

function MembershipStatusBadge({ status }) {
  const map = {
    active: {
      label: "Activa",
      className:
        "px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider",
    },
    none: {
      label: "Sin membresía",
      className:
        "px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider",
    },
    cancel_at_period_end: {
      label: "Cancelación programada",
      className:
        "px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider",
    },
    scheduled_change: {
      label: "Cambio programado",
      className:
        "px-3 py-1 bg-sky-100 text-sky-700 rounded-full text-xs font-bold uppercase tracking-wider",
    },
    pending: {
      label: "Pendiente",
      className:
        "px-3 py-1 bg-violet-100 text-violet-700 rounded-full text-xs font-bold uppercase tracking-wider",
    },
    expired: {
      label: "Vencida",
      className:
        "px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold uppercase tracking-wider",
    },
    cancelled: {
      label: "Cancelada",
      className:
        "px-3 py-1 bg-slate-200 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider",
    },
  };

  const meta = map[status] || map.none;
  return <span className={meta.className}>{meta.label}</span>;
}

function PaymentStatusBadge({ payment }) {
  const raw = String(payment?.mp_status || payment?.status || "").toLowerCase();

  let label = "Sin pagos";
  let className =
    "px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold uppercase tracking-wider";

  if (!payment) {
    return <span className={className}>{label}</span>;
  }

  if (raw.includes("approved") || raw === "paid" || raw === "accredited") {
    label = "Cobrado";
    className =
      "px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider";
  } else if (
    raw.includes("pending") ||
    raw.includes("process") ||
    raw === "created"
  ) {
    label = "Pendiente";
    className =
      "px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider";
  } else if (
    raw.includes("rejected") ||
    raw.includes("cancelled") ||
    raw.includes("refunded")
  ) {
    label = "Fallido";
    className =
      "px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold uppercase tracking-wider";
  }

  return <span className={className}>{label}</span>;
}

function InfoItem({ label, value }) {
  if (!value || value === "—") return null;

  return (
    <p>
      <span className="font-semibold text-slate-900">{label}:</span> {value}
    </p>
  );
}

function AdminBillingList({ loading, items, onOpenDetail }) {
  if (loading) {
    return <div className="text-sm text-slate-500">Cargando...</div>;
  }

  if (!items?.length) {
    return <div className="text-sm text-slate-500">Sin resultados</div>;
  }

  return (
    <div className="space-y-4">
      {items.map((item) => {
        const planName = item?.plan?.name || "Sin plan";
        const scheduledPlanName = item?.scheduled_plan?.name || null;
        const ownerName = item?.owner_name || "—";

        return (
          <div
            key={item.real_estate_id}
            className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm"
          >
            <div className="flex flex-col md:flex-row justify-between items-start gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex flex-wrap items-center gap-3 mb-4">
                  <h3 className="text-xl font-bold text-slate-900 break-words">
                    {item.real_estate_name || "—"}
                  </h3>

                  <span className="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider">
                    Facturación
                  </span>

                  <MembershipStatusBadge status={item.membership_status} />

                  {item.has_cancel_at_period_end && (
                    <MembershipStatusBadge status="cancel_at_period_end" />
                  )}

                  {item.has_scheduled_change && (
                    <MembershipStatusBadge status="scheduled_change" />
                  )}

                  <PaymentStatusBadge payment={item.last_payment} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-y-2 gap-x-8 text-sm text-slate-600">
                  <InfoItem label="Responsable" value={ownerName} />
                  <InfoItem label="Email" value={item.owner_email || "—"} />
                  <InfoItem label="CUIT" value={item.real_estate_cuit || "—"} />
                  <InfoItem label="Plan actual" value={planName} />
                  <InfoItem
                    label="Precio plan"
                    value={formatMoney(item?.plan?.price_ars)}
                  />
                  <InfoItem
                    label="Vencimiento"
                    value={formatDate(item?.membership?.end_date)}
                  />
                  <InfoItem
                    label="Último cobro"
                    value={formatMoney(item?.last_payment?.amount_ars)}
                  />
                  <InfoItem
                    label="Último pago"
                    value={formatDate(
                      item?.last_payment?.approved_at ||
                        item?.last_payment?.paid_at ||
                        item?.last_payment?.created_at,
                    )}
                  />
                  {scheduledPlanName && (
                    <InfoItem
                      label="Próximo plan"
                      value={`${scheduledPlanName} (${formatMoney(
                        item?.scheduled_plan?.price_ars,
                      )})`}
                    />
                  )}
                </div>
              </div>

              <div className="flex flex-row md:flex-col items-center md:items-end gap-3 w-full md:w-auto shrink-0">
                <button
                  type="button"
                  onClick={() => onOpenDetail(item.real_estate_id)}
                  className="text-primary font-bold text-sm hover:underline"
                >
                  Ver detalle
                </button>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default function AdminBilling() {
  const { user } = useAuth();
  const isAdmin = Number(user?.role) === 1;
  const navigate = useNavigate();

  if (!isAdmin) return <Navigate to="/" replace />;

  const [tab, setTab] = useState("active");
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [counts, setCounts] = useState({
    active: 0,
    none: 0,
    cancel_at_period_end: 0,
    scheduled_change: 0,
    pending: 0,
    expired: 0,
    cancelled: 0,
  });

  const [q, setQ] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  const [page, setPage] = useState(1);
  const [perPage] = useState(DEFAULT_PER_PAGE);

  const requestIdRef = useRef(0);

  const loadCounts = useCallback(async (query = "") => {
    try {
      const params = new URLSearchParams();

      if (query?.trim()) {
        params.set("q", query.trim());
      }

      const qs = params.toString();
      const res = await api.get(`/admin/billing/counts${qs ? `?${qs}` : ""}`);
      const data = unwrap(res);

      setCounts(
        data?.counts ?? {
          active: 0,
          none: 0,
          cancel_at_period_end: 0,
          scheduled_change: 0,
          pending: 0,
          expired: 0,
          cancelled: 0,
        },
      );
    } catch {
      // no romper UI
    }
  }, []);

  const loadList = useCallback(
    async ({ nextPage = 1, nextQuery = q, nextTab = tab } = {}) => {
      const currentRequestId = ++requestIdRef.current;

      setErr("");
      setLoading(true);
      setItems([]);
      setMeta(null);

      try {
        const params = new URLSearchParams({
          status: nextTab,
          page: String(nextPage),
          per_page: String(perPage),
        });

        if (nextQuery?.trim()) {
          params.set("q", nextQuery.trim());
        }

        const res = await api.get(`/admin/billing?${params.toString()}`);
        const data = unwrap(res);

        if (currentRequestId !== requestIdRef.current) return;

        setItems(Array.isArray(data?.items) ? data.items : []);
        setMeta(data?.meta ?? null);
        setPage(Number(data?.meta?.page || nextPage));
      } catch (e) {
        if (currentRequestId !== requestIdRef.current) return;

        setItems([]);
        setMeta(null);
        setErr(getErrorMessage(e, "No se pudo cargar la facturación"));
      } finally {
        if (currentRequestId === requestIdRef.current) {
          setLoading(false);
        }
      }
    },
    [tab, q, perPage],
  );

  useEffect(() => {
    setPage(1);
    loadCounts(q);
    loadList({
      nextPage: 1,
      nextQuery: q,
      nextTab: tab,
    });
  }, [tab, q, loadCounts, loadList]);

  function onChangeTab(nextTab) {
    if (nextTab === tab) return;
    setTab(nextTab);
  }

  function onSearchSubmit(e) {
    e.preventDefault();
    setQ(searchInput.trim());
  }

  const { visibleItems, totalForPagination, effectivePage, effectivePerPage } =
    useMemo(() => {
      return {
        visibleItems: items,
        totalForPagination: Number(meta?.total || 0),
        effectivePage: Number(meta?.page || page),
        effectivePerPage: Number(meta?.per_page || perPage),
      };
    }, [items, meta, page, perPage]);

  return (
    <div className="space-y-8">
      <div className="space-y-2">
        <h1 className="text-3xl md:text-4xl font-black tracking-tight text-slate-900">
          Facturación
        </h1>
        <p className="text-slate-500 text-base max-w-3xl">
          Gestioná membresías, renovaciones, cambios de plan, cancelaciones y
          últimos cobros de las inmobiliarias.
        </p>
      </div>

      {err && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
          {err}
        </div>
      )}

      <div className="space-y-6">
        <div className="hidden md:block">
          <BillingTabs
            tabs={TABS}
            value={tab}
            onChange={onChangeTab}
            counts={counts}
          />
        </div>

        <div className="md:hidden">
          <label
            htmlFor="billing-status"
            className="block text-sm font-semibold text-slate-700 mb-2"
          >
            Estado
          </label>
          <select
            id="billing-status"
            value={tab}
            onChange={(e) => onChangeTab(e.target.value)}
            className="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-primary"
          >
            {TABS.map((t) => (
              <option key={t.key} value={t.key}>
                {t.label} ({counts?.[t.key] ?? 0})
              </option>
            ))}
          </select>
        </div>

        <div className="flex flex-col lg:flex-row gap-4 lg:items-center">
          <form
            onSubmit={onSearchSubmit}
            className="flex w-full lg:w-auto lg:min-w-[420px] gap-2"
          >
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar por inmobiliaria, responsable, email o CUIT..."
              className="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-primary"
            />

            <button
              type="submit"
              className="rounded-lg bg-primary px-5 py-3 text-sm font-bold text-white hover:bg-primary/90 transition-colors"
            >
              Buscar
            </button>
          </form>
        </div>
      </div>

      <AdminBillingList
        loading={loading}
        items={visibleItems}
        onOpenDetail={(realEstateId) =>
          navigate(`/admin/billing/${realEstateId}`)
        }
      />

      {!loading && totalForPagination > 0 && (
        <PaginationPro
          page={effectivePage}
          perPage={effectivePerPage}
          total={totalForPagination}
          onPageChange={(p) =>
            loadList({
              nextPage: p,
              nextQuery: q,
              nextTab: tab,
            })
          }
        />
      )}
    </div>
  );
}
