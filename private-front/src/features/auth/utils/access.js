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