import { useCallback, useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../api/http";

function normalizeDigits(value) {
  return String(value || "").replace(/\D/g, "");
}

function isValidCuit(value) {
  const digits = normalizeDigits(value);
  return digits.length === 11;
}

function isValidPhone(value) {
  const digits = normalizeDigits(value);
  return digits.length >= 8;
}

function isValidWebsite(value) {
  const raw = String(value || "").trim();
  if (!raw) return false;

  try {
    const normalized = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
    const url = new URL(normalized);
    return !!url.hostname && url.hostname.includes(".");
  } catch {
    return false;
  }
}

function isValidTextName(value) {
  const raw = String(value || "").trim();
  if (raw.length < 3 || raw.length > 80) return false;

  return /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s.&,'\-()/]+$/.test(raw);
}

function getProfileValidation(profile) {
  if (!isValidTextName(profile?.name)) {
    return {
      ok: false,
      reason:
        "Completá un nombre comercial válido. Solo letras, números y caracteres básicos.",
      field: "name",
    };
  }

  if (!isValidTextName(profile?.legal_name)) {
    return {
      ok: false,
      reason:
        "Completá una razón social válida. Solo letras, números y caracteres básicos.",
      field: "legal_name",
    };
  }

  if (!isValidCuit(profile?.cuit)) {
    return {
      ok: false,
      reason: "Ingresá un CUIT válido de 11 dígitos.",
      field: "cuit",
    };
  }

  if (!String(profile?.email || "").trim()) {
    return {
      ok: false,
      reason: "Completá el email de contacto.",
      field: "email",
    };
  }

  if (!String(profile?.address || "").trim()) {
    return {
      ok: false,
      reason: "Completá la dirección.",
      field: "address",
    };
  }

  if (!String(profile?.address_place_id || "").trim()) {
    return {
      ok: false,
      reason: "Seleccioná una dirección válida desde Google Maps.",
      field: "address",
    };
  }

  if (profile?.address_lat === "" || profile?.address_lat === null) {
    return {
      ok: false,
      reason: "Seleccioná una dirección válida desde Google Maps.",
      field: "address",
    };
  }

  if (profile?.address_lng === "" || profile?.address_lng === null) {
    return {
      ok: false,
      reason: "Seleccioná una dirección válida desde Google Maps.",
      field: "address",
    };
  }

  if (!isValidPhone(profile?.phone)) {
    return {
      ok: false,
      reason: "Ingresá un teléfono válido.",
      field: "phone",
    };
  }

  if (!isValidWebsite(profile?.website)) {
    return {
      ok: false,
      reason: "Completá un sitio web válido.",
      field: "website",
    };
  }

  return {
    ok: true,
    reason: "",
    field: null,
  };
}

export function useRealEstateProfile({ loadMe }) {
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [ok, setOk] = useState("");

  const [realEstate, setRealEstate] = useState(null);
  const [profile, setProfile] = useState({
    name: "",
    legal_name: "",
    cuit: "",
    address: "",
    address_place_id: "",
    address_lat: "",
    address_lng: "",
    address_locality: "",
    address_province: "",
    address_postal_code: "",
    phone: "",
    email: "",
    website: "",
    instagram: "",
    facebook: "",
  });
  const [licenses, setLicenses] = useState([]);

  const profileValidation = useMemo(
    () => getProfileValidation(profile),
    [profile],
  );

  const profileOk = profileValidation.ok;
  const hasLicenses = (licenses?.length ?? 0) > 0;

  const load = useCallback(async () => {
    setErr("");
    setOk("");
    setLoading(true);

    try {
      const res = await api.get("/real-estate/me");
      const data = unwrap(res);

      setRealEstate(data?.real_estate ?? null);

      setProfile({
        name: data?.real_estate?.name ?? "",
        legal_name: data?.real_estate?.legal_name ?? "",
        cuit: data?.real_estate?.cuit ?? "",
        address: data?.real_estate?.address ?? "",
        address_place_id: data?.real_estate?.address_place_id ?? "",
        address_lat: data?.real_estate?.address_lat ?? "",
        address_lng: data?.real_estate?.address_lng ?? "",
        address_locality: data?.real_estate?.address_locality ?? "",
        address_province: data?.real_estate?.address_province ?? "",
        address_postal_code: data?.real_estate?.address_postal_code ?? "",
        phone: data?.real_estate?.phone ?? "",
        email: data?.real_estate?.email ?? "",
        website: data?.real_estate?.website ?? "",
        instagram: data?.real_estate?.instagram ?? "",
        facebook: data?.real_estate?.facebook ?? "",
      });

      setLicenses(Array.isArray(data?.licenses) ? data.licenses : []);
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo cargar"));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const saveProfile = useCallback(
    async (e) => {
      e?.preventDefault?.();
      setErr("");
      setOk("");

      const validation = getProfileValidation(profile);
      if (!validation.ok) {
        setErr(validation.reason);
        return false;
      }

      try {
        await api.post("/real-estate/profile", profile);
        setOk("Perfil guardado.");
        await load();
        await loadMe?.({ force: true });
        return true;
      } catch (e2) {
        setErr(getErrorMessage(e2, "No se pudo guardar"));
        return false;
      }
    },
    [profile, load, loadMe],
  );

  const addLicense = useCallback(
    async ({ license_number, province_id, is_primary }) => {
      setErr("");
      setOk("");

      const ln = String(license_number || "").trim();
      const pid = Number(province_id);

      if (!ln || !Number.isFinite(pid) || pid <= 0) {
        setErr("Completá número de matrícula y provincia.");
        return false;
      }

      try {
        await api.post("/real-estate/licenses", {
          license_number: ln,
          province_id: pid,
          is_primary: !!is_primary,
        });

        setOk("Matrícula agregada.");
        await load();
        await loadMe?.({ force: true });
        return true;
      } catch (e2) {
        setErr(getErrorMessage(e2, "No se pudo agregar"));
        return false;
      }
    },
    [load, loadMe],
  );

  const submitReview = useCallback(async () => {
    setErr("");
    setOk("");

    const validation = getProfileValidation(profile);
    if (!validation.ok) {
      setErr(validation.reason);
      return false;
    }

    if (!hasLicenses) {
      setErr("Agregá al menos una matrícula.");
      return false;
    }

    try {
      await api.post("/real-estate/submit-review", {});
      setOk("Enviado a revisión.");
      await loadMe?.({ force: true });
      await load();
      return true;
    } catch (e2) {
      setErr(getErrorMessage(e2, "No se pudo enviar"));
      return false;
    }
  }, [profile, hasLicenses, load, loadMe]);

  return {
    loading,
    err,
    ok,
    setOk,
    setErr,
    realEstate,
    profile,
    setProfile,
    licenses,
    profileOk,
    profileValidation,
    hasLicenses,
    actions: {
      load,
      saveProfile,
      addLicense,
      submitReview,
    },
  };
}
