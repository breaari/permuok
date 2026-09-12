import React, {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  api,
  clearTokens,
  setTokens,
  getAccessToken,
  getRefreshToken,
  tryRefresh,
  unwrap,
  getErrorMessage,
} from "../../../api/http";
import {
  canExploreDevelopments,
  canPublishDevelopments,
  canUseDevelopments,
  isAdmin,
  isAgent,
  isInvestor,
  isRealEstate,
} from "../utils/access";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [loading, setLoading] = useState(true);
  const [me, setMe] = useState(null);
  const [error, setError] = useState(null);

  const loadMeInFlight = useRef(null);

  async function loadMe({ force = false } = {}) {
    if (!force && loadMeInFlight.current) {
      return loadMeInFlight.current;
    }

    const p = (async () => {
      try {
        setError(null);

        const res = await api.get("/me");

        const payload = unwrap(res);

        setMe(payload);

        return payload;
      } catch (e) {
        const message = getErrorMessage(e, "No se pudo cargar la sesión");

        clearTokens();
        setMe(null);

        if (String(message).toLowerCase().includes("inmobiliaria suspendida")) {
          const suspendedError = new Error(
            "El acceso de esta inmobiliaria fue suspendido por el administrador.",
          );

          suspendedError.code = "REAL_ESTATE_SUSPENDED";

          throw suspendedError;
        }

        if (String(message).toLowerCase().includes("usuario inactivo")) {
          const inactiveError = new Error(
            "Tu usuario se encuentra desactivado.",
          );

          inactiveError.code = "USER_INACTIVE";

          throw inactiveError;
        }

        throw e;
      } finally {
        loadMeInFlight.current = null;
      }
    })();

    loadMeInFlight.current = p;

    return p;
  }

  async function login(email, password) {
    setError(null);
    setLoading(true);

    try {
      const res = await api.post("/auth/login", { email, password });
      const payload = unwrap(res);

      if (typeof payload === "string") {
        const clean = payload.replace(/<[^>]+>/g, "").trim();
        throw new Error(clean || "Error inesperado del servidor");
      }

      if (!payload || typeof payload !== "object") {
        throw new Error("Respuesta inválida del servidor");
      }

      if (!payload.access_token || !payload.refresh_token) {
        throw new Error(
          payload?.message || "El servidor no devolvió credenciales válidas",
        );
      }

      setTokens({
        access_token: payload.access_token,
        refresh_token: payload.refresh_token,
      });

      const current = await loadMe({
        force: true,
      });

      return current;
    } catch (e) {
      const message = getErrorMessage(e, "No se pudo iniciar sesión");
      setError(message);
      throw new Error(message);
    } finally {
      setLoading(false);
    }
  }

  async function register(data) {
    setError(null);
    setLoading(true);

    try {
      const res = await api.post("/auth/register", data);
      unwrap(res);
      return true;
    } catch (e) {
      const message = getErrorMessage(e, "No se pudo registrar la cuenta");
      setError(message);
      throw new Error(message);
    } finally {
      setLoading(false);
    }
  }

  async function logout() {
    setLoading(true);

    try {
      await api.post("/logout", {});
    } catch {
      // ignore
    } finally {
      clearTokens();
      setMe(null);
      setLoading(false);
    }
  }

  useEffect(() => {
    (async () => {
      try {
        const accessToken = getAccessToken();
        const refreshToken = getRefreshToken();

        if (!accessToken && !refreshToken) {
          setMe(null);
          return;
        }

        if (refreshToken) {
          const refreshed = await tryRefresh();

          if (!refreshed) {
            clearTokens();
            setMe(null);
            return;
          }
        }

        if (getAccessToken()) {
          await loadMe({ force: true });
        }
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  useEffect(() => {
    function handleBlockedSession(event) {
      const detail = event?.detail || {};

      clearTokens();
      setMe(null);

      if (detail.reason === "real_estate_suspended") {
        setError(
          "El acceso de esta inmobiliaria fue suspendido por el administrador.",
        );

        return;
      }

      if (detail.reason === "user_inactive") {
        setError("Tu usuario se encuentra desactivado.");

        return;
      }

      setError("Tu sesión ya no se encuentra habilitada.");
    }

    window.addEventListener("permuok:session-blocked", handleBlockedSession);

    return () => {
      window.removeEventListener(
        "permuok:session-blocked",
        handleBlockedSession,
      );
    };
  }, []);

  const user = me?.user ?? null;
  const access = me?.access ?? null;

  const permissions = useMemo(
    () => ({
      isAdmin: isAdmin(user),
      isRealEstate: isRealEstate(user),
      isAgent: isAgent(user),
      isInvestor: isInvestor(user),

      canPublishDevelopments: canPublishDevelopments(user, access),
      canViewDevelopments: canExploreDevelopments(user, access),
      canUseDevelopments: canUseDevelopments(user, access),
    }),
    [user, access],
  );

  const value = useMemo(
    () => ({
      loading,
      me,
      user,
      access,
      permissions,
      error,
      setError,
      loadMe,
      login,
      register,
      logout,
    }),
    [loading, me, user, access, permissions, error],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);

  if (!ctx) {
    throw new Error("useAuth debe usarse dentro de <AuthProvider>");
  }

  return ctx;
}
