import ConversationManagementCard from "./ConversationManagementCard";
import ConversationOpportunityCard from "./ConversationOpportunityCard";
import ConversationPrivacyCard from "./ConversationPrivacyCard";

export default function ConversationSidebar({
  conversation,
  directionLabel,
  navigate,
  statusLoading,
  actionLoading,
  contactData,
  shareRequest,
  isShareRequestForMe,
  onStatusChange,
  onRequestShare,
  onRespondShare,
  onArchive,
  onUnarchive,
}) {
  const isArchived = Boolean(conversation?.archived_at);

  return (
    <aside className="space-y-4 lg:space-y-5">
      <ConversationOpportunityCard
        conversation={conversation}
        directionLabel={directionLabel}
        onNavigate={navigate}
        statusLoading={statusLoading}
        onStatusChange={onStatusChange}
      />

      <ConversationPrivacyCard
        conversation={conversation}
        contactData={contactData}
        shareRequest={shareRequest}
        isShareRequestForMe={isShareRequestForMe}
        actionLoading={actionLoading}
        onRequestShare={onRequestShare}
        onRespondShare={onRespondShare}
      />

      <ConversationManagementCard
        archived={isArchived}
        loading={actionLoading}
        onArchive={onArchive}
        onUnarchive={onUnarchive}
      />
    </aside>
  );
}
