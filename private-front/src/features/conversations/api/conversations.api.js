import { api, unwrap } from "../../../api/http";

export async function startConversation({
  opportunity_type,
  opportunity_id,
  message,
}) {
  const res = await api.post("/conversations/start", {
    opportunity_type,
    opportunity_id,
    message,
  });

  return unwrap(res);
}

export async function getConversations(params = {}) {
  const res = await api.get("/conversations", {
    params: {
      page: params.page || 1,
      limit: params.limit || 20,
      archived: params.archived ? 1 : 0,
    },
  });

  return unwrap(res);
}

export async function getConversationDetail(conversationId) {
  const res = await api.get(`/conversations/${conversationId}`);

  return unwrap(res);
}

export async function sendConversationMessage(conversationId, body) {
  const res = await api.post(`/conversations/${conversationId}/messages`, {
    body,
  });

  return unwrap(res);
}

export async function requestContactShare(conversationId) {
  const res = await api.post(
    `/conversations/${conversationId}/share-contact/request`,
    {},
  );

  return unwrap(res);
}

export async function respondContactShare(
  conversationId,
  decision,
  reason = "",
) {
  const res = await api.post(
    `/conversations/${conversationId}/share-contact/respond`,
    {
      decision,
      reason,
    },
  );

  return unwrap(res);
}

export async function updateConversationStatus(id, status) {
  const res = await api.patch(`/conversations/${id}/status`, { status });
  return unwrap(res);
}

export async function archiveConversation(id) {
  const res = await api.post(`/conversations/${id}/archive`);
  return unwrap(res);
}

export async function unarchiveConversation(id) {
  const res = await api.post(`/conversations/${id}/unarchive`);
  return unwrap(res);
}

export async function getUnreadConversationsCount() {
  const res = await api.get("/conversations/unread-count");
  return unwrap(res);
}
