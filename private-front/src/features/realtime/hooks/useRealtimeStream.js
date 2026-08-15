import { useEffect, useRef } from "react";
import { getAccessToken, getApiBaseUrl, tryRefresh } from "../../../api/http";

function parseSseBlock(block) {
  const lines = block.split("\n");

  let event = "message";
  let data = "";

  lines.forEach((line) => {
    if (line.startsWith("event:")) {
      event = line.replace("event:", "").trim();
    }

    if (line.startsWith("data:")) {
      data += line.replace("data:", "").trim();
    }
  });

  if (!data) return null;

  try {
    return {
      event,
      data: JSON.parse(data),
    };
  } catch {
    return {
      event,
      data,
    };
  }
}

export default function useRealtimeStream({
  enabled = true,
  onEvent,
  onMessage,
  onNotification,
  onConversationUnread,
}) {
  const abortRef = useRef(null);
  const reconnectRef = useRef(null);

  useEffect(() => {
    if (!enabled) return;

    let stopped = false;

    async function connect() {
      if (stopped) return;

      const token = getAccessToken();

      if (!token) return;

      abortRef.current?.abort();
      abortRef.current = new AbortController();

      try {
        const res = await fetch(`${getApiBaseUrl()}/stream`, {
          method: "GET",
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: "text/event-stream",
          },
          signal: abortRef.current.signal,
        });

        if (res.status === 401) {
          const refreshed = await tryRefresh();

          if (refreshed && !stopped) {
            connect();
          }

          return;
        }

        if (!res.ok || !res.body) {
          throw new Error(`Stream error ${res.status}`);
        }

        const reader = res.body.getReader();
        const decoder = new TextDecoder("utf-8");

        let buffer = "";

        while (!stopped) {
          const { value, done } = await reader.read();

          if (done) break;

          buffer += decoder.decode(value, { stream: true });

          const blocks = buffer.split("\n\n");
          buffer = blocks.pop() || "";

          blocks.forEach((block) => {
            const parsed = parseSseBlock(block);

            if (!parsed) return;

            onEvent?.(parsed);

            if (parsed.event === "message.created") {
              onMessage?.(parsed.data);
            }

            if (parsed.event === "notification.created") {
              onNotification?.(parsed.data);
            }

            if (parsed.event === "conversation.unread_count") {
              onConversationUnread?.(parsed.data);
            }
          });
        }
      } catch (err) {
        if (stopped || err?.name === "AbortError") return;

        if (import.meta.env.DEV) {
          console.error("[SSE] stream error", err);
        }
      }

      if (!stopped) {
        reconnectRef.current = window.setTimeout(connect, 3000);
      }
    }

    connect();

    return () => {
      stopped = true;

      if (reconnectRef.current) {
        window.clearTimeout(reconnectRef.current);
      }

      abortRef.current?.abort();
    };
  }, [enabled, onEvent, onMessage, onNotification, onConversationUnread]);
}
