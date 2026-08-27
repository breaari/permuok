import { useEffect, useRef, useState } from "react";

import {
  archiveConversation,
  getInboxConversations,
  unarchiveConversation,
} from "../api/conversations.api";

import { useToast } from "../../../ui/toast/ToastProvider";

const PAGE_SIZE = 20;
const SEARCH_DELAY = 350;

export default function useInboxConversations() {
  const toast = useToast();

  const requestIdRef = useRef(0);
  const initializedRef = useRef(false);

  const [activeTab, setActiveTab] = useState("own");
  const [archiveMode, setArchiveMode] = useState("active");
  const [activeStatus, setActiveStatus] = useState("all");

  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");

  const [items, setItems] = useState([]);
  const [summary, setSummary] = useState({
    tabs: {
      own: {
        groups: 0,
        conversations: 0,
        unread: 0,
      },
      external: {
        groups: 0,
        conversations: 0,
        unread: 0,
      },
      matches: {
        conversations: 0,
        unread: 0,
      },
    },
    statuses: {
      all: 0,
      open: 0,
      negotiating: 0,
      visit_scheduled: 0,
      closed: 0,
      discarded: 0,
    },
  });

  const [pagination, setPagination] = useState({
    page: 1,
    limit: PAGE_SIZE,
    total: 0,
    total_pages: 0,
    has_more: false,
  });

  const [page, setPage] = useState(1);

  const [loading, setLoading] = useState(true);
  const [polling, setPolling] = useState(false);
  const [error, setError] = useState("");

  async function loadData({ silent = false, requestedPage = page } = {}) {
    const requestId = ++requestIdRef.current;

    try {
      if (!silent) {
        setLoading(true);
      } else {
        setPolling(true);
      }

      setError("");

      const res = await getInboxConversations({
        tab: activeTab,
        status: activeStatus,
        search: debouncedSearch,
        page: requestedPage,
        limit: PAGE_SIZE,
        archived: archiveMode === "archived",
      });

      /*
       * Si llegó una respuesta de una request vieja
       * después de una más nueva, no la aplicamos.
       */
      if (requestId !== requestIdRef.current) {
        return;
      }

      const nextItems = Array.isArray(res?.items) ? res.items : [];

      setItems(nextItems);

      setSummary((current) => ({
        ...current,
        ...(res?.summary || {}),
      }));

      setPagination({
        page: Number(res?.pagination?.page || requestedPage),

        limit: Number(res?.pagination?.limit || PAGE_SIZE),

        total: Number(res?.pagination?.total || 0),

        total_pages: Number(res?.pagination?.total_pages || 0),

        has_more: Boolean(res?.pagination?.has_more),
      });

      initializedRef.current = true;
    } catch (err) {
      if (requestId !== requestIdRef.current) {
        return;
      }

      if (!silent) {
        setError(err?.message || "No se pudieron cargar las conversaciones.");
      }
    } finally {
      if (requestId === requestIdRef.current) {
        setLoading(false);
        setPolling(false);
      }
    }
  }

  async function handleArchive(item) {
    try {
      await archiveConversation(item.id);

      toast.success("Conversación archivada.");

      /*
       * Recargamos desde backend porque archivar
       * modifica:
       * - items
       * - totals
       * - grupos
       * - unread
       * - status counts
       */
      await loadData({
        requestedPage: page,
      });
    } catch (err) {
      const message = err?.message || "No se pudo archivar la conversación.";

      setError(message);
      toast.error(message);
    }
  }

  async function handleUnarchive(item) {
    try {
      await unarchiveConversation(item.id);

      toast.success("Conversación desarchivada.");

      await loadData({
        requestedPage: page,
      });
    } catch (err) {
      const message = err?.message || "No se pudo desarchivar la conversación.";

      setError(message);
      toast.error(message);
    }
  }

  function changePage(nextPage) {
    const normalizedPage = Math.max(
      1,
      Math.min(Number(nextPage) || 1, Math.max(1, pagination.total_pages)),
    );

    setPage(normalizedPage);
  }

  /*
   * Debounce del buscador.
   *
   * Evitamos enviar una request por cada tecla.
   */
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedSearch(search.trim());
    }, SEARCH_DELAY);

    return () => window.clearTimeout(timer);
  }, [search]);

  /*
   * Cuando cambia cualquier criterio server-side
   * volvemos a página 1.
   */
  useEffect(() => {
    setPage(1);
  }, [activeTab, archiveMode, activeStatus, debouncedSearch]);

  /*
   * Carga principal.
   */
  useEffect(() => {
    loadData({
      requestedPage: page,
    });
  }, [activeTab, archiveMode, activeStatus, debouncedSearch, page]);

  /*
   * Polling.
   *
   * Ya NO hacemos merge de listas.
   * Refrescamos únicamente la página visible
   * y sus summaries globales.
   */
  useEffect(() => {
    const interval = window.setInterval(() => {
      if (document.hidden) {
        return;
      }

      loadData({
        silent: true,
        requestedPage: page,
      });
    }, 60000);

    return () => window.clearInterval(interval);
  }, [activeTab, archiveMode, activeStatus, debouncedSearch, page]);

  /*
   * Datos ya preparados por backend.
   */
  const ownGroups = activeTab === "own" ? items : [];

  const externalGroups = activeTab === "external" ? items : [];

  const matchItems = activeTab === "matches" ? items : [];

  /*
   * Los filtros ya se aplican server-side.
   * Conservamos este alias para que Inbox.jsx
   * no necesite cambiar todo de golpe.
   */
  const visibleItems = items;

  const statusCounts = summary?.statuses || {
    all: 0,
    open: 0,
    negotiating: 0,
    visit_scheduled: 0,
    closed: 0,
    discarded: 0,
  };

  const ownGroupCount = Number(summary?.tabs?.own?.groups || 0);

  const externalGroupCount = Number(summary?.tabs?.external?.groups || 0);

  const ownUnread = Number(summary?.tabs?.own?.unread || 0);

  const externalUnread = Number(summary?.tabs?.external?.unread || 0);

  const matchUnread = Number(summary?.tabs?.matches?.unread || 0);

  const matchCount = Number(summary?.tabs?.matches?.conversations || 0);

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

    visibleItems,
    statusCounts,

    ownUnread,
    externalUnread,
    matchUnread,

    handleArchive,
    handleUnarchive,

    matchItems,
    matchCount,

    ownGroups,
    ownGroupCount,

    externalGroups,
    externalGroupCount,

    pagination,
    page,
    setPage: changePage,

    reload: loadData,
  };
}
