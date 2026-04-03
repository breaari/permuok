export function formatDate(value) {
  if (!value) return "—";

  try {
    return new Date(value).toLocaleDateString("es-AR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

export function roleLabel(role) {
  if (Number(role) === 2 || role === "real_estate") return "Inmobiliaria";
  if (Number(role) === 3 || role === "agent") return "Agente";
  if (Number(role) === 4 || role === "investor") return "Inversor";
  return "Usuario";
}

export function statusLabel(isActive) {
  return Number(isActive) === 1 ? "Activo" : "Inactivo";
}

export function membershipStatusLabel(membershipStatus) {
  if (membershipStatus === "active") return "Activa";
  if (membershipStatus === "cancel_at_period_end") return "Cancelación programada";
  if (membershipStatus === "scheduled_change") return "Cambio programado";
  return "Sin membresía";
}

export function membershipStatusClasses(membershipStatus) {
  if (membershipStatus === "active") {
    return "bg-emerald-100 text-emerald-700";
  }

  if (membershipStatus === "cancel_at_period_end") {
    return "bg-amber-100 text-amber-700";
  }

  if (membershipStatus === "scheduled_change") {
    return "bg-sky-100 text-sky-700";
  }

  return "bg-slate-100 text-slate-600";
}

export function resolveStage(realEstate) {
  return realEstate?.admin_profile_stage || "initial_review";
}

export function getProfileStatusMeta(realEstate) {
  const stage = resolveStage(realEstate);

  switch (stage) {
    case "incomplete":
      return {
        label: "Incompleta",
        pillClass:
          "px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider",
        dotClass: "bg-slate-400",
        footerLabel: "Perfil incompleto",
      };
    case "ready_for_review":
      return {
        label: "Lista para revisión",
        pillClass:
          "px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold uppercase tracking-wider",
        dotClass: "bg-indigo-500",
        footerLabel: "Lista para ser enviada a revisión",
      };
    case "initial_review":
      return {
        label: "Revisión inicial",
        pillClass:
          "px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold uppercase tracking-wider",
        dotClass: "bg-amber-500 animate-pulse",
        footerLabel: "Pendiente de revisión inicial",
      };
    case "approved":
      return {
        label: "Aprobada",
        pillClass:
          "px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wider",
        dotClass: "bg-emerald-500",
        footerLabel: "Aprobada",
      };
    case "rejected":
      return {
        label: "Rechazada",
        pillClass:
          "px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold uppercase tracking-wider",
        dotClass: "bg-rose-500",
        footerLabel: "Rechazada",
      };
    case "changes_pending":
      return {
        label: "Cambios pendientes",
        pillClass:
          "px-3 py-1 bg-sky-100 text-sky-700 rounded-full text-xs font-bold uppercase tracking-wider",
        dotClass: "bg-sky-500 animate-pulse",
        footerLabel: "Cambios pendientes de revisión",
      };
    default:
      return {
        label: "Borrador",
        pillClass:
          "px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider",
        dotClass: "bg-slate-400",
        footerLabel: "Borrador",
      };
  }
}