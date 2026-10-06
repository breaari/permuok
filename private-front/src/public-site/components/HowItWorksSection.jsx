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

      {/* captura mobile */}

      <div
        className="
          mt-6
          h-[260px]
          w-full
          overflow-hidden
          rounded-[18px]
          border
          border-[#d8e1eb]
          shadow-[0_18px_45px_rgba(15,23,42,0.10)]

          sm:h-[390px]
          sm:rounded-[22px]
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
    </motion.article>
  );
}
