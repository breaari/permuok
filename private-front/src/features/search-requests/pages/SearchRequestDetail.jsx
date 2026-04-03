import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { getErrorMessage } from "../../../api/http";
import { Icon } from "../../../ui/icons/Index";
import { getSearchRequestDetail } from "../api/searchRequests.api";
import { formatSearchRequestPayment } from "../utils";

function getStatusMeta(status) {
  const map = {
    draft: {
      label: "Borrador",
      className: "bg-slate-100 text-slate-700 border-slate-200",
    },
    published: {
      label: "Publicada",
      className: "bg-emerald-100 text-emerald-700 border-emerald-200",
    },
    paused: {
      label: "Pausada",
      className: "bg-amber-100 text-amber-700 border-amber-200",
    },
    archived: {
      label: "Archivada",
      className: "bg-slate-200 text-slate-700 border-slate-300",
    },
    closed: {
      label: "Cerrada",
      className: "bg-rose-100 text-rose-700 border-rose-200",
    },
    deleted: {
      label: "Eliminada",
      className: "bg-rose-100 text-rose-700 border-rose-200",
    },
  };

  return (
    map[status] || {
      label: status || "Sin estado",
      className: "bg-slate-100 text-slate-600 border-slate-200",
    }
  );
}

function formatMoneyRange(item) {
  const currency = item?.currency || "USD";
  const min = item?.min_value;
  const max = item?.max_value;

  const format = (value) =>
    new Intl.NumberFormat("es-AR", {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    }).format(Number(value || 0));

  if (min && max) return `${format(min)} - ${format(max)}`;
  if (min) return `Desde ${format(min)}`;
  if (max) return `Hasta ${format(max)}`;
  return "Sin rango definido";
}

function DetailCard({ title, children }) {
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <h2 className="text-lg font-bold text-slate-900 mb-4">{title}</h2>
      {children}
    </section>
  );
}

function DetailField({ label, value }) {
  return (
    <div>
      <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
        {label}
      </p>
      <p className="mt-1 text-sm font-semibold text-slate-800">
        {value || "—"}
      </p>
    </div>
  );
}

export default function SearchRequestDetail() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  useEffect(() => {
    let cancelled = false;

    async function loadDetail() {
      try {
        setLoading(true);
        setErr("");

        const data = await getSearchRequestDetail(id);

        if (cancelled) return;
        setDetail(data);
      } catch (error) {
        if (cancelled) return;
        setErr(getErrorMessage(error, "No se pudo cargar la búsqueda."));
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadDetail();

    return () => {
      cancelled = true;
    };
  }, [id]);

  if (loading) {
    return (
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-10 py-10">
        <div className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm">
          Cargando búsqueda...
        </div>
      </div>
    );
  }

  if (err) {
    return (
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-10 py-10 space-y-4">
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
          {err}
        </div>

        <button
          type="button"
          onClick={() => navigate("/search-requests")}
          className="px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700"
        >
          Volver
        </button>
      </div>
    );
  }

  const request = detail?.search_request || {};
  const propertyTypes = Array.isArray(detail?.property_types)
    ? detail.property_types
    : [];
  const amenities = Array.isArray(detail?.amenities) ? detail.amenities : [];
  const statusMeta = getStatusMeta(request?.status);

  return (
    <main className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-10 py-8 sm:py-10 md:py-12 space-y-6">
      <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
        <div className="min-w-0">
          <button
            type="button"
            onClick={() => navigate("/search-requests")}
            className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-700 mb-4"
          >
            <Icon name="chevronLeft" size={16} />
            Volver a búsquedas
          </button>

          <div
            className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${statusMeta.className}`}
          >
            <span className="h-2 w-2 rounded-full bg-current opacity-70" />
            {statusMeta.label}
          </div>

          <h1 className="mt-3 text-2xl sm:text-3xl md:text-4xl font-black tracking-tight text-slate-900">
            {request?.title || "Sin título"}
          </h1>

          <p className="mt-3 max-w-3xl text-sm sm:text-base text-slate-500 leading-relaxed">
            {request?.description || "Sin descripción cargada."}
          </p>
        </div>

        <div className="flex flex-col sm:flex-row gap-3">
          <button
            type="button"
            onClick={() => navigate(`/search-requests/${request?.id}/edit`)}
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
          >
            <Icon name="edit" size={16} />
            Editar
          </button>
        </div>
      </div>

      <DetailCard title="Resumen">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          <DetailField
            label="Ubicación"
            value={[request?.city, request?.zone, request?.province].filter(Boolean).join(", ")}
          />
          <DetailField
            label="Tipo buscado"
            value={propertyTypes.length ? propertyTypes.join(", ") : "Sin definir"}
          />
          <DetailField
            label="Modalidad de pago"
            value={formatSearchRequestPayment(request)}
          />
          <DetailField
            label="Presupuesto"
            value={formatMoneyRange(request)}
          />
          <DetailField
            label="Urgencia"
            value={request?.urgency || "—"}
          />
          <DetailField
            label="Estado buscado"
            value={request?.property_condition || "—"}
          />
        </div>
      </DetailCard>

      <DetailCard title="Criterios mínimos">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          <DetailField
            label="Superficie total mínima"
            value={request?.min_total_area ? `${Number(request.min_total_area)} m²` : "—"}
          />
          <DetailField
            label="Superficie cubierta mínima"
            value={request?.min_covered_area ? `${Number(request.min_covered_area)} m²` : "—"}
          />
          <DetailField
            label="Antigüedad máxima"
            value={request?.max_antiquity ? `${request.max_antiquity} años` : "—"}
          />
          <DetailField
            label="Dormitorios mínimos"
            value={request?.min_bedrooms || "—"}
          />
          <DetailField
            label="Baños mínimos"
            value={request?.min_bathrooms || "—"}
          />
          <DetailField
            label="Cocheras mínimas"
            value={request?.min_garages || "—"}
          />
        </div>
      </DetailCard>

      <DetailCard title="Amenities deseadas">
        {amenities.length ? (
          <div className="flex flex-wrap gap-2">
            {amenities.map((item) => (
              <span
                key={item}
                className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700"
              >
                {item}
              </span>
            ))}
          </div>
        ) : (
          <p className="text-sm text-slate-500">No se cargaron amenities deseadas.</p>
        )}
      </DetailCard>

      <DetailCard title="Observaciones">
        <p className="text-sm text-slate-600 leading-relaxed whitespace-pre-line">
          {request?.notes || "Sin observaciones cargadas."}
        </p>
      </DetailCard>
    </main>
  );
}