import DetailHeader from "../../../shared/detail/components/DetailHeader";

export default function DevelopmentHeader({ development, id, onBack }) {
  return (
    <DetailHeader
      title={development?.title}
      reference={development?.id || id}
      onBack={onBack}
    />
  );
}