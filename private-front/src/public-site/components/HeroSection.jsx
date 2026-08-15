// src/public-site/components/HeroSection.jsx

import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import Reveal from "./Reveal";
import PublicButton from "./PublicButton";
import heroimagen from "../../assets/permuok-dashboard-hero.png";

const highlights = [
  "Permutas",
  "Operaciones cruzadas",
  "Inversores",
  "Red privada",
];

export default function HeroSection() {
  return (
    <section className="relative isolate overflow-hidden bg-brand-dark px-5 pb-16 pt-28 text-white sm:px-6 md:pb-24 md:pt-36 lg:pb-28">
      {/* Fondo */}
      <div className="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top_left,rgba(0,86,179,0.26),transparent_34%),radial-gradient(circle_at_bottom_right,rgba(118,188,33,0.12),transparent_30%),linear-gradient(180deg,#0a192f_0%,#0f172a_100%)]" />
      <div className="public-grid-bg absolute inset-0 -z-10 opacity-20" />

      {/* Marquee gigante */}
      <div className="pointer-events-none absolute left-0 top-24 -z-10 w-full overflow-hidden opacity-[0.055] md:top-28">
        <div className="public-marquee flex w-max whitespace-nowrap text-[24vw] font-black uppercase leading-none tracking-[-0.08em] text-white md:text-[16vw]">
          <span className="pr-16">
            Más conexiones · Más oportunidades · Más operaciones ·
          </span>
          <span className="pr-16">
            Más conexiones · Más oportunidades · Más operaciones ·
          </span>
        </div>
      </div>

      <div className="mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-[0.95fr_1.05fr] lg:gap-16">
        {/* Texto */}
        <div className="relative z-10">
          <Reveal>
            <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-slate-200 backdrop-blur sm:text-sm">
              <span className="h-2 w-2 rounded-full bg-secondary" />
              Permutas, operaciones cruzadas e IA
            </span>
          </Reveal>

          <Reveal delay={0.08}>
            <h1 className="mt-6 max-w-5xl text-[42px] font-black leading-[0.94] tracking-[-0.055em] text-white sm:text-6xl md:text-7xl xl:text-[82px]">
              Vendé tu propiedad con el poder de toda una red.
            </h1>
          </Reveal>

          <Reveal delay={0.16}>
            <p className="mt-6 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg md:text-xl md:leading-8">
              PermuOK conecta inmobiliarias, inversores y oportunidades de
              intercambio en una red privada diseñada para generar más
              operaciones, detectar permutas posibles y reducir los tiempos de
              venta.
            </p>
          </Reveal>

          <Reveal delay={0.24}>
            <div className="mt-8 grid gap-3 sm:flex sm:flex-wrap">
              <Link
                to="/register"
                className="inline-flex w-full items-center justify-center rounded-full bg-primary px-6 py-3 text-center text-sm font-bold text-white shadow-[0_16px_42px_rgba(0,86,179,0.38)] transition hover:bg-[#004996] active:scale-[0.98] sm:w-auto sm:px-7"
              >
                Quiero probar PermuOK
              </Link>

              <PublicButton
                href="#funciona"
                variant="secondary"
                className="w-full sm:w-auto"
              >
                Cómo funciona
              </PublicButton>
            </div>
          </Reveal>

          <Reveal delay={0.32}>
            <div className="mt-8 grid max-w-2xl grid-cols-2 gap-3 sm:mt-10 sm:grid-cols-4">
              {highlights.map((item, index) => (
                <motion.div
                  key={item}
                  initial={{ opacity: 0, y: 14 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: 0.3 + index * 0.06 }}
                  className="rounded-2xl border border-white/10 bg-white/[0.045] px-3 py-3 text-center backdrop-blur transition hover:border-secondary/40 hover:bg-white/[0.07] sm:px-4 sm:text-left"
                >
                  <p className="text-xs font-semibold text-slate-200 sm:text-sm">
                    {item}
                  </p>
                </motion.div>
              ))}
            </div>
          </Reveal>
        </div>

        {/* Visual principal */}
        <Reveal delay={0.18} y={42}>
          <div className="relative mx-auto w-full max-w-[680px] lg:mt-24 lg:translate-x-8 xl:mt-28 xl:translate-x-14">
            <div className="absolute -inset-5 rounded-[34px] bg-primary/20 blur-3xl sm:-inset-8 sm:rounded-[42px]" />

            <motion.div
              animate={{ y: [0, -6, 0] }}
              transition={{
                duration: 6.5,
                repeat: Infinity,
                ease: "easeInOut",
              }}
              className="relative overflow-hidden rounded-[30px] border border-white/10 bg-white/[0.055] p-3 shadow-[0_35px_90px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:rounded-[38px] sm:p-4"
            >
              <div className="relative h-[430px] overflow-hidden rounded-[24px] border border-white/10 bg-background-dark sm:h-[500px] sm:rounded-[30px] lg:h-[540px]">
                <img
                  src={heroimagen}
                  alt="Propiedad destacada en PermuOK"
                  className="h-full w-full object-cover"
                />

                <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(10,25,47,0.48)_0%,rgba(10,25,47,0.18)_45%,rgba(10,25,47,0.14)_100%)]" />
                <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(10,25,47,0.08)_0%,rgba(10,25,47,0.70)_100%)]" />

                {/* Scan IA */}
                <div className="pointer-events-none absolute inset-0 overflow-hidden">
                  <motion.div
                    animate={{ y: ["-30%", "125%"] }}
                    transition={{
                      duration: 3.4,
                      repeat: Infinity,
                      ease: "easeInOut",
                      repeatDelay: 1.3,
                    }}
                    className="absolute left-0 right-0 h-24 bg-gradient-to-b from-transparent via-primary/20 to-transparent blur-md sm:h-28"
                  />
                </div>

                {/* Badge superior */}
                <motion.div
                  initial={{ opacity: 0, y: -10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.55, duration: 0.45 }}
                  className="absolute left-4 top-4 rounded-full border border-white/10 bg-background-dark/85 px-3 py-2 shadow-xl backdrop-blur sm:left-5 sm:top-5 sm:px-4"
                >
                  <p className="text-[9px] font-bold uppercase tracking-[0.18em] text-secondary sm:text-[11px] sm:tracking-[0.22em]">
                    IA detectó una oportunidad
                  </p>
                </motion.div>

                {/* Match */}
                <motion.div
                  initial={{ opacity: 0, scale: 0.92 }}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ delay: 0.8, duration: 0.45 }}
                  className="z-100 absolute right-3 top-4 rounded-2xl border border-white/10 bg-primary px-3 py-2 text-center text-white shadow-2xl sm:right-5 sm:top-5 sm:px-4 sm:py-3"
                >
                  <p className="text-xl font-black sm:text-2xl">94%</p>
                  <p className="text-[9px] font-bold uppercase tracking-wide sm:text-[10px]">
                    match
                  </p>
                </motion.div>

                {/* Etiqueta de tipo */}
                <motion.div
                  initial={{ opacity: 0, x: -12 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.7, duration: 0.45 }}
                  className="absolute left-4 top-[58px] max-w-[230px] rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs font-semibold text-white backdrop-blur sm:left-5 sm:top-20 sm:max-w-none sm:px-4 sm:text-sm"
                >
                  Casa moderna · Permuta parcial
                </motion.div>

                {/* Recomendación IA */}
                <motion.div
                  initial={{ opacity: 0, x: -14 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 1.15, duration: 0.45 }}
                  className="hidden absolute bottom-[158px] left-3 z-20 max-w-[210px] rounded-full border border-white/10 bg-white/92 px-3 py-2 text-background-dark shadow-2xl sm:bottom-[210px] sm:left-6 sm:max-w-[280px] sm:rounded-2xl sm:px-4 sm:py-3"
                >
                  <p className="hidden text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500 sm:block">
                    Recomendación IA
                  </p>

                  <p className=" text-[10px] font-bold leading-4 sm:mt-1 sm:text-sm sm:font-semibold sm:leading-5">
                    <span className="text-primary sm:hidden">IA: </span>
                    Coincide con búsquedas activas
                    <span className="hidden sm:inline">
                      {" "}
                      y una posible permuta.
                    </span>
                  </p>
                </motion.div>
                {/* Card inferior principal */}
                <motion.div
                  initial={{ opacity: 0, y: 18 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.95, duration: 0.5 }}
                  className="absolute bottom-3 left-2 right-2 z-10 overflow-hidden rounded-[20px] border border-white/10 bg-background-dark/88 shadow-2xl backdrop-blur-xl sm:bottom-6 sm:left-6 sm:right-6 sm:rounded-[28px]"
                >
                  <div className="px-3.5 py-3.5 text-white sm:px-6 sm:py-5">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="text-[8px] font-bold uppercase tracking-[0.16em] text-secondary sm:text-[11px] sm:tracking-[0.22em]">
                          Publicación destacada
                        </p>

                        <h3 className="mt-1.5 max-w-[420px] text-[17px] font-black leading-[1.05] tracking-[-0.02em] sm:mt-2 sm:text-2xl">
                          Casa 5 ambientes con pileta y quincho
                        </h3>

                        <p className="mt-1.5 text-[11px] text-slate-300 sm:mt-2 sm:text-sm">
                          Los Troncos · Mar del Plata
                        </p>
                      </div>

                      <div className="shrink-0 text-right">
                        <p className="text-[17px] font-black leading-none sm:text-2xl">
                          US$ 480.000
                        </p>
                        <p className="mt-1 text-[10px] text-slate-400 sm:text-xs">
                          ID: #245
                        </p>
                      </div>
                    </div>

                    <div className="mt-3 grid grid-cols-4 gap-2 border-t border-white/10 pt-3 sm:mt-5 sm:gap-4 sm:pt-5">
                      <Stat value="320 m²" label="Sup." />
                      <Stat value="4" label="Dorm." />
                      <Stat value="3" label="Baños" />
                      <Stat value="2" label="Coch." />
                    </div>
                  </div>
                </motion.div>
              </div>
            </motion.div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}

function Stat({ value, label }) {
  return (
    <div className="min-w-0">
      <p className="text-[12px] font-black leading-none sm:text-lg">{value}</p>
      <p className="mt-1 text-[9px] leading-none text-slate-400 sm:text-xs">
        {label}
      </p>
    </div>
  );
}
