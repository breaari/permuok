import { Icon } from "../../../../ui/icons/Index";
import DetailSection from "../../../shared/detail/components/DetailSection";
import {
  formatDate,
  formatPriceRange,
  formatStage,
  formatUnits,
  getUnitsProgress,
} from "../developmentDetail.helpers";

function SidebarInfoItem({ icon, label, value }) {
  if (!value || value === "—") return null;

  return (
    <div className="flex items-start gap-3">
      <div className="rounded-lg bg-slate-100 p-2">
        <Icon name={icon} size={18} className="text-slate-900" />
      </div>

      <div>
        <p className="text-xs font-medium text-slate-500">{label}</p>
        <p className="font-semibold text-slate-900">{value}</p>
      </div>
    </div>
  );
}

export default function DevelopmentSidebar({
  development,
  locationLabel,
  onContact,
  actionLoading,
  canContact = true,
  contactDisabledReason = "",
}) {
  const progress = getUnitsProgress(development);

  return (
    <div className="space-y-6 lg:col-span-4">
      <DetailSection>
        <div className="space-y-1">
          <span className="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
            Valor desde
          </span>

          <div className="text-3xl font-black tracking-tighter text-slate-900">
            {formatPriceRange(development)}
          </div>
        </div>

        <div className="space-y-4 pt-6">
          <SidebarInfoItem
            icon="building2"
            label="Etapa"
            value={formatStage(development?.development_stage)}
          />

          <SidebarInfoItem
            icon="mapPin"
            label="Ubicación"
            value={locationLabel}
          />

          <SidebarInfoItem
            icon="calendar"
            label="Entrega estimada"
            value={formatDate(development?.delivery_date_estimated)}
          />

          <SidebarInfoItem
            icon="building"
            label="Desarrolladora"
            value={development?.developer_name}
          />

          <SidebarInfoItem
            icon="briefcase"
            label="Constructora"
            value={development?.construction_company}
          />
        </div>

        <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm font-bold text-slate-900">
              {formatUnits(development)}
            </p>

            {!!development?.total_units ? (
              <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">
                {progress}%
              </span>
            ) : null}
          </div>

          {!!development?.total_units ? (
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
              <div
                className="h-full rounded-full bg-emerald-500"
                style={{ width: `${progress}%` }}
              />
            </div>
          ) : null}
        </div>

        <button
          type="button"
          onClick={onContact}
          disabled={actionLoading}
          title={contactDisabledReason || undefined}
          className={`mt-6 w-full rounded-xl py-4 text-sm font-bold tracking-tight shadow-lg transition-all ${
            canContact
              ? "bg-slate-900 text-white hover:opacity-90 disabled:opacity-60"
              : "cursor-not-allowed bg-slate-300 text-slate-600"
          }`}
        >
          {canContact ? "Consultar desarrollo" : "Solo visualización"}
        </button>

        {!canContact && contactDisabledReason ? (
          <p className="mt-3 text-xs font-medium leading-relaxed text-slate-500">
            {contactDisabledReason}
          </p>
        ) : null}
      </DetailSection>
    </div>
  );
}
