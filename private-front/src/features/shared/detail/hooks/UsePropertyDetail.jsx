// hooks/usePropertyDetail.js

import { useEffect, useState } from "react";
import { api, unwrap, getErrorMessage } from "../../../api/http";

export default function usePropertyDetail(id) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [data, setData] = useState(null);
  const [mode, setMode] = useState("owned");

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError("");

      try {
        let payload = null;
        let currentMode = "owned";

        try {
          const res = await api.get(`/properties/${id}`);
          payload = unwrap(res);
        } catch {
          const res = await api.get(`/explore/properties/${id}`);
          payload = unwrap(res);
          currentMode = "explore";
        }

        if (cancelled) return;

        setData(payload);
        setMode(currentMode);
      } catch (e) {
        if (cancelled) return;
        setError(getErrorMessage(e, "No se pudo cargar la propiedad"));
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    load();

    return () => (cancelled = true);
  }, [id]);

  return { data, loading, error, mode };
}
