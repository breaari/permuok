import DetailHeader from "../../../shared/detail/components/DetailHeader";

export default function SearchRequestHeader({ request, id, onBack }) {
  return (
    <DetailHeader
      title={request?.title}
      reference={request?.id || id}
      onBack={onBack}
    />
  );
}