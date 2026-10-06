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

const ease = [0.22, 1, 0.36, 1];

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

        lg:h-[500vh]
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
          items-center
          overflow-hidden

          lg:sticky
          lg:top-0
          lg:flex
        "
      >
        <div
          className="
            mx-auto
            grid
            w-full
            max-w-[1320px]
            grid-cols-[0.78fr_1.22fr]
            items-center
            gap-20
            px-10
          "
        >
          {/* ===================================================
              IZQUIERDA
          =================================================== */}

          <div>
            <h2
              className="
                max-w-[520px]
                text-[48px]
                font-normal
                leading-[0.97]
                tracking-[-0.04em]
                text-[#0a192f]
              "
            >
              Así funciona Permuok
            </h2>

            <div className="mt-10 border-t border-[#cfd5dd]">
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
                        x: isActive ? 0 : -2,
                        opacity: isActive ? 1 : 0.48,
                      }}
                      transition={{
                        duration: 0.35,
                        ease,
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
                              y: -6,
                            }}
                            animate={{
                              height: "auto",
                              opacity: 1,
                              y: 0,
                            }}
                            exit={{
                              height: 0,
                              opacity: 0,
                              y: -4,
                            }}
                            transition={{
                              duration: 0.4,
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
              DERECHA — ESCENA DE CAPTURAS
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
            {/* marco fijo */}
            <div
              className="
                relative
                flex
                h-[500px]
                w-full
                max-w-[760px]
                items-center
                justify-center
                overflow-hidden
                rounded-[28px]
                border
                border-[#9fc5ff]/40
                bg-[#eaf2ff]/60
                p-4
              "
            >
              {/* halo suave */}
              <div
                className="
                  pointer-events-none
                  absolute
                  left-1/2
                  top-1/2
                  h-[360px]
                  w-[620px]
                  -translate-x-1/2
                  -translate-y-1/2
                  rounded-full
                  bg-[#9fc5ff]/15
                  blur-3xl
                "
              />

              <AnimatePresence initial={false} mode="wait" custom={direction}>
                <motion.div
                  key={activeIndex}
                  custom={direction}
                  variants={{
                    enter: (dir) => ({
                      opacity: 0,
                      x: dir > 0 ? 34 : -34,
                      y: 10,
                      scale: 0.975,
                    }),

                    center: {
                      opacity: 1,
                      x: 0,
                      y: 0,
                      scale: 1,
                    },

                    exit: (dir) => ({
                      opacity: 0,
                      x: dir > 0 ? -26 : 26,
                      y: -6,
                      scale: 0.985,
                    }),
                  }}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  transition={{
                    duration: 0.55,
                    ease,
                  }}
                  className="
                    relative
                    z-10
                    flex
                    h-full
                    w-full
                    items-center
                    justify-center
                  "
                >
                  <div
                    className="
    h-[410px]
    w-full
    overflow-hidden
    rounded-[20px]
    border
    border-[#d8e1eb]
    shadow-[0_26px_70px_rgba(15,23,42,0.14)]
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
                  </div>
                </motion.div>
              </AnimatePresence>
            </div>

            {/* indicador inferior */}
            <div
              className="
                absolute
                bottom-5
                left-1/2
                flex
                -translate-x-1/2
                gap-2
              "
            >
              {steps.map((step, index) => (
                <motion.span
                  key={step.title}
                  animate={{
                    width: index === activeIndex ? 30 : 6,
                    opacity: index === activeIndex ? 1 : 0.4,
                  }}
                  transition={{
                    duration: 0.35,
                    ease,
                  }}
                  className="
                    h-1.5
                    rounded-full
                    bg-[#3974ba]
                  "
                />
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* =====================================================
          MOBILE / TABLET
      ====================================================== */}

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
        <div className="mx-auto max-w-[760px]">
          <h2
            className="
              text-[38px]
              font-normal
              leading-[0.98]
              tracking-[-0.04em]
              text-[#0a192f]

              sm:text-[46px]
            "
          >
            Así funciona Permuok
          </h2>

          <div className="mt-12 space-y-14">
            {steps.map((step, index) => (
              <MobileStep key={step.title} step={step} index={index} />
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

/* =========================================================
   MOBILE STEP
========================================================= */

function MobileStep({ step, index }) {
  return (
    <motion.article
      initial={{
        opacity: 0,
        y: 28,
      }}
      whileInView={{
        opacity: 1,
        y: 0,
      }}
      viewport={{
        once: true,
        amount: 0.25,
      }}
      transition={{
        duration: 0.55,
        ease,
      }}
    >
      <p
        className="
          text-[11px]
          font-bold
          uppercase
          tracking-[0.14em]
          text-[#3974ba]
        "
      >
        Paso {index + 1}
      </p>

      <h3
        className="
          mt-2
          text-[24px]
          font-bold
          leading-[1.12]
          tracking-[-0.03em]
          text-[#0a192f]

          sm:text-[28px]
        "
      >
        {step.title}
      </h3>

      <p
        className="
          mt-3
          max-w-[600px]
          text-[16px]
          font-medium
          leading-[1.45]
          text-[#4b5563]

          sm:text-[17px]
        "
      >
        {step.text}
      </p>

      {/* tamaño visual constante también en mobile */}
      <div
        className="
          mt-6
          flex
          h-[260px]
          w-full
          items-center
          justify-center
          overflow-hidden
          rounded-[18px]
          border
          border-[#9fc5ff]/45
          bg-[#eaf2ff]/50
          p-2
          shadow-[0_18px_45px_rgba(15,23,42,0.10)]

          sm:h-[390px]
          sm:rounded-[22px]
          sm:p-3
        "
      >
        <div
          className="
    h-full
    w-full
    overflow-hidden
    rounded-[14px]
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
        </div>
      </div>
    </motion.article>
  );
}
