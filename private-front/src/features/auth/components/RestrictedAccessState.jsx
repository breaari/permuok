import { useState } from "react";
import { useNavigate } from "react-router-dom";

import { Icon } from "../../../ui/icons/Index";
import { getOnboardingStage, getRestrictedAccessAction } from "../utils/access";

const CONTENT = {
  compatibilities: {
    pageTitle: "Matches IA",

    profile: {
      badge: "Matches inteligentes",
      title: "Tus matches están a un paso",
      description:
        "Para que Permuok pueda empezar a detectar oportunidades para tu inmobiliaria, primero necesitamos verificar tu perfil.",
    },

    review: {
      badge: "Matches inteligentes",
      title: "Tu perfil está en revisión",
      description:
        "Estamos verificando los datos de tu inmobiliaria. Cuando sea aprobado vas a poder acceder a tus matches.",
    },

    rejected: {
      badge: "Matches inteligentes",
      title: "Necesitamos que revises tu perfil",
      description:
        "Hay algunos datos que necesitan corrección antes de habilitar tus matches.",
    },

    membership: {
      badge: "Matches inteligentes",
      title: "Tus matches están listos para activarse",
      description:
        "Tu inmobiliaria ya fue verificada. Activá tu membresía para acceder a las oportunidades detectadas por Permuok.",
    },
  },

  properties: {
    pageTitle: "Mis publicaciones",

    profile: {
      badge: "Publicaciones",
      title: "Tus publicaciones están a un paso",
      description:
        "Completá y verificá el perfil de tu inmobiliaria para empezar a publicar propiedades.",
    },

    review: {
      badge: "Publicaciones",
      title: "Tu perfil está en revisión",
      description:
        "Cuando finalice la verificación vas a poder empezar a cargar y administrar tus publicaciones.",
    },

    rejected: {
      badge: "Publicaciones",
      title: "Necesitamos que revises tu perfil",
      description:
        "Hay algunos datos que necesitan corrección antes de habilitar tus publicaciones.",
    },

    membership: {
      badge: "Publicaciones",
      title: "Activá tu membresía para publicar",
      description:
        "Tu inmobiliaria ya fue verificada. Activá tu plan para empezar a administrar tus publicaciones.",
    },
  },

  searches: {
    pageTitle: "Mis búsquedas",

    profile: {
      badge: "Búsquedas",
      title: "Tus búsquedas están a un paso",
      description:
        "Completá el perfil de tu inmobiliaria para publicar necesidades de tus clientes y conectarlas con la red.",
    },

    review: {
      badge: "Búsquedas",
      title: "Tu perfil está en revisión",
      description:
        "Cuando finalice la verificación vas a poder empezar a publicar tus búsquedas.",
    },

    rejected: {
      badge: "Búsquedas",
      title: "Necesitamos que revises tu perfil",
      description:
        "Hay algunos datos que necesitan corrección antes de habilitar tus búsquedas.",
    },

    membership: {
      badge: "Búsquedas",
      title: "Activá tu membresía para publicar búsquedas",
      description:
        "Tu inmobiliaria ya fue verificada. Activá tu plan para empezar a trabajar con la red.",
    },
  },

  users: {
    pageTitle: "Mis usuarios",

    profile: {
      badge: "Equipo",
      title: "Tu equipo está a un paso",
      description:
        "Primero necesitamos completar y verificar los datos de tu inmobiliaria.",
    },

    review: {
      badge: "Equipo",
      title: "Tu perfil está en revisión",
      description:
        "Cuando tu inmobiliaria sea aprobada vas a poder avanzar con la gestión de tu equipo.",
    },

    rejected: {
      badge: "Equipo",
      title: "Necesitamos que revises tu perfil",
      description:
        "Hay algunos datos pendientes antes de habilitar la administración de usuarios.",
    },

    membership: {
      badge: "Equipo",
      title: "Activá tu membresía para sumar tu equipo",
      description:
        "La cantidad de agentes e inversores disponibles depende del plan que elijas.",
    },
  },
};

function getContent(section, stage) {
  const content = CONTENT[section] || CONTENT.compatibilities;

  if (stage === "profile_review") {
    return {
      pageTitle: content.pageTitle,
      ...content.review,
    };
  }

  if (stage === "profile_rejected") {
    return {
      pageTitle: content.pageTitle,
      ...content.rejected,
    };
  }

  if (stage === "membership_required") {
    return {
      pageTitle: content.pageTitle,
      ...content.membership,
    };
  }

  return {
    pageTitle: content.pageTitle,
    ...content.profile,
  };
}

export default function RestrictedAccessState({
  access,
  section = "compatibilities",
}) {
  const navigate = useNavigate();

  const [showMatchInfo, setShowMatchInfo] = useState(false);

  const stage = getOnboardingStage(access);

  const content = getContent(section, stage);

  const action = getRestrictedAccessAction(access);

  return (
    <main className="mx-auto min-h-screen w-full max-w-6xl px-4 py-6 sm:px-5 sm:py-8 md:px-6 md:py-12 lg:px-12">
      {/* TÍTULO DE PÁGINA */}
      <div className="mb-8 sm:mb-10">
        <h1 className="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl md:text-4xl">
          {content.pageTitle}
        </h1>
      </div>

      {/* BLOQUE DE ACCESO RESTRINGIDO */}
      <section
        className="
          relative
          overflow-hidden
          rounded-[26px]
          border
          border-emerald-100
          bg-gradient-to-br
          from-white
          via-white
          to-emerald-50/70
          px-6
          py-8
          shadow-sm
          sm:px-8
          sm:py-10
          md:px-10
          md:py-12
        "
      >
        {/* DETALLE VISUAL */}
        <div
          className="
            pointer-events-none
            absolute
            -right-24
            -top-28
            h-72
            w-72
            rounded-full
            bg-emerald-100/40
            blur-3xl
          "
        />

        <div className="relative max-w-2xl">
          {/* BADGE */}
          <div
            className="
              inline-flex
              items-center
              gap-2
              rounded-full
              border
              border-emerald-200
              bg-emerald-50
              px-3
              py-1.5
              text-[11px]
              font-bold
              uppercase
              tracking-[0.12em]
              text-emerald-700
            "
          >
            <Icon
              name={section === "compatibilities" ? "sparkles" : "shieldCheck"}
              size={14}
            />

            {content.badge}
          </div>

          {/* CONTENIDO */}
          <h2
            className="
              mt-5
              max-w-xl
              text-2xl
              font-bold
              leading-tight
              text-[#30363d]
              sm:text-3xl
            "
          >
            {content.title}
          </h2>

          <p
            className="
              mt-4
              max-w-xl
              text-sm
              leading-6
              text-slate-500
              sm:text-base
              sm:leading-7
            "
          >
            {content.description}
          </p>

          {/* ACCIONES */}
          <div className="mt-7 flex flex-wrap items-center gap-3">
            {action && (
              <button
                type="button"
                onClick={() => navigate(action.href)}
                className="
                  inline-flex
                  items-center
                  gap-2
                  rounded-xl
                  bg-[#76bc21]
                  px-5
                  py-3
                  text-sm
                  font-bold
                  text-white
                  shadow-sm
                  transition
                  hover:brightness-95
                "
              >
                {action.label}

                <Icon name="arrowRight" size={16} />
              </button>
            )}

            {section === "compatibilities" && (
              <button
                type="button"
                onClick={() => setShowMatchInfo((current) => !current)}
                aria-expanded={showMatchInfo}
                className="
                  inline-flex
                  items-center
                  gap-2
                  rounded-xl
                  border
                  border-slate-200
                  bg-white
                  px-5
                  py-3
                  text-sm
                  font-semibold
                  text-slate-600
                  transition
                  hover:border-slate-300
                  hover:bg-slate-50
                  hover:text-slate-900
                "
              >
                <Icon name="info" size={16} />
                ¿Cómo funciona el motor de match?
                <span
                  className={`
                    ml-1
                    text-xs
                    transition-transform
                    duration-200
                    ${showMatchInfo ? "rotate-180" : ""}
                  `}
                >
                  ↓
                </span>
              </button>
            )}
          </div>

          {/* EXPLICACIÓN MOTOR DE MATCH */}
          {section === "compatibilities" && showMatchInfo && (
            <div
              className="
                  mt-5
                  max-w-2xl
                  rounded-2xl
                  border
                  border-slate-200
                  bg-white/80
                  px-5
                  py-5
                "
            >
              <p className="text-sm font-bold text-[#30363d]">
                Permuok cruza automáticamente las oportunidades de la red.
              </p>

              <p className="mt-2 text-sm leading-6 text-slate-500">
                El motor compara tus publicaciones y búsquedas con las
                oportunidades disponibles en Permuok para detectar posibles
                compatibilidades.
              </p>

              <p className="mt-3 text-sm leading-6 text-slate-500">
                Tiene en cuenta criterios como tipo de propiedad, ubicación,
                valor, características y forma de operación. Cuando encuentra
                una coincidencia relevante, aparece dentro de Matches IA para
                que puedas evaluarla.
              </p>
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
