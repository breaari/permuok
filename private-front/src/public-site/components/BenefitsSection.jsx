// src/public-site/components/BenefitsSection.jsx

import { motion } from "framer-motion";
import Reveal from "./Reveal";

const benefits = [
  {
    number: "01",
    title: "Más formas de mover una propiedad",
    text: "No dependés únicamente de encontrar un comprador directo: también podés activar permutas, intercambios parciales, inversores u operaciones compartidas.",
  },
  {
    number: "02",
    title: "Más valor frente al propietario",
    text: "Cuando captás una propiedad, podés ofrecer algo más potente que una publicación: una red privada donde esa propiedad puede encontrar nuevos caminos de cierre.",
  },
  {
    number: "03",
    title: "Más oportunidades con colegas",
    text: "La plataforma permite que una inmobiliaria conecte con otra cuando sus propiedades, búsquedas o clientes pueden formar una operación compatible.",
  },
  {
    number: "04",
    title: "Más foco comercial",
    text: "En lugar de revisar mensajes sueltos o buscar manualmente, trabajás sobre oportunidades ordenadas y listas para evaluar.",
  },
];

const movingConcepts = [
  "Permutas",
  "Inversores",
  "Búsquedas activas",
  "Operaciones cruzadas",
  "Propiedades compatibles",
  "Red inmobiliaria",
];

export default function BenefitsSection() {
  return (
    <section className="relative overflow-hidden bg-[#f3f1ea] px-6 pb-10 pt-10 text-background-dark md:pb-56 md:pt-32">
      {/* Transición suave desde sección clara anterior */}
      <div className="absolute left-0 top-0 h-40 w-full bg-gradient-to-b from-background-light to-[#f3f1ea]" />

      {/* Fondo */}
      <div className="absolute inset-0 -z-10 bg-[#f3f1ea]" />
      <div className="absolute left-[-240px] top-[-160px] -z-10 h-[520px] w-[520px] rounded-full bg-primary/8 blur-3xl" />
      <div className="absolute bottom-[60px] right-[-180px] -z-10 h-[560px] w-[560px] rounded-full bg-secondary/10 blur-3xl" />

      {/* Transición limpia hacia Memberships */}
      <div className="pointer-events-none absolute bottom-0 left-0 z-0 h-64 w-full bg-gradient-to-b from-transparent via-[#31506f]/45 to-[#06224a]" />

      <div className="relative z-10 mx-auto max-w-7xl">
        {/* Header horizontal */}
        <div className="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-end">
          <Reveal>
            <div>
              <span className="inline-flex rounded-full border border-primary/10 bg-white/55 px-4 py-2 text-sm font-black uppercase tracking-[0.18em] text-primary shadow-sm backdrop-blur">
                Para inmobiliarias
              </span>

              <h2 className="mt-6 max-w-4xl text-4xl font-black tracking-[-0.05em] text-background-dark md:text-6xl">
                Dejá de operar solo. Sumá nuevas formas de cerrar.
              </h2>
            </div>
          </Reveal>

          <Reveal delay={0.12}>
            <p className="max-w-2xl rounded-[30px] border border-black/5 bg-white/65 p-7 text-lg leading-8 text-slate-700 shadow-sm backdrop-blur">
              PermuOK ayuda a que cada inmobiliaria amplíe sus posibilidades:
              conectar con colegas, detectar permutas, encontrar inversores y
              transformar una publicación en una oportunidad compartida.
            </p>
          </Reveal>
        </div>

        {/* Banda central de conceptos */}
        <Reveal delay={0.18}>
          <div className="mt-16 overflow-hidden rounded-[36px] border border-black/5 bg-background-dark p-4 shadow-[0_30px_90px_rgba(15,23,42,0.20)]">
            <div className="relative overflow-hidden rounded-[28px] border border-white/10 bg-white/[0.04] py-6">
              <motion.div
                animate={{ x: ["0%", "-50%"] }}
                transition={{
                  duration: 22,
                  repeat: Infinity,
                  ease: "linear",
                }}
                className="flex w-max gap-4 whitespace-nowrap px-4"
              >
                {[...movingConcepts, ...movingConcepts].map(
                  (concept, index) => (
                    <span
                      key={`${concept}-${index}`}
                      className="rounded-full border border-white/10 bg-white/[0.06] px-6 py-3 text-sm font-black uppercase tracking-[0.16em] text-white"
                    >
                      {concept}
                    </span>
                  ),
                )}
              </motion.div>

              <div className="pointer-events-none absolute inset-y-0 left-0 w-32 bg-gradient-to-r from-background-dark to-transparent" />
              <div className="pointer-events-none absolute inset-y-0 right-0 w-32 bg-gradient-to-l from-background-dark to-transparent" />
            </div>
          </div>
        </Reveal>

        {/* Beneficios en grilla horizontal */}
        <div className="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
          {benefits.map((benefit, index) => (
            <Reveal key={benefit.title} delay={0.22 + index * 0.07}>
              <motion.article
                whileHover={{ y: -10 }}
                transition={{ duration: 0.25 }}
                className="group relative h-full overflow-hidden rounded-[34px] border border-black/5 bg-white/75 p-7 shadow-sm backdrop-blur transition hover:border-primary/25 hover:bg-white hover:shadow-[0_24px_70px_rgba(15,23,42,0.12)]"
              >
                <div className="absolute right-[30px] top-0 text-8xl font-black tracking-[-0.08em] text-slate-200/70 transition group-hover:text-primary/10">
                  {benefit.number}
                </div>

                <div className="relative z-10">
                  <div className="mb-8 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-sm font-black text-primary ring-1 ring-primary/15">
                    {benefit.number}
                  </div>

                  <h3 className="text-2xl font-black tracking-tight text-background-dark">
                    {benefit.title}
                  </h3>

                  <p className="mt-4 text-sm leading-7 text-slate-600">
                    {benefit.text}
                  </p>
                </div>
              </motion.article>
            </Reveal>
          ))}
        </div>

        {/* Cierre horizontal */}
        <Reveal delay={0.34}>
          <div className="mt-12 rounded-[38px] border border-black/5 bg-white/75 p-8 text-center shadow-[0_24px_70px_rgba(15,23,42,0.08)] backdrop-blur md:p-10">
            <p className="text-sm font-black uppercase tracking-[0.22em] text-secondary">
              El valor está en ampliar escenarios
            </p>

            <h3 className="mx-auto mt-4 max-w-4xl text-3xl font-black tracking-[-0.04em] text-background-dark md:text-5xl">
              Una propiedad puede ser una venta, una permuta, una inversión o
              una operación compartida.
            </h3>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
