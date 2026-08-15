import { useNavigate } from "react-router-dom";

import { Icon } from "../../../ui/icons/Index";
import ConversationCard from "../components/ConversationCard";
import InboxFilters, { InboxTabs } from "../components/InboxFilters";
import useInboxConversations from "../detail/useInboxConversations";
import InboxHeader from "../components/InboxHeader";

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
    ownItems,
    externalItems,
    visibleItems,
    statusCounts,
    ownUnread,
    externalUnread,
    handleArchive,
    handleUnarchive,
  } = useInboxConversations();

  return (
    <main className="mx-auto w-full max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-10">
      <InboxHeader polling={polling} />

      <InboxTabs
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        ownCount={ownItems.length}
        ownUnread={ownUnread}
        externalCount={externalItems.length}
        externalUnread={externalUnread}
      />

      <InboxFilters
        archiveMode={archiveMode}
        setArchiveMode={setArchiveMode}
        activeStatus={activeStatus}
        setActiveStatus={setActiveStatus}
        statusCounts={statusCounts}
        search={search}
        setSearch={setSearch}
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
      ) : !visibleItems.length ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-400 sm:rounded-3xl sm:p-10">
          No hay conversaciones para este filtro.
        </div>
      ) : (
        <section className="space-y-3 sm:space-y-4">
          <div className="mb-4 sm:mb-5">
            <h2 className="text-lg font-black text-slate-900 sm:text-xl">
              {activeTab === "own"
                ? "Mensajes sobre mis publicaciones"
                : "Mensajes sobre publicaciones externas"}
            </h2>

            <p className="mt-1 text-sm leading-relaxed text-slate-500">
              {archiveMode === "archived"
                ? "Conversaciones archivadas de esta categoría."
                : activeTab === "own"
                  ? "Consultas que otros usuarios iniciaron sobre tus oportunidades."
                  : "Consultas que vos iniciaste sobre oportunidades de terceros."}
            </p>
          </div>

          {visibleItems.map((item) => (
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
        </section>
      )}
    </main>
  );
}
