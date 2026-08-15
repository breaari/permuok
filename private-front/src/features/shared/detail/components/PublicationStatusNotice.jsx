function getNoticeMeta(item) {
  const status = item?.status;
  const deletedAt = item?.deleted_at;

  if (deletedAt) {
    return {
      eyebrow: "Publicación no disponible",
      title: "Publicación eliminada",
      message:
        "Esta publicación fue eliminada y ya no forma parte de la red activa. El enlace puede seguir disponible desde una vista previa, historial o conversación anterior.",
      className: "border-rose-200 bg-rose-50 text-rose-900",
      badgeClassName: "bg-rose-100 text-rose-700",
    };
  }

  if (status === "paused") {
    return {
      eyebrow: "Visibilidad pausada",
      title: "Publicación pausada",
      message:
        "Esta publicación está temporalmente pausada. No aparece en la exploración pública ni recibe nuevas consultas desde el marketplace.",
      className: "border-amber-200 bg-amber-50 text-amber-900",
      badgeClassName: "bg-amber-100 text-amber-700",
    };
  }

  if (status === "archived") {
    return {
      eyebrow: "Contenido archivado",
      title: "Publicación archivada",
      message:
        "Esta publicación se conserva como historial interno, pero ya no está activa dentro de la red.",
      className: "border-slate-200 bg-slate-100 text-slate-800",
      badgeClassName: "bg-slate-200 text-slate-700",
    };
  }

  if (status === "closed") {
    return {
      eyebrow: "Operación cerrada",
      title: "Publicación cerrada",
      message:
        "Esta publicación fue cerrada porque la oportunidad ya no se encuentra disponible.",
      className: "border-slate-200 bg-slate-100 text-slate-800",
      badgeClassName: "bg-slate-200 text-slate-700",
    };
  }

  return null;
}

function capitalize(value) {
  const text = String(value || "").trim();
  if (!text) return "";
  return text.charAt(0).toUpperCase() + text.slice(1);
}

export default function PublicationStatusNotice({ item }) {
  const meta = getNoticeMeta(item);

  if (!meta) return null;

  return (
    <div
      className={`mb-8 rounded-2xl border px-6 py-5 shadow-sm ${meta.className}`}
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="max-w-3xl">
          <p className="text-[11px] font-black uppercase tracking-[0.18em] opacity-70">
            {meta.eyebrow}
          </p>

          <h2 className="mt-2 text-lg font-black tracking-tight">
            {meta.title}
          </h2>

          <p className="mt-2 text-sm font-medium leading-6 opacity-90">
            {meta.message}
          </p>
        </div>

        <span
          className={`inline-flex w-fit rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide ${meta.badgeClassName}`}
        >
          No activa
        </span>
      </div>
    </div>
  );
}
