import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";

import { useNavigate } from "react-router-dom";

import {
  getMultilateralCompatibilities,
} from "../api/compatibilities.api";

/* =========================================================
   HELPERS
========================================================= */

function formatMoney(
  value,
  currency,
) {
  if (
    value === null ||
    value === undefined ||
    value === ""
  ) {
    return "—";
  }

  const amount =
    Number(value);

  if (Number.isNaN(amount)) {
    return "—";
  }

  return new Intl.NumberFormat(
    "es-AR",
    {
      style: "currency",
      currency:
        currency || "USD",
      maximumFractionDigits: 0,
    },
  ).format(amount);
}

function formatDate(value) {
  if (!value) {
    return "—";
  }

  const normalized =
    String(value).replace(
      " ",
      "T",
    );

  const date = new Date(
    /Z$|[+-]\d{2}:\d{2}$/.test(
      normalized,
    )
      ? normalized
      : `${normalized}Z`,
  );

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return "—";
  }

  return new Intl.DateTimeFormat(
    "es-AR",
    {
      day: "2-digit",
      month: "short",
      year: "numeric",
    },
  ).format(date);
}

function resolveCommercialStatus(
  item,
) {
  if (
    item?.status ===
    "archived"
  ) {
    return {
      label: "Archivada",
      description:
        "La oportunidad dejó de estar disponible.",
      className:
        "bg-slate-100 text-slate-700",
      boxClass:
        "border-slate-200 bg-slate-50",
    };
  }

  if (
    item?.commercial_status ===
    "declined"
  ) {
    return {
      label: "No disponible",
      description:
        "La cadena no continuará.",
      className:
        "bg-slate-100 text-slate-700",
      boxClass:
        "border-slate-200 bg-slate-50",
    };
  }

  if (
    item?.commercial_status ===
    "confirmed"
  ) {
    return {
      label: "Confirmada",
      description:
        "Todas las partes aceptaron. Contactos habilitados.",
      className:
        "bg-emerald-100 text-emerald-800",
      boxClass:
        "border-emerald-100 bg-emerald-50",
    };
  }

  if (
    item?.my_response ===
    "interested"
  ) {
    return {
      label:
        "Interés registrado",
      description:
        "Esperando la decisión de las demás inmobiliarias.",
      className:
        "bg-blue-100 text-blue-800",
      boxClass:
        "border-blue-100 bg-blue-50",
    };
  }

  return {
    label:
      "Pendiente de respuesta",
    description:
      "Todavía tenés que indicar si querés avanzar.",
    className:
      "bg-amber-100 text-amber-800",
    boxClass:
      "border-slate-200 bg-slate-50",
  };
}

function resolveDifferenceLabel(
  item,
) {
  const difference =
    Number(
      item?.own_cash_difference ||
        0,
    );

  const currency =
    item?.own_comparison_currency ||
    "USD";

  if (!difference) {
    return {
      label: "Permuta total",
      description:
        "No requiere diferencia de dinero.",
      tone: "emerald",
    };
  }

  if (
    item?.own_direction ===
    "a_favor"
  ) {
    return {
      label: `Aportás ${formatMoney(
        difference,
        currency,
      )}`,
      description:
        "La propiedad que buscás tiene un valor superior a la que ofrecés.",
      tone: "amber",
    };
  }

  if (
    item?.own_direction ===
    "en_contra"
  ) {
    return {
      label: `Recibís ${formatMoney(
        difference,
        currency,
      )}`,
      description:
        "Tu propiedad tiene un valor superior dentro de este tramo.",
      tone: "blue",
    };
  }

  return {
    label: formatMoney(
      difference,
      currency,
    ),
    description:
      "Diferencia estimada de la operación.",
    tone: "slate",
  };
}

/* =========================================================
   ICON
========================================================= */

function CycleIcon({
  className = "h-5 w-5",
}) {
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
   SUMMARY
========================================================= */

function SummaryCard({
  value,
  label,
  description,
  accent = false,
}) {
  return (
    <div
      className={`rounded-2xl border bg-white p-4 shadow-sm ${
        accent
          ? "border-violet-200"
          : "border-slate-200"
      }`}
    >
      <div className="text-3xl font-black tracking-tight text-slate-900">
        {value}
      </div>

      <div className="mt-1 text-sm font-bold text-slate-800">
        {label}
      </div>

      <p className="mt-1 text-xs leading-relaxed text-slate-500">
        {description}
      </p>
    </div>
  );
}

/* =========================================================
   CARD
========================================================= */

function MultilateralCard({
  item,
  onOpen,
}) {
  const difference =
    resolveDifferenceLabel(
      item,
    );

  const commercial =
    resolveCommercialStatus(
      item,
    );

  const differenceStyles = {
    emerald:
      "bg-emerald-50 text-emerald-700 border-emerald-100",

    amber:
      "bg-amber-50 text-amber-700 border-amber-100",

    blue:
      "bg-blue-50 text-blue-700 border-blue-100",

    slate:
      "bg-slate-50 text-slate-700 border-slate-100",
  };

  return (
    <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
      {/* Header */}

      <div className="border-b border-slate-100 p-5">
        <div className="flex items-start justify-between gap-4">
          <div className="flex min-w-0 items-center gap-3">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
              <CycleIcon />
            </div>

            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-violet-700">
                  Operación
                  multilateral
                </span>

                <span
                  className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${commercial.className}`}
                >
                  {
                    commercial.label
                  }
                </span>
              </div>

              <h2 className="mt-1 truncate text-lg font-black tracking-tight text-slate-900">
                {item?.participants_count ||
                  0}{" "}
                inmobiliarias
                conectadas
              </h2>
            </div>
          </div>

          <div className="shrink-0 text-right">
            <div className="text-2xl font-black tracking-tight text-slate-900">
              {Math.round(
                Number(
                  item?.score ||
                    0,
                ),
              )}
              %
            </div>

            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">
              compatibilidad
            </div>
          </div>
        </div>
      </div>

      {/* Body */}

      <div className="p-5">
        <div className="grid gap-3 md:grid-cols-2">
          {/* Ofrecés */}

          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
              Vos ofrecés
            </div>

            <div className="mt-1 line-clamp-2 min-h-[40px] font-bold leading-snug text-slate-900">
              {item?.offered_property_title ||
                "Propiedad publicada"}
            </div>

            <div className="mt-1 text-sm text-slate-500">
              {formatMoney(
                item?.offered_property_price,
                item?.offered_property_currency,
              )}
            </div>
          </div>

          {/* Recibís */}

          <div className="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
            <div className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-violet-600">
              Vos recibís
            </div>

            <div className="mt-1 line-clamp-2 min-h-[40px] font-bold leading-snug text-slate-900">
              {item?.target_property_title ||
                "Propiedad objetivo"}
            </div>

            <div className="mt-1 text-sm text-slate-500">
              {formatMoney(
                item?.target_property_price,
                item?.target_property_currency,
              )}
            </div>

            {(item?.target_property_city ||
              item?.target_property_zone) && (
              <div className="mt-1 truncate text-xs text-slate-400">
                {[
                  item?.target_property_zone,
                  item?.target_property_city,
                ]
                  .filter(Boolean)
                  .join(", ")}
              </div>
            )}
          </div>
        </div>

        {/* Diferencia */}

        <div
          className={`mt-4 rounded-xl border p-3 ${
            differenceStyles[
              difference.tone
            ] ||
            differenceStyles.slate
          }`}
        >
          <div className="text-sm font-extrabold">
            {difference.label}
          </div>

          <div className="mt-0.5 text-xs opacity-80">
            {
              difference.description
            }
          </div>
        </div>

        {/* Estado */}

        <div
          className={`mt-4 rounded-xl border px-4 py-3 ${commercial.boxClass}`}
        >
          <div className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
            Estado de la
            operación
          </div>

          <div className="mt-1 text-sm font-bold text-slate-800">
            {
              commercial.description
            }
          </div>
        </div>

        {/* Footer */}

        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
          <div className="text-xs text-slate-400">
            Detectada{" "}
            {formatDate(
              item?.detected_at,
            )}
          </div>

          <button
            type="button"
            onClick={() =>
              onOpen(item)
            }
            className="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
          >
            Ver operación
          </button>
        </div>
      </div>
    </article>
  );
}

/* =========================================================
   PAGE
========================================================= */

export default function MultilateralCompatibilities() {
  const navigate =
    useNavigate();

  const [items, setItems] =
    useState([]);

  const [meta, setMeta] =
    useState(null);

  const [view, setView] =
    useState("active");

  const [page, setPage] =
    useState(1);

  const [loading, setLoading] =
    useState(true);

  const [error, setError] =
    useState("");

  /* =========================================================
     LOAD
  ========================================================= */

  const load =
    useCallback(async () => {
      try {
        setLoading(true);
        setError("");

        const data =
          await getMultilateralCompatibilities(
            {
              page,
              limit: 12,
              view,
            },
          );

        setItems(
          data?.items ?? [],
        );

        setMeta(
          data?.meta ?? null,
        );
      } catch (err) {
        console.error(
          "Error cargando oportunidades multilaterales:",
          err,
        );

        setError(
          err?.response?.data
            ?.error ||
            err?.message ||
            "No se pudieron cargar las oportunidades multilaterales.",
        );
      } finally {
        setLoading(false);
      }
    }, [page, view]);

  useEffect(() => {
    load();
  }, [load]);

  /* =========================================================
     DERIVED
  ========================================================= */

  const total = Number(
    meta?.total || 0,
  );

  const pages = Number(
    meta?.pages || 1,
  );

  const averageScore =
    useMemo(() => {
      if (!items.length) {
        return 0;
      }

      const totalScore =
        items.reduce(
          (acc, item) =>
            acc +
            Number(
              item?.score ||
                0,
            ),
          0,
        );

      return Math.round(
        totalScore /
          items.length,
      );
    }, [items]);

  const participantsCount =
    useMemo(
      () =>
        items.reduce(
          (acc, item) =>
            acc +
            Number(
              item?.participants_count ||
                0,
            ),
          0,
        ),
      [items],
    );

  /* =========================================================
     ACTIONS
  ========================================================= */

  function changeView(
    nextView,
  ) {
    if (view === nextView) {
      return;
    }

    setView(nextView);
    setPage(1);
  }

  /* =========================================================
     RENDER
  ========================================================= */

  return (
    <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      {/* =====================================================
          HEADER
      ====================================================== */}

      <div className="mb-6">
        <span className="text-xs font-bold uppercase tracking-[0.18em] text-primary">
          Match inteligente
        </span>

        <div className="mt-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
              Compatibilidades
            </h1>

            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
              Oportunidades que
              conectan tres o más
              inmobiliarias para
              completar una cadena de
              permutas compatible.
            </p>
          </div>

          <div className="shrink-0 text-sm font-medium text-slate-500">
            {total} oportunidad
            {total === 1
              ? ""
              : "es"}
          </div>
        </div>
      </div>

      {/* =====================================================
          DIRECTAS / MULTILATERALES
      ====================================================== */}

      <div className="mb-7 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
        <button
          type="button"
          onClick={() =>
            navigate(
              "/compatibilities",
            )
          }
          className="rounded-lg px-4 py-2 text-sm font-bold text-slate-500 transition hover:text-slate-900"
        >
          Directas
        </button>

        <button
          type="button"
          className="rounded-lg bg-white px-4 py-2 text-sm font-bold text-primary shadow-sm"
        >
          Multilaterales
        </button>
      </div>

      {/* =====================================================
          SUMMARY
      ====================================================== */}

      {!loading &&
        view === "active" && (
          <div className="mb-7 grid grid-cols-2 gap-3 lg:grid-cols-3">
            <SummaryCard
              value={total}
              label="Oportunidades activas"
              description="Cadenas completas disponibles actualmente."
              accent
            />

            <SummaryCard
              value={`${averageScore}%`}
              label="Compatibilidad promedio"
              description="Promedio de las oportunidades visibles."
            />

            <div className="col-span-2 lg:col-span-1">
              <SummaryCard
                value={
                  participantsCount
                }
                label="Participaciones conectadas"
                description="Inmobiliarias vinculadas en estas cadenas."
              />
            </div>
          </div>
        )}

      {/* =====================================================
          ACTIVE / HISTORY
      ====================================================== */}

      <div className="mb-6 border-b border-slate-200">
        <div className="flex gap-6">
          {[
            [
              "active",
              "Activas",
            ],
            [
              "history",
              "Historial",
            ],
          ].map(
            ([key, label]) => (
              <button
                key={key}
                type="button"
                onClick={() =>
                  changeView(
                    key,
                  )
                }
                className={`relative pb-3 text-sm font-bold transition ${
                  view === key
                    ? "text-primary"
                    : "text-slate-500 hover:text-slate-800"
                }`}
              >
                {label}

                {view === key && (
                  <span className="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-primary" />
                )}
              </button>
            ),
          )}
        </div>
      </div>

      {/* Error */}

      {error && (
        <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {error}
        </div>
      )}

      {/* =====================================================
          CONTENT
      ====================================================== */}

      {loading ? (
        <div className="grid gap-4 lg:grid-cols-2">
          {[1, 2, 3, 4].map(
            (item) => (
              <div
                key={item}
                className="h-[430px] animate-pulse rounded-2xl border border-slate-200 bg-white"
              />
            ),
          )}
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
            <CycleIcon />
          </div>

          <h2 className="mt-4 text-lg font-black text-slate-900">
            {view === "active"
              ? "Todavía no hay oportunidades multilaterales"
              : "No hay operaciones en el historial"}
          </h2>

          <p className="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-slate-500">
            {view === "active"
              ? "Cuando PermuOK encuentre una cadena viable entre tres o más inmobiliarias, aparecerá automáticamente acá."
              : "Las oportunidades multilaterales que dejen de estar disponibles aparecerán en esta sección."}
          </p>
        </div>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {items.map(
            (item) => (
              <MultilateralCard
                key={item.id}
                item={item}
                onOpen={(
                  operation,
                ) =>
                  navigate(
                    `/compatibilities/multilateral/${operation.id}`,
                  )
                }
              />
            ),
          )}
        </div>
      )}

      {/* =====================================================
          PAGINATION
      ====================================================== */}

      {!loading &&
        pages > 1 && (
          <div className="mt-8 flex items-center justify-center gap-3">
            <button
              type="button"
              disabled={
                page <= 1
              }
              onClick={() =>
                setPage(
                  (current) =>
                    Math.max(
                      1,
                      current -
                        1,
                    ),
                )
              }
              className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Anterior
            </button>

            <span className="text-sm font-medium text-slate-500">
              Página {page} de{" "}
              {pages}
            </span>

            <button
              type="button"
              disabled={
                page >= pages
              }
              onClick={() =>
                setPage(
                  (current) =>
                    Math.min(
                      pages,
                      current +
                        1,
                    ),
                )
              }
              className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Siguiente
            </button>
          </div>
        )}
    </div>
  );
}