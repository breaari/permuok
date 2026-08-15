const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL?.replace(/\/+$/, "") ||
  "http://localhost/permuok/public";

const ACCESS_KEY = "permuok_access_token";
const REFRESH_KEY = "permuok_refresh_token";

let refreshPromise = null;

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
  console.log("[AUTH] setTokens()", {
    hasAccessToken: !!access_token,
    hasRefreshToken: !!refresh_token,
  });

  if (access_token) {
    localStorage.setItem(ACCESS_KEY, access_token);
  }

  if (refresh_token) {
    localStorage.setItem(REFRESH_KEY, refresh_token);
  }
}

export function clearTokens() {
  localStorage.removeItem(ACCESS_KEY);
  localStorage.removeItem(REFRESH_KEY);
}

export function getErrorMessage(err, fallback = "Ocurrió un error") {
  const data = err?.data;

  if (data && typeof data === "object") {
    if (typeof data.message === "string") return data.message;
    if (typeof data.error === "string") return data.error;

    if (Array.isArray(data.errors) && data.errors.length) {
      return data.errors
        .map((item) => {
          if (typeof item === "string") return item;
          return item?.message || item?.error || "";
        })
        .filter(Boolean)
        .join(" ");
    }
  }

  if (typeof data === "string" && data.trim()) {
    return data;
  }

  if (typeof err?.message === "string" && err.message.trim()) {
    return err.message;
  }

  return fallback;
}

function buildUrl(path, params) {
  const baseUrl = `${API_BASE_URL}${path.startsWith("/") ? path : `/${path}`}`;

  if (!params || typeof params !== "object") {
    return baseUrl;
  }

  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;

    if (Array.isArray(value)) {
      if (!value.length) return;
      search.append(key, value.join(","));
      return;
    }

    search.append(key, String(value));
  });

  const qs = search.toString();

  return qs ? `${baseUrl}?${qs}` : baseUrl;
}

async function parseResponse(res) {
  const text = await res.text();

  if (!text) return null;

  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

function buildError(res, data) {
  const message =
    (data && typeof data === "object" && (data.message || data.error)) ||
    (typeof data === "string" ? data : null) ||
    `HTTP ${res.status} ${res.statusText}`;

  const err = new Error(message);
  err.status = res.status;
  err.data = data;

  return err;
}

async function doRefresh() {
  const refreshToken = getRefreshToken();

  console.log("[AUTH] tryRefresh()", {
    hasRefreshToken: !!refreshToken,
    refreshTokenPreview: refreshToken
      ? `${refreshToken.slice(0, 16)}...`
      : null,
  });

  if (!refreshToken) {
    console.log("[AUTH] No refresh token found");
    return false;
  }

  try {
    const data = await request(
      "/refresh",
      {
        method: "POST",
        body: { refresh_token: refreshToken },
        skipAuth: true,
      },
      { retry: false },
    );

    const payload = unwrap(data);

    if (payload?.access_token) {
      setTokens({
        access_token: payload.access_token,
        refresh_token: payload.refresh_token || refreshToken,
      });

      return true;
    }

    clearTokens();
    return false;
  } catch (error) {
    console.log("[AUTH] Refresh failed", {
      status: error?.status,
      message: error?.message,
      data: error?.data,
    });

    clearTokens();
    return false;
  }
}

export async function tryRefresh() {
  if (!refreshPromise) {
    refreshPromise = doRefresh().finally(() => {
      refreshPromise = null;
    });
  }

  return refreshPromise;
}

async function request(
  path,
  { method = "GET", body, headers, params, skipAuth = false } = {},
  { retry = true } = {},
) {
  const url = buildUrl(path, params);

  const finalHeaders = { ...(headers || {}) };
  const hasBody = body !== undefined && body !== null;

  if (hasBody && !(body instanceof FormData)) {
    finalHeaders["Content-Type"] =
      finalHeaders["Content-Type"] || "application/json";
  }

  const access = getAccessToken();

  if (!skipAuth && access) {
    finalHeaders.Authorization = `Bearer ${access}`;
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

  if (res.status === 401 && retry && path !== "/refresh") {
    console.log("[HTTP] 401 detected, trying refresh...", { url });

    const refreshed = await tryRefresh();

    if (refreshed) {
      return request(
        path,
        {
          method,
          body,
          headers,
          params,
          skipAuth,
        },
        { retry: false },
      );
    }
  }

  const data = await parseResponse(res);

  if (!res.ok) {
    throw buildError(res, data);
  }

  return data;
}

export const api = {
  get: (path, options = {}) =>
    request(path, {
      method: "GET",
      params: options.params,
      headers: options.headers,
      skipAuth: options.skipAuth,
    }),

  post: (path, body, options = {}) =>
    request(path, {
      method: "POST",
      body,
      headers: options.headers,
      params: options.params,
      skipAuth: options.skipAuth,
    }),

  patch: (path, body, options = {}) =>
    request(path, {
      method: "PATCH",
      body,
      headers: options.headers,
      params: options.params,
      skipAuth: options.skipAuth,
    }),

  put: (path, body, options = {}) =>
    request(path, {
      method: "PUT",
      body,
      headers: options.headers,
      params: options.params,
      skipAuth: options.skipAuth,
    }),

  del: (path, options = {}) =>
    request(path, {
      method: "DELETE",
      headers: options.headers,
      params: options.params,
      skipAuth: options.skipAuth,
    }),
};

export const http = api;
