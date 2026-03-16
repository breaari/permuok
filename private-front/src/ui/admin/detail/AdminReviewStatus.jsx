import { getProfileStatusMeta } from "./AdminDetailHelpers";

export function ReviewStatusPill({ realEstate }) {
  const meta = getProfileStatusMeta(realEstate);
  return <span className={meta.pillClass}>{meta.label}</span>;
}

export function ReviewFooterStatus({ realEstate }) {
  const meta = getProfileStatusMeta(realEstate);

  return (
    <div className="flex items-center gap-2">
      <span className={`size-2 rounded-full ${meta.dotClass}`} />
      <p className="text-sm font-bold text-slate-700">{meta.footerLabel}</p>
    </div>
  );
}