import { Icon } from "../../../../ui/icons/Index";
import DetailSection from "../../../shared/detail/components/DetailSection";

export default function DevelopmentCommercialLinks({ development }) {
  const hasLinks =
    development?.whatsapp_url ||
    development?.brochure_url ||
    development?.video_url;

  if (!hasLinks) return null;

  return (
    <DetailSection title="Material comercial">
      <div className="flex flex-col gap-3">
        {development?.whatsapp_url ? (
          <a
            href={development.whatsapp_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
          >
            <Icon name="messages" size={16} />
            WhatsApp
          </a>
        ) : null}

        {development?.brochure_url ? (
          <a
            href={development.brochure_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
          >
            <Icon name="clipboardList" size={16} />
            Brochure
          </a>
        ) : null}

        {development?.video_url ? (
          <a
            href={development.video_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
          >
            <Icon name="image" size={16} />
            Video
          </a>
        ) : null}
      </div>
    </DetailSection>
  );
}