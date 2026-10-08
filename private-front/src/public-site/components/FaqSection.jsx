// src/public-site/components/FaqSection.jsx

import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import Reveal from "./Reveal";

const faqs = [
  {
    question: "¿Permuok es un portal inmobiliario?",
    answer:
      "No. Permuok es una red profesional entre inmobiliarias. No está pensada como una vidriera abierta al público, sino como un entorno de trabajo donde las inmobiliarias pueden publicar, buscar, detectar compatibilidades y conectar oportunidades.",
  },
  {
    question: "¿Solo sirve para permutas?",
    answer:
      "No. La permuta es uno de sus principales diferenciales, pero también podés trabajar búsquedas activas, ventas convencionales, desarrollos, operaciones con diferencia en dinero, múltiples bienes y cadenas entre distintas partes.",
  },
  {
    question: "¿Quién puede formar parte de la red?",
    answer:
      "Permuok está pensada exclusivamente para inmobiliarias verificadas. Cada registro pasa por un proceso de validación antes de acceder a la red.",
  },
  {
    question: "¿Cómo se generan las compatibilidades?",
    answer:
      "Permuok cruza automáticamente la información de propiedades, búsquedas y desarrollos publicados para detectar posibles compatibilidades. La plataforma acerca oportunidades; la evaluación final siempre queda en manos de cada inmobiliaria.",
  },
  {
    question: "¿Qué pasa cuando encuentro una oportunidad?",
    answer:
      "Podés manifestar tu interés e iniciar una conversación con la otra inmobiliaria. Si ambas partes quieren avanzar, pueden acordar compartir sus datos de contacto y continuar la operación directamente por fuera de Permuok.",
  },
  {
    question: "¿Puedo trabajar con varios agentes o publicar desarrollos?",
    answer:
      "Sí. La cantidad de agentes, inversores asociados y la posibilidad de publicar desarrollos dependen del plan contratado. Podés elegir la membresía que mejor se adapte a la estructura de tu inmobiliaria.",
  },
];

const ease = [0.22, 1, 0.36, 1];

export default function FaqSection() {
  const [openIndex, setOpenIndex] = useState(0);

  return (
    <section
      id="faq"
      className="
        relative
        overflow-hidden
        bg-[#f3f4f6]
     
       
        text-[#30363d]
        
   px-5
        sm:px-8
        lg:px-10

        py-14
sm:py-16
lg:py-20
      
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

      <div
        className="
          relative
          z-10
          mx-auto
          w-full
          max-w-[1120px]
        "
      >
        {/* =====================================================
            HEADER
        ====================================================== */}

        <Reveal>
          <div
            className="
              mx-auto
              max-w-[760px]
              text-center
            "
          >
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
              Preguntas frecuentes
            </h2>

            <p
              className="
                mx-auto
                mt-5
                max-w-[620px]
                text-[17px]
                font-medium
                leading-[1.45]
                text-[#687384]

                sm:text-[18px]
              "
            >
              Todo lo que necesitás saber antes de sumarte a la red.
            </p>
          </div>
        </Reveal>

        {/* =====================================================
            ACCORDION
        ====================================================== */}

        <div
          className="
            mt-12
            border-t
            border-[#cfd5dd]

            sm:mt-14

            lg:mt-16
          "
        >
          {faqs.map((faq, index) => {
            const isOpen = openIndex === index;

            return (
              <Reveal key={faq.question} delay={index * 0.04}>
                <div
                  className="
                    relative
                    border-b
                    border-[#cfd5dd]
                  "
                >
                  {/* línea activa */}

                  <motion.div
                    initial={false}
                    animate={{
                      scaleX: isOpen ? 1 : 0,
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

                  <button
                    type="button"
                    onClick={() => setOpenIndex(isOpen ? null : index)}
                    className="
                      group
                      flex
                      w-full
                      items-start
                      justify-between
                      gap-6
                      py-6
                      text-left

                      sm:py-7

                      lg:py-8
                    "
                  >
                    <h3
                      className={`
                        max-w-[900px]
                        text-[20px]
                        font-semibold
                        leading-[1.2]
                        tracking-[-0.025em]
                        transition-colors
                        duration-300

                        sm:text-[22px]

                        lg:text-[24px]

                        ${
                          isOpen
                            ? "text-[#30363d]"
                            : "text-[#687384] group-hover:text-[#30363d]"
                        }
                      `}
                    >
                      {faq.question}
                    </h3>

                    {/* + / - */}

                    <span
                      className="
                        relative
                        mt-[2px]
                        block
                        h-6
                        w-6
                        shrink-0
                      "
                    >
                      <span
                        className="
                          absolute
                          left-1/2
                          top-1/2
                          h-px
                          w-5
                          -translate-x-1/2
                          -translate-y-1/2
                          bg-[#3974ba]
                        "
                      />

                      <motion.span
                        animate={{
                          rotate: isOpen ? 0 : 90,
                          opacity: isOpen ? 0 : 1,
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
                          w-5
                          -translate-x-1/2
                          -translate-y-1/2
                          bg-[#3974ba]
                        "
                      />
                    </span>
                  </button>

                  {/* RESPUESTA */}

                  <AnimatePresence initial={false}>
                    {isOpen && (
                      <motion.div
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
                        <motion.p
                          initial={{
                            y: -6,
                          }}
                          animate={{
                            y: 0,
                          }}
                          exit={{
                            y: -4,
                          }}
                          transition={{
                            duration: 0.35,
                            ease,
                          }}
                          className="
                            max-w-[900px]
                            pb-7
                            pr-10
                            text-[16px]
                            font-medium
                            leading-[1.6]
                            text-[#5b6470]

                            sm:pb-8
                            sm:text-[17px]

                            lg:pb-9
                          "
                        >
                          {faq.answer}
                        </motion.p>
                      </motion.div>
                    )}
                  </AnimatePresence>
                </div>
              </Reveal>
            );
          })}
        </div>
      </div>
    </section>
  );
}
