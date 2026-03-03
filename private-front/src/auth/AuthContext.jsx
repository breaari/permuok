// src/auth/AuthContext.jsx
import React, { createContext, useContext, useEffect, useMemo, useRef, useState } from "react";
import { api, clearTokens, setTokens, getAccessToken, unwrap } from "../api/http";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [loading, setLoading] = useState(true);
  const [me, setMe] = useState(null); // { user, access }
  const [error, setError] = useState(null);

  const loadMeInFlight = useRef(null);

  async function loadMe({ force = false } = {}) {
    if (!force && loadMeInFlight.current) return loadMeInFlight.current;

    const p = (async () => {
      try {
        setError(null);

        const res = await api.get("/me");
        const payload = unwrap(res);

        // payload esperado: { user, access }
        setMe(payload);
        return payload;
      } catch (e) {
        // si falla /me, limpiamos sesión
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

      // backend con ResponseHelper: { success, status, data: {...} }
      const payload = unwrap(res);

      console.log("[login] payload:", payload); // <-- DEBUG

      setTokens({
        access_token: payload?.access_token,
        refresh_token: payload?.refresh_token,
      });

      console.log("[login] access in LS:", localStorage.getItem("permuok_access_token")); // <-- DEBUG

      const current = await loadMe({ force: true });
      if (!current) throw new Error("No se pudo cargar /me");

      return current;
    } finally {
      setLoading(false);
    }
  }

  async function register(data) {
    setError(null);
    setLoading(true);
    try {
      const res = await api.post("/auth/register", data);
      // register puede devolver {success,status,data} o algo simple, no importa
      unwrap(res);
      return true;
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
        if (getAccessToken()) {
          await loadMe({ force: true });
        }
      } finally {
        setLoading(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
  if (!ctx) throw new Error("useAuth debe usarse dentro de <AuthProvider>");
  return ctx;
}