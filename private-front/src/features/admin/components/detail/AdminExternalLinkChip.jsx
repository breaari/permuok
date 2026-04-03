import { Icon } from "../../../../ui/icons/Index";

function buildExternalUrl(value, tone = "default") {
  const raw = String(value || "").trim();
  if (!raw) return "";

  if (/^https?:\/\//i.test(raw)) return raw;

  const cleaned = raw.replace(/^@+/, "").trim();
  if (!cleaned) return "";

  if (tone === "instagram") {
    if (/instagram\.com\//i.test(cleaned)) return `https://${cleaned}`;
    return `https://instagram.com/${cleaned}`;
  }

  if (tone === "facebook") {
    if (/facebook\.com\//i.test(cleaned)) return `https://${cleaned}`;
    return `https://facebook.com/${cleaned}`;
  }

  return `https://${cleaned}`;
}

export default function AdminExternalLinkChip({
  href,
  label,
  tone = "default",
}) {
  if (!href) return null;

  const normalizedHref = buildExternalUrl(href, tone);
  if (!normalizedHref) return null;

  const base =
    "flex items-center gap-2.5 px-4 py-2.5 rounded-lg bg-slate-50 border border-slate-200 hover:bg-white transition-all group";

  const tones = {
    default: "hover:border-primary",
    instagram: "hover:border-pink-500 hover:bg-pink-50",
    facebook: "hover:border-blue-600 hover:bg-blue-50",
  };

  const iconName =
    tone === "instagram"
      ? "instagram"
      : tone === "facebook"
        ? "facebook"
        : "globe";

  return (
    <a
      className={`${base} ${tones[tone] ?? tones.default}`}
      href={normalizedHref}
      target="_blank"
      rel="noreferrer"
    >
      <span className="text-slate-400 group-hover:text-primary transition-colors">
        <Icon name={iconName} size={18} />
      </span>
      <span className="text-sm font-semibold text-slate-700">{label}</span>
    </a>
  );
}