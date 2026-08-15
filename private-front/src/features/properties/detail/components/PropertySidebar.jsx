import { Icon } from "../../../../ui/icons/Index";
import DetailSection from "../../../shared/detail/components/DetailSection";
import DetailSummaryCard from "../../../shared/detail/components/DetailSummaryCard";
import { formatMoney, propertyTypeLabel } from "../propertyDetail.helpers";

export default function PropertySidebar({
  property,
  detailMode,
  locationLabel,
  summarySpecs = [],
  canContact = true,
  contactDisabledReason = "",
  onContact,
}) {
  return (
    <div className="space-y-6 lg:col-span-4">
      <DetailSection>
        <div className="space-y-1">
          <span className="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
            Valor estimado
          </span>
          <div className="text-4xl font-black tracking-tighter text-slate-900">
            {formatMoney(property?.price, property?.currency || "USD")}
          </div>
        </div>

        <div className="space-y-4 pt-6">
          <div className="flex items-start gap-3">
            <div className="rounded-lg bg-slate-100 p-2">
              <Icon name="building2" size={18} className="text-slate-900" />
            </div>
            <div>
              <p className="text-xs font-medium text-slate-500">Categoría</p>
              <p className="font-semibold text-slate-900">
                {propertyTypeLabel(property?.property_type)}
              </p>
            </div>
          </div>

          <div className="flex items-start gap-3">
            <div className="rounded-lg bg-slate-100 p-2">
              <Icon name="mapPin" size={18} className="text-slate-900" />
            </div>
            <div>
              <p className="text-xs font-medium text-slate-500">Ubicación</p>
              <p className="font-semibold text-slate-900">
                {locationLabel || "—"}
              </p>
            </div>
          </div>
        </div>

        <button
          type="button"
          onClick={onContact}
          title={contactDisabledReason || undefined}
          className={`mt-6 w-full rounded-xl py-4 text-sm font-bold tracking-tight shadow-lg transition-all ${
            canContact
              ? "bg-slate-900 text-white hover:opacity-90"
              : "cursor-not-allowed bg-slate-300 text-slate-600"
          }`}
        >
          {canContact
            ? detailMode === "explore"
              ? "Iniciar propuesta de permuta"
              : "Ver oportunidad de intercambio"
            : "Solo visualización"}
        </button>

        {!canContact && contactDisabledReason ? (
          <p className="mt-3 text-xs font-medium leading-relaxed text-slate-500">
            {contactDisabledReason}
          </p>
        ) : null}
      </DetailSection>

      <DetailSection noCard>
        <div className="rounded-2xl border border-slate-200 bg-slate-100 p-6 sm:p-7">
          <h3 className="mb-6 text-sm font-black uppercase tracking-[0.18em] text-slate-700">
            Ficha técnica
          </h3>

          <div className="grid grid-cols-2 gap-x-10 gap-y-6">
            {summarySpecs.map((item) => (
              <DetailSummaryCard
                key={item.label}
                label={item.label}
                value={item.value}
                type={item.type}
              />
            ))}
          </div>
        </div>
      </DetailSection>
    </div>
  );
}
