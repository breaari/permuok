// src/public-site/components/ProblemSection.jsx

import { motion } from "framer-motion";
import Reveal from "./Reveal";

export default function ProblemSection() {
  return (
    <section
      id="permutas"
      className="relative overflow-hidden bg-background-light px-5 pb-24 pt-14 text-background-dark sm:px-6 sm:pb-28 sm:pt-16 md:pb-40 md:pt-28"
    >
      {/* Fondo decorativo claro */}
      <div className="absolute inset-0 -z-10 bg-[linear-gradient(180deg,#f8fafc_0%,#eef4fb_100%)]" />
      <div className="absolute right-[-180px] top-[-160px] -z-10 h-[420px] w-[420px] rounded-full bg-primary/10 blur-3xl" />
      <div className="absolute bottom-[-220px] left-[-180px] -z-10 h-[420px] w-[420px] rounded-full bg-secondary/10 blur-3xl" />

      {/* Fondo animado desktop */}
      <div className="pointer-events-none absolute inset-0 z-0 hidden overflow-hidden lg:block">
        <div className="absolute inset-0">
          <div className="absolute inset-0 bg-background-light/35" />
          <div className="absolute inset-y-0 left-0 w-[42%] bg-gradient-to-r from-background-light via-background-light/88 to-transparent" />
          <div className="absolute inset-y-0 right-0 w-[18%] bg-gradient-to-l from-background-light/90 to-transparent" />
          <GhostNotificationsBackground />
        </div>
      </div>

      <div className="relative z-10 mx-auto max-w-7xl">
        {/* Header */}
        <div className="grid gap-6 lg:grid-cols-[0.95fr_1.05fr] lg:items-end lg:gap-10">
          <Reveal>
            <div>
              <span className="inline-flex rounded-full border border-primary/10 bg-primary/5 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-primary sm:text-sm sm:tracking-[0.18em]">
                El problema
              </span>

              <h2 className="mt-4 max-w-4xl text-[34px] font-black leading-[0.95] tracking-[-0.055em] text-background-dark sm:text-[42px] md:text-6xl">
                Las oportunidades existen. El problema es que no siempre se
                encuentran.
              </h2>
            </div>
          </Reveal>

          <Reveal delay={0.12}>
            <InfoCard>
              Cada vez hay más operaciones que podrían resolverse con permutas,
              intercambios parciales, inversores o acuerdos entre inmobiliarias.
              Pero gran parte de esa información circula de forma informal,
              desordenada y descentralizada.
            </InfoCard>
          </Reveal>
        </div>

        {/* Diagrama */}
        <Reveal delay={0.18}>
          <div className="mt-8 rounded-[30px] border border-slate-200 bg-white/80 p-4 shadow-[0_24px_70px_rgba(15,23,42,0.08)] backdrop-blur sm:mt-10 sm:rounded-[36px] sm:p-6 md:p-8">
            <div className="grid gap-3 lg:grid-cols-[1fr_auto_1fr_auto_1fr] lg:items-center lg:gap-5">
              <FlowCard
                label="Hoy"
                title="Chats, contactos y grupos"
                text="La información circula, pero queda fragmentada."
              />

              <Connector />

              <FlowCard
                label="Resultado"
                title="Oportunidades invisibles"
                text="Las partes compatibles no siempre llegan a encontrarse."
              />

              <Connector />

              <FlowCard
                label="Pérdida"
                title="Operaciones que no avanzan"
                text="La oportunidad existe, pero no aparece a tiempo."
              />
            </div>
          </div>
        </Reveal>

        {/* Cierre */}
        <Reveal delay={0.28}>
          <div className="mt-8 overflow-hidden rounded-[30px] bg-brand-dark text-white shadow-[0_28px_80px_rgba(10,25,47,0.22)] sm:mt-10 sm:rounded-[36px]">
            <div className="grid gap-5 p-6 sm:p-8 md:grid-cols-[0.8fr_1.2fr] md:gap-8 md:p-10">
              <div>
                <p className="text-xs font-black uppercase tracking-[0.2em] text-secondary sm:text-sm sm:tracking-[0.22em]">
                  La oportunidad está en conectar
                </p>

                <h3 className="mt-3 text-[28px] font-black leading-[1.02] tracking-[-0.04em] md:text-4xl">
                  Una red informal no alcanza para escalar operaciones.
                </h3>
              </div>

              <p className="text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                Si una inmobiliaria tiene una propiedad apta para permuta, otra
                tiene un cliente buscando algo similar y un inversor podría
                completar la operación, el valor está en conectar esas partes a
                tiempo.
              </p>
            </div>
          </div>
        </Reveal>
      </div>

      {/* Transición hacia IA */}
      <div className="pointer-events-none absolute bottom-0 left-0 h-28 w-full overflow-hidden sm:h-40 md:h-56">
        <div className="absolute bottom-[-52px] left-1/2 h-32 w-[155%] -translate-x-1/2 rounded-t-[100%] bg-brand-dark sm:bottom-[-72px] sm:h-40 sm:w-[150%] md:bottom-[-92px] md:h-56 md:w-[145%]" />
        <div className="absolute bottom-[-52px] left-1/2 h-32 w-[155%] -translate-x-1/2 rounded-t-[100%] shadow-[0_-30px_80px_rgba(0,86,179,0.08)] sm:bottom-[-72px] sm:h-40 sm:w-[150%] md:bottom-[-92px] md:h-56 md:w-[145%]" />
      </div>
    </section>
  );
}

function InfoCard({ children }) {
  return (
    <div className="rounded-[28px] border border-slate-200 bg-white/88 px-5 py-5 shadow-[0_14px_34px_rgba(15,23,42,0.08)] backdrop-blur sm:px-6 sm:py-6 md:px-8 md:py-7">
      <p className="text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
        {children}
      </p>
    </div>
  );
}

function FlowCard({ label, title, text }) {
  return (
    <motion.div
      whileHover={{ scale: 1.015 }}
      transition={{ duration: 0.22 }}
      className="rounded-[26px] border border-slate-200 bg-slate-50 px-5 py-5 shadow-sm sm:rounded-[28px] sm:px-6 sm:py-6"
    >
      <p className="text-[11px] font-black uppercase tracking-[0.18em] text-primary sm:text-xs sm:tracking-[0.2em]">
        {label}
      </p>

      <h3 className="mt-2 text-lg font-black leading-tight text-background-dark sm:mt-3 sm:text-xl">
        {title}
      </h3>

      <p className="mt-2 text-sm leading-6 text-slate-600 sm:mt-3">{text}</p>
    </motion.div>
  );
}

function Connector() {
  return (
    <>
      {/* Mobile */}
      <div className="flex justify-center py-1 lg:hidden">
        <motion.div
          animate={{ y: [0, 6, 0], opacity: [0.4, 1, 0.4] }}
          transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
          className="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-sm font-black text-white shadow-[0_12px_28px_rgba(0,86,179,0.22)]"
        >
          ↓
        </motion.div>
      </div>

      {/* Desktop */}
      <div className="hidden items-center justify-center lg:flex">
        <motion.div
          animate={{ x: [0, 8, 0], opacity: [0.35, 1, 0.35] }}
          transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
          className="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-white shadow-[0_12px_28px_rgba(0,86,179,0.24)]"
        >
          →
        </motion.div>
      </div>
    </>
  );
}

const ghostNotifications = [
  {
    label: "Grupo",
    title: "Buscan depto 3 amb.",
    detail: "Zona Norte · hasta US$ 150K",
    className: "left-[8%] top-12",
    rotate: -5,
    delay: 0,
  },
  {
    label: "Contacto",
    title: "Acepta permuta parcial",
    detail: "Casa + diferencia",
    className: "left-[24%] top-24",
    rotate: 4,
    delay: 1,
  },
  {
    label: "Mensaje",
    title: "Inversor interesado",
    detail: "Busca oportunidad con renta",
    className: "left-[44%] top-10",
    rotate: -3,
    delay: 2,
  },
  {
    label: "Planilla",
    title: "Propiedad compatible",
    detail: "Sin seguimiento centralizado",
    className: "left-[63%] top-28",
    rotate: 5,
    delay: 3,
  },
  {
    label: "Búsqueda",
    title: "Cliente busca casa",
    detail: "Permuta + diferencia",
    className: "left-[76%] top-14",
    rotate: -4,
    delay: 4,
  },
  {
    label: "Oportunidad",
    title: "Nunca llegó a cruzarse",
    detail: "Información dispersa",
    className: "left-[68%] top-[250px]",
    rotate: 2,
    delay: 5,
  },
  {
    label: "Contacto",
    title: "Tiene propiedad para entregar",
    detail: "Operación parcial",
    className: "left-[16%] top-[280px]",
    rotate: -6,
    delay: 1.8,
  },
  {
    label: "Grupo",
    title: "Buscan lote o casa",
    detail: "Escuchan propuestas",
    className: "left-[36%] top-[300px]",
    rotate: 3,
    delay: 2.7,
  },
  {
    label: "Mensaje",
    title: "Cliente abierto a permuta",
    detail: "Falta cruce con otra parte",
    className: "left-[54%] top-[340px]",
    rotate: -2,
    delay: 3.6,
  },
];

function GhostNotificationsBackground() {
  return (
    <div className="relative h-full w-full opacity-[0.22]">
      <div className="absolute bottom-8 right-14 h-28 w-80 rounded-full bg-primary/10 blur-3xl" />

      {ghostNotifications.map((note) => (
        <motion.div
          key={`${note.label}-${note.title}`}
          initial={{
            opacity: 0,
            y: -36,
            x: 0,
            scale: 0.96,
            rotate: note.rotate - 2,
          }}
          whileInView={{
            opacity: [0, 0.75, 0.72, 0.58],
            y: [-36, 0, 18, 34],
            x: [0, 4, -2, 0],
            scale: [0.96, 1, 1, 0.98],
            rotate: [
              note.rotate - 2,
              note.rotate,
              note.rotate + 1,
              note.rotate,
            ],
          }}
          viewport={{ once: false, amount: 0.2 }}
          transition={{
            duration: 8.5,
            delay: note.delay,
            repeat: Infinity,
            repeatDelay: 1.4,
            ease: "easeInOut",
          }}
          className={`absolute w-[220px] rounded-[22px] border border-slate-200 bg-white/92 p-4 shadow-[0_18px_50px_rgba(15,23,42,0.08)] backdrop-blur-sm ${note.className}`}
        >
          <p className="text-[11px] font-black uppercase tracking-[0.18em] text-primary">
            {note.label}
          </p>

          <p className="mt-2 text-base font-black leading-tight text-background-dark">
            {note.title}
          </p>

          <p className="mt-1 text-sm leading-5 text-slate-500">{note.detail}</p>
        </motion.div>
      ))}
    </div>
  );
}
