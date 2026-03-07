import { useCallback, useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../api/http";

function normalizeDigits(value) {
  return String(value || "").replace(/\D/g, "");
}

function normalizeText(value) {
  return String(value || "").trim();
}

function normalizeWebsite(value) {
  const raw = normalizeText(value);
  if (!raw) return "";
  return /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
}

function isValidCuit(value) {
  return normalizeDigits(value).length === 11;
}

function isValidPhone(value) {
  return normalizeDigits(value).length >= 8;
}

function isValidWebsite(value) {
  const raw = normalizeText(value);
  if (!raw) return false;

  try {
    const url = new URL(normalizeWebsite(raw));
    return !!url.hostname && url.hostname.includes(".");
  } catch {
    return false;
  }
}

function isValidTextName(value) {
  const raw = normalizeText(value);
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

  if (!normalizeText(profile?.email)) {
    return {
      ok: false,
      reason: "Completá el email de contacto.",
      field: "email",
    };
  }

  if (!normalizeText(profile?.address)) {
    return {
      ok: false,
      reason: "Completá la dirección.",
      field: "address",
    };
  }

  if (!normalizeText(profile?.address_place_id)) {
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

function normalizeProfileFromApi(realEstate) {
  return {
    name: realEstate?.name ?? "",
    legal_name: realEstate?.legal_name ?? "",
    cuit: realEstate?.cuit ?? "",
    address: realEstate?.address ?? "",
    address_place_id: realEstate?.address_place_id ?? "",
    address_lat: realEstate?.address_lat ?? "",
    address_lng: realEstate?.address_lng ?? "",
    address_locality: realEstate?.address_locality ?? "",
    address_province: realEstate?.address_province ?? "",
    address_postal_code: realEstate?.address_postal_code ?? "",
    phone: realEstate?.phone ?? "",
    email: realEstate?.email ?? "",
    website: realEstate?.website ?? "",
    instagram: realEstate?.instagram ?? "",
    facebook: realEstate?.facebook ?? "",
  };
}

function buildProfilePayload(profile) {
  return {
    ...profile,
    name: normalizeText(profile.name),
    legal_name: normalizeText(profile.legal_name),
    cuit: normalizeDigits(profile.cuit),
    email: normalizeText(profile.email),
    address: normalizeText(profile.address),
    phone: normalizeText(profile.phone),
    website: normalizeWebsite(profile.website),
    instagram: normalizeText(profile.instagram),
    facebook: normalizeText(profile.facebook),
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
    [profile]
  );

  const profileOk = profileValidation.ok;
  const hasLicenses = (licenses?.length ?? 0) > 0;

  const clearMessages = useCallback(() => {
    setErr("");
    setOk("");
  }, []);

  const load = useCallback(
    async ({ preserveMessages = false } = {}) => {
      if (!preserveMessages) {
        setErr("");
        setOk("");
      }

      setLoading(true);

      try {
        const res = await api.get("/real-estate/me");
        const data = unwrap(res);
        const realEstateData = data?.real_estate ?? null;

        setRealEstate(realEstateData);
        setProfile(normalizeProfileFromApi(realEstateData));
        setLicenses(Array.isArray(data?.licenses) ? data.licenses : []);
      } catch (e) {
        setErr(getErrorMessage(e, "No se pudo cargar"));
      } finally {
        setLoading(false);
      }
    },
    []
  );

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
        const payload = buildProfilePayload(profile);

        await api.post("/real-estate/profile", payload);
        await load({ preserveMessages: true });
        await loadMe?.({ force: true });

        setOk("Perfil guardado.");
        return true;
      } catch (e2) {
        setErr(getErrorMessage(e2, "No se pudo guardar"));
        return false;
      }
    },
    [profile, load, loadMe]
  );

  const addLicense = useCallback(
    async ({ license_number, province_id, is_primary }) => {
      setErr("");
      setOk("");

      const ln = normalizeText(license_number);
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

        await load({ preserveMessages: true });
        await loadMe?.({ force: true });

        setOk("Matrícula agregada.");
        return true;
      } catch (e2) {
        setErr(getErrorMessage(e2, "No se pudo agregar"));
        return false;
      }
    },
    [load, loadMe]
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
      await load({ preserveMessages: true });
      await loadMe?.({ force: true });

      setOk("Enviado a revisión.");
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
    clearMessages,
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