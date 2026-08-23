import { useEffect, useState } from "react";
import { SEARCH_REQUEST_CURRENCIES } from "../utils";
import { Icon } from "../../../ui/icons/Index";
import { getMyPublishedProperties } from "../../properties/api/properties.api";

function Field({ label, children, hint = "" }) {
  return (
    <div>
      <label className="block text-sm font-medium text-slate-700 mb-1.5">
        {label}
      </label>
      {children}
      {hint ? <p className="mt-1 text-xs text-slate-500">{hint}</p> : null}
    </div>
  );
}

function ModeCard({ checked, title, description, onChange }) {
  return (
    <label
      className={`rounded-2xl border p-4 cursor-pointer transition ${
        checked
          ? "border-emerald-500 bg-emerald-50"
          : "border-slate-200 bg-white hover:bg-slate-50"
      }`}
    >
      <div className="flex items-start gap-3">
        <input
          type="checkbox"
          checked={!!checked}
          onChange={onChange}
          className="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
        />
        <div>
          <p className="text-sm font-bold text-slate-900">{title}</p>
          <p className="text-xs text-slate-500 mt-1">{description}</p>
        </div>
      </div>
    </label>
  );
}

function SectionBlock({ title, description = "", children }) {
  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-sm font-bold text-slate-900">{title}</h3>
        {description ? (
          <p className="mt-1 text-xs text-slate-500">{description}</p>
        ) : null}
      </div>
      {children}
    </div>
  );
}

export default function SearchRequestPaymentSection({ form, setField }) {
  const acceptsCash = !!form.payment_mode_cash;
  const acceptsSwap = !!form.payment_mode_swap;
  const acceptsBoth = acceptsCash && acceptsSwap;

  const [properties, setProperties] = useState([]);
  const [propertiesLoading, setPropertiesLoading] = useState(false);

  useEffect(() => {
    if (!acceptsSwap) {
      return;
    }

    let cancelled = false;

    async function loadProperties() {
      try {
        setPropertiesLoading(true);

        const result = await getMyPublishedProperties();

        if (!cancelled) {
          setProperties(Array.isArray(result?.items) ? result.items : []);
        }
      } finally {
        if (!cancelled) {
          setPropertiesLoading(false);
        }
      }
    }

    loadProperties();

    return () => {
      cancelled = true;
    };
  }, [acceptsSwap]);

  function handleCashChange(checked) {
    setField("payment_mode_cash", checked);
  }

  function handleSwapChange(checked) {
    setField("payment_mode_swap", checked);

    if (!checked) {
      setField("cash_difference_max", "");
      setField("exchange_property_id", "");
    }
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Modalidad y referencia de valor
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Definí cómo podría resolverse la operación y en qué rango de valor se
          mueve esta búsqueda.
        </p>
      </div>

      <div className="space-y-6">
        <div className="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-4">
          <div className="flex items-start gap-3">
            <div className="mt-0.5 text-emerald-700">
              <Icon name="info" size={18} />
            </div>

            <div>
              <p className="text-sm font-semibold text-emerald-900">
                El rango de valor funciona como referencia para encontrar
                mejores coincidencias.
              </p>
              <p className="mt-1 text-xs sm:text-sm text-emerald-800">
                No representa necesariamente dinero en efectivo. También sirve
                para búsquedas con permuta, tomando como referencia el valor
                estimado de la operación.
              </p>
            </div>
          </div>
        </div>

        <SectionBlock
          title="Cómo podría resolverse la operación"
          description="Podés marcar una sola opción o combinar ambas si se acepta permuta con diferencia en dinero."
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <ModeCard
              checked={acceptsCash}
              title="Pago con dinero"
              description="La búsqueda puede resolverse total o parcialmente con dinero."
              onChange={(e) => handleCashChange(e.target.checked)}
            />

            <ModeCard
              checked={acceptsSwap}
              title="Acepta permuta"
              description="Se puede evaluar una propuesta con otra propiedad o activo como parte de la operación."
              onChange={(e) => handleSwapChange(e.target.checked)}
            />
          </div>
        </SectionBlock>
        {acceptsSwap ? (
          <SectionBlock
            title="Propiedad ofrecida en permuta"
            description="Seleccioná una propiedad publicada de tu inmobiliaria que forma parte de la propuesta."
          >
            <Field label="Propiedad ofrecida">
              <select
                value={form.exchange_property_id || ""}
                onChange={(e) =>
                  setField("exchange_property_id", e.target.value)
                }
                disabled={propertiesLoading}
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              >
                <option value="">
                  {propertiesLoading
                    ? "Cargando propiedades..."
                    : "Seleccionar propiedad"}
                </option>

                {properties.map((property) => (
                  <option key={property.id} value={property.id}>
                    {property.title || `Propiedad #${property.id}`} ·{" "}
                    {property.currency} {property.price}
                  </option>
                ))}
              </select>
            </Field>
          </SectionBlock>
        ) : null}
        <SectionBlock
          title="Rango de valor estimado"
          description="Usalo como referencia del valor de la propiedad buscada o del negocio esperado."
        >
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Field label="Moneda">
              <select
                value={form.currency}
                onChange={(e) => setField("currency", e.target.value)}
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              >
                {SEARCH_REQUEST_CURRENCIES.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </Field>

            <Field label="Valor estimado mínimo" hint="Opcional">
              <input
                type="number"
                min="0"
                value={form.min_value}
                onChange={(e) => setField("min_value", e.target.value)}
                placeholder="Ej. 80000"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Valor estimado máximo" hint="Opcional">
              <input
                type="number"
                min="0"
                value={form.max_value}
                onChange={(e) => setField("max_value", e.target.value)}
                placeholder="Ej. 150000"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>
          </div>
        </SectionBlock>

        {acceptsBoth ? (
          <SectionBlock
            title="Diferencia en dinero"
            description="Completá esto solo si la búsqueda admite permuta más una diferencia en efectivo."
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Diferencia máxima en dinero" hint="Opcional">
                <input
                  type="number"
                  min="0"
                  value={form.cash_difference_max}
                  onChange={(e) =>
                    setField("cash_difference_max", e.target.value)
                  }
                  placeholder="Ej. 30000"
                  className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                />
              </Field>

              <Field label="Moneda de la diferencia">
                <select
                  value={form.cash_difference_currency}
                  onChange={(e) =>
                    setField("cash_difference_currency", e.target.value)
                  }
                  className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                >
                  {SEARCH_REQUEST_CURRENCIES.map((item) => (
                    <option key={item.value} value={item.value}>
                      {item.label}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
          </SectionBlock>
        ) : null}

        <SectionBlock
          title="Observaciones"
          description="Podés aclarar perfil del cliente, plazos, condiciones particulares o cómo imaginan la negociación."
        >
          <Field label="">
            <textarea
              rows={4}
              value={form.notes}
              onChange={(e) => setField("notes", e.target.value)}
              placeholder="Ej. Tengo un Mercedes Benz 2015 que podría entrar como parte de pago."
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition resize-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </Field>
        </SectionBlock>
      </div>
    </section>
  );
}
