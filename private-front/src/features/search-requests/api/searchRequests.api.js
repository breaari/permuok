import { http, unwrap } from "../../../api/http";

export async function listSearchRequests(params = {}) {
  const res = await http.get("/search-requests", { params });
  return unwrap(res);
}

export async function getSearchRequestDetail(id) {
  const res = await http.get(`/search-requests/${id}`);
  return unwrap(res);
}

export async function createSearchRequestDraft(payload) {
  const res = await http.post("/search-requests", payload);
  return unwrap(res);
}

export async function updateSearchRequestDraft(id, payload) {
  const res = await http.patch(`/search-requests/${id}`, payload);
  return unwrap(res);
}

export async function publishSearchRequest(id) {
  const res = await http.post(`/search-requests/${id}/publish`, {});
  return unwrap(res);
}

export async function pauseSearchRequest(id) {
  const res = await http.post(`/search-requests/${id}/pause`, {});
  return unwrap(res);
}

export async function archiveSearchRequest(id) {
  const res = await http.post(`/search-requests/${id}/archive`, {});
  return unwrap(res);
}

export async function deleteSearchRequest(id) {
  const res = await http.post(`/search-requests/${id}/delete`, {});
  return unwrap(res);
}