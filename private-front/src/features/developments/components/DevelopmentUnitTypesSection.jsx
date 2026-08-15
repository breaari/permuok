import { useMemo, useState } from "react";
import { Icon } from "../../../ui/icons/Index";
import {
  createDevelopmentUnitType,
  deleteDevelopmentUnitType,
  updateDevelopmentUnitType,
} from "../api/developments.api";

const UNIT_TYPE_OPTIONS = [
  { value: "apartment", label: "Departamento" },
  { value: "house", label: "Casa" },
  { value: "land", label: "Lote" },
  { value: "commercial", label: "Local" },
  { value: "office", label: "Oficina" },
  { value: "warehouse", label: "Depósito" },
  { value: "garage", label: "Cochera" },
  { value: "other", label: "Otro" },
];

const CURRENCY_OPTIONS = [
  { value: "USD", label: "USD" },
  { value: "ARS", label: "ARS" },
];

const LABEL_MAX = 120;

const initialForm = {
  unit_type: "apartment",
  label: "",
  rooms: "",
  bedrooms: "",
  bathrooms: "",
  garages: "",
  area_from: "",
  area_to: "",
  price_from: "",
  price_to: "",
  currency: "USD",
  available_units: "",
};

function Field({ label, children, error }) {
  return (
    <div>
      <label className="mb-1.5 block text-sm font-medium text-slate-700">
        {label}
      </label>
      {children}
      {error ? <p className="mt-1 text-xs text-rose-500">{error}</p> : null}
    </div>
  );
}

function fieldClass(error, compact = false) {
  return [
    "no-spinner w-full rounded-xl border bg-white text-sm text-slate-900 outline-none transition",
    compact ? "px-3 py-2" : "px-4 py-3",
    error
      ? "border-rose-400 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10"
      : "border-slate-300 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10",
  ].join(" ");
}

function Badge({ children }) {
  return (
    <span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
      {children}
    </span>
  );
}

function InfoBox({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
      <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
        {label}
      </p>
      <p className="mt-1 text-sm font-semibold text-slate-900">{value}</p>
    </div>
  );
}

function toNumberOrNull(value) {
  if (value === "" || value === null || value === undefined) return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function normalizeFormPayload(form) {
  return {
    unit_type: form.unit_type,
    label: String(form.label || "").trim(),
    rooms: form.rooms,
    bedrooms: form.bedrooms,
    bathrooms: form.bathrooms,
    garages: form.garages,
    area_from: form.area_from,
    area_to: form.area_to,
    price_from: form.price_from,
    price_to: form.price_to,
    currency: form.currency,
    available_units: form.available_units,
  };
}

function validateForm(form) {
  const errors = {};

  const label = String(form.label || "").trim();
  const rooms = toNumberOrNull(form.rooms);
  const bedrooms = toNumberOrNull(form.bedrooms);
  const bathrooms = toNumberOrNull(form.bathrooms);
  const garages = toNumberOrNull(form.garages);
  const availableUnits = toNumberOrNull(form.available_units);
  const areaFrom = toNumberOrNull(form.area_from);
  const areaTo = toNumberOrNull(form.area_to);
  const priceFrom = toNumberOrNull(form.price_from);
  const priceTo = toNumberOrNull(form.price_to);

  if (!form.unit_type) {
    errors.unit_type = "Seleccioná un tipo de unidad.";
  }

  if (label.length > LABEL_MAX) {
    errors.label = `Máximo ${LABEL_MAX} caracteres.`;
  }

  const numericFields = [
    ["rooms", rooms, "Ambientes"],
    ["bedrooms", bedrooms, "Dormitorios"],
    ["bathrooms", bathrooms, "Baños"],
    ["garages", garages, "Cocheras"],
    ["available_units", availableUnits, "Disponibles"],
    ["area_from", areaFrom, "Superficie desde"],
    ["area_to", areaTo, "Superficie hasta"],
    ["price_from", priceFrom, "Precio desde"],
    ["price_to", priceTo, "Precio hasta"],
  ];

  numericFields.forEach(([key, value, labelText]) => {
    if (value !== null && value < 0) {
      errors[key] = `${labelText} no puede ser negativo.`;
    }
  });

  if (rooms !== null && rooms > 50) {
    errors.rooms = "Revisá este valor. Parece demasiado alto.";
  }

  if (bedrooms !== null && bedrooms > 30) {
    errors.bedrooms = "Revisá este valor. Parece demasiado alto.";
  }

  if (bathrooms !== null && bathrooms > 30) {
    errors.bathrooms = "Revisá este valor. Parece demasiado alto.";
  }

  if (garages !== null && garages > 50) {
    errors.garages = "Revisá este valor. Parece demasiado alto.";
  }

  if (availableUnits !== null && availableUnits > 10000) {
    errors.available_units = "Revisá este valor. Parece demasiado alto.";
  }

  if (rooms !== null && bedrooms !== null && bedrooms > rooms) {
    errors.bedrooms = "Los dormitorios no deberían superar los ambientes.";
  }

  if (rooms !== null && bathrooms !== null && bathrooms > rooms + 2) {
    errors.bathrooms = "Revisá la cantidad de baños para esta tipología.";
  }

  if (areaFrom !== null && areaTo !== null && areaFrom > areaTo) {
    errors.area_from = "La superficie mínima no puede ser mayor a la máxima.";
    errors.area_to = "La superficie máxima debe ser mayor o igual a la mínima.";
  }

  if (priceFrom !== null && priceTo !== null && priceFrom > priceTo) {
    errors.price_from = "El precio mínimo no puede ser mayor al máximo.";
    errors.price_to = "El precio máximo debe ser mayor o igual al mínimo.";
  }

  if (!["ARS", "USD"].includes(form.currency)) {
    errors.currency = "Seleccioná una moneda válida.";
  }

  return errors;
}

function unitTypeLabel(value) {
  return (
    UNIT_TYPE_OPTIONS.find((item) => item.value === value)?.label ||
    value ||
    "—"
  );
}

function formatMoney(value, currency = "USD") {
  const amount = Number(value || 0);

  if (!Number.isFinite(amount) || amount <= 0) {
    return "—";
  }

  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(amount);
}

export default function DevelopmentUnitTypesSection({
  developmentId,
  unitTypes = [],
  setUnitTypes,
  onError,
  onSuccess,
}) {
  const [form, setForm] = useState(initialForm);
  const [editingId, setEditingId] = useState(null);
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});

  const isEditing = Boolean(editingId);
  const labelLength = String(form.label || "").length;

  const sortedItems = useMemo(() => {
    return Array.isArray(unitTypes) ? unitTypes : [];
  }, [unitTypes]);

  function clearMessages() {
    onError?.("");
    onSuccess?.("");
  }

  function setField(field, value) {
    setForm((prev) => ({
      ...prev,
      [field]: value,
    }));

    setFieldErrors((prev) => ({
      ...prev,
      [field]: null,
    }));
  }

  function resetForm() {
    setForm(initialForm);
    setEditingId(null);
    setFieldErrors({});
  }

  function loadItemIntoForm(item) {
    setEditingId(item.id);
    setFieldErrors({});
    setForm({
      unit_type: item?.unit_type ?? "apartment",
      label: item?.label ?? "",
      rooms: item?.rooms ?? "",
      bedrooms: item?.bedrooms ?? "",
      bathrooms: item?.bathrooms ?? "",
      garages: item?.garages ?? "",
      area_from: item?.area_from ?? "",
      area_to: item?.area_to ?? "",
      price_from: item?.price_from ?? "",
      price_to: item?.price_to ?? "",
      currency: item?.currency ?? "USD",
      available_units: item?.available_units ?? "",
    });
  }

  async function handleSubmit() {
    clearMessages();

    if (!developmentId) {
      onError?.("Primero guardá el desarrollo para poder cargar tipologías.");
      return;
    }

    const errors = validateForm(form);
    setFieldErrors(errors);

    if (Object.keys(errors).length) {
      onError?.("Revisá los campos marcados antes de guardar la tipología.");
      return;
    }

    try {
      setSaving(true);

      const payload = normalizeFormPayload(form);

      let response;
      if (isEditing) {
        response = await updateDevelopmentUnitType(editingId, payload);
        onSuccess?.("Tipología actualizada correctamente.");
      } else {
        response = await createDevelopmentUnitType(developmentId, payload);
        onSuccess?.("Tipología agregada correctamente.");
      }

      setUnitTypes(Array.isArray(response?.items) ? response.items : []);
      resetForm();
    } catch (err) {
      onError?.(err?.message || "No se pudo guardar la tipología.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(item) {
    clearMessages();

    if (!item?.id) return;

    try {
      setSaving(true);

      const response = await deleteDevelopmentUnitType(item.id);
      setUnitTypes(Array.isArray(response?.items) ? response.items : []);
      onSuccess?.("Tipología eliminada correctamente.");

      if (editingId === item.id) {
        resetForm();
      }
    } catch (err) {
      onError?.(err?.message || "No se pudo eliminar la tipología.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Tipologías
        </h2>
        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Definí los tipos de unidades que ofrece el desarrollo. Esta sección es
          necesaria para poder publicarlo.
        </p>
      </div>

      <div className="space-y-8">
        <div>
          <div className="mb-5 flex items-start justify-between gap-3">
            <div>
              <h3 className="text-sm font-bold text-slate-900">
                {isEditing ? "Editar tipología" : "Agregar tipología"}
              </h3>
              <p className="mt-1 text-xs text-slate-500">
                Completá solo los datos que aporten valor comercial.
              </p>
            </div>

            {isEditing ? (
              <button
                type="button"
                onClick={resetForm}
                className="text-xs font-semibold text-slate-600 hover:text-slate-900"
              >
                Cancelar
              </button>
            ) : null}
          </div>

          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Tipo de unidad" error={fieldErrors.unit_type}>
                <select
                  value={form.unit_type}
                  onChange={(e) => setField("unit_type", e.target.value)}
                  className={fieldClass(fieldErrors.unit_type)}
                >
                  {UNIT_TYPE_OPTIONS.map((item) => (
                    <option key={item.value} value={item.value}>
                      {item.label}
                    </option>
                  ))}
                </select>
              </Field>

              <Field
                label={`Nombre comercial (${labelLength}/${LABEL_MAX})`}
                error={fieldErrors.label}
              >
                <input
                  type="text"
                  maxLength={LABEL_MAX}
                  value={form.label}
                  onChange={(e) => setField("label", e.target.value)}
                  placeholder="Ej. Semipiso Premium"
                  className={fieldClass(fieldErrors.label)}
                />
              </Field>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
              <p className="mb-4 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                Detalles de la unidad
              </p>

              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
                <Field label="Ambientes" error={fieldErrors.rooms}>
                  <input
                    type="number"
                    min="0"
                    step="0.5"
                    value={form.rooms}
                    onChange={(e) => setField("rooms", e.target.value)}
                    placeholder="Ej. 3"
                    className={fieldClass(fieldErrors.rooms, true)}
                  />
                </Field>

                <Field label="Dormitorios" error={fieldErrors.bedrooms}>
                  <input
                    type="number"
                    min="0"
                    value={form.bedrooms}
                    onChange={(e) => setField("bedrooms", e.target.value)}
                    placeholder="Ej. 2"
                    className={fieldClass(fieldErrors.bedrooms, true)}
                  />
                </Field>

                <Field label="Baños" error={fieldErrors.bathrooms}>
                  <input
                    type="number"
                    min="0"
                    value={form.bathrooms}
                    onChange={(e) => setField("bathrooms", e.target.value)}
                    placeholder="Ej. 1"
                    className={fieldClass(fieldErrors.bathrooms, true)}
                  />
                </Field>

                <Field label="Cocheras" error={fieldErrors.garages}>
                  <input
                    type="number"
                    min="0"
                    value={form.garages}
                    onChange={(e) => setField("garages", e.target.value)}
                    placeholder="Ej. 1"
                    className={fieldClass(fieldErrors.garages, true)}
                  />
                </Field>

                <Field label="Disponibles" error={fieldErrors.available_units}>
                  <input
                    type="number"
                    min="0"
                    value={form.available_units}
                    onChange={(e) => setField("available_units", e.target.value)}
                    placeholder="Ej. 8"
                    className={fieldClass(fieldErrors.available_units, true)}
                  />
                </Field>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
              <div className="space-y-4">
                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                  Superficies (m²)
                </p>

                <div className="grid grid-cols-2 gap-4">
                  <Field label="Desde" error={fieldErrors.area_from}>
                    <input
                      type="number"
                      min="0"
                      value={form.area_from}
                      onChange={(e) => setField("area_from", e.target.value)}
                      placeholder="Ej. 52"
                      className={fieldClass(fieldErrors.area_from)}
                    />
                  </Field>

                  <Field label="Hasta" error={fieldErrors.area_to}>
                    <input
                      type="number"
                      min="0"
                      value={form.area_to}
                      onChange={(e) => setField("area_to", e.target.value)}
                      placeholder="Ej. 78"
                      className={fieldClass(fieldErrors.area_to)}
                    />
                  </Field>
                </div>
              </div>

              <div className="space-y-4">
                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                  Precios y moneda
                </p>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-[110px_1fr_1fr]">
                  <Field label="Moneda" error={fieldErrors.currency}>
                    <select
                      value={form.currency}
                      onChange={(e) => setField("currency", e.target.value)}
                      className={fieldClass(fieldErrors.currency)}
                    >
                      {CURRENCY_OPTIONS.map((item) => (
                        <option key={item.value} value={item.value}>
                          {item.label}
                        </option>
                      ))}
                    </select>
                  </Field>

                  <Field label="Desde" error={fieldErrors.price_from}>
                    <input
                      type="number"
                      min="0"
                      value={form.price_from}
                      onChange={(e) => setField("price_from", e.target.value)}
                      placeholder="Ej. 120000"
                      className={fieldClass(fieldErrors.price_from)}
                    />
                  </Field>

                  <Field label="Hasta" error={fieldErrors.price_to}>
                    <input
                      type="number"
                      min="0"
                      value={form.price_to}
                      onChange={(e) => setField("price_to", e.target.value)}
                      placeholder="Ej. 180000"
                      className={fieldClass(fieldErrors.price_to)}
                    />
                  </Field>
                </div>
              </div>
            </div>

            <div className="flex justify-end">
              <button
                type="button"
                onClick={handleSubmit}
                disabled={saving}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:opacity-90 disabled:opacity-60"
              >
                <Icon name="save" size={16} />
                {saving
                  ? "Guardando..."
                  : isEditing
                    ? "Guardar tipología"
                    : "Agregar tipología"}
              </button>
            </div>
          </div>
        </div>

        <div className="space-y-4">
          <div>
            <h3 className="text-sm font-bold text-slate-900">
              Tipologías cargadas
            </h3>
            <p className="mt-1 text-xs text-slate-500">
              Estas unidades formarán parte de la publicación del desarrollo.
            </p>
          </div>

          {!sortedItems.length ? (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
              Todavía no cargaste ninguna tipología.
            </div>
          ) : (
            <div className="grid gap-4">
              {sortedItems.map((item) => {
                const priceLabel =
                  item?.price_from || item?.price_to
                    ? item?.price_from && item?.price_to
                      ? `${formatMoney(item.price_from, item?.currency)} - ${formatMoney(item.price_to, item?.currency)}`
                      : item?.price_from
                        ? `Desde ${formatMoney(item.price_from, item?.currency)}`
                        : `Hasta ${formatMoney(item.price_to, item?.currency)}`
                    : "Sin precio informado";

                const areaLabel =
                  item?.area_from || item?.area_to
                    ? item?.area_from && item?.area_to
                      ? `${item.area_from} m² - ${item.area_to} m²`
                      : item?.area_from
                        ? `Desde ${item.area_from} m²`
                        : `Hasta ${item.area_to} m²`
                    : "Sin superficie informada";

                return (
                  <article
                    key={item.id}
                    className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md"
                  >
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                      <div className="min-w-0">
                        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">
                          {unitTypeLabel(item?.unit_type)}
                        </p>

                        <h4 className="mt-1 text-base sm:text-lg font-bold text-slate-900">
                          {item?.label || "Sin nombre comercial"}
                        </h4>

                        <div className="mt-3 flex flex-wrap gap-2">
                          {item?.rooms ? (
                            <Badge>{item.rooms} ambientes</Badge>
                          ) : null}

                          {item?.bedrooms ? (
                            <Badge>{item.bedrooms} dorm.</Badge>
                          ) : null}

                          {item?.bathrooms ? (
                            <Badge>{item.bathrooms} baños</Badge>
                          ) : null}

                          {item?.garages ? (
                            <Badge>{item.garages} coch.</Badge>
                          ) : null}

                          {item?.available_units ? (
                            <Badge>{item.available_units} disponibles</Badge>
                          ) : null}
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                          <InfoBox label="Superficie" value={areaLabel} />
                          <InfoBox label="Precio" value={priceLabel} />
                        </div>
                      </div>

                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={() => loadItemIntoForm(item)}
                          className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        >
                          Editar
                        </button>

                        <button
                          type="button"
                          onClick={() => handleDelete(item)}
                          className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100"
                        >
                          Eliminar
                        </button>
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}