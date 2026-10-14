import { useCallback, useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../../../api/http";
import { useToast } from "../../../ui/toast/ToastProvider";

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

function normalizeProfileFromApi(realEstate, accountEmail = "") {
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
    email: realEstate?.email ?? normalizeText(accountEmail),
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

export function useRealEstateProfile({ loadMe, accountEmail = "" }) {
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

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

  const profileProgress = useMemo(() => {
    const requirements = [
      {
        key: "name",
        label: "Nombre comercial",
        complete: isValidTextName(profile?.name),
      },
      {
        key: "legal_name",
        label: "Razón social",
        complete: isValidTextName(profile?.legal_name),
      },
      {
        key: "cuit",
        label: "CUIT",
        complete: isValidCuit(profile?.cuit),
      },
      {
        key: "email",
        label: "Email",
        complete: !!normalizeText(profile?.email),
      },
      {
        key: "address",
        label: "Dirección",
        complete: !!normalizeText(profile?.address),
      },
      {
        key: "address_valid",
        label: "Ubicación validada",
        complete:
          !!normalizeText(profile?.address_place_id) &&
          profile?.address_lat !== "" &&
          profile?.address_lat !== null &&
          profile?.address_lng !== "" &&
          profile?.address_lng !== null,
      },
      {
        key: "phone",
        label: "Teléfono",
        complete: isValidPhone(profile?.phone),
      },
      {
        key: "website",
        label: "Sitio web",
        complete: isValidWebsite(profile?.website),
      },
      {
        key: "license",
        label: "Matrícula",
        complete: hasLicenses,
      },
    ];

    const completed = requirements.filter((item) => item.complete).length;

    const total = requirements.length;

    return {
      completed,
      total,
      percentage: Math.round((completed / total) * 100),
    };
  }, [profile, hasLicenses]);

  const clearMessages = useCallback(() => {
    setErr("");
  }, []);

  const load = useCallback(
    async ({ preserveMessages = false, silent = false } = {}) => {
      if (!preserveMessages) {
        setErr("");
      }

      if (!silent) {
        setLoading(true);
      }

      try {
        const res = await api.get("/real-estate/me");

        const data = unwrap(res);

        const realEstateData = data?.real_estate ?? null;

        setRealEstate(realEstateData);

        setProfile(normalizeProfileFromApi(realEstateData, accountEmail));

        setLicenses(Array.isArray(data?.licenses) ? data.licenses : []);

        return realEstateData;
      } catch (e) {
        setErr(getErrorMessage(e, "No se pudo cargar"));

        return null;
      } finally {
        if (!silent) {
          setLoading(false);
        }
      }
    },
    [accountEmail],
  );

  useEffect(() => {
    load();
  }, [load]);

  const saveProfile = useCallback(
    async (e) => {
      e?.preventDefault?.();
      setErr("");

      try {
        const payload = buildProfilePayload(profile);

        await api.post("/real-estate/profile", payload);
        await load({
          preserveMessages: true,
          silent: true,
        });
        await loadMe?.({ force: true });

        toast.success("Perfil guardado correctamente.");
        return true;
      } catch (e2) {
        const message = getErrorMessage(e2, "No se pudo guardar");
        toast.error(message);
        return false;
      }
    },
    [profile, load, loadMe, toast],
  );

  const addLicense = useCallback(
    async ({ license_number, province_id, is_primary }) => {
      setErr("");

      const ln = normalizeText(license_number);
      const pid = Number(province_id);

      if (!ln || !Number.isFinite(pid) || pid <= 0) {
        toast.error("Completá número de matrícula y provincia.");
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

        toast.success("Matrícula agregada correctamente.");
        return true;
      } catch (e2) {
        const message = getErrorMessage(e2, "No se pudo agregar");
        toast.error(message);
        return false;
      }
    },
    [load, loadMe, toast],
  );

  const submitReview = useCallback(async () => {
    setErr("");

    const validation = getProfileValidation(profile);
    if (!validation.ok) {
      toast.error(validation.reason);
      return false;
    }

    if (!hasLicenses) {
      toast.error("Agregá al menos una matrícula.");
      return false;
    }

    try {
      await api.post("/real-estate/submit-review", {});
      await load({ preserveMessages: true });
      await loadMe?.({ force: true });

      toast.success("Perfil enviado a revisión correctamente.");
      return true;
    } catch (e2) {
      const message = getErrorMessage(e2, "No se pudo enviar");
      toast.error(message);
      return false;
    }
  }, [profile, hasLicenses, load, loadMe, toast]);

  return {
    loading,
    err,
    setErr,
    clearMessages,
    realEstate,
    profile,
    setProfile,
    licenses,
    profileOk,
    profileValidation,
    hasLicenses,
    profileProgress,
    actions: {
      load,
      saveProfile,
      addLicense,
      submitReview,
    },
  };
}
