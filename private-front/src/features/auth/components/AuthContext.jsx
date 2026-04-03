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
} from "../../../api/http";

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
        clearTokens();
        setMe(null);
        return null;
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

      // DEBUG útil
      console.log("[AUTH LOGIN] raw response:", res);

      const payload = unwrap(res);

      console.log("[AUTH LOGIN] payload:", payload);

      // 👉 Caso 1: backend devolvió string (ej: error PHP)
      if (typeof payload === "string") {
        const clean = payload.replace(/<[^>]+>/g, "").trim(); // limpia HTML
        throw new Error(clean || "Error inesperado del servidor");
      }

      // 👉 Caso 2: payload inválido
      if (!payload || typeof payload !== "object") {
        throw new Error("Respuesta inválida del servidor");
      }

      // 👉 Caso 3: backend respondió pero sin tokens
      if (!payload.access_token || !payload.refresh_token) {
        console.error("[AUTH LOGIN] payload sin tokens:", payload);
        throw new Error(
          payload?.message || "El servidor no devolvió credenciales válidas"
        );
      }

      // guardar tokens
      setTokens({
        access_token: payload.access_token,
        refresh_token: payload.refresh_token,
      });

      // cargar usuario
      const current = await loadMe({ force: true });

      if (!current) {
        throw new Error("No se pudo cargar la sesión del usuario");
      }

      return current;
    } catch (e) {
      console.error("[AUTH LOGIN ERROR]", e);
      setError(e?.message || "No se pudo iniciar sesión");
      throw e;
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
      setError(e?.message || "No se pudo registrar la cuenta");
      throw e;
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

  const value = useMemo(
    () => ({
      loading,
      me,
      user: me?.user ?? null,
      access: me?.access ?? null,
      error,
      setError,
      loadMe,
      login,
      register,
      logout,
    }),
    [loading, me, error]
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