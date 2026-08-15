import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
} from "react";
import { Icon } from "../icons/Index";

const ToastContext = createContext(null);

const TYPE_META = {
  success: {
    title: "Acción realizada",
    icon: "checkCircle",
    className: "border-emerald-200 bg-emerald-50 text-emerald-800",
    iconClassName: "text-emerald-600",
  },
  error: {
    title: "No se pudo completar la acción",
    icon: "alertCircle",
    className: "border-rose-200 bg-rose-50 text-rose-800",
    iconClassName: "text-rose-600",
  },
  warning: {
    title: "Revisá esta información",
    icon: "warning",
    className: "border-amber-200 bg-amber-50 text-amber-800",
    iconClassName: "text-amber-600",
  },
  info: {
    title: "Información",
    icon: "info",
    className: "border-slate-200 bg-white text-slate-800",
    iconClassName: "text-slate-500",
  },
};

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const removeToast = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const showToast = useCallback(
    ({ type = "info", title = "", message = "", duration = 6000 }) => {
      if (!message && !title) return;

      const id = `${Date.now()}-${Math.random()}`;

      setToasts((current) => [
        ...current,
        {
          id,
          type,
          title,
          message,
        },
      ]);

      if (duration > 0) {
        window.setTimeout(() => {
          removeToast(id);
        }, duration);
      }

      return id;
    },
    [removeToast],
  );

  const value = useMemo(
    () => ({
      showToast,
      success: (message, title) =>
        showToast({ type: "success", title, message }),
      error: (message, title) => showToast({ type: "error", title, message }),
      warning: (message, title) =>
        showToast({ type: "warning", title, message }),
      info: (message, title) => showToast({ type: "info", title, message }),
    }),
    [showToast],
  );

  return (
    <ToastContext.Provider value={value}>
      {children}

      <div className="pointer-events-none fixed bottom-4 left-3 right-3 z-[9999] flex flex-col gap-3 sm:left-auto sm:right-5 sm:bottom-5 sm:w-full sm:max-w-md">
        {toasts.map((toast) => (
          <ToastItem
            key={toast.id}
            toast={toast}
            onClose={() => removeToast(toast.id)}
          />
        ))}
      </div>
    </ToastContext.Provider>
  );
}

function ToastItem({ toast, onClose }) {
  const meta = TYPE_META[toast.type] || TYPE_META.info;
  const title = toast.title || meta.title;

  return (
    <div
      role="alert"
      aria-live="assertive"
      className={`pointer-events-auto rounded-2xl border p-4 shadow-xl ${meta.className}`}
    >
      <div className="flex items-start gap-3">
        <Icon
          name={meta.icon}
          size={20}
          className={`mt-0.5 shrink-0 ${meta.iconClassName}`}
        />

        <div className="min-w-0 flex-1">
          {title ? <p className="text-sm font-black">{title}</p> : null}
          {toast.message ? (
            <p className="mt-1 break-words text-sm leading-relaxed">
              {toast.message}
            </p>
          ) : null}
        </div>

        <button
          type="button"
          onClick={onClose}
          className="rounded-lg p-1 text-current opacity-70 hover:opacity-100"
          aria-label="Cerrar mensaje"
        >
          <Icon name="x" size={16} />
        </button>
      </div>
    </div>
  );
}

export function useToast() {
  const context = useContext(ToastContext);

  if (!context) {
    throw new Error("useToast debe usarse dentro de ToastProvider");
  }

  return context;
}