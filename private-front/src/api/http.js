// src/api/http.js
const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL?.replace(/\/+$/, "") ||
  "http://localhost/permuok/public";

const ACCESS_KEY = "permuok_access_token";
const REFRESH_KEY = "permuok_refresh_token";

/**
 * Opción B:
 * - Si backend responde { success, status, data }, devolvemos data
 * - Si responde data directo, devolvemos tal cual
 */
export function unwrap(res) {
  if (res && typeof res === "object" && "success" in res && "data" in res) {
    return res.data;
  }
  return res;
}

export function getApiBaseUrl() {
  return API_BASE_URL;
}

export function getAccessToken() {
  return localStorage.getItem(ACCESS_KEY);
}

export function getRefreshToken() {
  return localStorage.getItem(REFRESH_KEY);
}

export function setTokens({ access_token, refresh_token }) {
  if (access_token) localStorage.setItem(ACCESS_KEY, access_token);
  if (refresh_token) localStorage.setItem(REFRESH_KEY, refresh_token);
}

export function clearTokens() {
  localStorage.removeItem(ACCESS_KEY);
  localStorage.removeItem(REFRESH_KEY);
}

/**
 * Para mostrar errores en UI de forma consistente:
 * - usa err.data.message / err.data.error si existe
 * - si no, usa err.message
 */
export function getErrorMessage(err, fallback = "Ocurrió un error") {
  const d = err?.data;
  return (
    (d && typeof d === "object" && (d.message || d.error)) ||
    err?.message ||
    fallback
  );
}

async function request(
  path,
  { method = "GET", body, headers } = {},
  { retry = true } = {}
) {
  const url = `${API_BASE_URL}${path.startsWith("/") ? path : `/${path}`}`;

  const finalHeaders = { ...(headers || {}) };

  const hasBody = body !== undefined && body !== null;

  // JSON body helper
  if (hasBody && !(body instanceof FormData)) {
    finalHeaders["Content-Type"] =
      finalHeaders["Content-Type"] || "application/json";
  }

  // Auth header
  const access = getAccessToken();
  if (access) {
    finalHeaders["Authorization"] = `Bearer ${access}`;
  }

  const res = await fetch(url, {
    method,
    headers: finalHeaders,
    body: hasBody
      ? body instanceof FormData
        ? body
        : JSON.stringify(body)
      : undefined,
  });

  // Si access expiró, intentamos refresh una sola vez
  if (res.status === 401 && retry) {
    const refreshed = await tryRefresh();
    if (refreshed) {
      return request(path, { method, body, headers }, { retry: false });
    }
  }

  // parse response
  const text = await res.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text || null;
  }

  // error
  if (!res.ok) {
    const message =
      (data && typeof data === "object" && (data.message || data.error)) ||
      (typeof data === "string" ? data : null) ||
      `HTTP ${res.status} ${res.statusText}`;

    const err = new Error(message);
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}

async function tryRefresh() {
  const refreshToken = getRefreshToken();
  if (!refreshToken) return false;

  try {
    const data = await request(
      "/refresh",
      { method: "POST", body: { refresh_token: refreshToken } },
      { retry: false }
    );

    const payload = unwrap(data);

    if (payload?.access_token && payload?.refresh_token) {
      setTokens({
        access_token: payload.access_token,
        refresh_token: payload.refresh_token,
      });
      return true;
    }

    clearTokens();
    return false;
  } catch {
    clearTokens();
    return false;
  }
}

export const api = {
  get: (path) => request(path, { method: "GET" }),
  post: (path, body) => request(path, { method: "POST", body }),
  patch: (path, body) => request(path, { method: "PATCH", body }),
  put: (path, body) => request(path, { method: "PUT", body }),
  del: (path) => request(path, { method: "DELETE" }),
};

// compat
export const http = api;