// src/public-site/components/MembershipsSection.jsx

import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import Reveal from "./Reveal";
import { Icon } from "../../ui/icons/Index";

const plans = [
  {
    name: "Plan Inicial",
    price: "$19.900",
    period: "/ 30 días",
    description:
      "Ideal para empezar a operar con una estructura simple y profesional.",
    cta: "Elegir Inicial",
    highlighted: false,
    bullets: ["1 agente", "0 inversores", "Publicar desarrollos: No"],
  },
  {
    name: "Plan Crecimiento",
    price: "$25.400",
    period: "/ 30 días",
    description:
      "La opción recomendada para inmobiliarias que buscan crecer con una estructura más sólida.",
    cta: "Elegir Crecimiento",
    highlighted: true,
    badge: "Recomendado",
    bullets: ["Hasta 3 agentes", "1 inversor", "Publicar desarrollos: No"],
  },
  {
    name: "Plan Proyectos",
    price: "$33.700",
    period: "/ 30 días",
    description:
      "Pensado para equipos con mayor volumen comercial y necesidad de más capacidad operativa.",
    cta: "Elegir Proyectos",
    highlighted: false,
    bullets: [
      "Hasta 5 agentes",
      "Hasta 2 inversores",
      "Publicar desarrollos: Sí",
    ],
  },
];

export default function MembershipsSection() {
  return (
    <section
      id="membresias"
      className="
        relative
        overflow-hidden
        px-5
       py-16
sm:py-20
lg:py-24

        text-[#30363d]

        sm:px-8
      

        lg:px-10
     
      "
    >
      <div className="relative z-10 mx-auto w-full max-w-[1320px]">
        {/* Header */}
        <Reveal>
          <div className="mx-auto max-w-[760px] text-center">
            <h2
              className="
                text-[40px]
                font-normal
                leading-[0.98]
                tracking-[-0.04em]
                text-[#30363d]

                sm:text-[46px]

                lg:text-[52px]
              "
            >
              Elegí el plan que mejor se adapta a tu lugar en la red.
            </h2>
          </div>
        </Reveal>

        {/* Mobile / tablet */}
        <div
          className="
    mt-12
    grid
    gap-7
    lg:hidden
  "
        >
          {plans.map((plan, index) => (
            <PlanCard key={plan.name} plan={plan} index={index} mobile />
          ))}
        </div>

        {/* Desktop */}
        <div
          className="
            mt-16
            hidden
            grid-cols-3
            items-stretch
            gap-6
            lg:grid
          "
        >
          {plans.map((plan, index) => (
            <PlanCard key={plan.name} plan={plan} index={index} />
          ))}
        </div>
      </div>
    </section>
  );
}

function PlanCard({ plan, index, mobile = false }) {
  return (
    <Reveal delay={0.08 + index * 0.06} className={mobile ? "shrink-0" : ""}>
      <motion.article
        whileHover={mobile ? undefined : { y: -4 }}
        transition={{
          duration: 0.28,
          ease: [0.22, 1, 0.36, 1],
        }}
        className={`
  relative
  flex
  h-full
  flex-col
  rounded-[18px]
  border
  bg-white
  shadow-[0_18px_45px_rgba(15,23,42,0.05)]

px-5
pt-9
pb-6

sm:px-7
sm:pt-10
sm:pb-7

          ${mobile ? "w-full" : ""}

          ${
            plan.highlighted
              ? "border-[#2166c2] shadow-[0_20px_55px_rgba(33,102,194,0.12)]"
              : "border-[#d8dde4]"
          }
        `}
      >
        {plan.highlighted && (
          <div
            className="
    absolute
    left-1/2
    top-0
    -translate-x-1/2
    -translate-y-1/2
    whitespace-nowrap
    rounded-full
    bg-[#2166c2]
    px-5
    py-2
    text-[11px]
    font-bold
    uppercase
    tracking-[0.08em]
    text-white
    shadow-[0_10px_30px_rgba(33,102,194,0.22)]
  "
          >
            {plan.badge}
          </div>
        )}

        <div className="flex h-full flex-col">
          <h3
            className="
              text-[24px]
              font-bold
              tracking-[-0.03em]
    text-[#30363d]
            "
          >
            {plan.name}
          </h3>

          <div className="mt-5 flex items-end gap-2">
            <p
              className="
                text-[38px]
sm:text-[42px]
                font-semibold
                leading-none
                tracking-[-0.05em]
                text-[#2166c2]
              "
            >
              {plan.price}
            </p>

            <p
              className="
                pb-1
                text-[14px]
                font-medium
                text-[#667085]
              "
            >
              {plan.period}
            </p>
          </div>

          <p
            className="
              mt-4
              lg:min-h-[72px]
              text-[15px]
              font-medium
              leading-[1.45]
              text-[#5b6470]
            "
          >
            {plan.description}
          </p>

          <Link
            to="/register"
            className={`
              mt-6
              flex
              min-h-[50px]
              w-full
              items-center
              justify-center
              rounded-[14px]
              border
              px-5
              text-[15px]
              font-bold
              transition
              duration-300

              ${
                plan.highlighted
                  ? `
                    border-[#2166c2]
                    bg-[#2166c2]
                    text-white
                    hover:bg-[#3974ba]
                    hover:border-[#3974ba]
                  `
                  : `
                    border-[#e5e7eb]
                    bg-[#f3f4f6]
            text-[#30363d]
                    hover:bg-[#e8ecf2]
                  `
              }
            `}
          >
            {plan.cta}
          </Link>

          <div className="mt-8 border-t border-[#e5e7eb] pt-6">
            <div className="grid gap-4">
              {plan.bullets.map((item) => (
                <div key={item} className="flex items-start gap-3">
                  <span className="mt-[2px] text-[#2166c2]">
                    <Icon name="checkCircle" size={17} />
                  </span>

                  <p
                    className="
                      text-[15px]
                      font-medium
                      leading-[1.4]
                      text-[#334155]
                    "
                  >
                    {item}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </motion.article>
    </Reveal>
  );
}
