// src/public-site/components/HowItWorksSection.jsx

import { useMemo, useRef, useState } from "react";
import { motion, useScroll, useMotionValueEvent } from "framer-motion";

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

export default function HowItWorksSection() {
  const sectionRef = useRef(null);
  const [activeIndex, setActiveIndex] = useState(0);

  const { scrollYProgress } = useScroll({
    target: sectionRef,
    offset: ["start start", "end end"],
  });

  useMotionValueEvent(scrollYProgress, "change", (latest) => {
    const nextIndex = Math.min(
      steps.length - 1,
      Math.floor(latest * steps.length),
    );

    setActiveIndex(nextIndex);
  });

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
    text-[#0a192f]
  "
            >
              Así funciona Permuok
            </h2>

            <div className="mt-10">
              {steps.map((step, index) => {
                const isActive = index === activeIndex;

                return (
                  <button
                    key={step.title}
                    type="button"
                    onClick={() => setActiveIndex(index)}
                    className="
                      group
                      block
                      w-full
                      border-t
                      border-[#cfd5dd]
                      py-5
                      text-left

                      last:border-b
                    "
                  >
                    <div className="flex items-start gap-4">
                      <span
                        className={`
                          mt-[5px]
                          h-2.5
                          w-2.5
                          shrink-0
                          rounded-full
                          transition-all
                          duration-300

                          ${
                            isActive
                              ? "scale-100 bg-[#9fc5ff]"
                              : "scale-75 bg-[#c9d2dd]"
                          }
                        `}
                      />

                      <div>
                        <h3
                          className={`
                            text-[20px]
                            font-bold
                            leading-[1.15]
                            tracking-[-0.025em]
                            transition-colors
                            duration-300

                            ${isActive ? "text-[#0a192f]" : "text-[#687384]"}
                          `}
                        >
                          {step.title}
                        </h3>

                        <div
                          className={`
                            grid
                            transition-all
                            duration-500

                            ${
                              isActive
                                ? "grid-rows-[1fr] opacity-100"
                                : "grid-rows-[0fr] opacity-0"
                            }
                          `}
                        >
                          <div className="overflow-hidden">
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
                          </div>
                        </div>
                      </div>
                    </div>
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
              h-[620px]
              w-full
            "
          >
            {/* fondo de escena */}
            <div
              className="
                absolute
                inset-[8%_4%]
                rounded-[34px]
                border
                border-[#9fc5ff]/35
                bg-[#eaf2ff]/65
              "
            />

            {steps.map((step, index) => {
              const distance = index - activeIndex;
              const isActive = index === activeIndex;

              const translateX = distance * 55;
              const translateY = Math.abs(distance) * 22;
              const scale = isActive ? 1 : 0.9 - Math.abs(distance) * 0.035;
              const opacity = Math.abs(distance) > 2 ? 0 : isActive ? 1 : 0.26;

              return (
                <motion.div
                  key={step.title}
                  animate={{
                    x: translateX,
                    y: translateY,
                    scale,
                    opacity,
                  }}
                  transition={{
                    duration: 0.55,
                    ease: [0.22, 1, 0.36, 1],
                  }}
                  className="
                    absolute
                    left-1/2
                    top-1/2
                    w-[88%]
                    -translate-x-1/2
                    -translate-y-1/2
                  "
                  style={{
                    zIndex: steps.length - Math.abs(distance),
                  }}
                >
                  <div
                    className={`
                      overflow-hidden
                      rounded-[24px]
                      border
                      bg-white
                      shadow-[0_30px_80px_rgba(15,23,42,0.14)]
                      transition-colors
                      duration-300

                      ${isActive ? "border-[#9fc5ff]" : "border-[#d9e0e8]"}
                    `}
                  >
                    <img
                      src={step.image}
                      alt={step.title}
                      className="
                        block
                        h-auto
                        w-full
                      "
                    />
                  </div>
                </motion.div>
              );
            })}

            {/* indicador inferior */}
            <div
              className="
                absolute
                bottom-2
                left-1/2
                flex
                -translate-x-1/2
                gap-2
              "
            >
              {steps.map((step, index) => (
                <span
                  key={step.title}
                  className={`
                    h-1.5
                    rounded-full
                    transition-all
                    duration-300

                    ${
                      index === activeIndex
                        ? "w-8 bg-[#3974ba]"
                        : "w-1.5 bg-[#cbd5e1]"
                    }
                  `}
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
          <p
            className="
              text-[12px]
              font-bold
              uppercase
              tracking-[0.16em]
              text-[#3974ba]
            "
          >
            Cómo funciona
          </p>

          <h2
            className="
              mt-4
              text-[38px]
              font-normal
              leading-[0.98]
              tracking-[-0.04em]
              text-[#0a192f]

              sm:text-[46px]
            "
          >
            De tu cartera a una nueva oportunidad.
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
    <article>
      <div className="flex items-start gap-3">
        <span
          className="
            mt-[6px]
            h-2.5
            w-2.5
            shrink-0
            rounded-full
            bg-[#9fc5ff]
          "
        />

        <div>
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
        </div>
      </div>

      <div
        className="
          mt-6
          overflow-hidden
          rounded-[18px]
          border
          border-[#9fc5ff]/45
          bg-white
          shadow-[0_18px_45px_rgba(15,23,42,0.10)]

          sm:rounded-[22px]
        "
      >
        <img
          src={step.image}
          alt={step.title}
          className="
            block
            h-auto
            w-full
          "
        />
      </div>
    </article>
  );
}
