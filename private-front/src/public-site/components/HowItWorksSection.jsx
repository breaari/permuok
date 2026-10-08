// src/public-site/components/HowItWorksSection.jsx

import { useRef, useState } from "react";
import {
  AnimatePresence,
  motion,
  useMotionValueEvent,
  useScroll,
} from "framer-motion";

import registrateImg from "../../assets/registrate.png";
import publicaImg from "../../assets/publica.png";
import matchesImg from "../../assets/matches.png";
import explorarImg from "../../assets/explorar.png";
import consultarImg from "../../assets/consultar.png";

const steps = [
  {
    title: "Registrate y verificá tu inmobiliaria",
    text: "Creá tu cuenta y validá tu inmobiliaria para formar parte de una red profesional y segura.",
    image: registrateImg,
  },
  {
    title: "Publicá tu cartera",
    text: "Cargá propiedades, búsquedas activas y desarrollos que acepten permuta.",
    image: publicaImg,
  },
  {
    title: "Permuok detecta compatibilidades",
    text: "El sistema cruza automáticamente tu cartera para encontrar nuevas oportunidades.",
    image: matchesImg,
  },
  {
    title: "Explorá cada oportunidad",
    text: "Revisá las compatibilidades que Permuok detecta automáticamente o recorré las publicaciones disponibles en toda la red.",
    image: explorarImg,
  },
  {
    title: "Dá el primer paso",
    text: "Hacé saber que te interesa una publicación, conversá con la otra inmobiliaria y, si ambos desean avanzar, compartan sus datos de contacto para continuar la operación por fuera de Permuok.",
    image: consultarImg,
  },
];

const ease = [0.76, 0, 0.24, 1];

export default function HowItWorksSection() {
  const sectionRef = useRef(null);

  const [activeIndex, setActiveIndex] = useState(0);
  const [direction, setDirection] = useState(1);

  const { scrollYProgress } = useScroll({
    target: sectionRef,
    offset: ["start start", "end end"],
  });

  useMotionValueEvent(scrollYProgress, "change", (latest) => {
    const nextIndex = Math.min(
      steps.length - 1,
      Math.floor(latest * steps.length),
    );

    if (nextIndex !== activeIndex) {
      setDirection(nextIndex > activeIndex ? 1 : -1);
      setActiveIndex(nextIndex);
    }
  });

  function handleStepClick(index) {
    if (index === activeIndex) return;

    setDirection(index > activeIndex ? 1 : -1);
    setActiveIndex(index);
  }

  return (
    <section
      ref={sectionRef}
      id="funciona"
      className="
        relative
        bg-[#f3f4f6]
        text-[#0a192f]

lg:h-[380vh]
      "
    >
      {/* =====================================================
          FONDO PUNTEADO
      ====================================================== */}

      <div
        className="pointer-events-none absolute inset-0 z-0 opacity-65"
        style={{
          backgroundImage:
            "radial-gradient(rgba(71,85,105,0.13) 0.75px, transparent 0.75px)",
          backgroundSize: "18px 18px",
        }}
      />

      {/* =====================================================
          DESKTOP
      ====================================================== */}

     <div
  className="
    relative
    z-10
    hidden
    h-screen
    overflow-hidden

    lg:sticky
    lg:top-0
    lg:flex
    lg:items-start
    lg:pt-[135px]
  "
>
        <div
          className="
            mx-auto
            grid
            w-full
            max-w-[1320px]
            grid-cols-[0.78fr_1.22fr]
            gap-20
            px-10
          "
        >
          {/* ===================================================
              IZQUIERDA — PASOS
          =================================================== */}

          <div>
            <h2
              className="
    max-w-[520px]
    text-[48px]
    font-normal
    leading-[0.97]
    tracking-[-0.04em]
    text-[#30363d]
  "
            >
              Así funciona Permu
              <span className="text-[#2166c2]">ok</span>
            </h2>

            <div className="mt-10">
              {steps.map((step, index) => {
                const isActive = index === activeIndex;

                return (
                  <button
                    key={step.title}
                    type="button"
                    onClick={() => handleStepClick(index)}
                    className="
                      group
                      relative
                      block
                      w-full
                      border-b
                      border-[#cfd5dd]
                      py-5
                      text-left
                    "
                  >
                    {/* línea activa */}

                    <motion.span
                      initial={false}
                      animate={{
                        scaleX: isActive ? 1 : 0,
                      }}
                      transition={{
                        duration: 0.45,
                        ease,
                      }}
                      className="
                        absolute
                        bottom-[-1px]
                        left-0
                        h-[2px]
                        w-full
                        origin-left
                        bg-[#9fc5ff]
                      "
                    />

                    <motion.div
                      animate={{
                        opacity: isActive ? 1 : 0.38,
                      }}
                      transition={{
                        duration: 0.35,
                      }}
                    >
                      <h3
                        className={`
                          text-[20px]
                          font-semibold
                          leading-[1.15]
                          tracking-[-0.025em]
                          transition-colors
                          duration-300

                          ${isActive ? "text-[#0a192f]" : "text-[#687384]"}
                        `}
                      >
                        {step.title}
                      </h3>

                      <AnimatePresence initial={false}>
                        {isActive && (
                          <motion.div
                            key={`${step.title}-description`}
                            initial={{
                              height: 0,
                              opacity: 0,
                            }}
                            animate={{
                              height: "auto",
                              opacity: 1,
                            }}
                            exit={{
                              height: 0,
                              opacity: 0,
                            }}
                            transition={{
                              duration: 0.42,
                              ease,
                            }}
                            className="overflow-hidden"
                          >
                            <p
                              className="
                                max-w-[500px]
                                pt-3
                                text-[16px]
                                font-medium
                                leading-[1.45]
                                text-[#4b5563]
                              "
                            >
                              {step.text}
                            </p>
                          </motion.div>
                        )}
                      </AnimatePresence>
                    </motion.div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* ===================================================
              DERECHA — SCREENSHOTS
          =================================================== */}

          <div
            className="
              relative
              flex
              h-[590px]
              w-full
              items-center
              justify-center
            "
          >
            {/* viewport fijo donde las capturas se deslizan */}

            <div
              className="
                relative
                h-[430px]
                w-full
                max-w-[760px]
                overflow-hidden
                rounded-[20px]
                border
                border-[#d8e1eb]
                shadow-[0_26px_70px_rgba(15,23,42,0.12)]
              "
            >
              <AnimatePresence initial={false} custom={direction} mode="sync">
                <motion.div
                  key={activeIndex}
                  custom={direction}
                  variants={{
                    enter: (dir) => ({
                      y: dir > 0 ? "100%" : "-100%",
                    }),

                    center: {
                      y: "0%",
                    },

                    exit: (dir) => ({
                      y: dir > 0 ? "-100%" : "100%",
                    }),
                  }}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  transition={{
                    duration: 0.72,
                    ease,
                  }}
                  className="
                    absolute
                    inset-0
                    h-full
                    w-full
                  "
                >
                  <img
                    src={steps[activeIndex].image}
                    alt={steps[activeIndex].title}
                    className="
                      block
                      h-full
                      w-full
                      object-cover
                      object-center
                    "
                  />
                </motion.div>
              </AnimatePresence>
            </div>
          </div>
        </div>
      </div>
      {/* =====================================================
    MOBILE / TABLET
===================================================== */}

      <div
        className="
    relative
    z-10
    px-5
    py-20

    sm:px-8
    sm:py-24

    lg:hidden
  "
      >
        <MobileHowItWorks />
      </div>
    </section>
  );
}

/* =========================================================
   MOBILE / TABLET ACCORDION
========================================================= */

function MobileHowItWorks() {
  const [mobileActiveIndex, setMobileActiveIndex] = useState(0);

  function toggleStep(index) {
    setMobileActiveIndex(index);
  }

  return (
    <div className="mx-auto max-w-[760px]">
      <h2
        className="
          text-[38px]
          font-normal
          leading-[0.98]
          tracking-[-0.04em]
          text-[#30363d]

          sm:text-[46px]
        "
      >
        Así funciona Permu
        <span className="text-[#2166c2]">ok</span>
      </h2>

      <div className="mt-10">
        {steps.map((step, index) => {
          const isActive = mobileActiveIndex === index;

          return (
            <div
              key={step.title}
              className="
                border-b
                border-[#cfd5dd]
              "
            >
              {/* CABECERA */}

              <button
                type="button"
                onClick={() => toggleStep(index)}
                className="
                  flex
                  w-full
                  items-start
                  justify-between
                  gap-5
                  py-6
                  text-left
                "
              >
                <h3
                  className={`
                    text-[20px]
                    font-semibold
                    leading-[1.2]
                    tracking-[-0.025em]
                    transition-colors
                    duration-300

                    ${isActive ? "text-[#30363d]" : "text-[#687384]"}
                  `}
                >
                  {step.title}
                </h3>

                {/* + / − */}

                <span
                  className="
                    relative
                    mt-[2px]
                    block
                    h-5
                    w-5
                    shrink-0
                  "
                >
                  <span
                    className="
                      absolute
                      left-1/2
                      top-1/2
                      h-px
                      w-4
                      -translate-x-1/2
                      -translate-y-1/2
                      bg-[#687384]
                    "
                  />

                  <motion.span
                    animate={{
                      rotate: isActive ? 0 : 90,
                      opacity: isActive ? 0 : 1,
                    }}
                    transition={{
                      duration: 0.3,
                      ease,
                    }}
                    className="
                      absolute
                      left-1/2
                      top-1/2
                      h-px
                      w-4
                      -translate-x-1/2
                      -translate-y-1/2
                      bg-[#687384]
                    "
                  />
                </span>
              </button>

              {/* CONTENIDO ABIERTO */}

              <AnimatePresence initial={false}>
                {isActive && (
                  <motion.div
                    key={`${step.title}-mobile-content`}
                    initial={{
                      height: 0,
                      opacity: 0,
                    }}
                    animate={{
                      height: "auto",
                      opacity: 1,
                    }}
                    exit={{
                      height: 0,
                      opacity: 0,
                    }}
                    transition={{
                      duration: 0.5,
                      ease,
                    }}
                    className="overflow-hidden"
                  >
                    <div className="pb-7">
                      <motion.p
                        initial={{
                          opacity: 0,
                          y: 10,
                        }}
                        animate={{
                          opacity: 1,
                          y: 0,
                        }}
                        transition={{
                          delay: 0.08,
                          duration: 0.42,
                          ease,
                        }}
                        className="
                          max-w-[620px]
                          text-[16px]
                          font-medium
                          leading-[1.5]
                          text-[#4b5563]

                          sm:text-[17px]
                        "
                      >
                        {step.text}
                      </motion.p>

                      {/* IMAGEN */}

                      <motion.div
                        initial={{
                          opacity: 0,
                          y: 20,
                          scale: 0.985,
                        }}
                        animate={{
                          opacity: 1,
                          y: 0,
                          scale: 1,
                        }}
                        transition={{
                          delay: 0.12,
                          duration: 0.55,
                          ease,
                        }}
                        className="
                          mt-6
                          h-[260px]
                          w-full
                          overflow-hidden
                          rounded-[16px]
                          border
                          border-[#d8e1eb]
                          shadow-[0_18px_45px_rgba(15,23,42,0.10)]

                          sm:h-[390px]
                          sm:rounded-[20px]
                        "
                      >
                        <img
                          src={step.image}
                          alt={step.title}
                          className="
                            block
                            h-full
                            w-full
                            object-cover
                            object-center
                          "
                        />
                      </motion.div>
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </div>
          );
        })}
      </div>
    </div>
  );
}
