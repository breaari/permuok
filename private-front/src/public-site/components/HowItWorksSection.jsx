// src/public-site/components/HowItWorksSection.jsx

import { motion } from "framer-motion";
import Reveal from "./Reveal";

const steps = [
  {
    number: "01",
    title: "Cargá tu cartera",
    text: "Subí propiedades, desarrollos y oportunidades disponibles para venta o permuta.",
  },
  {
    number: "02",
    title: "Registrá búsquedas activas",
    text: "Cargá lo que tus clientes están buscando: zona, valor, tipo de propiedad y condiciones.",
  },
  {
    number: "03",
    title: "La IA cruza información",
    text: "PermuOK analiza compatibilidades entre publicaciones, búsquedas, permutas e inversores.",
    highlighted: true,
  },
  {
    number: "04",
    title: "Contactá a la otra parte",
    text: "Iniciá conversaciones con inmobiliarias que tengan oportunidades compatibles.",
  },
  {
    number: "05",
    title: "Convertí conexiones en operaciones",
    text: "Evaluá propuestas, coordiná condiciones y transformá una coincidencia en una oportunidad real.",
  },
];

export default function HowItWorksSection() {
  return (
    <section
      id="funciona"
      className="relative overflow-hidden bg-background-light px-5 pb-10 pt-20 text-background-dark sm:px-6 sm:pb-28 sm:pt-24 md:pb-44 md:pt-32"
    >
      {/* Fondo */}
      <div className="absolute inset-0 -z-10 bg-background-light" />
      <div className="absolute left-[-220px] top-[-160px] -z-10 h-[460px] w-[460px] rounded-full bg-primary/10 blur-3xl" />
      <div className="absolute bottom-[-220px] right-[-180px] -z-10 h-[460px] w-[460px] rounded-full bg-secondary/10 blur-3xl" />

      {/* Transición desde IA */}
      <div className="pointer-events-none absolute left-0 top-0 h-20 w-full overflow-hidden sm:h-28 md:h-32">
        <div className="absolute left-1/2 top-[-64px] h-24 w-[155%] -translate-x-1/2 rounded-b-[100%] bg-brand-dark sm:top-[-80px] sm:h-28 sm:w-[150%] md:top-[-92px] md:h-32 md:w-[145%]" />
      </div>

      <div className="relative z-10 mx-auto max-w-7xl">
        <Reveal>
          <div className="mx-auto max-w-4xl text-center">
            <span className="inline-flex rounded-full border border-primary/10 bg-primary/5 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-primary sm:text-sm sm:tracking-[0.18em]">
              Cómo funciona
            </span>

            <h2 className="mt-5 text-[34px] font-black leading-[0.98] tracking-[-0.055em] text-background-dark sm:text-[42px] md:text-6xl md:leading-none">
              Tu camino hacia un cierre más inteligente.
            </h2>

            <p className="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
              PermuOK ordena la información, detecta coincidencias y te muestra
              oportunidades concretas para que puedas actuar antes.
            </p>
          </div>
        </Reveal>

        {/* Desktop timeline */}
        <div className="relative mt-20 hidden lg:block">
          <div className="absolute left-0 right-0 top-[42px] h-px bg-slate-200" />

          <motion.div
            initial={{ scaleX: 0 }}
            whileInView={{ scaleX: 1 }}
            viewport={{ once: true, margin: "-120px" }}
            transition={{ duration: 1.3, ease: "easeInOut" }}
            className="absolute left-0 right-0 top-[42px] h-px origin-left bg-gradient-to-r from-primary via-secondary to-primary"
          />

          <div className="grid grid-cols-5 gap-6">
            {steps.map((step, index) => (
              <Reveal key={step.title} delay={0.12 + index * 0.08}>
                <motion.article
                  whileHover={{ y: -8 }}
                  transition={{ duration: 0.25 }}
                  className="relative flex flex-col items-center text-center"
                >
                  <motion.div
                    initial={{ scale: 0.8, opacity: 0 }}
                    whileInView={{ scale: 1, opacity: 1 }}
                    viewport={{ once: true }}
                    transition={{ delay: 0.2 + index * 0.08, duration: 0.35 }}
                    className={`relative z-10 flex h-[84px] w-[84px] items-center justify-center rounded-full border shadow-[0_20px_60px_rgba(15,23,42,0.12)] ${
                      step.highlighted
                        ? "border-secondary/40 bg-secondary text-background-dark"
                        : "border-slate-200 bg-white text-primary"
                    }`}
                  >
                    <span className="text-lg font-black">{step.number}</span>

                    {step.highlighted && (
                      <motion.span
                        animate={{
                          scale: [1, 1.35, 1],
                          opacity: [0.45, 0, 0.45],
                        }}
                        transition={{ duration: 2.2, repeat: Infinity }}
                        className="absolute inset-0 rounded-full border border-secondary"
                      />
                    )}
                  </motion.div>

                  <div className="mt-8 rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary/30 hover:shadow-[0_20px_60px_rgba(0,86,179,0.10)]">
                    <p className="text-[11px] font-black uppercase tracking-[0.18em] text-primary">
                      Paso {index + 1}
                    </p>

                    <h3 className="mt-2 text-xl font-black leading-tight text-background-dark">
                      {step.title}
                    </h3>

                    <p className="mt-3 text-sm leading-6 text-slate-600">
                      {step.text}
                    </p>
                  </div>
                </motion.article>
              </Reveal>
            ))}
          </div>
        </div>

        {/* Mobile / tablet */}
        <div className="relative mt-10 lg:hidden">
          <div className="absolute bottom-8 left-[22px] top-5 w-px bg-slate-200" />

          <div className="grid gap-4">
            {steps.map((step, index) => (
              <Reveal key={step.title} delay={0.08 + index * 0.06}>
                <div className="relative grid grid-cols-[44px_1fr] gap-4">
                  <div
                    className={`relative z-10 flex h-11 w-11 items-center justify-center rounded-full border text-xs font-black shadow-sm ${
                      step.highlighted
                        ? "border-secondary/40 bg-secondary text-background-dark shadow-[0_14px_34px_rgba(118,188,33,0.22)]"
                        : "border-slate-200 bg-white text-primary"
                    }`}
                  >
                    {step.number}

                    {step.highlighted && (
                      <motion.span
                        animate={{
                          scale: [1, 1.28, 1],
                          opacity: [0.42, 0, 0.42],
                        }}
                        transition={{ duration: 2.2, repeat: Infinity }}
                        className="absolute inset-0 rounded-full border border-secondary"
                      />
                    )}
                  </div>

                  <div
                    className={`rounded-[24px] border p-4 shadow-sm ${
                      step.highlighted
                        ? "border-secondary/30 bg-white shadow-[0_18px_46px_rgba(118,188,33,0.12)]"
                        : "border-slate-200 bg-white"
                    }`}
                  >
                    <p className="text-[10px] font-black uppercase tracking-[0.18em] text-primary">
                      Paso {index + 1}
                    </p>

                    <h3 className="mt-2 text-lg font-black leading-tight text-background-dark">
                      {step.title}
                    </h3>

                    <p className="mt-2 text-sm leading-6 text-slate-600">
                      {step.text}
                    </p>
                  </div>
                </div>
              </Reveal>
            ))}
          </div>
        </div>

        {/* Cierre */}
        <Reveal delay={0.28}>
          <div className="mx-auto mt-10 max-w-4xl rounded-[30px] border border-slate-200 bg-white p-6 text-center shadow-[0_24px_70px_rgba(15,23,42,0.08)] sm:mt-12 sm:p-8 md:mt-16 md:rounded-[36px] md:p-10">
            <p className="text-xs font-black uppercase tracking-[0.18em] text-secondary sm:text-sm sm:tracking-[0.22em]">
              Menos búsqueda manual. Más oportunidades accionables.
            </p>

            <h3 className="mt-4 text-[28px] font-black leading-[1.02] tracking-[-0.04em] text-background-dark md:text-4xl">
              Entrás, revisás las coincidencias y avanzás con las operaciones
              que tienen más sentido.
            </h3>
          </div>
        </Reveal>
      </div>

      {/* Transición hacia beneficios */}
      <div className="pointer-events-none absolute bottom-0 left-0 h-24 w-full overflow-hidden sm:h-32 md:h-44">
        <div className="absolute bottom-[-58px] left-1/2 h-28 w-[155%] -translate-x-1/2 rounded-t-[100%] bg-background-light sm:bottom-[-72px] sm:h-32 sm:w-[150%] md:bottom-[-94px] md:h-44 md:w-[145%]" />
      </div>
    </section>
  );
}
