import { Icon } from "../../../../ui/icons/Index";

function getSummaryIcon(type) {
  const map = {
    total_area: "ruler",
    covered_area: "ruler",
    bedrooms: "bed",
    bathrooms: "bath",
    garages: "car",
    antiquity: "calendar",
    property_type: "home",
    location: "mapPin",
    payment: "creditCard",
    price: "creditCard",
    urgency: "warning",
    condition: "info",
  };

  return map[type] || "info";
}

export default function DetailSummaryCard({
  label,
  value,
  type = "",
  dark = false,
}) {
  const iconName = getSummaryIcon(type);

  return (
    <div className="min-w-0">
      <div className="mb-1.5 flex items-center gap-2">
        <Icon
          name={iconName}
          size={14}
          className={dark ? "text-white/50" : "text-slate-500"}
        />

        <p
          className={`text-[11px] font-bold uppercase tracking-[0.12em] ${
            dark ? "text-white/50" : "text-slate-500"
          }`}
        >
          {label}
        </p>
      </div>

      <p
        className={`text-xl font-black ${
          dark ? "text-white" : "text-slate-900"
        }`}
      >
        {value || "—"}
      </p>
    </div>
  );
}
