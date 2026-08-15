// src/public-site/components/FaqSection.jsx

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import Reveal from "./Reveal";

const faqs = [
  {
    tag: "Concepto",
    question: "¿PermuOK es un portal inmobiliario más?",
    answer:
      "No. PermuOK no está pensado como una vidriera pública para competir por leads, sino como una red privada B2B donde las inmobiliarias pueden conectar cartera, búsquedas, permutas, inversores y proyectos para detectar nuevas oportunidades de operación.",
  },
  {
    tag: "Operaciones",
    question: "¿Qué problema concreto viene a resolver?",
    answer:
      "Muchas oportunidades inmobiliarias se pierden porque la información está dispersa: chats, grupos, contactos, planillas y memoria comercial. PermuOK ordena esa información y ayuda a encontrar cruces que manualmente serían difíciles de detectar.",
  },
  {
    tag: "Permutas",
    question: "¿Solo sirve para permutas?",
    answer:
      "No. La permuta es uno de sus grandes diferenciales, pero la plataforma también permite trabajar ventas, búsquedas activas, operaciones compartidas, inversores y proyectos inmobiliarios.",
  },
  {
    tag: "IA",
    question: "¿La inteligencia artificial decide por mí?",
    answer:
      "No. La IA no reemplaza el criterio profesional de la inmobiliaria. Funciona como un motor de detección: analiza información cargada y sugiere posibles coincidencias para que el equipo comercial evalúe, contacte y negocie.",
  },
  {
    tag: "Red",
    question: "¿Voy a compartir mi cartera con cualquiera?",
    answer:
      "No se trata de exponer sin control, sino de participar en una red privada donde las oportunidades se trabajan entre usuarios habilitados. La lógica es colaborar para generar más operaciones, sin perder el rol comercial de cada inmobiliaria.",
  },
  {
    tag: "Proyectos",
    question: "¿Puedo publicar proyectos inmobiliarios?",
    answer:
      "Sí, según la membresía contratada. El Plan Proyectos permite incorporar desarrollos, unidades disponibles y oportunidades vinculadas a inversión o comercialización de proyectos inmobiliarios.",
  },
  {
    tag: "Equipos",
    question: "¿Sirve si mi inmobiliaria trabaja con varios agentes?",
    answer:
      "Sí. Las membresías definen la cantidad de agentes habilitados para operar dentro de la cuenta. Esto permite ordenar el trabajo del equipo y escalar la operación a medida que crece la inmobiliaria.",
  },
  {
    tag: "Valor",
    question: "¿Por qué una inmobiliaria pagaría una membresía?",
    answer:
      "Porque no paga solo por publicar: paga por acceder a una estructura que puede ampliar escenarios comerciales. Una propiedad puede encontrar comprador, permuta, inversor, colega compatible o formar parte de una operación cruzada.",
  },
  {
    tag: "Inicio",
    question: "¿Necesito cargar todo desde el primer día?",
    answer:
      "No. Podés empezar con lo esencial: propiedades, búsquedas activas y oportunidades concretas. Cuanta más información útil cargue tu equipo, más capacidad tendrá la red para detectar coincidencias relevantes.",
  },
];

const trustPoints = [
  {
    label: "Red privada",
    text: "Pensada para inmobiliarias, no para publicar de forma abierta al público.",
  },
  {
    label: "Criterio profesional",
    text: "La IA sugiere coincidencias, pero la decisión comercial siempre queda en manos de la inmobiliaria.",
  },
  {
    label: "Más escenarios",
    text: "Una propiedad puede abrir caminos de venta, permuta, inversión o colaboración entre colegas.",
  },
];

export default function FaqSection() {
  const [openIndex, setOpenIndex] = useState(0);

  return (
    <section
      id="faq"
      className="relative overflow-hidden bg-[#07111f] px-6 pb-10 pt-10 text-white md:pb-40 md:pt-32"
    >
      {/* Continuación desde Memberships */}
      <div className="absolute left-0 top-0 h-28 w-full bg-gradient-to-b from-[#06224a] to-[#07111f]" />

      {/* Fondo */}
      <div className="absolute inset-0 -z-20 bg-[#07111f]" />
      <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(0,86,179,0.28),transparent_34%),radial-gradient(circle_at_bottom_right,rgba(118,188,33,0.12),transparent_30%)]" />
      <div className="absolute left-[-260px] top-24 -z-10 h-[560px] w-[560px] rounded-full bg-primary/20 blur-3xl" />
      <div className="absolute bottom-[-260px] right-[-180px] -z-10 h-[560px] w-[560px] rounded-full bg-secondary/12 blur-3xl" />

      {/* Patrón */}
      <div className="pointer-events-none absolute inset-0 z-0 opacity-[0.06]">
        <div className="absolute inset-0 bg-[linear-gradient(115deg,transparent_0%,transparent_42%,rgba(255,255,255,0.18)_42%,rgba(255,255,255,0.18)_43%,transparent_43%,transparent_100%)] bg-[length:120px_120px]" />
      </div>

      <div className="relative z-10 mx-auto max-w-7xl">
        {/* Header */}
        <div className="max-w-5xl">
          <Reveal>
            <div>
              <span className="inline-flex rounded-full border border-white/10 bg-white/[0.08] px-4 py-2 text-sm font-black uppercase tracking-[0.18em] text-secondary backdrop-blur">
                Preguntas frecuentes
              </span>

              <h2 className="mt-6 max-w-4xl text-4xl font-black tracking-[-0.05em] text-white md:text-6xl">
                Antes de sumarte a una red, es lógico querer entenderla bien.
              </h2>
            </div>
          </Reveal>
        </div>

        <div className="mt-10 grid gap-8 lg:mt-14 lg:grid-cols-[0.75fr_1.25fr]">
          {/* Panel izquierdo fijo */}
          <Reveal delay={0.16}>
            <div className="sticky top-28 hidden rounded-[38px] border border-white/10 bg-white/[0.07] p-7 shadow-[0_30px_90px_rgba(0,20,50,0.24)] backdrop-blur lg:block">
              <p className="text-sm font-black uppercase tracking-[0.22em] text-secondary">
                Lo importante
              </p>

              <h3 className="mt-5 text-3xl font-black tracking-[-0.04em] text-white">
                No es publicar más. Es encontrar mejores cruces comerciales.
              </h3>

              <p className="mt-5 text-base leading-8 text-slate-300">
                La plataforma ordena información clave para que una inmobiliaria
                pueda detectar oportunidades que normalmente quedan dispersas o
                dependen de contactos aislados.
              </p>

              <div className="mt-8 space-y-4 border-t border-white/10 pt-6">
                {trustPoints.map((point) => (
                  <div
                    key={point.label}
                    className="rounded-2xl border border-white/10 bg-white/[0.055] p-4"
                  >
                    <p className="text-sm font-black text-white">
                      {point.label}
                    </p>

                    <p className="mt-2 text-sm leading-6 text-slate-400">
                      {point.text}
                    </p>
                  </div>
                ))}
              </div>

              <div className="mt-8 grid grid-cols-3 gap-3 border-t border-white/10 pt-6">
                <MiniStat value="B2B" label="red privada" />
                <MiniStat value="IA" label="detección" />
                <MiniStat value="+op." label="más caminos" />
              </div>
            </div>
          </Reveal>

          {/* Accordion */}
          <div className="space-y-4">
            {faqs.map((faq, index) => {
              const isOpen = openIndex === index;

              return (
                <Reveal key={faq.question} delay={0.18 + index * 0.035}>
                  <motion.article
                    layout
                    className={`overflow-hidden rounded-[28px] border transition ${
                      isOpen
                        ? "border-secondary/40 bg-white/[0.105] shadow-[0_24px_80px_rgba(118,188,33,0.12)]"
                        : "border-white/10 bg-white/[0.055] hover:border-white/20 hover:bg-white/[0.075]"
                    }`}
                  >
                    <button
                      type="button"
                      onClick={() => setOpenIndex(isOpen ? null : index)}
                      className="flex w-full items-start justify-between gap-5 px-6 py-6 text-left"
                    >
                      <div>
                        <span
                          className={`inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] ${
                            isOpen
                              ? "bg-secondary/15 text-secondary"
                              : "bg-white/10 text-slate-400"
                          }`}
                        >
                          {faq.tag}
                        </span>

                        <h3 className="mt-3 text-xl font-black tracking-[-0.02em] text-white md:text-2xl">
                          {faq.question}
                        </h3>
                      </div>

                      <motion.span
                        animate={{ rotate: isOpen ? 45 : 0 }}
                        transition={{ duration: 0.2 }}
                        className={`mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border text-2xl font-light ${
                          isOpen
                            ? "border-secondary/40 bg-secondary text-background-dark"
                            : "border-white/10 bg-white/10 text-white"
                        }`}
                      >
                        +
                      </motion.span>
                    </button>

                    <AnimatePresence initial={false}>
                      {isOpen && (
                        <motion.div
                          initial={{ height: 0, opacity: 0 }}
                          animate={{ height: "auto", opacity: 1 }}
                          exit={{ height: 0, opacity: 0 }}
                          transition={{ duration: 0.25, ease: "easeInOut" }}
                        >
                          <div className="px-6 pb-6 text-base leading-8 text-slate-300">
                            {faq.answer}
                          </div>
                        </motion.div>
                      )}
                    </AnimatePresence>
                  </motion.article>
                </Reveal>
              );
            })}
          </div>
        </div>

        {/* Cierre */}
        <Reveal delay={0.34}>
          <div className="mt-12 rounded-[36px] border border-white/10 bg-white/[0.07] p-8 text-center shadow-[0_24px_70px_rgba(0,20,50,0.20)] backdrop-blur md:p-10">
            <p className="text-sm font-black uppercase tracking-[0.22em] text-secondary">
              La idea es simple
            </p>

            <h3 className="mx-auto mt-4 max-w-4xl text-3xl font-black tracking-[-0.04em] text-white md:text-5xl">
              Menos oportunidades perdidas. Más conexiones inmobiliarias con
              intención real.
            </h3>
          </div>
        </Reveal>
      </div>
    </section>
  );
}

function MiniStat({ value, label }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-4 text-center">
      <p className="text-xl font-black text-white">{value}</p>
      <p className="mt-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">
        {label}
      </p>
    </div>
  );
}
