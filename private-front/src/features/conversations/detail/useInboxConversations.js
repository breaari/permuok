import { useEffect, useMemo, useRef, useState } from "react";

import {
  archiveConversation,
  getConversations,
  unarchiveConversation,
} from "../api/conversations.api";

import { useToast } from "../../../ui/toast/ToastProvider";

function mergeConversationLists(currentItems, incomingItems) {
  const map = new Map();

  currentItems.forEach((item) => map.set(Number(item.id), item));
  incomingItems.forEach((item) => map.set(Number(item.id), item));

  return Array.from(map.values()).sort((a, b) => {
    const aDate = new Date(
      a?.last_message_at || a?.updated_at || a?.created_at || 0,
    ).getTime();

    const bDate = new Date(
      b?.last_message_at || b?.updated_at || b?.created_at || 0,
    ).getTime();

    return bDate - aDate;
  });
}

function countUnread(items) {
  return items.reduce((acc, item) => acc + Number(item?.unread_count || 0), 0);
}

export default function useInboxConversations() {
  const toast = useToast();
  const initializedRef = useRef(false);

  const [activeTab, setActiveTab] = useState("own");
  const [archiveMode, setArchiveMode] = useState("active");
  const [activeStatus, setActiveStatus] = useState("all");
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [polling, setPolling] = useState(false);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");

  const ownItems = useMemo(
    () => items.filter((item) => item?.direction === "received"),
    [items],
  );

  const externalItems = useMemo(
    () => items.filter((item) => item?.direction === "sent"),
    [items],
  );

  const baseItems = activeTab === "own" ? ownItems : externalItems;

  const statusCounts = useMemo(() => {
    const counts = {
      all: baseItems.length,
      open: 0,
      negotiating: 0,
      visit_scheduled: 0,
      closed: 0,
      discarded: 0,
    };

    baseItems.forEach((item) => {
      const status = String(item?.status || "open");
      if (counts[status] !== undefined) counts[status] += 1;
    });

    return counts;
  }, [baseItems]);

  const visibleItems = useMemo(() => {
    let filtered = baseItems;

    if (activeStatus !== "all") {
      filtered = filtered.filter(
        (item) => String(item?.status || "open") === activeStatus,
      );
    }

    if (search.trim()) {
      const term = search.toLowerCase();

      filtered = filtered.filter((item) => {
        return (
          String(item?.subject || "")
            .toLowerCase()
            .includes(term) ||
          String(item?.last_message_body || "")
            .toLowerCase()
            .includes(term) ||
          String(item?.last_message_sanitized_body || "")
            .toLowerCase()
            .includes(term)
        );
      });
    }

    return filtered;
  }, [baseItems, activeStatus, search]);

  const ownUnread = countUnread(ownItems);
  const externalUnread = countUnread(externalItems);

  async function loadData({ silent = false } = {}) {
    try {
      if (!initializedRef.current && !silent) setLoading(true);
      if (silent) setPolling(true);

      setError("");

      const res = await getConversations({
        page: 1,
        limit: 50,
        archived: archiveMode === "archived",
      });

      const incomingItems = Array.isArray(res?.items) ? res.items : [];

      setItems((prev) =>
        silent ? mergeConversationLists(prev, incomingItems) : incomingItems,
      );

      initializedRef.current = true;
    } catch (err) {
      if (!silent) {
        setError(err?.message || "No se pudieron cargar las conversaciones.");
      }
    } finally {
      setLoading(false);
      setPolling(false);
    }
  }

  async function handleArchive(item) {
    try {
      await archiveConversation(item.id);

      setItems((prev) =>
        prev.filter((row) => Number(row.id) !== Number(item.id)),
      );

      toast.success("Conversación archivada.");
    } catch (err) {
      const message = err?.message || "No se pudo archivar la conversación.";
      setError(message);
      toast.error(message);
    }
  }

  async function handleUnarchive(item) {
    try {
      await unarchiveConversation(item.id);

      setItems((prev) =>
        prev.filter((row) => Number(row.id) !== Number(item.id)),
      );

      toast.success("Conversación desarchivada.");
    } catch (err) {
      const message = err?.message || "No se pudo desarchivar la conversación.";
      setError(message);
      toast.error(message);
    }
  }

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    initializedRef.current = false;
    setItems([]);
    setActiveStatus("all");
    loadData();
  }, [archiveMode]);

  useEffect(() => {
    const interval = window.setInterval(() => {
      if (document.hidden) return;
      loadData({ silent: true });
    }, 60000);

    return () => window.clearInterval(interval);
  }, [archiveMode]);

  useEffect(() => {
    setActiveStatus("all");
  }, [activeTab]);

  return {
    activeTab,
    setActiveTab,
    archiveMode,
    setArchiveMode,
    activeStatus,
    setActiveStatus,
    items,
    loading,
    polling,
    error,
    search,
    setSearch,
    ownItems,
    externalItems,
    visibleItems,
    statusCounts,
    ownUnread,
    externalUnread,
    handleArchive,
    handleUnarchive,
  };
}
