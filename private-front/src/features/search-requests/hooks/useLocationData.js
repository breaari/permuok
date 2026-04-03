import { useEffect, useState } from "react";
import { getProvinces } from "../api/location.api";

export function useLocationData(country) {
  const [provinces, setProvinces] = useState([]);

  useEffect(() => {
    if (country !== "argentina") {
      setProvinces([]);
      return;
    }

    getProvinces().then(setProvinces).catch(() => {
      setProvinces([]);
    });
  }, [country]);

  return { provinces };
}