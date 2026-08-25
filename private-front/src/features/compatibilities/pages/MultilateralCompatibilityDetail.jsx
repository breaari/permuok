import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";

import PropertyCard from "../../properties/components/PropertyCard";
import SearchRequestCard from "../../search-requests/components/SearchRequestCard";

import {
  getMultilateralCompatibilityDetail,
  respondToMultilateralCompatibility,
} from "../api/compatibilities.api";

/* =========================================================
   HELPERS
========================================================= */

function formatMoney(value, currency = "USD") {
  if (value === null || value === undefined || value === "") {
    return "—";
  }

  const amount = Number(value);

  if (!Number.isFinite(amount)) {
    return "—";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: currency || "USD",
    maximumFractionDigits: 0,
  }).format(amount);
}

function formatDate(value) {
  if (!value) {
    return "—";
  }

  const normalized = String(value).replace(" ", "T");

  const date = new Date(
    /Z$|[+-]\d{2}:\d{2}$/.test(normalized) ? normalized : `${normalized}Z`,
  );

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat("es-AR", {
    day: "2-digit",
    month: "long",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function getDifferenceMeta(leg) {
  const difference = Number(leg?.cash_difference || 0);
  const currency = leg?.comparison_currency || "USD";

  if (!difference) {
    return {
      label: "Permuta total",
      detail: "Sin diferencia",
      className: "bg-emerald-50 text-emerald-700 border-emerald-200",
    };
  }

  if (leg?.direction === "a_favor") {
    return {
      label: `Aporta ${formatMoney(difference, currency)}`,
      detail: "Diferencia a aportar",
      className: "bg-amber-50 text-amber-700 border-amber-200",
    };
  }

  if (leg?.direction === "en_contra") {
    return {
      label: `Recibe ${formatMoney(difference, currency)}`,
      detail: "Diferencia a recibir",
      className: "bg-blue-50 text-blue-700 border-blue-200",
    };
  }

  return {
    label: formatMoney(difference, currency),
    detail: "Diferencia estimada",
    className: "bg-slate-50 text-slate-700 border-slate-200",
  };
}

/* =========================================================
   ICONS
========================================================= */

function ArrowLeftIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className="h-4 w-4"
      aria-hidden="true"
    >
      <path d="m15 18-6-6 6-6" />
    </svg>
  );
}

function ArrowRightIcon({ className = "h-5 w-5" }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </svg>
  );
}

function CycleIcon({ className = "h-5 w-5" }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M20 7h-5V2" />
      <path d="M4 17h5v5" />
      <path d="M5.2 9A8 8 0 0 1 18.6 5.6L20 7" />
      <path d="M18.8 15A8 8 0 0 1 5.4 18.4L4 17" />
    </svg>
  );
}

/* =========================================================
   CIRCUIT
========================================================= */

function CircuitParticipant({ leg, index, total, navigate }) {
  const difference = getDifferenceMeta(leg);

  return (
    <div className="flex min-w-0 flex-1 items-stretch">
      <article
        className={`flex min-w-0 flex-1 flex-col rounded-2xl border bg-white p-4 ${
          leg?.is_my_leg
            ? "border-violet-300 ring-2 ring-violet-100"
            : "border-slate-200"
        }`}
      >
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-[9px] font-extrabold uppercase tracking-[0.16em] text-slate-400">
                Participante {index + 1}
              </span>

              {leg?.is_my_leg && (
                <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wide text-violet-700">
                  Tu inmobiliaria
                </span>
              )}
            </div>

            <h3 className="mt-1 truncate text-base font-black text-slate-900">
              {leg?.source_real_estate_name || "Inmobiliaria"}
            </h3>
          </div>

          <div className="shrink-0 text-right">
            <div className="text-xl font-black text-slate-900">
              {Math.round(Number(leg?.score || 0))}%
            </div>

            <div className="text-[8px] font-bold uppercase tracking-wide text-slate-400">
              match
            </div>
          </div>
        </div>

        <div className="mt-4">
          <button
            type="button"
            onClick={() =>
              navigate(`/explore/properties/${leg.offered_property_id}`)
            }
            className="block w-full text-left"
          >
            <div className="text-[9px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
              Entrega
            </div>

            <div className="mt-1 line-clamp-2 min-h-[40px] text-sm font-bold leading-snug text-slate-900 transition hover:text-primary">
              {leg?.offered_property_title || "Propiedad"}
            </div>
          </button>
        </div>

        <div className="my-3 flex items-center gap-3">
          <div className="h-px flex-1 bg-slate-200" />

          <ArrowRightIcon className="h-4 w-4 rotate-90 text-violet-500" />

          <div className="h-px flex-1 bg-slate-200" />
        </div>

        <div>
          <button
            type="button"
            onClick={() => navigate(`/explore/properties/${leg.property_id}`)}
            className="block w-full text-left"
          >
            <div className="text-[9px] font-extrabold uppercase tracking-[0.14em] text-violet-600">
              Recibe
            </div>

            <div className="mt-1 line-clamp-2 min-h-[40px] text-sm font-bold leading-snug text-slate-900 transition hover:text-primary">
              {leg?.property_title || "Propiedad"}
            </div>

            <div className="mt-1 text-[10px] text-slate-400">
              de {leg?.target_real_estate_name || "otra inmobiliaria"}
            </div>
          </button>
        </div>

        <div
          className={`mt-4 rounded-lg border px-3 py-2 ${difference.className}`}
        >
          <div className="text-xs font-extrabold">{difference.label}</div>
        </div>
      </article>

      {index < total - 1 && (
        <div className="hidden w-10 shrink-0 items-center justify-center text-violet-400 lg:flex">
          <ArrowRightIcon />
        </div>
      )}
    </div>
  );
}

function CompactCircuit({ legs, navigate }) {
  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div className="p-5 sm:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:gap-0">
          {legs.map((leg, index) => (
            <CircuitParticipant
              key={leg.id || `${leg.position}-${index}`}
              leg={leg}
              index={index}
              total={legs.length}
              navigate={navigate}
            />
          ))}
        </div>
      </div>

      <div className="border-t border-violet-100 bg-violet-50/60 px-5 py-3">
        <div className="flex items-center justify-center gap-2 text-xs font-bold text-violet-700">
          <CycleIcon className="h-4 w-4" />
          La última propiedad vuelve a conectar con la primera inmobiliaria y
          completa la cadena
        </div>
      </div>
    </div>
  );
}

/* =========================================================
   COMMERCIAL STATUS
========================================================= */

function CommercialStatus({
  operation,
  myResponse,
  contacts,
  responding,
  responseError,
  onResponse,
}) {
  if (operation.status !== "detected") {
    return (
      <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
        <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-slate-400">
          Oportunidad finalizada
        </div>

        <h2 className="mt-1 text-lg font-black text-slate-900">
          Esta cadena ya no se encuentra disponible
        </h2>
      </div>
    );
  }

  if (operation.commercial_status === "declined") {
    return (
      <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
        <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-slate-400">
          Oportunidad no disponible
        </div>

        <h2 className="mt-1 text-lg font-black text-slate-900">
          La cadena no continuará por el momento
        </h2>

        <p className="mt-1 text-sm text-slate-500">
          Las respuestas de las demás inmobiliarias permanecen privadas.
        </p>
      </div>
    );
  }

  if (operation.commercial_status === "confirmed") {
    return (
      <div className="rounded-2xl border border-emerald-300 bg-emerald-50 p-5 sm:p-6">
        <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">
          Cadena confirmada
        </div>

        <h2 className="mt-1 text-xl font-black text-slate-900">
          Todas las partes manifestaron interés
        </h2>

        <p className="mt-1 text-sm text-slate-600">
          Ya podés contactar a las demás inmobiliarias para coordinar la
          operación.
        </p>

        {contacts.length > 0 && (
          <div className="mt-5 grid gap-3 md:grid-cols-2">
            {contacts.map((contact) => (
              <div
                key={`${contact.real_estate_id}-${contact.user_id}`}
                className="rounded-xl border border-emerald-200 bg-white p-4"
              >
                <div className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-emerald-700">
                  {contact.real_estate_name}
                </div>

                <div className="mt-1 font-black text-slate-900">
                  {[contact.first_name, contact.last_name]
                    .filter(Boolean)
                    .join(" ") || "Contacto responsable"}
                </div>

                <div className="mt-3 space-y-1">
                  {contact.phone && (
                    <a
                      href={`tel:${contact.phone}`}
                      className="block text-sm font-bold text-primary hover:underline"
                    >
                      {contact.phone}
                    </a>
                  )}

                  {contact.email && (
                    <a
                      href={`mailto:${contact.email}`}
                      className="block break-all text-sm text-slate-600 hover:text-primary"
                    >
                      {contact.email}
                    </a>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  }

  if (myResponse === "interested") {
    return (
      <div className="rounded-2xl border border-blue-200 bg-blue-50 p-5">
        <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-blue-700">
          Interés registrado
        </div>

        <h2 className="mt-1 text-lg font-black text-slate-900">
          Marcaste que te interesa avanzar
        </h2>

        <p className="mt-1 text-sm text-slate-600">
          Estamos esperando la decisión de las demás inmobiliarias. No se
          informa quién respondió o quién falta responder.
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-slate-400">
        Tu decisión
      </div>

      <h2 className="mt-1 text-lg font-black text-slate-900">
        ¿Te interesa avanzar con esta cadena?
      </h2>

      <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
        Tu respuesta es privada. Los contactos sólo se habilitan cuando todas
        las inmobiliarias manifiestan interés.
      </p>

      {responseError && (
        <div className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {responseError}
        </div>
      )}

      <div className="mt-5 flex flex-col gap-3 sm:flex-row">
        <button
          type="button"
          disabled={responding}
          onClick={() => onResponse("interested")}
          className="rounded-xl bg-primary px-5 py-3 text-sm font-extrabold text-white transition hover:opacity-90 disabled:opacity-50"
        >
          {responding ? "Guardando..." : "Me interesa avanzar"}
        </button>

        <button
          type="button"
          disabled={responding}
          onClick={() => onResponse("declined")}
          className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
        >
          No me interesa
        </button>
      </div>
    </div>
  );
}

/* =========================================================
   PAGE
========================================================= */

export default function MultilateralCompatibilityDetail() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [data, setData] = useState(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [responding, setResponding] = useState(false);
  const [responseError, setResponseError] = useState("");

  const [publicationsTab, setPublicationsTab] = useState("properties");

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        setLoading(true);
        setError("");

        const result = await getMultilateralCompatibilityDetail(id);

        if (active) {
          setData(result);
        }
      } catch (err) {
        console.error("Error cargando operación multilateral:", err);

        if (active) {
          setError(
            err?.response?.data?.error ||
              err?.message ||
              "No se pudo cargar la oportunidad multilateral.",
          );
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    load();

    return () => {
      active = false;
    };
  }, [id]);

  const operation = data?.operation || null;

  const legs = useMemo(() => data?.legs || [], [data]);

  const myLeg = useMemo(
    () => legs.find((leg) => leg?.is_my_leg) || null,
    [legs],
  );

  const contacts = useMemo(
    () => (data?.contacts || []).filter((contact) => !contact?.is_me),
    [data],
  );

  /*
   * Cada propiedad aparece una sola vez.
   * En un ciclo válido cada propiedad ofrecida
   * representa a una inmobiliaria participante.
   */
  const properties = useMemo(() => {
    const map = new Map();

    legs.forEach((leg) => {
      const property = leg?.offered_property;

      if (!property?.id || map.has(property.id)) {
        return;
      }

      map.set(property.id, {
        property,
        realEstateName: leg?.source_real_estate_name,
        isMine: !!leg?.is_my_leg,
      });
    });

    return [...map.values()];
  }, [legs]);

  const searches = useMemo(
    () =>
      legs
        .filter((leg) => leg?.search_request?.id)
        .map((leg) => ({
          search: leg.search_request,

          realEstateName: leg?.source_real_estate_name,

          isMine: !!leg?.is_my_leg,
        })),
    [legs],
  );

  async function handleResponse(response) {
    try {
      setResponding(true);
      setResponseError("");

      await respondToMultilateralCompatibility(id, response);

      const refreshed = await getMultilateralCompatibilityDetail(id);

      setData(refreshed);
    } catch (err) {
      console.error("Error respondiendo operación multilateral:", err);

      setResponseError(
        err?.response?.data?.error ||
          err?.message ||
          "No se pudo registrar tu respuesta.",
      );
    } finally {
      setResponding(false);
    }
  }

  if (loading) {
    return (
      <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div className="h-5 w-44 animate-pulse rounded bg-slate-200" />

        <div className="mt-6 h-52 animate-pulse rounded-2xl border border-slate-200 bg-white" />

        <div className="mt-6 h-32 animate-pulse rounded-2xl border border-slate-200 bg-white" />

        <div className="mt-6 h-80 animate-pulse rounded-2xl border border-slate-200 bg-white" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <button
          type="button"
          onClick={() => navigate("/compatibilities/multilateral")}
          className="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-slate-900"
        >
          <ArrowLeftIcon />
          Volver
        </button>

        <div className="mt-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
          {error}
        </div>
      </div>
    );
  }

  if (!operation) {
    return null;
  }

  const archived = operation.status === "archived";

  return (
    <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      {/* =====================================================
          VOLVER
      ====================================================== */}

     <button
  type="button"
  onClick={() => navigate("/compatibilities")}
  className="mb-5 inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-900"
>
  <ArrowLeftIcon />
  Volver a compatibilidades
</button>

      {/* =====================================================
          HEADER
      ====================================================== */}

      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="p-5 sm:p-6">
          <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex gap-4">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                <CycleIcon />
              </div>

              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-violet-700">
                    Operación multilateral
                  </span>

                  <span
                    className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                      archived
                        ? "bg-slate-100 text-slate-600"
                        : "bg-emerald-50 text-emerald-700"
                    }`}
                  >
                    {archived ? "Archivada" : "Activa"}
                  </span>
                </div>

                <h1 className="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                  Cadena de {operation.participants_count} inmobiliarias
                </h1>

                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
                  PermuOK encontró una cadena en la que cada inmobiliaria puede
                  entregar una propiedad y recibir otra compatible.
                </p>
              </div>
            </div>

            <div className="shrink-0 sm:text-right">
              <div className="text-4xl font-black tracking-tight text-slate-900">
                {Math.round(Number(operation.score || 0))}%
              </div>

              <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                compatibilidad global
              </div>
            </div>
          </div>
        </div>

        <div className="grid gap-px bg-slate-200 sm:grid-cols-3">
          <div className="bg-white p-4">
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">
              Compatibilidad mínima
            </div>

            <div className="mt-1 text-xl font-black text-slate-900">
              {Math.round(Number(operation.minimum_edge_score || 0))}%
            </div>
          </div>

          <div className="bg-white p-4">
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">
              Promedio de tramos
            </div>

            <div className="mt-1 text-xl font-black text-slate-900">
              {Math.round(Number(operation.average_edge_score || 0))}%
            </div>
          </div>

          <div className="bg-white p-4">
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">
              Detectada
            </div>

            <div className="mt-1 text-sm font-bold text-slate-900">
              {formatDate(operation.detected_at)}
            </div>
          </div>
        </div>
      </section>

      {/* =====================================================
          TU PARTICIPACIÓN
      ====================================================== */}

      {myLeg && (
        <section className="mt-5 rounded-2xl border border-violet-200 bg-violet-50/60 px-5 py-4">
          <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-violet-700">
            Tu participación
          </div>

          <div className="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="min-w-0 flex-1">
              <div className="text-sm font-bold text-slate-500">Entregás</div>

              <div className="mt-0.5 font-black text-slate-900">
                {myLeg.offered_property_title || "una propiedad"}
              </div>
            </div>

            <div className="hidden shrink-0 text-violet-500 sm:block">
              <ArrowRightIcon />
            </div>

            <div className="min-w-0 flex-1">
              <div className="text-sm font-bold text-violet-600">Recibís</div>

              <div className="mt-0.5 font-black text-slate-900">
                {myLeg.property_title || "otra propiedad"}
              </div>
            </div>

            <div className="shrink-0 sm:text-right">
              <div className="text-xl font-black text-slate-900">
                {Math.round(Number(myLeg.score || 0))}%
              </div>

              <div className="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                tu match
              </div>
            </div>
          </div>
        </section>
      )}

      {/* =====================================================
          ESTADO COMERCIAL
      ====================================================== */}

      <section className="mt-5">
        <CommercialStatus
          operation={operation}
          myResponse={data?.my_response || null}
          contacts={contacts}
          responding={responding}
          responseError={responseError}
          onResponse={handleResponse}
        />
      </section>

      {/* =====================================================
          CIRCUITO
      ====================================================== */}

      <section className="mt-8">
        <div className="mb-4">
          <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-400">
            Circuito de la operación
          </span>

          <h2 className="mt-1 text-xl font-black text-slate-900">
            Cómo se conecta la cadena
          </h2>

          <p className="mt-1 text-sm text-slate-500">
            Cada bloque representa lo que una inmobiliaria entrega y lo que
            recibe a cambio.
          </p>
        </div>

        <CompactCircuit legs={legs} navigate={navigate} />
      </section>

      {/* =====================================================
          PUBLICACIONES
      ====================================================== */}

      <section className="mt-10">
        <div className="mb-5">
          <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-400">
            Publicaciones involucradas
          </span>

          <h2 className="mt-1 text-xl font-black text-slate-900">
            Información completa
          </h2>

          <p className="mt-1 text-sm text-slate-500">
            Consultá cada propiedad o búsqueda involucrada en esta oportunidad.
          </p>
        </div>

        <div className="mb-6 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
          <button
            type="button"
            onClick={() => setPublicationsTab("properties")}
            className={`rounded-lg px-4 py-2 text-sm font-bold transition ${
              publicationsTab === "properties"
                ? "bg-white text-primary shadow-sm"
                : "text-slate-500 hover:text-slate-900"
            }`}
          >
            Propiedades ({properties.length})
          </button>

          <button
            type="button"
            onClick={() => setPublicationsTab("searches")}
            className={`rounded-lg px-4 py-2 text-sm font-bold transition ${
              publicationsTab === "searches"
                ? "bg-white text-primary shadow-sm"
                : "text-slate-500 hover:text-slate-900"
            }`}
          >
            Búsquedas ({searches.length})
          </button>
        </div>

        {publicationsTab === "properties" && (
          <div className="space-y-5">
            {properties.map(({ property, realEstateName, isMine }) => (
              <div key={property.id}>
                <div className="mb-2 flex flex-wrap items-center gap-2">
                  <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
                    {realEstateName}
                  </span>

                  {isMine && (
                    <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-extrabold uppercase text-violet-700">
                      Tu propiedad
                    </span>
                  )}
                </div>

                <PropertyCard
                  item={property}
                  variant="dashboard"
                  detailHref={`/explore/properties/${property.id}`}
                />
              </div>
            ))}
          </div>
        )}

        {publicationsTab === "searches" && (
          <div className="space-y-5">
            {searches.map(({ search, realEstateName, isMine }) => (
              <div key={search.id}>
                <div className="mb-2 flex flex-wrap items-center gap-2">
                  <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
                    {realEstateName}
                  </span>

                  {isMine && (
                    <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-extrabold uppercase text-violet-700">
                      Tu búsqueda
                    </span>
                  )}
                </div>

                <SearchRequestCard
                  item={search}
                  variant="dashboard"
                  onView={() =>
                    navigate(`/explore/search-requests/${search.id}`)
                  }
                />
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
