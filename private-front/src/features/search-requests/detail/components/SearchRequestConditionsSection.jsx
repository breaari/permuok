import DetailSection from "../../../shared/detail/components/DetailSection";
import DetailSummaryCard from "../../../shared/detail/components/DetailSummaryCard";

function hasRealValue(value) {
  if (value === undefined || value === null || value === "") return false;
  if (value === "0") return false;
  if (value === 0) return false;
  return true;
}

export default function SearchRequestConditionsSection({ items = [] }) {
  const visibleItems = items.filter((item) => hasRealValue(item?.value));

  if (!visibleItems.length) return null;

  return (
    <DetailSection noCard>
      <div className="rounded-2xl border border-slate-200 bg-slate-100 p-6 sm:p-7">
        <h3 className="mb-6 text-sm font-black uppercase tracking-[0.18em] text-slate-700">
          Condiciones buscadas
        </h3>

        <div className="grid grid-cols-2 gap-x-10 gap-y-6 sm:grid-cols-3">
          {visibleItems.map((item) => (
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
  );
}