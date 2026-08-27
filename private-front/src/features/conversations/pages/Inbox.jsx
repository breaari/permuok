import { useNavigate } from "react-router-dom";

import { Icon } from "../../../ui/icons/Index";
import ConversationCard from "../components/ConversationCard";
import InboxFilters, { InboxTabs } from "../components/InboxFilters";
import useInboxConversations from "../detail/useInboxConversations";
import InboxHeader from "../components/InboxHeader";
import ConversationGroupCard from "../components/ConversationGroupCard";

function PaginationControls({ page, pagination, onChange }) {
  const currentPage = Number(page || 1);
  const totalPages = Number(pagination?.total_pages || 0);

  if (totalPages <= 1) {
    return null;
  }

  return (
    <div className="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm font-semibold text-slate-500">
        Página <span className="font-black text-slate-900">{currentPage}</span>{" "}
        de <span className="font-black text-slate-900">{totalPages}</span>
      </p>

      <div className="flex items-center gap-2">
        <button
          type="button"
          disabled={currentPage <= 1}
          onClick={() => onChange(currentPage - 1)}
          className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
        >
          Anterior
        </button>

        <button
          type="button"
          disabled={currentPage >= totalPages}
          onClick={() => onChange(currentPage + 1)}
          className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
        >
          Siguiente
        </button>
      </div>
    </div>
  );
}

export default function Inbox() {
  const navigate = useNavigate();

  const {
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

    matchCount,

    ownGroupCount,
    ownGroups,

    externalGroupCount,
    externalGroups,

    pagination,
    page,
    setPage,

    selectedGroup,
    groupItems,
    groupLoading,
    groupError,
    groupPagination,

    openGroup,
    closeGroup,
    changeGroupPage,
  } = useInboxConversations();

  const activeGroups =
    activeTab === "own"
      ? ownGroups
      : activeTab === "external"
        ? externalGroups
        : [];

  const hasVisibleContent =
    activeTab === "own" || activeTab === "external"
      ? activeGroups.length > 0
      : visibleItems.length > 0;

  function handleTabChange(tab) {
    closeGroup();
    setActiveTab(tab);
  }

  function handleArchiveModeChange(mode) {
    closeGroup();
    setArchiveMode(mode);
  }

  function handleStatusChange(status) {
    closeGroup();
    setActiveStatus(status);
  }

  function handleSearchChange(value) {
    closeGroup();
    setSearch(value);
  }

  return (
    <main className="mx-auto w-full max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-10">
      <InboxHeader polling={polling} />

      <InboxTabs
        activeTab={activeTab}
        setActiveTab={handleTabChange}
        ownCount={ownGroupCount}
        ownUnread={ownUnread}
        externalCount={externalGroupCount}
        externalUnread={externalUnread}
        matchCount={matchCount}
        matchUnread={matchUnread}
      />

      <InboxFilters
        archiveMode={archiveMode}
        setArchiveMode={handleArchiveModeChange}
        activeStatus={activeStatus}
        setActiveStatus={handleStatusChange}
        statusCounts={statusCounts}
        search={search}
        setSearch={handleSearchChange}
      />

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm font-semibold text-slate-500 shadow-sm sm:rounded-3xl sm:p-10">
          Cargando conversaciones...
        </div>
      ) : error ? (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold leading-relaxed text-rose-700 sm:rounded-3xl sm:p-5">
          {error}
        </div>
      ) : !items.length ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center sm:rounded-3xl sm:p-14">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white sm:h-16 sm:w-16 sm:rounded-3xl">
            <Icon name="messagesSquare" size={26} />
          </div>

          <h2 className="mt-5 text-xl font-black text-slate-900 sm:mt-6 sm:text-2xl">
            {archiveMode === "archived"
              ? "No tenés conversaciones archivadas"
              : "Todavía no hay conversaciones"}
          </h2>

          <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-slate-500">
            {archiveMode === "archived"
              ? "Cuando archives una conversación, aparecerá en esta sección."
              : "Las consultas iniciadas desde publicaciones, búsquedas y desarrollos aparecerán acá."}
          </p>
        </div>
      ) : !hasVisibleContent ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-400 sm:rounded-3xl sm:p-10">
          No hay conversaciones para este filtro.
        </div>
      ) : (
        <section className="space-y-3 sm:space-y-4">
          <div className="mb-4 sm:mb-5">
            <h2 className="text-lg font-black text-slate-900 sm:text-xl">
              {activeTab === "own"
                ? "Mensajes sobre mis publicaciones"
                : activeTab === "external"
                  ? "Mensajes sobre publicaciones externas"
                  : "Conversaciones por matches"}
            </h2>

            <p className="mt-1 text-sm leading-relaxed text-slate-500">
              {archiveMode === "archived"
                ? "Conversaciones archivadas de esta categoría."
                : activeTab === "own"
                  ? "Consultas que otros usuarios iniciaron sobre tus oportunidades."
                  : activeTab === "external"
                    ? "Consultas que vos iniciaste sobre oportunidades de terceros."
                    : "Conversaciones habilitadas cuando ambas partes mostraron interés en un match."}
            </p>
          </div>

          {activeTab === "own" || activeTab === "external" ? (
            selectedGroup ? (
              <div className="space-y-4">
                <div className="mb-5">
                  <button
                    type="button"
                    onClick={closeGroup}
                    className="text-sm font-black text-slate-600 transition hover:text-slate-900"
                  >
                    ←{" "}
                    {activeTab === "own"
                      ? "Volver a mis publicaciones"
                      : "Volver a publicaciones externas"}
                  </button>

                  <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:rounded-3xl sm:p-5">
                    <p className="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">
                      Conversaciones de esta publicación
                    </p>

                    <h3 className="mt-2 text-lg font-black text-slate-900">
                      {selectedGroup.subject || "Publicación"}
                    </h3>

                    <p className="mt-1 text-sm font-semibold text-slate-500">
                      {Number(selectedGroup.conversation_count || 0)}{" "}
                      {Number(selectedGroup.conversation_count || 0) === 1
                        ? "conversación"
                        : "conversaciones"}
                    </p>
                  </div>
                </div>

                {groupLoading ? (
                  <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm font-semibold text-slate-500">
                    Cargando conversaciones...
                  </div>
                ) : groupError ? (
                  <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">
                    {groupError}
                  </div>
                ) : groupItems.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-400">
                    No hay conversaciones en esta publicación.
                  </div>
                ) : (
                  <>
                    {groupItems.map((item) => (
                      <ConversationCard
                        key={item.id}
                        item={item}
                        mode={activeTab}
                        archived={archiveMode === "archived"}
                        onOpen={() => navigate(`/conversations/${item.id}`)}
                        onArchive={() => handleArchive(item)}
                        onUnarchive={() => handleUnarchive(item)}
                      />
                    ))}

                    <PaginationControls
                      page={groupPagination?.page || 1}
                      pagination={groupPagination}
                      onChange={changeGroupPage}
                    />
                  </>
                )}
              </div>
            ) : (
              <>
                {activeGroups.map((group) => (
                  <ConversationGroupCard
                    key={group.key}
                    group={group}
                    onOpen={() => openGroup(group)}
                  />
                ))}

                <PaginationControls
                  page={page}
                  pagination={pagination}
                  onChange={setPage}
                />
              </>
            )
          ) : (
            <>
              {visibleItems.map((item) => (
                <ConversationCard
                  key={item.id}
                  item={item}
                  mode="matches"
                  archived={archiveMode === "archived"}
                  onOpen={() => navigate(`/conversations/${item.id}`)}
                  onArchive={() => handleArchive(item)}
                  onUnarchive={() => handleUnarchive(item)}
                />
              ))}

              <PaginationControls
                page={page}
                pagination={pagination}
                onChange={setPage}
              />
            </>
          )}
        </section>
      )}
    </main>
  );
}
