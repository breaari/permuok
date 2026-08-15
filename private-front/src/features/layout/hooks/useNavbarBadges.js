import { useCallback, useEffect, useState } from "react";

import { getUnreadConversationsCount } from "../../conversations/api/conversations.api";
import { getUnreadNotificationsCount } from "../../notifications/api/notifications.api";

export default function useNavbarBadges(userId) {
  const [notificationsCount, setNotificationsCount] = useState(0);
  const [conversationsCount, setConversationsCount] = useState(0);

  const loadCounts = useCallback(async () => {
    if (!userId) return;

    try {
      const [notificationsRes, conversationsRes] = await Promise.all([
        getUnreadNotificationsCount(),
        getUnreadConversationsCount(),
      ]);

      setNotificationsCount(Number(notificationsRes?.count || 0));
      setConversationsCount(Number(conversationsRes?.count || 0));
    } catch (err) {
      if (import.meta.env.DEV) {
        console.error("[NAVBAR] badges count error", err);
      }
    }
  }, [userId]);

  useEffect(() => {
    if (!userId) return;

    loadCounts();

    // fallback liviano por si SSE falla
    const interval = window.setInterval(() => {
      if (document.hidden) return;
      loadCounts();
    }, 60000);

    return () => window.clearInterval(interval);
  }, [userId, loadCounts]);

  return {
    notificationsCount,
    setNotificationsCount,
    conversationsCount,
    setConversationsCount,
    refreshBadges: loadCounts,
  };
}
