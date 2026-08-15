import DetailSection from "../../../shared/detail/components/DetailSection";
import DetailSummaryCard from "../../../shared/detail/components/DetailSummaryCard";
import { formatMoneyRange } from "../searchRequestDetail.helpers";

function hasValue(value) {
  return value !== undefined && value !== null && value !== "";
}

function normalizeSidebarItems(quickFacts = [], summaryItems = []) {
  const blockedLabels = new Set([
    "presupuesto",
    "rango de valor",
    "valor",
    "tipo",
    "localidad",
  ]);

  const merged = [...summaryItems, ...quickFacts];

  const seen = new Set();

  return merged.filter((item) => {
    const label = String(item?.label || "").trim();
    const value = item?.value;
    const key = label.toLowerCase();

    if (!label) return false;
    if (!hasValue(value)) return false;
    if (blockedLabels.has(key)) return false;
    if (seen.has(key)) return false;

    seen.add(key);
    return true;
  });
}

export default function SearchRequestSidebar({
  request,
  quickFacts = [],
  summaryItems = [],
  canContact = true,
  contactDisabledReason = "",
  onContact,
}) {
  const items = normalizeSidebarItems(quickFacts, summaryItems);

  const buttonLabel = canContact ? "Contactar intercambio" : "Solo visualización";

  return (
    <div className="space-y-6 lg:col-span-4">
      <DetailSection noCard>
        <div className="rounded-2xl border border-slate-200 bg-slate-100 p-6 sm:p-7">
          <div className="mb-7">
            <p className="mb-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
              Presupuesto
            </p>

            <p className="text-3xl font-black tracking-tight text-slate-900">
              {formatMoneyRange(request)}
            </p>
          </div>

          {items.length ? (
            <div className="grid grid-cols-2 gap-x-10 gap-y-6">
              {items.map((item) => (
                <DetailSummaryCard
                  key={item.label}
                  label={item.label}
                  value={item.value}
                  type={item.type}
                />
              ))}
            </div>
          ) : null}

          <button
            type="button"
            onClick={onContact}
            title={contactDisabledReason || undefined}
            className={`mt-8 w-full rounded-xl py-4 text-sm font-bold tracking-tight shadow-lg transition-all ${
              canContact
                ? "bg-slate-900 text-white hover:opacity-90"
                : "cursor-not-allowed bg-slate-300 text-slate-600"
            }`}
          >
            {buttonLabel}
          </button>

          {!canContact && contactDisabledReason ? (
            <p className="mt-3 text-xs font-medium leading-relaxed text-slate-500">
              {contactDisabledReason}
            </p>
          ) : null}
        </div>
      </DetailSection>
    </div>
  );
}