import DetailHeader from "../../../shared/detail/components/DetailHeader";

export default function PropertyHeader({ property, id, onBack }) {
  return (
    <DetailHeader
      title={property?.title}
      reference={property?.id || id}
      onBack={onBack}
    />
  );
}