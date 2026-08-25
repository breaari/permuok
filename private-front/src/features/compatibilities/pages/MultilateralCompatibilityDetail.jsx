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

function buildLocation(zone, city) {
  return [zone, city].filter(Boolean).join(", ");
}

function getDifferenceMeta(leg) {
  const difference = Number(leg?.cash_difference || 0);
  const currency = leg?.comparison_currency || "USD";

  if (!difference) {
    return {
      label: "Permuta total",
      description: "No requiere diferencia de dinero.",
      className: "border-emerald-200 bg-emerald-50 text-emerald-800",
    };
  }

  if (leg?.direction === "a_favor") {
    return {
      label: `Aporta ${formatMoney(difference, currency)}`,
      description:
        "Esta inmobiliaria debe sumar dinero para completar este tramo.",
      className: "border-amber-200 bg-amber-50 text-amber-800",
    };
  }

  if (leg?.direction === "en_contra") {
    return {
      label: `Recibe ${formatMoney(difference, currency)}`,
      description:
        "Esta inmobiliaria recibe una diferencia de dinero en este tramo.",
      className: "border-blue-200 bg-blue-50 text-blue-800",
    };
  }

  return {
    label: formatMoney(difference, currency),
    description: "Diferencia estimada para completar el tramo.",
    className: "border-slate-200 bg-slate-50 text-slate-700",
  };
}

/* =========================================================
   ICONOS
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

function ArrowDownIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className="h-5 w-5"
      aria-hidden="true"
    >
      <path d="M12 5v14" />
      <path d="m19 12-7 7-7-7" />
    </svg>
  );
}

function CycleIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      className="h-5 w-5"
      aria-hidden="true"
    >
      <path d="M20 7h-5V2" />
      <path d="M4 17h5v5" />
      <path d="M5.2 9A8 8 0 0 1 18.6 5.6L20 7" />
      <path d="M18.8 15A8 8 0 0 1 5.4 18.4L4 17" />
    </svg>
  );
}

function CompactCircuit({ legs, navigate }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="grid gap-3 lg:grid-cols-3">
        {legs.map((leg, index) => {
          const difference = getDifferenceMeta(leg);

          return (
            <div
              key={leg.id || `${leg.position}-${index}`}
              className={`relative rounded-2xl border p-4 ${
                leg?.is_my_leg
                  ? "border-violet-300 bg-violet-50/50 ring-2 ring-violet-100"
                  : "border-slate-200 bg-slate-50"
              }`}
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
                      Inmobiliaria {index + 1}
                    </span>

                    {leg?.is_my_leg && (
                      <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-extrabold uppercase text-violet-700">
                        Tu inmobiliaria
                      </span>
                    )}
                  </div>

                  <h3 className="mt-1 text-lg font-black text-slate-900">
                    {leg?.source_real_estate_name || "Inmobiliaria"}
                  </h3>
                </div>

                <div className="text-right">
                  <div className="text-xl font-black text-slate-900">
                    {Math.round(Number(leg?.score || 0))}%
                  </div>

                  <div className="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                    match
                  </div>
                </div>
              </div>

              <div className="mt-4 space-y-3">
                <button
                  type="button"
                  onClick={() =>
                    navigate(`/explore/properties/${leg.offered_property_id}`)
                  }
                  className="block w-full rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-slate-300"
                >
                  <div className="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">
                    Entrega
                  </div>

                  <div className="mt-1 line-clamp-2 text-sm font-bold text-slate-900">
                    {leg?.offered_property_title || "Propiedad"}
                  </div>
                </button>

                <div className="flex justify-center text-violet-500">
                  <ArrowDownIcon />
                </div>

                <button
                  type="button"
                  onClick={() =>
                    navigate(`/explore/properties/${leg.property_id}`)
                  }
                  className="block w-full rounded-xl border border-violet-200 bg-violet-50 p-3 text-left transition hover:border-violet-300"
                >
                  <div className="text-[9px] font-extrabold uppercase tracking-wider text-violet-600">
                    Recibe
                  </div>

                  <div className="mt-1 line-clamp-2 text-sm font-bold text-slate-900">
                    {leg?.property_title || "Propiedad"}
                  </div>

                  <div className="mt-1 text-[11px] text-slate-500">
                    de {leg?.target_real_estate_name || "otra inmobiliaria"}
                  </div>
                </button>
              </div>

              <div
                className={`mt-4 rounded-xl border px-3 py-2 ${difference.className}`}
              >
                <div className="text-xs font-extrabold">{difference.label}</div>
              </div>
            </div>
          );
        })}
      </div>

      {legs.length > 1 && (
        <div className="mt-4 flex items-center justify-center gap-2 rounded-xl border border-dashed border-violet-200 bg-violet-50/40 px-4 py-3 text-xs font-bold text-violet-700">
          <CycleIcon />
          El último intercambio vuelve al primero y completa el circuito
        </div>
      )}
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
      <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <div className="h-8 w-52 animate-pulse rounded bg-slate-200" />

        <div className="mt-6 h-40 animate-pulse rounded-2xl border border-slate-200 bg-white" />

        <div className="mt-6 space-y-4">
          {[1, 2, 3].map((item) => (
            <div
              key={item}
              className="h-72 animate-pulse rounded-2xl border border-slate-200 bg-white"
            />
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
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

  const archived = operation?.status === "archived";

  return (
    <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
      {/* VOLVER */}
      <button
        type="button"
        onClick={() => navigate("/compatibilities/multilateral")}
        className="mb-5 inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-900"
      >
        <ArrowLeftIcon />
        Volver a multilaterales
      </button>

      {/* HEADER */}
      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 p-5 sm:p-6">
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
                  PermuOK detectó una cadena en la que cada inmobiliaria puede
                  entregar una propiedad y recibir otra compatible.
                </p>
              </div>
            </div>

            <div className="shrink-0 sm:text-right">
              <div className="text-4xl font-black tracking-tight text-slate-900">
                {Math.round(Number(operation.score || 0))}%
              </div>

              <div className="text-xs font-bold uppercase tracking-wide text-slate-400">
                compatibilidad global
              </div>
            </div>
          </div>
        </div>

        <div className="grid gap-px bg-slate-200 sm:grid-cols-3">
          <div className="bg-white p-4">
            <div className="text-xs font-bold text-slate-400">
              Compatibilidad mínima
            </div>

            <div className="mt-1 text-xl font-black text-slate-900">
              {Math.round(Number(operation.minimum_edge_score || 0))}%
            </div>
          </div>

          <div className="bg-white p-4">
            <div className="text-xs font-bold text-slate-400">
              Promedio de tramos
            </div>

            <div className="mt-1 text-xl font-black text-slate-900">
              {Math.round(Number(operation.average_edge_score || 0))}%
            </div>
          </div>

          <div className="bg-white p-4">
            <div className="text-xs font-bold text-slate-400">Detectada</div>

            <div className="mt-1 text-sm font-bold text-slate-900">
              {formatDate(operation.detected_at)}
            </div>
          </div>
        </div>
      </div>

      {/* TU PARTICIPACIÓN */}
      {myLeg && (
        <div className="mt-6 rounded-2xl border border-violet-200 bg-violet-50/60 p-5">
          <div className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-violet-700">
            Tu participación
          </div>

          <h2 className="mt-1 text-lg font-black text-slate-900">
            Entregás {myLeg.offered_property_title || "una propiedad"} y recibís{" "}
            {myLeg.property_title || "otra propiedad"}
          </h2>

          <p className="mt-1 text-sm text-slate-600">
            Tu tramo tiene una compatibilidad de{" "}
            <strong>{Math.round(Number(myLeg.score || 0))}%</strong>.
          </p>
        </div>
      )}
      {/* DECISIÓN COMERCIAL */}
      {operation.status === "detected" && (
        <section className="mt-6">
          {operation.commercial_status === "open" && !data?.my_response && (
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-slate-400">
                ¿Querés avanzar?
              </div>

              <h2 className="mt-1 text-lg font-black text-slate-900">
                Indicá si esta oportunidad te interesa
              </h2>

              <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
                Tu respuesta es privada. Los datos de contacto sólo se
                habilitarán si todas las inmobiliarias de la cadena manifiestan
                interés.
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
                  onClick={() => handleResponse("interested")}
                  className="rounded-xl bg-primary px-5 py-3 text-sm font-extrabold text-white transition hover:opacity-90 disabled:opacity-50"
                >
                  {responding ? "Guardando..." : "Me interesa avanzar"}
                </button>

                <button
                  type="button"
                  disabled={responding}
                  onClick={() => handleResponse("declined")}
                  className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                >
                  No me interesa
                </button>
              </div>
            </div>
          )}

          {operation.commercial_status === "open" &&
            data?.my_response === "interested" && (
              <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">
                  Respuesta registrada
                </div>

                <h2 className="mt-1 text-lg font-black text-slate-900">
                  Te interesa avanzar
                </h2>

                <p className="mt-2 text-sm text-slate-600">
                  Estamos esperando la decisión de las demás inmobiliarias. Sus
                  respuestas permanecen privadas.
                </p>
              </div>
            )}

          {operation.commercial_status === "confirmed" && (
            <div className="rounded-2xl border border-emerald-300 bg-emerald-50 p-5">
              <div className="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">
                Cadena confirmada
              </div>

              <h2 className="mt-1 text-xl font-black text-slate-900">
                Todas las partes manifestaron interés
              </h2>

              <p className="mt-2 text-sm text-slate-600">
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
                      <div className="text-xs font-extrabold uppercase tracking-wide text-emerald-700">
                        {contact.real_estate_name}
                      </div>

                      <div className="mt-1 font-black text-slate-900">
                        {[contact.first_name, contact.last_name]
                          .filter(Boolean)
                          .join(" ") || "Contacto responsable"}
                      </div>

                      {contact.phone && (
                        <a
                          href={`tel:${contact.phone}`}
                          className="mt-3 block text-sm font-bold text-primary hover:underline"
                        >
                          {contact.phone}
                        </a>
                      )}

                      {contact.email && (
                        <a
                          href={`mailto:${contact.email}`}
                          className="mt-1 block break-all text-sm font-medium text-slate-600 hover:text-primary"
                        >
                          {contact.email}
                        </a>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {operation.commercial_status === "declined" && (
            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
              <h2 className="text-lg font-black text-slate-900">
                Esta oportunidad ya no se encuentra disponible
              </h2>

              <p className="mt-1 text-sm text-slate-500">
                La cadena no continuará por el momento.
              </p>
            </div>
          )}
        </section>
      )}
      {/* CIRCUITO */}
      <section className="mt-8">
        <div className="mb-5">
          <span className="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
            Circuito completo
          </span>

          <h2 className="mt-1 text-xl font-black text-slate-900">
            Cómo se completa la operación
          </h2>

          <p className="mt-1 text-sm text-slate-500">
            Cada tramo representa lo que una inmobiliaria entrega y lo que
            recibiría a cambio.
          </p>
        </div>

        <div>
          <section className="mt-8">
            <div className="mb-5">
              <span className="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
                Circuito de la operación
              </span>

              <h2 className="mt-1 text-xl font-black text-slate-900">
                Cómo se conecta la cadena
              </h2>

              <p className="mt-1 text-sm text-slate-500">
                Cada inmobiliaria entrega una propiedad y recibe la propiedad
                que necesita de la siguiente parte.
              </p>
            </div>

            <CompactCircuit legs={legs} navigate={navigate} />
          </section>
        </div>
        <section className="mt-8">
          <div className="mb-5">
            <span className="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
              Publicaciones involucradas
            </span>

            <h2 className="mt-1 text-xl font-black text-slate-900">
              Revisá la información completa
            </h2>

            <p className="mt-1 text-sm text-slate-500">
              Cada propiedad y búsqueda aparece una sola vez.
            </p>
          </div>

          <div className="mb-5 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
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
            <div className="space-y-4">
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
            <div className="space-y-4">
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
        {legs.length > 1 && (
          <div className="mt-3 rounded-2xl border border-dashed border-violet-300 bg-violet-50/40 px-5 py-4 text-center">
            <div className="flex items-center justify-center gap-2 text-sm font-extrabold text-violet-700">
              <CycleIcon />
              El último tramo completa el circuito con el primero
            </div>

            <p className="mt-1 text-xs text-slate-500">
              La operación sólo es viable si todos los tramos de la cadena se
              mantienen compatibles.
            </p>
          </div>
        )}
      </section>
    </div>
  );
}
