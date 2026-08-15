import { useMemo } from "react";

const STAGE_OPTIONS = [
  { value: "", label: "Seleccionar etapa" },
  { value: "land", label: "Lote / tierra" },
  { value: "prelaunch", label: "Prelanzamiento" },
  { value: "launch", label: "Lanzamiento" },
  { value: "presale", label: "Preventa" },
  { value: "under_construction", label: "En construcción" },
  { value: "finished", label: "Finalizado" },
];

const CURRENCY_OPTIONS = [
  { value: "USD", label: "USD" },
  { value: "ARS", label: "ARS" },
];

function inputClass(hasError) {
  return `w-full rounded-xl border px-4 py-3 text-sm outline-none transition no-spinner ${
    hasError
      ? "border-rose-400 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10"
      : "border-slate-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
  } bg-white text-slate-900`;
}

function isValidUrl(value) {
  if (!value) return true;
  try {
    new URL(value);
    return true;
  } catch {
    return false;
  }
}

export default function DevelopmentCommercialSection({ form, setField }) {
  function handleNumberChange(name, value) {
    setField(name, value === "" ? "" : Number(value));
  }

  const errors = useMemo(() => {
    const total = Number(form.total_units);
    const available = Number(form.available_units);
    const priceFrom = Number(form.price_from);
    const priceTo = Number(form.price_to);

    return {
      price:
        priceFrom && priceTo && priceFrom > priceTo
          ? "El precio mínimo no puede ser mayor al máximo"
          : null,

      units:
        total && available && available > total
          ? "Las unidades disponibles no pueden superar las totales"
          : null,

      total_units: total < 0 ? "No puede ser negativo" : null,

      available_units: available < 0 ? "No puede ser negativo" : null,

      developer_name:
        form.developer_name?.length > 180 ? "Máximo 180 caracteres" : null,

      construction_company:
        form.construction_company?.length > 180
          ? "Máximo 180 caracteres"
          : null,

      whatsapp_url: !isValidUrl(form.whatsapp_url) ? "URL inválida" : null,

      brochure_url: !isValidUrl(form.brochure_url) ? "URL inválida" : null,

      video_url: !isValidUrl(form.video_url) ? "URL inválida" : null,
    };
  }, [form]);

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Comercialización y datos del proyecto
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Completá etapa, entrega estimada, precios, unidades y datos
          comerciales principales del desarrollo.
        </p>
      </div>

      <div className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Etapa del desarrollo
            </label>
            <select
              value={form.development_stage || ""}
              onChange={(e) => setField("development_stage", e.target.value)}
              className={inputClass()}
            >
              {STAGE_OPTIONS.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Entrega estimada
            </label>
            <input
              type="date"
              value={form.delivery_date_estimated || ""}
              onChange={(e) =>
                setField("delivery_date_estimated", e.target.value)
              }
              className={inputClass()}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Desarrolladora
            </label>
            <input
              type="text"
              maxLength={180}
              placeholder="Ej. Grupo desarrollador"
              value={form.developer_name || ""}
              onChange={(e) => setField("developer_name", e.target.value)}
              className={inputClass(errors.developer_name)}
            />
            {errors.developer_name && (
              <p className="text-xs text-rose-500 mt-1">
                {errors.developer_name}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              Constructora
            </label>
            <input
              type="text"
              maxLength={180}
              placeholder="Ej. Constructora S.A."
              value={form.construction_company || ""}
              onChange={(e) =>
                setField("construction_company", e.target.value)
              }
              className={inputClass(errors.construction_company)}
            />
            {errors.construction_company && (
              <p className="text-xs text-rose-500 mt-1">
                {errors.construction_company}
              </p>
            )}
          </div>
        </div>

        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:p-5">
          <h3 className="text-sm font-semibold text-slate-800 mb-4">
            Valores y disponibilidad
          </h3>

          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input
              type="number"
              placeholder="Precio desde"
              value={form.price_from ?? ""}
              onChange={(e) =>
                handleNumberChange("price_from", e.target.value)
              }
              className={inputClass(errors.price)}
            />

            <input
              type="number"
              placeholder="Precio hasta"
              value={form.price_to ?? ""}
              onChange={(e) => handleNumberChange("price_to", e.target.value)}
              className={inputClass(errors.price)}
            />

            <select
              value={form.currency || "USD"}
              onChange={(e) => setField("currency", e.target.value)}
              className={inputClass()}
            >
              {CURRENCY_OPTIONS.map((c) => (
                <option key={c.value} value={c.value}>
                  {c.label}
                </option>
              ))}
            </select>

            <input
              type="number"
              placeholder="Unidades totales"
              value={form.total_units ?? ""}
              onChange={(e) =>
                handleNumberChange("total_units", e.target.value)
              }
              className={inputClass(errors.total_units || errors.units)}
            />

            <input
              type="number"
              placeholder="Disponibles"
              value={form.available_units ?? ""}
              onChange={(e) =>
                handleNumberChange("available_units", e.target.value)
              }
              className={inputClass(errors.available_units || errors.units)}
            />
          </div>

          {(errors.price || errors.units) && (
            <p className="text-xs text-rose-500 mt-3">
              {errors.price || errors.units}
            </p>
          )}
        </div>

        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:p-5">
          <h3 className="text-sm font-semibold text-slate-800 mb-4">
            Links comerciales
          </h3>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <input
                type="text"
                placeholder="Link de WhatsApp"
                value={form.whatsapp_url || ""}
                onChange={(e) => setField("whatsapp_url", e.target.value)}
                className={inputClass(errors.whatsapp_url)}
              />
              {errors.whatsapp_url && (
                <p className="text-xs text-rose-500 mt-1">
                  {errors.whatsapp_url}
                </p>
              )}
            </div>

            <div>
              <input
                type="text"
                placeholder="Link al brochure"
                value={form.brochure_url || ""}
                onChange={(e) => setField("brochure_url", e.target.value)}
                className={inputClass(errors.brochure_url)}
              />
              {errors.brochure_url && (
                <p className="text-xs text-rose-500 mt-1">
                  {errors.brochure_url}
                </p>
              )}
            </div>

            <div>
              <input
                type="text"
                placeholder="Link de video"
                value={form.video_url || ""}
                onChange={(e) => setField("video_url", e.target.value)}
                className={inputClass(errors.video_url)}
              />
              {errors.video_url && (
                <p className="text-xs text-rose-500 mt-1">
                  {errors.video_url}
                </p>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}