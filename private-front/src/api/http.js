const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL?.replace(/\/+$/, "") ||
  "http://localhost/permuok/public";

const ACCESS_KEY = "permuok_access_token";
const REFRESH_KEY = "permuok_refresh_token";

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

export function getErrorMessage(err, fallback = "Ocurrió un error") {
  const d = err?.data;
  return (
    (d && typeof d === "object" && (d.message || d.error)) ||
    err?.message ||
    fallback
  );
}

function buildUrl(path, params) {
  const baseUrl = `${API_BASE_URL}${path.startsWith("/") ? path : `/${path}`}`;

  if (!params || typeof params !== "object") {
    return baseUrl;
  }

  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    search.append(key, String(value));
  });

  const qs = search.toString();
  return qs ? `${baseUrl}?${qs}` : baseUrl;
}

async function request(
  path,
  { method = "GET", body, headers, params } = {},
  { retry = true } = {}
) {
  const url = buildUrl(path, params);

  const finalHeaders = { ...(headers || {}) };

  const hasBody = body !== undefined && body !== null;

  if (hasBody && !(body instanceof FormData)) {
    finalHeaders["Content-Type"] =
      finalHeaders["Content-Type"] || "application/json";
  }

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

  if (res.status === 401 && retry) {
    const refreshed = await tryRefresh();
    if (refreshed) {
      return request(path, { method, body, headers, params }, { retry: false });
    }
  }

  const text = await res.text();
  let data = null;

  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text || null;
  }

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
  get: (path, options = {}) =>
    request(path, {
      method: "GET",
      params: options.params,
      headers: options.headers,
    }),
  post: (path, body, options = {}) =>
    request(path, {
      method: "POST",
      body,
      headers: options.headers,
      params: options.params,
    }),
  patch: (path, body, options = {}) =>
    request(path, {
      method: "PATCH",
      body,
      headers: options.headers,
      params: options.params,
    }),
  put: (path, body, options = {}) =>
    request(path, {
      method: "PUT",
      body,
      headers: options.headers,
      params: options.params,
    }),
  del: (path, options = {}) =>
    request(path, {
      method: "DELETE",
      headers: options.headers,
      params: options.params,
    }),
};

export const http = api;