import MessageBubble from "./MessageBubble";
import MessageInput from "./MessageInput";

function wasReadByOtherParticipant(message, readState, currentUserId) {
  if (
    !message?.id ||
    Number(message?.sender_user_id) !== Number(currentUserId)
  ) {
    return false;
  }

  const otherParticipant = Array.isArray(readState)
    ? readState.find((item) => Number(item?.user_id) !== Number(currentUserId))
    : null;

  if (!otherParticipant?.last_read_message_id) return false;

  return Number(otherParticipant.last_read_message_id) >= Number(message.id);
}

export default function ConversationMessagesPanel({
  messages = [],
  readState = [],
  currentUserId,
  actionLoading = false,
  messagesEndRef,
  onSend,
}) {
  return (
    <div className="flex min-h-[420px] flex-col sm:h-[560px] lg:h-[670px]">
      <div className="flex-1 space-y-4 overflow-y-auto bg-slate-50 p-4 sm:p-5">
        {messages.length ? (
          messages.map((message) => (
            <MessageBubble
              key={message.id}
              message={message}
              currentUserId={currentUserId}
              seen={wasReadByOtherParticipant(
                message,
                readState,
                currentUserId,
              )}
            />
          ))
        ) : (
          <div className="flex h-full items-center justify-center text-sm font-semibold text-slate-400">
            Todavía no hay mensajes.
          </div>
        )}

        <div ref={messagesEndRef} />
      </div>

      <MessageInput onSend={onSend} disabled={actionLoading} />
    </div>
  );
}
