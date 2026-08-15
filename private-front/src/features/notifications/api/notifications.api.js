import { api, unwrap } from "../../../api/http";

export async function getNotifications(params = {}) {
  const res = await api.get("/notifications", { params });
  return unwrap(res);
}

export async function getUnreadNotificationsCount() {
  const res = await api.get("/notifications/unread-count");
  return unwrap(res);
}

export async function markNotificationAsRead(id) {
  const res = await api.post(`/notifications/${id}/read`);
  return unwrap(res);
}

export async function markAllNotificationsAsRead() {
  const res = await api.post("/notifications/read-all");
  return unwrap(res);
}