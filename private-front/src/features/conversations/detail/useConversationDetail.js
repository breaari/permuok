import { useCallback, useEffect, useState } from "react";

import {
  archiveConversation,
  getConversationDetail,
  requestContactShare,
  respondContactShare,
  sendConversationMessage,
  updateConversationStatus,
  unarchiveConversation,
} from "../api/conversations.api";

import { useToast } from "../../../ui/toast/ToastProvider";
import { mergeMessages } from "./conversationDetail.helpers";

export default function useConversationDetail(id, navigate) {
  const toast = useToast();

  const [detail, setDetail] = useState(null);
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [polling, setPolling] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [statusLoading, setStatusLoading] = useState(false);
  const [error, setError] = useState("");

  const typingUsers = [];

  const loadDetail = useCallback(
    async ({ silent = false } = {}) => {
      try {
        if (!silent) setLoading(true);
        if (silent) setPolling(true);

        setError("");

        const res = await getConversationDetail(id);
        const incomingMessages = Array.isArray(res?.messages)
          ? res.messages
          : [];

        setDetail(res);

        setMessages((prev) =>
          silent ? mergeMessages(prev, incomingMessages) : incomingMessages,
        );
      } catch (err) {
        if (!silent) {
          setDetail(null);
          setMessages([]);
          setError(err?.message || "No se pudo cargar la conversación.");
        }
      } finally {
        if (!silent) setLoading(false);
        if (silent) setPolling(false);
      }
    },
    [id],
  );

  async function handleSend(body) {
    try {
      setActionLoading(true);
      setError("");

      const res = await sendConversationMessage(id, body);
      const createdMessage = res?.message;

      if (createdMessage?.id) {
        setMessages((prev) => mergeMessages(prev, [createdMessage]));
      } else {
        await loadDetail({ silent: true });
      }
    } catch (err) {
      const message = err?.message || "No se pudo enviar el mensaje.";
      setError(message);
      toast.error(message);
      throw err;
    } finally {
      setActionLoading(false);
    }
  }

  async function handleStatusChange(status) {
    try {
      setStatusLoading(true);
      setError("");

      const res = await updateConversationStatus(id, status);
      const updatedConversation = res?.conversation;

      if (updatedConversation) {
        setDetail((prev) => ({
          ...prev,
          conversation: {
            ...(prev?.conversation || {}),
            ...updatedConversation,
          },
        }));
      }

      toast.success("Estado de conversación actualizado.");
      await loadDetail({ silent: true });
    } catch (err) {
      const message = err?.message || "No se pudo actualizar el estado.";
      setError(message);
      toast.error(message);
    } finally {
      setStatusLoading(false);
    }
  }

  async function handleArchiveConversation() {
    try {
      setActionLoading(true);
      setError("");

      await archiveConversation(id);

      toast.success("Conversación archivada.");
      navigate("/conversations");
    } catch (err) {
      const message = err?.message || "No se pudo archivar la conversación.";
      setError(message);
      toast.error(message);
    } finally {
      setActionLoading(false);
    }
  }

  async function handleUnarchiveConversation() {
    try {
      setActionLoading(true);
      setError("");

      await unarchiveConversation(id);
      toast.success("Conversación desarchivada.");

      await loadDetail({ silent: true });
    } catch (err) {
      const message =
        err?.message || "No se pudo desarchivar la conversación.";
      setError(message);
      toast.error(message);
    } finally {
      setActionLoading(false);
    }
  }

  async function handleRequestContactShare() {
    try {
      setActionLoading(true);
      setError("");

      await requestContactShare(id);
      toast.success("Solicitud para compartir datos enviada.");

      await loadDetail({ silent: true });
    } catch (err) {
      const message =
        err?.message || "No se pudo solicitar compartir datos.";
      setError(message);
      toast.error(message);
    } finally {
      setActionLoading(false);
    }
  }

  async function handleRespondShare(decision) {
    try {
      setActionLoading(true);
      setError("");

      await respondContactShare(id, decision);

      toast.success(
        decision === "accepted"
          ? "Solicitud aceptada. Los datos de contacto fueron habilitados."
          : "Solicitud rechazada.",
      );

      await loadDetail({ silent: true });
    } catch (err) {
      const message = err?.message || "No se pudo responder la solicitud.";
      setError(message);
      toast.error(message);
    } finally {
      setActionLoading(false);
    }
  }

  function appendMessage(message) {
    if (!message?.id) return;

    setMessages((prev) => mergeMessages(prev, [message]));
  }

  useEffect(() => {
    loadDetail();
  }, [loadDetail]);

  useEffect(() => {
    const interval = window.setInterval(() => {
      if (document.hidden) return;
      loadDetail({ silent: true });
    }, 60000);

    return () => window.clearInterval(interval);
  }, [loadDetail]);

  return {
    detail,
    messages,
    loading,
    polling,
    actionLoading,
    statusLoading,
    error,
    loadDetail,
    appendMessage,
    handleSend,
    handleStatusChange,
    handleArchiveConversation,
    handleUnarchiveConversation,
    handleRequestContactShare,
    handleRespondShare,
  };
}