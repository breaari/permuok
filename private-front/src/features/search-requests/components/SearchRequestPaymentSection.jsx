import { useEffect, useMemo, useState } from "react";
import {
  SEARCH_REQUEST_CURRENCIES,
  SEARCH_REQUEST_PROPERTY_TYPES,
} from "../utils";
import { Icon } from "../../../ui/icons/Index";
import { getMyPublishedProperties } from "../../properties/api/properties.api";

function Field({ label, children, hint = "" }) {
  return (
    <div>
      {label ? (
        <label className="block text-sm font-medium text-slate-700 mb-1.5">
          {label}
        </label>
      ) : null}

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

function emptyOffer(type = "property") {
  return {
    offer_type: type,

    property_id: "",
    title: "",
    description: "",
    property_type: "",

    vehicle_type: "",
    vehicle_brand: "",
    vehicle_model: "",
    vehicle_year: "",

    estimated_price: "",
    currency: "USD",

    country_code: "",
    country: "",
    province: "",
    city: "",
    zone: "",

    total_area: "",
    covered_area: "",
    bedrooms: "",
    bathrooms: "",
    garages: "",
    antiquity: "",
  };
}

function getOfferTitle(offer) {
  if (offer.offer_type === "property") {
    if (offer.property_id) {
      return offer.property_title || offer.title || "Propiedad publicada";
    }

    return offer.title || "Propiedad";
  }

  if (offer.offer_type === "vehicle") {
    const vehicleType =
      offer.vehicle_type === "car"
        ? "Auto"
        : offer.vehicle_type === "motorcycle"
          ? "Moto"
          : "Vehículo";

    const detail = [
      offer.vehicle_brand,
      offer.vehicle_model,
      offer.vehicle_year,
    ]
      .filter(Boolean)
      .join(" ");

    return detail ? `${vehicleType} · ${detail}` : vehicleType;
  }

  if (offer.offer_type === "other") {
    return offer.title || "Otro bien";
  }

  return "Oferta";
}

function getOfferTypeLabel(offer) {
  if (offer.offer_type === "property") {
    return offer.property_id ? "Propiedad publicada" : "Propiedad no publicada";
  }

  if (offer.offer_type === "vehicle") {
    return "Vehículo";
  }

  if (offer.offer_type === "other") {
    return "Otro bien";
  }

  return "Oferta";
}

function OfferCard({ offer, onEdit, onRemove }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
            {getOfferTypeLabel(offer)}
          </span>

          <h4 className="mt-2 font-bold text-slate-900">
            {getOfferTitle(offer)}
          </h4>

          {offer.estimated_price ? (
            <p className="mt-1 text-sm font-semibold text-emerald-700">
              {offer.currency || "USD"}{" "}
              {Number(offer.estimated_price).toLocaleString("es-AR")}
            </p>
          ) : (
            <p className="mt-1 text-xs text-slate-500">Valor sin informar</p>
          )}

          {offer.description ? (
            <p className="mt-2 text-sm text-slate-600">{offer.description}</p>
          ) : null}
        </div>

        <div className="flex shrink-0 gap-2">
          <button
            type="button"
            onClick={onEdit}
            className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Editar
          </button>

          <button
            type="button"
            onClick={onRemove}
            className="rounded-xl border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
          >
            Eliminar
          </button>
        </div>
      </div>
    </div>
  );
}

export default function SearchRequestPaymentSection({ form, setField }) {
  const acceptsCash = !!form.payment_mode_cash;
  const acceptsSwap = !!form.payment_mode_swap;
  const acceptsBoth = acceptsCash && acceptsSwap;

  const offers = Array.isArray(form.exchange_offers)
    ? form.exchange_offers
    : [];

  const [properties, setProperties] = useState([]);
  const [propertiesLoading, setPropertiesLoading] = useState(false);

  const [editorOpen, setEditorOpen] = useState(false);
  const [editingIndex, setEditingIndex] = useState(null);

  const [draftOffer, setDraftOffer] = useState(emptyOffer());

  const [propertySource, setPropertySource] = useState("published");

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
      } catch (error) {
        if (!cancelled) {
          setProperties([]);
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

  const selectedPublishedProperty = useMemo(() => {
    if (!draftOffer.property_id) {
      return null;
    }

    return (
      properties.find(
        (property) => String(property.id) === String(draftOffer.property_id),
      ) || null
    );
  }, [draftOffer.property_id, properties]);

  function updateDraftOffer(field, value) {
    setDraftOffer((current) => ({
      ...current,
      [field]: value,
    }));
  }

  function handleCashChange(checked) {
    setField("payment_mode_cash", checked);

    if (!checked) {
      setField("cash_difference_max", "");
    }
  }

  function handleSwapChange(checked) {
    setField("payment_mode_swap", checked);

    if (!checked) {
      setField("exchange_offers", []);
      setField("cash_difference_max", "");

      setEditorOpen(false);
      setEditingIndex(null);
      setDraftOffer(emptyOffer());
    }
  }

  function startNewOffer(type) {
    setEditingIndex(null);
    setDraftOffer(emptyOffer(type));

    if (type === "property") {
      setPropertySource("published");
    }

    setEditorOpen(true);
  }

  function startEditOffer(index) {
    const offer = offers[index];

    if (!offer) {
      return;
    }

    setEditingIndex(index);

    setDraftOffer({
      ...emptyOffer(offer.offer_type),
      ...offer,
    });

    if (offer.offer_type === "property") {
      setPropertySource(offer.property_id ? "published" : "manual");
    }

    setEditorOpen(true);
  }

  function cancelOfferEditor() {
    setEditorOpen(false);
    setEditingIndex(null);
    setDraftOffer(emptyOffer());
    setPropertySource("published");
  }

  function removeOffer(index) {
    const next = offers.filter((_, itemIndex) => itemIndex !== index);

    setField("exchange_offers", next);

    if (editingIndex === index) {
      cancelOfferEditor();
    }
  }

  function validateOffer(offer) {
    if (offer.offer_type === "property") {
      if (propertySource === "published" && !offer.property_id) {
        throw new Error("Seleccioná una propiedad publicada.");
      }

      if (propertySource === "manual" && !String(offer.title || "").trim()) {
        throw new Error("Ingresá una referencia para la propiedad ofrecida.");
      }

      return;
    }

    if (offer.offer_type === "vehicle") {
      if (!offer.vehicle_type) {
        throw new Error("Seleccioná el tipo de vehículo.");
      }

      if (!String(offer.vehicle_brand || "").trim()) {
        throw new Error("Ingresá la marca del vehículo.");
      }

      if (!String(offer.vehicle_model || "").trim()) {
        throw new Error("Ingresá el modelo del vehículo.");
      }

      if (!offer.estimated_price || Number(offer.estimated_price) <= 0) {
        throw new Error("Ingresá el valor estimado del vehículo.");
      }

      return;
    }

    if (offer.offer_type === "other") {
      if (!String(offer.title || "").trim()) {
        throw new Error("Indicá qué bien se ofrece.");
      }

      if (!offer.estimated_price || Number(offer.estimated_price) <= 0) {
        throw new Error("Ingresá el valor estimado del bien.");
      }
    }
  }

  function saveOffer() {
    try {
      const nextOffer = {
        ...draftOffer,
      };

      if (nextOffer.offer_type === "property") {
        if (propertySource === "published") {
          const property = selectedPublishedProperty;

          if (property) {
            nextOffer.property_id = property.id;

            nextOffer.title = property.title || "";

            nextOffer.property_type = property.property_type || "";

            nextOffer.estimated_price = property.price || "";

            nextOffer.currency = property.currency || "USD";

            nextOffer.country_code = property.country_code || "";

            nextOffer.country = property.country || "";

            nextOffer.province = property.province || "";

            nextOffer.city = property.city || "";

            nextOffer.zone = property.zone || "";

            nextOffer.total_area = property.total_area || "";

            nextOffer.covered_area = property.covered_area || "";

            nextOffer.bedrooms = property.bedrooms || "";

            nextOffer.bathrooms = property.bathrooms || "";

            nextOffer.garages = property.garages || "";

            nextOffer.antiquity = property.antiquity || "";
          }
        } else {
          nextOffer.property_id = "";
        }
      }

      validateOffer(nextOffer);

      const next = [...offers];

      if (editingIndex === null) {
        next.push(nextOffer);
      } else {
        next[editingIndex] = nextOffer;
      }

      setField("exchange_offers", next);

      cancelOfferEditor();
    } catch (error) {
      window.alert(error?.message || "Revisá los datos de la oferta.");
    }
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
      <div className="mb-5 sm:mb-6">
        <h2 className="text-lg sm:text-xl font-bold text-slate-900">
          Modalidad y referencia de valor
        </h2>

        <p className="text-sm text-slate-500 mt-1 max-w-2xl">
          Definí cómo puede resolverse la operación y, si existe una permuta,
          qué bienes puede ofrecer el cliente.
        </p>
      </div>

      <div className="space-y-7">
        <div className="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-4">
          <div className="flex items-start gap-3">
            <div className="mt-0.5 text-emerald-700">
              <Icon name="info" size={18} />
            </div>

            <div>
              <p className="text-sm font-semibold text-emerald-900">
                La búsqueda y los bienes ofrecidos son cosas diferentes.
              </p>

              <p className="mt-1 text-xs sm:text-sm text-emerald-800">
                El rango de valor describe la propiedad buscada. Si el cliente
                ofrece una propiedad, vehículo u otro bien como parte de pago,
                podés cargarlo aparte.
              </p>
            </div>
          </div>
        </div>

        <SectionBlock
          title="Cómo puede resolverse la operación"
          description="Podés seleccionar dinero, permuta o combinar ambas alternativas."
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <ModeCard
              checked={acceptsCash}
              title="Pago con dinero"
              description="El cliente dispone de dinero para resolver total o parcialmente la operación."
              onChange={(event) => handleCashChange(event.target.checked)}
            />

            <ModeCard
              checked={acceptsSwap}
              title="Ofrece bienes en permuta"
              description="El cliente puede ofrecer una propiedad, vehículo u otro bien como parte de la operación."
              onChange={(event) => handleSwapChange(event.target.checked)}
            />
          </div>
        </SectionBlock>

        {acceptsSwap ? (
          <SectionBlock
            title="Bienes ofrecidos"
            description="No es obligatorio que el bien esté publicado en PermuOK. Podés cargar uno o varios."
          >
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <button
                type="button"
                onClick={() => startNewOffer("property")}
                className="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50/40"
              >
                <p className="text-sm font-bold text-slate-900">+ Propiedad</p>

                <p className="mt-1 text-xs text-slate-500">
                  Publicada o cargada manualmente.
                </p>
              </button>

              <button
                type="button"
                onClick={() => startNewOffer("vehicle")}
                className="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50/40"
              >
                <p className="text-sm font-bold text-slate-900">+ Vehículo</p>

                <p className="mt-1 text-xs text-slate-500">
                  Auto, moto u otro vehículo.
                </p>
              </button>

              <button
                type="button"
                onClick={() => startNewOffer("other")}
                className="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50/40"
              >
                <p className="text-sm font-bold text-slate-900">+ Otro bien</p>

                <p className="mt-1 text-xs text-slate-500">
                  Embarcación, maquinaria u otro activo.
                </p>
              </button>
            </div>

            {offers.length ? (
              <div className="space-y-3">
                {offers.map((offer, index) => (
                  <OfferCard
                    key={`${offer.offer_type}-${index}`}
                    offer={offer}
                    onEdit={() => startEditOffer(index)}
                    onRemove={() => removeOffer(index)}
                  />
                ))}
              </div>
            ) : (
              <div className="rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-center">
                <p className="text-sm font-medium text-slate-700">
                  Todavía no cargaste ningún bien.
                </p>

                <p className="mt-1 text-xs text-slate-500">
                  Podés dejarlo vacío si todavía no se conoce qué ofrecerá el
                  cliente.
                </p>
              </div>
            )}

            {editorOpen ? (
              <div className="rounded-2xl border border-emerald-200 bg-emerald-50/30 p-4 sm:p-5 space-y-5">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h4 className="font-bold text-slate-900">
                      {editingIndex === null ? "Agregar bien" : "Editar bien"}
                    </h4>

                    <p className="mt-1 text-xs text-slate-500">
                      Completá únicamente los datos que conozcas.
                    </p>
                  </div>

                  <button
                    type="button"
                    onClick={cancelOfferEditor}
                    className="text-sm font-semibold text-slate-500 hover:text-slate-900"
                  >
                    Cerrar
                  </button>
                </div>

                {draftOffer.offer_type === "property" ? (
                  <>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <label
                        className={`rounded-xl border p-3 cursor-pointer ${
                          propertySource === "published"
                            ? "border-emerald-500 bg-white"
                            : "border-slate-200 bg-white"
                        }`}
                      >
                        <div className="flex gap-2">
                          <input
                            type="radio"
                            checked={propertySource === "published"}
                            onChange={() => {
                              setPropertySource("published");

                              updateDraftOffer("property_id", "");
                            }}
                          />

                          <div>
                            <p className="text-sm font-semibold text-slate-900">
                              Ya está publicada
                            </p>

                            <p className="text-xs text-slate-500">
                              Seleccionarla de PermuOK.
                            </p>
                          </div>
                        </div>
                      </label>

                      <label
                        className={`rounded-xl border p-3 cursor-pointer ${
                          propertySource === "manual"
                            ? "border-emerald-500 bg-white"
                            : "border-slate-200 bg-white"
                        }`}
                      >
                        <div className="flex gap-2">
                          <input
                            type="radio"
                            checked={propertySource === "manual"}
                            onChange={() => {
                              setPropertySource("manual");

                              updateDraftOffer("property_id", "");
                            }}
                          />

                          <div>
                            <p className="text-sm font-semibold text-slate-900">
                              No está publicada
                            </p>

                            <p className="text-xs text-slate-500">
                              Cargar datos básicos.
                            </p>
                          </div>
                        </div>
                      </label>
                    </div>

                    {propertySource === "published" ? (
                      <Field label="Propiedad publicada">
                        <select
                          value={draftOffer.property_id || ""}
                          disabled={propertiesLoading}
                          onChange={(event) =>
                            updateDraftOffer("property_id", event.target.value)
                          }
                          className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
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
                    ) : (
                      <>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <Field label="Referencia de la propiedad">
                            <input
                              value={draftOffer.title || ""}
                              onChange={(event) =>
                                updateDraftOffer("title", event.target.value)
                              }
                              placeholder="Ej. Departamento en Centro"
                              className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                            />
                          </Field>

                          <Field label="Tipo">
                            <select
                              value={draftOffer.property_type || ""}
                              onChange={(event) =>
                                updateDraftOffer(
                                  "property_type",
                                  event.target.value,
                                )
                              }
                              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-500"
                            >
                              <option value="">Sin especificar</option>

                              {SEARCH_REQUEST_PROPERTY_TYPES.map((item) => (
                                <option key={item.value} value={item.value}>
                                  {item.label}
                                </option>
                              ))}
                            </select>
                          </Field>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <Field label="Valor estimado">
                            <input
                              type="number"
                              min="0"
                              value={draftOffer.estimated_price || ""}
                              onChange={(event) =>
                                updateDraftOffer(
                                  "estimated_price",
                                  event.target.value,
                                )
                              }
                              placeholder="Ej. 80000"
                              className="w-full no-spinner rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-emerald-500"
                            />
                          </Field>

                          <Field label="Moneda">
                            <select
                              value={draftOffer.currency || "USD"}
                              onChange={(event) =>
                                updateDraftOffer("currency", event.target.value)
                              }
                              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-500"
                            >
                              {SEARCH_REQUEST_CURRENCIES.map((item) => (
                                <option key={item.value} value={item.value}>
                                  {item.label}
                                </option>
                              ))}
                            </select>
                          </Field>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                          <Field label="Provincia">
                            <input
                              value={draftOffer.province || ""}
                              onChange={(event) =>
                                updateDraftOffer("province", event.target.value)
                              }
                              className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />
                          </Field>

                          <Field label="Ciudad">
                            <input
                              value={draftOffer.city || ""}
                              onChange={(event) =>
                                updateDraftOffer("city", event.target.value)
                              }
                              className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />
                          </Field>

                          <Field label="Zona">
                            <input
                              value={draftOffer.zone || ""}
                              onChange={(event) =>
                                updateDraftOffer("zone", event.target.value)
                              }
                              className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />
                          </Field>
                        </div>

                        <Field label="Descripción" hint="Opcional">
                          <textarea
                            rows={3}
                            value={draftOffer.description || ""}
                            onChange={(event) =>
                              updateDraftOffer(
                                "description",
                                event.target.value,
                              )
                            }
                            placeholder="Datos adicionales de la propiedad ofrecida..."
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm resize-none outline-none focus:border-emerald-500"
                          />
                        </Field>
                      </>
                    )}
                  </>
                ) : null}

                {draftOffer.offer_type === "vehicle" ? (
                  <>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Field label="Tipo de vehículo">
                        <select
                          value={draftOffer.vehicle_type || ""}
                          onChange={(event) =>
                            updateDraftOffer("vehicle_type", event.target.value)
                          }
                          className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"
                        >
                          <option value="">Seleccionar</option>
                          <option value="car">Auto</option>
                          <option value="motorcycle">Moto</option>
                          <option value="other">Otro</option>
                        </select>
                      </Field>

                      <Field label="Año">
                        <input
                          type="number"
                          min="1900"
                          max={new Date().getFullYear() + 1}
                          value={draftOffer.vehicle_year || ""}
                          onChange={(event) =>
                            updateDraftOffer("vehicle_year", event.target.value)
                          }
                          placeholder="Ej. 2022"
                          className="w-full no-spinner rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                      </Field>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Field label="Marca">
                        <input
                          value={draftOffer.vehicle_brand || ""}
                          onChange={(event) =>
                            updateDraftOffer(
                              "vehicle_brand",
                              event.target.value,
                            )
                          }
                          placeholder="Ej. Toyota"
                          className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                      </Field>

                      <Field label="Modelo">
                        <input
                          value={draftOffer.vehicle_model || ""}
                          onChange={(event) =>
                            updateDraftOffer(
                              "vehicle_model",
                              event.target.value,
                            )
                          }
                          placeholder="Ej. Corolla"
                          className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                      </Field>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Field label="Valor estimado">
                        <input
                          type="number"
                          min="0"
                          value={draftOffer.estimated_price || ""}
                          onChange={(event) =>
                            updateDraftOffer(
                              "estimated_price",
                              event.target.value,
                            )
                          }
                          placeholder="Ej. 25000"
                          className="w-full no-spinner rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                      </Field>

                      <Field label="Moneda">
                        <select
                          value={draftOffer.currency || "USD"}
                          onChange={(event) =>
                            updateDraftOffer("currency", event.target.value)
                          }
                          className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"
                        >
                          {SEARCH_REQUEST_CURRENCIES.map((item) => (
                            <option key={item.value} value={item.value}>
                              {item.label}
                            </option>
                          ))}
                        </select>
                      </Field>
                    </div>

                    <Field label="Descripción" hint="Opcional">
                      <textarea
                        rows={3}
                        value={draftOffer.description || ""}
                        onChange={(event) =>
                          updateDraftOffer("description", event.target.value)
                        }
                        placeholder="Ej. Único dueño, buen estado..."
                        className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm resize-none"
                      />
                    </Field>
                  </>
                ) : null}

                {draftOffer.offer_type === "other" ? (
                  <>
                    <Field label="¿Qué bien ofrece?">
                      <input
                        value={draftOffer.title || ""}
                        onChange={(event) =>
                          updateDraftOffer("title", event.target.value)
                        }
                        placeholder="Ej. Embarcación, maquinaria, lote..."
                        className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                      />
                    </Field>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Field label="Valor estimado">
                        <input
                          type="number"
                          min="0"
                          value={draftOffer.estimated_price || ""}
                          onChange={(event) =>
                            updateDraftOffer(
                              "estimated_price",
                              event.target.value,
                            )
                          }
                          className="w-full no-spinner rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                      </Field>

                      <Field label="Moneda">
                        <select
                          value={draftOffer.currency || "USD"}
                          onChange={(event) =>
                            updateDraftOffer("currency", event.target.value)
                          }
                          className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"
                        >
                          {SEARCH_REQUEST_CURRENCIES.map((item) => (
                            <option key={item.value} value={item.value}>
                              {item.label}
                            </option>
                          ))}
                        </select>
                      </Field>
                    </div>

                    <Field label="Descripción">
                      <textarea
                        rows={3}
                        value={draftOffer.description || ""}
                        onChange={(event) =>
                          updateDraftOffer("description", event.target.value)
                        }
                        placeholder="Describí brevemente el bien..."
                        className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm resize-none"
                      />
                    </Field>
                  </>
                ) : null}

                <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                  <button
                    type="button"
                    onClick={cancelOfferEditor}
                    className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"
                  >
                    Cancelar
                  </button>

                  <button
                    type="button"
                    onClick={saveOffer}
                    className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                  >
                    {editingIndex === null ? "Agregar bien" : "Guardar cambios"}
                  </button>
                </div>
              </div>
            ) : null}
          </SectionBlock>
        ) : null}

        <SectionBlock
          title="Rango de valor estimado"
          description="Es el valor de referencia de la propiedad que el cliente está buscando."
        >
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Field label="Moneda">
              <select
                value={form.currency}
                onChange={(event) => setField("currency", event.target.value)}
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
                onChange={(event) => setField("min_value", event.target.value)}
                placeholder="Ej. 80000"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>

            <Field label="Valor estimado máximo" hint="Opcional">
              <input
                type="number"
                min="0"
                value={form.max_value}
                onChange={(event) => setField("max_value", event.target.value)}
                placeholder="Ej. 150000"
                className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
              />
            </Field>
          </div>
        </SectionBlock>

        {acceptsBoth ? (
          <SectionBlock
            title="Diferencia en dinero"
            description="Si además de los bienes ofrecidos el cliente puede agregar dinero, indicá hasta qué monto."
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Diferencia máxima en dinero" hint="Opcional">
                <input
                  type="number"
                  min="0"
                  value={form.cash_difference_max}
                  onChange={(event) =>
                    setField("cash_difference_max", event.target.value)
                  }
                  placeholder="Ej. 30000"
                  className="w-full no-spinner rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                />
              </Field>

              <Field label="Moneda de la diferencia">
                <select
                  value={form.cash_difference_currency}
                  onChange={(event) =>
                    setField("cash_difference_currency", event.target.value)
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
          description="Aclaraciones comerciales, flexibilidad, plazos o cualquier dato útil para evaluar la operación."
        >
          <Field label="">
            <textarea
              rows={4}
              value={form.notes}
              onChange={(event) => setField("notes", event.target.value)}
              placeholder="Ej. El cliente podría evaluar otras alternativas y dispone de margen para negociar una diferencia."
              className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition resize-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
            />
          </Field>
        </SectionBlock>
      </div>
    </section>
  );
}
