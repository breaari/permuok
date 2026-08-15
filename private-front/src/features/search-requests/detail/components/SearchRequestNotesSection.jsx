import DetailSection from "../../../shared/detail/components/DetailSection";

export default function SearchRequestNotesSection({ notes }) {
  if (!String(notes || "").trim()) return null;

  return (
    <DetailSection title="Observaciones">
      <p className="whitespace-pre-line text-lg italic leading-relaxed text-slate-700">
        {notes}
      </p>
    </DetailSection>
  );
}