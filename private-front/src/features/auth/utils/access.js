export function getRole(user) {
  return Number(user?.role || 0);
}

export function isAdmin(user) {
  return getRole(user) === 1;
}

export function isRealEstate(user) {
  return getRole(user) === 2;
}

export function isAgent(user) {
  return getRole(user) === 3;
}

export function isInvestor(user) {
  return getRole(user) === 4;
}

/* =========================================================
   ACCESS LEVEL
========================================================= */

export function getAccessLevel(access) {
  return String(access?.level || "").trim();
}

/*
 * Estados de una inmobiliaria durante el onboarding.
 *
 * No confundimos:
 * - completar perfil
 * - revisión
 * - correcciones
 * - membresía
 * - acceso completo
 */
export function getOnboardingStage(access) {
  const level = getAccessLevel(access);

  switch (level) {
    case "real_estate_not_linked":
      return "profile_not_started";

    case "real_estate_draft":
      return "profile_incomplete";

    case "real_estate_review":
      return "profile_review";

    case "real_estate_rejected":
      return "profile_rejected";

    case "real_estate_unpaid":
    case "real_estate_unpaid_changes_pending":
      return "membership_required";

    case "real_estate_active":
    case "real_estate_active_changes_pending":
      return "active";

    case "real_estate_suspended":
      return "suspended";

    case "agent_active":
      return "active";

    case "agent_restricted":
    case "agent_unlinked":
    case "agent_real_estate_suspended":
      return "restricted";

    case "investor_active":
      return "active";

    case "investor_restricted":
    case "investor_unlinked":
      return "restricted";

    case "super_admin":
      return "active";

    default:
      return "unknown";
  }
}

/*
 * Acceso completo a las funciones operativas de la red:
 * matches, publicaciones, búsquedas, conversaciones, etc.
 */
export function canUseNetwork(access) {
  return getOnboardingStage(access) === "active";
}

export function needsProfileCompletion(access) {
  return [
    "profile_not_started",
    "profile_incomplete",
    "profile_rejected",
  ].includes(getOnboardingStage(access));
}

export function isProfileUnderReview(access) {
  return getOnboardingStage(access) === "profile_review";
}

export function needsMembership(access) {
  return getOnboardingStage(access) === "membership_required";
}

/*
 * Permite decidir qué CTA mostrar cuando una pantalla
 * está bloqueada.
 */
export function getRestrictedAccessAction(access) {
  const stage = getOnboardingStage(access);

  switch (stage) {
    case "profile_not_started":
    case "profile_incomplete":
      return {
        label: "Completar mi perfil",
        href: "/my-profile",
      };

    case "profile_rejected":
      return {
        label: "Revisar mi perfil",
        href: "/my-profile",
      };

    case "profile_review":
      return {
        label: "Ver estado de mi perfil",
        href: "/my-profile",
      };

    case "membership_required":
      return {
        label: "Activar mi membresía",
        href: "/billing",
      };

    default:
      return null;
  }
}

/* =========================================================
   DEVELOPMENTS
========================================================= */

export function canPublishDevelopments(user, access = null) {
  const candidates = [
    user?.can_publish_projects,
    user?.membership?.can_publish_projects,

    access?.can_publish_projects,
    access?.membership?.can_publish_projects,

    access?.features?.can_publish_projects,
    access?.features?.publish_projects,
    access?.features?.can_publish_developments,
    access?.features?.developments_publish,
  ];

  return candidates.some((value) => Number(value) === 1 || value === true);
}

export function canViewDevelopments(user, access = null) {
  const candidates = [
    user?.can_view_projects,
    user?.membership?.can_view_projects,

    access?.can_view_projects,
    access?.membership?.can_view_projects,

    access?.features?.can_view_projects,
    access?.features?.view_projects,
    access?.features?.can_view_developments,
    access?.features?.developments_view,
  ];

  return candidates.some((value) => Number(value) === 1 || value === true);
}

export function canUseDevelopments(user, access = null) {
  const role = getRole(user);

  if (![2, 3].includes(role)) return false;

  return canPublishDevelopments(user, access);
}

export function canExploreDevelopments(user, access = null) {
  const role = getRole(user);

  if (![2, 3, 4].includes(role)) return false;

  return canViewDevelopments(user, access);
}
