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

function groupConversationsByOpportunity(items) {
  const groups = new Map();

  items.forEach((item) => {
    const type = String(item?.opportunity_type || "");
    const id = Number(item?.opportunity_id || 0);

    if (!type || !id) {
      return;
    }

    const key = `${type}:${id}`;

    if (!groups.has(key)) {
      groups.set(key, {
        key,
        opportunity_type: type,
        opportunity_id: id,
        subject: item?.subject || "Publicación",
        conversations: [],
        conversation_count: 0,
        unread_count: 0,
        last_activity_at: null,
        last_conversation: null,
      });
    }

    const group = groups.get(key);

    group.conversations.push(item);
    group.conversation_count += 1;
    group.unread_count += Number(item?.unread_count || 0);

    const itemDate =
      item?.last_message_created_at ||
      item?.last_message_at ||
      item?.updated_at ||
      item?.created_at ||
      null;

    if (
      itemDate &&
      (!group.last_activity_at ||
        new Date(itemDate).getTime() >
          new Date(group.last_activity_at).getTime())
    ) {
      group.last_activity_at = itemDate;
      group.last_conversation = item;
    }
  });

  return Array.from(groups.values()).sort(
    (a, b) =>
      new Date(b.last_activity_at || 0).getTime() -
      new Date(a.last_activity_at || 0).getTime(),
  );
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

  const matchItems = useMemo(
    () => items.filter((item) => Number(item?.compatibility_id || 0) > 0),
    [items],
  );

  const ownItems = useMemo(
    () =>
      items.filter(
        (item) => !item?.compatibility_id && item?.direction === "received",
      ),
    [items],
  );

  const ownGroupCount = useMemo(
    () => groupConversationsByOpportunity(ownItems).length,
    [ownItems],
  );

  const externalItems = useMemo(
    () =>
      items.filter(
        (item) => !item?.compatibility_id && item?.direction === "sent",
      ),
    [items],
  );

  const externalGroupCount = useMemo(
    () => groupConversationsByOpportunity(externalItems).length,
    [externalItems],
  );

  const baseItems =
    activeTab === "own"
      ? ownItems
      : activeTab === "external"
        ? externalItems
        : matchItems;

  const matchUnread = countUnread(matchItems);

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
        const searchableText = [
          item?.subject,
          item?.last_message_body,
          item?.last_message_sanitized_body,

          item?.compatibility_property_title,
          item?.compatibility_search_title,

          item?.compatibility_property_id,
          item?.compatibility_search_request_id,
        ]
          .filter((value) => value !== null && value !== undefined)
          .join(" ")
          .toLowerCase();

        return searchableText.includes(term);
      });
    }

    return filtered;
  }, [baseItems, activeStatus, search]);

  const ownGroups = useMemo(
    () =>
      groupConversationsByOpportunity(
        activeTab === "own" ? visibleItems : ownItems,
      ),
    [activeTab, visibleItems, ownItems],
  );

  const externalGroups = useMemo(
    () =>
      groupConversationsByOpportunity(
        activeTab === "external" ? visibleItems : externalItems,
      ),
    [activeTab, visibleItems, externalItems],
  );

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
    matchItems,
    matchUnread,
    ownGroups,
    ownGroupCount,
    externalGroupCount,
    externalGroups,
  };
}
