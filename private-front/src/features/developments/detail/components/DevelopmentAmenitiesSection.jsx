import { Icon } from "../../../../ui/icons/Index";
import DetailSection from "../../../shared/detail/components/DetailSection";
import { amenityLabel } from "../developmentDetail.helpers";

export default function DevelopmentAmenitiesSection({ amenities = [] }) {
  if (!amenities.length) return null;

  return (
    <DetailSection title="Amenities">
      <div className="grid grid-cols-2 gap-6 md:grid-cols-3">
        {amenities.map((amenity, index) => (
          <div
            key={`${amenityLabel(amenity)}-${index}`}
            className="flex items-center gap-3"
          >
            <Icon name="checkCircle" size={18} className="text-emerald-600" />
            <span className="text-sm font-medium text-slate-700">
              {amenityLabel(amenity)}
            </span>
          </div>
        ))}
      </div>
    </DetailSection>
  );
}