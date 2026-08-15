export function getShareStatusLabel(request) {
  if (!request) return null;
  if (request?.status === "pending") return "Solicitud pendiente";
  if (request?.status === "accepted") return "Datos compartidos";
  if (request?.status === "rejected") return "Solicitud rechazada";
  return null;
}

export function getDirectionLabel(conversation, currentUserId) {
  if (Number(conversation?.owner_user_id) === Number(currentUserId)) {
    return "Consulta recibida";
  }

  if (Number(conversation?.created_by_user_id) === Number(currentUserId)) {
    return "Consulta iniciada";
  }

  return "Conversación";
}

export function mergeMessages(currentMessages, incomingMessages) {
  const map = new Map();

  currentMessages.forEach((message) => {
    map.set(Number(message.id), message);
  });

  incomingMessages.forEach((message) => {
    map.set(Number(message.id), message);
  });

  return Array.from(map.values()).sort((a, b) => Number(a.id) - Number(b.id));
}