import { useEffect, useRef } from "react";
import { useNavigate, useParams } from "react-router-dom";

import { useAuth } from "../../auth/components/AuthContext";

import ConversationHeader from "../components/ConversationHeader";
import ConversationMessagesPanel from "../components/ConversationMessagesPanel";
import ConversationSidebar from "../components/ConversationSidebar";

import {
  getDirectionLabel,
  getShareStatusLabel,
} from "../detail/conversationDetail.helpers";

import useConversationDetail from "../detail/useConversationDetail";

export default function ConversationDetail() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const { id } = useParams();

  const messagesEndRef = useRef(null);

  const {
    detail,
    messages,
    loading,
    polling,
    actionLoading,
    statusLoading,
    error,
    handleSend,
    handleStatusChange,
    handleArchiveConversation,
    handleUnarchiveConversation,
    handleRequestContactShare,
    handleRespondShare,
    typingUsers,
  } = useConversationDetail(id, navigate);

  const conversation = detail?.conversation || null;
  const shareRequest = detail?.contact_share_request || null;
  const contactData = detail?.contact_data || null;
  const readState = Array.isArray(detail?.read_state) ? detail.read_state : [];

  const currentUserId = user?.id;
  const shareStatusLabel = getShareStatusLabel(shareRequest);
  const directionLabel = getDirectionLabel(conversation, currentUserId);

  const isShareRequestForMe =
    shareRequest?.status === "pending" &&
    Number(shareRequest?.requested_to_user_id) === Number(currentUserId);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages.length]);

  if (loading) {
    return (
      <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 text-sm font-semibold text-slate-500 shadow-sm">
          Cargando conversación...
        </div>
      </main>
    );
  }

  if (error && !detail) {
    return (
      <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-700">
          {error}
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-slate-50">
      <div className="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-10">
        <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-6">
          <section className="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <ConversationHeader
              conversation={conversation}
              directionLabel={directionLabel}
              shareStatusLabel={shareStatusLabel}
              polling={polling}
              onBack={() => navigate("/conversations")}
            />

            {error ? (
              <div className="border-b border-rose-200 bg-rose-50 px-4 py-3 sm:px-5 text-sm font-semibold text-rose-700">
                {error}
              </div>
            ) : null}

            <ConversationMessagesPanel
              messages={messages}
              readState={readState}
              currentUserId={currentUserId}
              actionLoading={actionLoading}
              messagesEndRef={messagesEndRef}
              onSend={handleSend}
            />
          </section>

          <ConversationSidebar
            conversation={conversation}
            directionLabel={directionLabel}
            navigate={navigate}
            statusLoading={statusLoading}
            actionLoading={actionLoading}
            contactData={contactData}
            shareRequest={shareRequest}
            isShareRequestForMe={isShareRequestForMe}
            onStatusChange={handleStatusChange}
            onRequestShare={handleRequestContactShare}
            onRespondShare={handleRespondShare}
            onArchive={handleArchiveConversation}
            onUnarchive={handleUnarchiveConversation}
          />
        </div>
      </div>
    </main>
  );
}
