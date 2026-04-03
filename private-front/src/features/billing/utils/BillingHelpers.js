export function getPlanMeta(plan, plans) {
  const sorted = [...plans].sort(
    (a, b) => Number(a.price_ars) - Number(b.price_ars),
  );

  const middleIndex = sorted.length === 3 ? 1 : -1;
  const currentIndex = sorted.findIndex((p) => p.id === plan.id);

  if (currentIndex === middleIndex) {
    return {
      featured: true,
      badge: "Más elegido",
      description:
        "La opción recomendada para inmobiliarias que buscan crecer con una estructura más sólida.",
    };
  }

  if (currentIndex === 0) {
    return {
      featured: false,
      badge: "",
      description:
        "Ideal para comenzar a operar con una estructura simple y profesional.",
    };
  }

  return {
    featured: false,
    badge: "",
    description:
      "Pensado para equipos con mayor volumen comercial y necesidad de más capacidad operativa.",
  };
}

export function resolveCurrentPlan(plans, access) {
  if (!Array.isArray(plans) || plans.length === 0) return null;

  const membershipPlanId = Number(access?.membership?.plan_id ?? 0);

  if (membershipPlanId > 0) {
    const byMembership = plans.find((p) => Number(p.id) === membershipPlanId);
    if (byMembership) return byMembership;
  }

  if (!access?.limits) return null;

  return (
    plans.find((p) => {
      return (
        Number(p.max_agents) === Number(access?.limits?.agents) &&
        Number(p.max_investors) === Number(access?.limits?.investors) &&
        Number(p.can_publish_projects) ===
          (access?.features?.publish_projects ? 1 : 0)
      );
    }) ?? null
  );
}