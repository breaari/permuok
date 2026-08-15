// src/public-site/components/MembershipsSection.jsx

import { useState } from "react";
import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import Reveal from "./Reveal";

const plans = [
  {
    name: "Plan Inicial",
    price: "$19.900",
    period: "/ 30 días",
    description:
      "Para inmobiliarias que quieren ingresar a la red, publicar oportunidades y empezar a operar con una estructura simple.",
    note: "Para empezar",
    cta: "Empezar con Inicial",
    agents: 1,
    investors: 0,
    projects: false,
    focus: "Inicio en la red",
    highlighted: false,
    features: [
      { label: "Publicaciones y búsquedas activas", enabled: true },
      { label: "Acceso a oportunidades de la red", enabled: true },
      { label: "Recomendaciones con IA", enabled: false },
      { label: "Publicación de proyectos", enabled: false },
    ],
  },
  {
    name: "Plan Crecimiento",
    price: "$25.400",
    period: "/ 30 días",
    description:
      "Para inmobiliarias que buscan sumar equipo, ampliar oportunidades y trabajar con más capacidad comercial.",
    note: "Más elegido",
    cta: "Quiero crecer con PermuOK",
    agents: 3,
    investors: 1,
    projects: false,
    focus: "Crecimiento comercial",
    highlighted: true,
    features: [
      { label: "Publicaciones y búsquedas activas", enabled: true },
      { label: "Acceso a oportunidades de la red", enabled: true },
      { label: "Recomendaciones con IA", enabled: true },
      { label: "Publicación de proyectos", enabled: false },
    ],
  },
  {
    name: "Plan Proyectos",
    price: "$33.700",
    period: "/ 30 días",
    description:
      "Para equipos con mayor volumen operativo, más usuarios asociados y necesidad de publicar proyectos.",
    note: "Mayor capacidad",
    cta: "Operar con Proyectos",
    agents: 5,
    investors: 2,
    projects: true,
    focus: "Equipos y proyectos",
    highlighted: false,
    features: [
      { label: "Publicaciones y búsquedas activas", enabled: true },
      { label: "Acceso a oportunidades de la red", enabled: true },
      { label: "Recomendaciones con IA", enabled: true },
      { label: "Publicación de proyectos", enabled: true },
    ],
  },
];

export default function MembershipsSection() {
  const [selectedPlan, setSelectedPlan] = useState(
    plans.find((plan) => plan.highlighted)?.name || plans[0].name,
  );

  return (
    <section
      id="membresias"
      className="relative overflow-hidden bg-[#06224a] px-5 pb-16 pt-16 text-white sm:px-6 md:pb-40 md:pt-32"
    >
      {/* Continuación desde Benefits */}
      <div className="absolute left-0 top-0 h-24 w-full bg-gradient-to-b from-[#06224a] to-transparent" />

      {/* Fondo */}
      <div className="absolute inset-0 -z-20 bg-[#06224a]" />
      <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(118,188,33,0.16),transparent_30%),radial-gradient(circle_at_bottom_right,rgba(0,86,179,0.45),transparent_38%)]" />
      <div className="absolute left-[-220px] top-24 -z-10 h-[520px] w-[520px] rounded-full bg-primary/20 blur-3xl" />
      <div className="absolute bottom-[-260px] right-[-180px] -z-10 h-[560px] w-[560px] rounded-full bg-secondary/12 blur-3xl" />

      {/* Patrón visual */}
      <div className="pointer-events-none absolute inset-0 z-0 opacity-[0.08]">
        <div className="absolute inset-0 bg-[linear-gradient(115deg,transparent_0%,transparent_42%,rgba(255,255,255,0.16)_42%,rgba(255,255,255,0.16)_43%,transparent_43%,transparent_100%)] bg-[length:120px_120px]" />
      </div>

      <div className="relative z-10 mx-auto max-w-7xl">
        {/* Header */}
        <div className="grid gap-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-end lg:gap-10">
          <Reveal>
            <div>
              <span className="inline-flex rounded-full border border-white/10 bg-white/[0.08] px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-secondary backdrop-blur sm:text-sm sm:tracking-[0.18em]">
                Membresías
              </span>

              <h2 className="mt-5 max-w-4xl text-[34px] font-black leading-[0.98] tracking-[-0.055em] text-white sm:text-[42px] md:text-6xl md:leading-none">
                Elegí cómo querés crecer dentro de la red PermuOK.
              </h2>
            </div>
          </Reveal>

          <Reveal delay={0.12}>
            <p className="max-w-2xl rounded-[26px] border border-white/10 bg-white/[0.08] p-5 text-base leading-7 text-slate-200 shadow-[0_24px_70px_rgba(0,20,50,0.24)] backdrop-blur sm:rounded-[30px] sm:p-7 sm:text-lg sm:leading-8">
              Compará cada membresía por capacidad real: cantidad de agentes,
              inversores asociados, publicación de proyectos y herramientas
              comerciales disponibles.
            </p>
          </Reveal>
        </div>

        {/* Ayuda mobile */}
        <Reveal delay={0.14}>
          <div className="mt-7 flex items-center justify-between gap-4 md:hidden">
            <p className="text-sm font-semibold text-slate-300">
              Deslizá para ver más opciones de membresías
            </p>

            <div className="flex items-center gap-2 text-secondary">
              <span className="text-xs font-black uppercase tracking-[0.16em]">
                Ver más
              </span>
              <span className="text-lg leading-none">→</span>
            </div>
          </div>
        </Reveal>

        {/* Membresías comparables */}
        <div className="mt-5 md:mt-14 lg:mt-16">
          {/* Mobile / tablet: carrusel horizontal */}
          <div className="relative -mx-5 lg:hidden">
            {/* Fade derecho para insinuar continuidad */}
            <div className="pointer-events-none absolute right-0 top-0 z-20 h-full w-14 bg-gradient-to-l from-[#06224a] via-[#06224a]/85 to-transparent" />

            <div className="overflow-x-auto scroll-smooth px-5 pb-5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
              <div className="flex w-max snap-x snap-mandatory gap-4 pr-5">
                {plans.map((plan, index) => {
                  const isSelected = selectedPlan === plan.name;

                  return (
                    <PlanCard
                      key={plan.name}
                      plan={plan}
                      index={index}
                      isSelected={isSelected}
                      onSelect={() => setSelectedPlan(plan.name)}
                      mobile
                    />
                  );
                })}
              </div>
            </div>
          </div>

          {/* Desktop: grilla */}
          <div className="hidden gap-6 lg:grid lg:grid-cols-3">
            {plans.map((plan, index) => {
              const isSelected = selectedPlan === plan.name;

              return (
                <PlanCard
                  key={plan.name}
                  plan={plan}
                  index={index}
                  isSelected={isSelected}
                  onSelect={() => setSelectedPlan(plan.name)}
                />
              );
            })}
          </div>
        </div>
      </div>
    </section>
  );
}

function PlanCard({ plan, index, isSelected, onSelect, mobile = false }) {
  return (
    <Reveal delay={0.16 + index * 0.08} className={mobile ? "shrink-0" : ""}>
      <motion.article
        role="button"
        tabIndex={0}
        onClick={onSelect}
        onKeyDown={(event) => {
          if (event.key === "Enter" || event.key === " ") {
            onSelect();
          }
        }}
        whileHover={mobile ? undefined : { y: -8 }}
        whileTap={{ scale: 0.985 }}
        transition={{ duration: 0.25 }}
        className={`relative flex h-full cursor-pointer flex-col overflow-hidden rounded-[30px] border p-5 text-left outline-none transition sm:rounded-[34px] sm:p-6 lg:rounded-[36px] lg:p-7 ${
          mobile ? "w-[calc(100vw-64px)] max-w-[350px] snap-start" : ""
        } ${
          isSelected
            ? "border-secondary bg-white/[0.16] text-white shadow-[0_34px_110px_rgba(118,188,33,0.18)]"
            : "border-white/10 bg-white/[0.075] text-white shadow-[0_22px_70px_rgba(0,20,50,0.20)] hover:border-white/25 hover:bg-white/[0.11]"
        }`}
      >
        {isSelected && (
          <motion.div
            layoutId="selectedPlanGlow"
            className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(118,188,33,0.20),transparent_38%)]"
          />
        )}

        {plan.highlighted && (
          <div
            className={`absolute right-5 top-5 rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] sm:right-6 sm:top-6 sm:px-4 sm:py-2 sm:text-xs sm:tracking-[0.14em] ${
              isSelected
                ? "bg-secondary text-background-dark"
                : "bg-white/12 text-secondary"
            }`}
          >
            Más elegido
          </div>
        )}

        <div className="relative z-10 flex h-full flex-col">
          <span
            className={`inline-flex w-fit rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.14em] sm:px-4 sm:py-2 sm:text-xs sm:tracking-[0.16em] ${
              isSelected
                ? "bg-secondary/15 text-secondary"
                : "bg-white/10 text-slate-300"
            }`}
          >
            {plan.note}
          </span>

          <h3 className="mt-5 text-2xl font-black tracking-tight text-white">
            {plan.name}
          </h3>

          <p className="mt-3 min-h-[78px] text-sm leading-6 text-slate-300 sm:min-h-[90px]">
            {plan.description}
          </p>

          <div className="mt-5 flex items-end gap-2 sm:mt-7">
            <p className="text-[40px] font-black leading-none tracking-[-0.05em] text-white sm:text-5xl">
              {plan.price}
            </p>

            <p className="pb-1.5 text-sm font-semibold text-slate-300 sm:pb-2 sm:text-base">
              {plan.period}
            </p>
          </div>

          {/* Comparación gráfica */}
          <div className="mt-6 overflow-hidden rounded-[24px] border border-white/10 bg-primary/14 sm:mt-8 sm:rounded-[26px]">
            <CompareRow
              label="Agentes"
              value={`${plan.agents}`}
              selected={isSelected}
            >
              <CapacityDots
                total={5}
                active={plan.agents}
                selected={isSelected}
              />
            </CompareRow>

            <CompareRow
              label="Inversores"
              value={`${plan.investors}`}
              selected={isSelected}
            >
              <CapacityDots
                total={2}
                active={plan.investors}
                selected={isSelected}
              />
            </CompareRow>

            <CompareRow
              label="Proyectos"
              value={plan.projects ? "Incluye" : "No incluye"}
              selected={isSelected}
            >
              <StatusIcon enabled={plan.projects} />
            </CompareRow>

            <CompareRow
              label="Enfoque"
              value={plan.focus}
              selected={isSelected}
              last
            />
          </div>

          {/* Features */}
          <div className="mt-5 grid gap-2.5 sm:mt-7 sm:gap-3">
            {plan.features.map((feature) => (
              <FeatureItem
                key={feature.label}
                label={feature.label}
                enabled={feature.enabled}
                selected={isSelected}
              />
            ))}
          </div>

          <div
            className={`mt-6 rounded-2xl px-5 py-3.5 text-center text-sm font-black sm:mt-8 sm:py-4 ${
              isSelected
                ? "bg-secondary text-background-dark"
                : "bg-white/10 text-slate-300"
            }`}
          >
            {isSelected ? "Plan seleccionado" : "Tocar para comparar"}
          </div>

          <Link
            to="/register"
            onClick={(event) => event.stopPropagation()}
            className={`mt-3 inline-flex w-full items-center justify-center rounded-2xl px-5 py-3.5 text-sm font-black transition active:scale-[0.98] sm:mt-4 sm:py-4 ${
              isSelected
                ? "bg-primary text-white shadow-[0_16px_38px_rgba(0,86,179,0.28)] hover:bg-[#004996]"
                : "bg-white/10 text-white hover:bg-white/15"
            }`}
          >
            {plan.cta}
          </Link>
        </div>
      </motion.article>
    </Reveal>
  );
}

function CompareRow({
  label,
  value,
  children,
  selected = false,
  last = false,
}) {
  return (
    <div
      className={`grid grid-cols-[1fr_auto] items-center gap-4 px-4 py-3.5 sm:py-4 ${
        last ? "" : "border-b border-white/10"
      }`}
    >
      <div>
        <p className="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
          {label}
        </p>

        <p
          className={`mt-1 text-sm font-black ${
            selected ? "text-white" : "text-slate-300"
          }`}
        >
          {value}
        </p>
      </div>

      {children && (
        <div className="flex items-center justify-end">{children}</div>
      )}
    </div>
  );
}

function CapacityDots({ total, active, selected = false }) {
  return (
    <div className="flex items-center gap-1.5">
      {Array.from({ length: total }).map((_, index) => {
        const enabled = index < active;

        return (
          <motion.span
            key={index}
            initial={{ scale: 0.7, opacity: 0.4 }}
            whileInView={{ scale: 1, opacity: 1 }}
            viewport={{ once: true }}
            transition={{ delay: index * 0.06, duration: 0.2 }}
            className={`h-2.5 w-2.5 rounded-full ${
              enabled
                ? selected
                  ? "bg-secondary shadow-[0_0_14px_rgba(118,188,33,0.45)]"
                  : "bg-slate-200"
                : "bg-white/18"
            }`}
          />
        );
      })}
    </div>
  );
}

function StatusIcon({ enabled }) {
  return (
    <span
      className={`flex h-8 w-8 items-center justify-center rounded-full border text-sm font-black ${
        enabled
          ? "border-secondary/40 bg-secondary/15 text-secondary"
          : "border-white/15 bg-white/8 text-slate-400"
      }`}
    >
      {enabled ? "✓" : "×"}
    </span>
  );
}

function FeatureItem({ label, enabled, selected = false }) {
  return (
    <div
      className={`flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold ${
        enabled
          ? selected
            ? "border-secondary/20 bg-white/[0.08] text-slate-100"
            : "border-white/10 bg-white/[0.05] text-slate-300"
          : "border-white/8 bg-white/[0.035] text-slate-400"
      }`}
    >
      <span
        className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-xs ${
          enabled
            ? "border-secondary text-secondary"
            : "border-white/20 text-slate-400"
        }`}
      >
        {enabled ? "✓" : "×"}
      </span>

      <span>{label}</span>
    </div>
  );
}
