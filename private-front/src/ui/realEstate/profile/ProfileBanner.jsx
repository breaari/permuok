// ui/realEstate/profile/ProfileBanner.jsx
import { Icon } from "../../icons/Index";

const bannerToneClasses = {
  info: "border-primary/20 bg-primary/5",
  warn: "border-amber-300/40 bg-amber-50",
  success: "border-emerald-300/40 bg-emerald-50",
};

export function ProfileBanner({ banner }) {
  return (
    <div
      className={`mb-8 p-4 md:p-5 rounded-xl border flex flex-col md:flex-row items-start md:items-center justify-between gap-4 ${
        bannerToneClasses[banner.tone] ?? bannerToneClasses.info
      }`}
    >
      <div className="flex gap-3">
        <span className="text-primary mt-0.5">
          <Icon name="info" size={18} />
        </span>
        <div>
          <p className="text-slate-900 font-bold text-base">{banner.title}</p>
          <p className="text-slate-600 text-sm">{banner.desc}</p>
        </div>
      </div>

      {banner.cta && (
        <button
          type="button"
          onClick={banner.cta.onClick}
          className="text-sm font-bold text-primary flex items-center gap-1 hover:underline underline-offset-4"
        >
          {banner.cta.label}
          <span className="text-primary">→</span>
        </button>
      )}
    </div>
  );
}