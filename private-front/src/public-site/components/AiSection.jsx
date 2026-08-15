// src/public-site/components/AiSection.jsx

import { motion } from "framer-motion";
import Reveal from "./Reveal";

const inputs = [
  {
    label: "Publicación",
    title: "Casa con opción de permuta",
    text: "Propiedad cargada por una inmobiliaria de la red.",
  },
  {
    label: "Búsqueda",
    title: "Cliente busca alternativa similar",
    text: "Zona, valor y características compatibles.",
  },
  {
    label: "Condición",
    title: "Acepta diferencia o intercambio parcial",
    text: "La operación no depende solo de una venta directa.",
  },
];

const results = [
  {
    title: "Permuta posible",
    text: "Detecta propiedades que podrían formar parte de un intercambio total o parcial.",
    score: "94%",
  },
  {
    title: "Búsqueda compatible",
    text: "Relaciona publicaciones activas con pedidos cargados por otras inmobiliarias.",
    score: "89%",
  },
  {
    title: "Operación cruzada",
    text: "Encuentra escenarios donde varias partes pueden participar de un mismo negocio.",
    score: "91%",
  },
];

export default function AiSection() {
  return (
    <section
      id="ia"
      className="relative -mt-px overflow-hidden bg-brand-dark px-5 pb-6 pt-0 text-white sm:px-6 md:pb-32 md:pt-10"
    >
      {/* Fondo */}
      <div className="absolute inset-0 -z-10 bg-brand-dark" />
      <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,rgba(0,86,179,0.14),transparent_36%),radial-gradient(circle_at_bottom_left,rgba(118,188,33,0.05),transparent_34%)]" />
      <div className="public-grid-bg absolute inset-0 -z-10 opacity-[0.06]" />

      <div className="mx-auto max-w-7xl">
        {/* Header */}
        <div className="grid gap-6 lg:grid-cols-[0.95fr_1.05fr] lg:items-end lg:gap-10">
          <Reveal>
            <div>
              <span className="inline-flex rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-secondary sm:text-sm sm:tracking-[0.18em]">
                Inteligencia artificial
              </span>

              <h2 className="mt-5 max-w-4xl text-[34px] font-black leading-[0.98] tracking-[-0.055em] sm:text-[42px] md:text-6xl md:leading-none">
                De buscar a mano, a recibir oportunidades listas para evaluar.
              </h2>
            </div>
          </Reveal>

          <Reveal delay={0.12}>
            <p className="max-w-2xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
              PermuOK cruza publicaciones, búsquedas, zonas, valores y
              condiciones de permuta para mostrar relaciones comerciales que
              podrían pasar desapercibidas trabajando de forma aislada.
            </p>
          </Reveal>
        </div>

        {/* Motor visual */}
        <div className="mt-10 grid gap-6 lg:mt-16 lg:grid-cols-[0.9fr_0.45fr_1fr] lg:items-center lg:gap-8">
          {/* Inputs */}
          <Reveal delay={0.16}>
            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 lg:gap-4">
              {inputs.map((item, index) => (
                <motion.div
                  key={item.title}
                  initial={{ opacity: 0, x: -18 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: 0.18 + index * 0.08 }}
                  className="rounded-[24px] border border-white/10 bg-white/[0.045] p-4 backdrop-blur transition hover:border-primary/50 hover:bg-white/[0.07] sm:p-5 sm:rounded-[28px]"
                >
                  <p className="text-[10px] font-black uppercase tracking-[0.18em] text-secondary sm:text-[11px] sm:tracking-[0.2em]">
                    {item.label}
                  </p>

                  <h3 className="mt-2 text-lg font-black leading-tight text-white sm:text-xl">
                    {item.title}
                  </h3>

                  <p className="mt-2 text-sm leading-6 text-slate-400">
                    {item.text}
                  </p>
                </motion.div>
              ))}
            </div>
          </Reveal>

          {/* Núcleo IA */}
          <Reveal delay={0.26}>
            <div className="relative flex min-h-[150px] items-center justify-center lg:min-h-[280px]">
              <div className="absolute h-36 w-36 rounded-full border border-white/10 sm:h-44 sm:w-44 lg:h-64 lg:w-64" />

              <motion.div
                animate={{ rotate: 360 }}
                transition={{ duration: 18, repeat: Infinity, ease: "linear" }}
                className="absolute h-28 w-28 rounded-full border border-dashed border-primary/40 sm:h-36 sm:w-36 lg:h-52 lg:w-52"
              />

              <motion.div
                animate={{ scale: [1, 1.08, 1] }}
                transition={{ duration: 2.8, repeat: Infinity }}
                className="relative z-10 flex h-24 w-24 flex-col items-center justify-center rounded-[24px] border border-white/10 bg-primary text-center shadow-[0_24px_70px_rgba(0,86,179,0.35)] sm:h-28 sm:w-28 sm:rounded-[28px] lg:h-36 lg:w-36 lg:rounded-[34px]"
              >
                <p className="text-3xl font-black sm:text-4xl">IA</p>
                <p className="mt-1 text-[8px] font-black uppercase tracking-[0.14em] text-blue-100 sm:text-[9px] lg:text-[10px] lg:tracking-[0.18em]">
                  Cruce inteligente
                </p>
              </motion.div>

              <motion.span
                animate={{ opacity: [0.3, 1, 0.3], scale: [1, 1.25, 1] }}
                transition={{ duration: 2.2, repeat: Infinity }}
                className="absolute left-[28%] top-8 h-2.5 w-2.5 rounded-full bg-secondary lg:left-10 lg:top-12 lg:h-3 lg:w-3"
              />

              <motion.span
                animate={{ opacity: [0.4, 1, 0.4], scale: [1, 1.2, 1] }}
                transition={{ duration: 2.6, repeat: Infinity }}
                className="absolute bottom-8 right-[28%] h-2.5 w-2.5 rounded-full bg-white lg:bottom-14 lg:right-8 lg:h-3 lg:w-3"
              />
            </div>
          </Reveal>

          {/* Results */}
          <Reveal delay={0.18}>
            <div className="rounded-[30px] border border-white/10 bg-white p-4 text-background-dark shadow-[0_35px_90px_rgba(0,0,0,0.28)] sm:rounded-[36px] sm:p-5">
              <div className="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between sm:pb-5">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.18em] text-primary sm:text-[11px] sm:tracking-[0.2em]">
                    Resultado
                  </p>

                  <h3 className="mt-1 text-2xl font-black leading-tight">
                    Recomendaciones accionables
                  </h3>
                </div>

                <span className="w-fit rounded-full bg-primary px-4 py-2 text-xs font-black text-white sm:text-sm">
                  12 nuevas
                </span>
              </div>

              <div className="mt-4 space-y-3 sm:mt-5 sm:space-y-4">
                {results.map((item, index) => (
                  <motion.div
                    key={item.title}
                    initial={{ opacity: 0, y: 16 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true }}
                    transition={{ delay: 0.28 + index * 0.08 }}
                    className="rounded-[22px] border border-slate-200 bg-slate-50 p-4 transition hover:border-primary/30 hover:bg-white sm:rounded-[26px] sm:p-5"
                  >
                    <div className="flex items-start justify-between gap-3 sm:gap-4">
                      <div className="min-w-0">
                        <h4 className="text-base font-black leading-tight text-background-dark sm:text-lg">
                          {item.title}
                        </h4>

                        <p className="mt-2 text-sm leading-6 text-slate-600">
                          {item.text}
                        </p>
                      </div>

                      <div className="shrink-0 rounded-2xl bg-primary px-3 py-2 text-center text-white sm:px-4 sm:py-3">
                        <p className="text-lg font-black sm:text-xl">
                          {item.score}
                        </p>
                        <p className="text-[9px] font-black uppercase sm:text-[10px]">
                          match
                        </p>
                      </div>
                    </div>
                  </motion.div>
                ))}
              </div>
            </div>
          </Reveal>
        </div>

        {/* Cierre */}
        <Reveal delay={0.28}>
          <div className="mt-10 rounded-[30px] border border-white/10 bg-white/[0.045] p-6 backdrop-blur sm:mt-12 sm:rounded-[36px] sm:p-8 md:mt-14 md:p-10">
            <div className="grid gap-5 md:grid-cols-[0.8fr_1.2fr] md:items-center md:gap-8">
              <h3 className="text-[28px] font-black leading-[1.02] tracking-[-0.04em] md:text-4xl">
                La IA no decide por la inmobiliaria. Le muestra dónde mirar
                primero.
              </h3>

              <p className="text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                Cada recomendación es una señal para evaluar: una permuta que
                podría funcionar, una búsqueda compatible, un inversor
                relacionado o una operación cruzada con otra inmobiliaria de la
                red.
              </p>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
