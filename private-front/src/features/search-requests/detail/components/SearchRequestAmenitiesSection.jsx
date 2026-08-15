import { Icon } from "../../../../ui/icons/Index";
import DetailSection from "../../../shared/detail/components/DetailSection";

import { getAmenityLabel } from "../../../shared/helpers/amenities";

export default function SearchRequestAmenitiesSection({ amenities = [] }) {
  if (!amenities.length) return null;

  return (
    <DetailSection title="Amenities deseadas">
      <div className="grid grid-cols-2 gap-6 md:grid-cols-3">
        {amenities.map((item, index) => (
          <div key={`${item}-${index}`} className="flex items-center gap-3">
            <Icon name="checkCircle" size={18} className="text-emerald-600" />

            <span className="text-sm font-medium text-slate-700">
              {getAmenityLabel(item)}
            </span>
          </div>
        ))}
      </div>
    </DetailSection>
  );
}
