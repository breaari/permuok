// src/public-site/components/ContactSection.jsx

import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import Reveal from "./Reveal";

const quickReasons = [
  "Conocer la plataforma",
  "Elegir membresía",
  "Publicar proyectos",
  "Soporte técnico",
];

export default function ContactSection() {
  return (
    <section
      id="contacto"
      className="relative overflow-hidden bg-[#06224a] px-5 pb-20 pt-20 text-white sm:px-6 md:pb-40 md:pt-32"
    >
      {/* Continuación desde FAQ */}
      <div className="absolute left-0 top-0 h-24 w-full bg-gradient-to-b from-[#07111f] to-[#06224a] md:h-28" />

      {/* Fondo */}
      <div className="absolute inset-0 -z-20 bg-[#06224a]" />
      <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(118,188,33,0.18),transparent_30%),radial-gradient(circle_at_bottom_right,rgba(0,86,179,0.40),transparent_40%)]" />
      <div className="absolute left-[-260px] top-16 -z-10 h-[560px] w-[560px] rounded-full bg-primary/20 blur-3xl" />
      <div className="absolute bottom-[-280px] right-[-220px] -z-10 h-[620px] w-[620px] rounded-full bg-secondary/12 blur-3xl" />

      {/* Patrón */}
      <div className="pointer-events-none absolute inset-0 z-0 opacity-[0.075]">
        <div className="absolute inset-0 bg-[linear-gradient(115deg,transparent_0%,transparent_42%,rgba(255,255,255,0.16)_42%,rgba(255,255,255,0.16)_43%,transparent_43%,transparent_100%)] bg-[length:120px_120px]" />
      </div>

      <div className="relative z-10 mx-auto max-w-7xl">
        {/* Bloque principal */}
        <div className="overflow-hidden rounded-[32px] border border-white/10 bg-white/[0.075] shadow-[0_34px_120px_rgba(0,20,50,0.34)] backdrop-blur md:rounded-[46px]">
          <div className="grid lg:grid-cols-[1.05fr_0.95fr]">
            {/* Mensaje principal */}
            <div className="relative p-6 md:p-10 lg:p-12">
              <Reveal>
                <span className="inline-flex rounded-full border border-white/10 bg-white/[0.08] px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-secondary backdrop-blur sm:text-sm sm:tracking-[0.18em]">
                  Contacto
                </span>
              </Reveal>

              <Reveal delay={0.08}>
                <h2 className="mt-5 max-w-4xl text-[34px] font-black leading-[0.98] tracking-[-0.055em] text-white sm:text-[42px] md:text-6xl md:leading-none">
                  Probá una forma más inteligente de generar operaciones.
                </h2>
              </Reveal>

              <Reveal delay={0.16}>
                <p className="mt-5 max-w-2xl text-base leading-7 text-slate-200 sm:text-lg sm:leading-8">
                  Sumá tu inmobiliaria a una red pensada para conectar cartera,
                  detectar permutas, trabajar con inversores y activar nuevas
                  oportunidades comerciales.
                </p>
              </Reveal>
            </div>

            {/* CTA */}
            <Reveal delay={0.14} y={28}>
              <div className="relative flex h-full flex-col justify-between overflow-hidden border-t border-white/10 bg-[#07111f]/70 p-6 md:p-10 lg:min-h-[420px] lg:border-l lg:border-t-0 lg:p-12">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(118,188,33,0.22),transparent_36%),radial-gradient(circle_at_bottom_left,rgba(0,86,179,0.36),transparent_38%)]" />

                <div className="relative z-10">
                  <p className="text-xs font-black uppercase tracking-[0.18em] text-secondary sm:text-sm sm:tracking-[0.2em]">
                    Acceso a la plataforma
                  </p>

                  <h3 className="mt-3 text-[28px] font-black leading-[1.02] tracking-[-0.04em] md:text-5xl">
                    Entrá, explorá y empezá a cargar oportunidades.
                  </h3>

                  <p className="mt-4 text-sm leading-6 text-slate-300 sm:text-base sm:leading-7">
                    La mejor forma de entender el valor de PermuOK es probar la
                    red desde adentro.
                  </p>
                </div>

                <div className="relative z-10 mt-6 lg:mt-8">
                  <Link
                    to="/register"
                    className="inline-flex w-full items-center justify-center rounded-2xl bg-secondary px-6 py-3.5 text-sm font-black text-background-dark shadow-[0_18px_44px_rgba(118,188,33,0.24)] transition hover:bg-lime-300 active:scale-[0.98] sm:py-4"
                  >
                    Crear cuenta
                  </Link>
                </div>
              </div>
            </Reveal>
          </div>
        </div>

        {/* Contactanos */}
        <Reveal delay={0.22}>
          <div className="mt-8 overflow-hidden rounded-[32px] border border-white/10 bg-white/[0.075] shadow-[0_30px_100px_rgba(0,20,50,0.28)] backdrop-blur md:mt-10 md:rounded-[46px]">
            {/* Encabezado del formulario */}
            <div className="border-b border-white/10 p-6 md:p-9">
              <div className="grid gap-5 lg:grid-cols-[0.85fr_1.15fr] lg:items-end">
                <div>
                  <p className="text-xs font-black uppercase tracking-[0.18em] text-secondary sm:text-sm sm:tracking-[0.2em]">
                    Contactanos
                  </p>

                  <h3 className="mt-3 text-[28px] font-black leading-[1.02] tracking-[-0.04em] text-white md:text-5xl">
                    ¿Preferís hablar con alguien antes?
                  </h3>
                </div>

                <p className="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base sm:leading-7">
                  Dejanos tus datos y contanos qué necesitás. Podemos ayudarte
                  con membresías, proyectos inmobiliarios, implementación o
                  soporte técnico.
                </p>
              </div>

              <div className="mt-5 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:flex-wrap md:gap-3">
                {quickReasons.map((reason) => (
                  <span
                    key={reason}
                    className="inline-flex shrink-0 rounded-full border border-white/10 bg-white/[0.07] px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-300 sm:px-4 sm:text-xs sm:tracking-[0.14em]"
                  >
                    {reason}
                  </span>
                ))}
              </div>
            </div>

            {/* Formulario */}
            <form className="relative p-6 md:p-9">
              <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(118,188,33,0.10),transparent_34%)]" />

              <div className="relative z-10 grid gap-5 xl:grid-cols-[0.85fr_1.15fr] xl:gap-6">
                {/* Datos de contacto */}
                <div className="rounded-[26px] border border-white/10 bg-white/[0.055] p-4 md:rounded-[34px] md:p-6">
                  <p className="mb-4 text-xs font-black uppercase tracking-[0.18em] text-secondary">
                    Tus datos
                  </p>

                  <div className="grid gap-3.5 md:gap-4">
                    <Field label="Nombre">
                      <input
                        className="contact-input-dark"
                        placeholder="Tu nombre"
                        type="text"
                      />
                    </Field>

                    <Field label="Inmobiliaria">
                      <input
                        className="contact-input-dark"
                        placeholder="Nombre de la inmobiliaria"
                        type="text"
                      />
                    </Field>

                    <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-1 md:gap-4">
                      <Field label="Email">
                        <input
                          className="contact-input-dark"
                          placeholder="tuemail@dominio.com"
                          type="email"
                        />
                      </Field>

                      <Field label="WhatsApp">
                        <input
                          className="contact-input-dark"
                          placeholder="+54 9 ..."
                          type="tel"
                        />
                      </Field>
                    </div>
                  </div>
                </div>

                {/* Consulta */}
                <div className="rounded-[26px] border border-white/10 bg-white/[0.055] p-4 md:rounded-[34px] md:p-6">
                  <p className="mb-4 text-xs font-black uppercase tracking-[0.18em] text-secondary">
                    Tu consulta
                  </p>

                  <div className="grid gap-3.5 sm:grid-cols-2 md:gap-4">
                    <Field label="Ciudad">
                      <input
                        className="contact-input-dark"
                        placeholder="Ej. Mar del Plata"
                        type="text"
                      />
                    </Field>

                    <Field label="Tipo de consulta">
                      <select className="contact-input-dark appearance-none">
                        <option>Quiero conocer PermuOK</option>
                        <option>Consulta sobre membresías</option>
                        <option>Quiero publicar proyectos</option>
                        <option>Soporte técnico</option>
                        <option>Otro</option>
                      </select>
                    </Field>
                  </div>

                  <Field label="Mensaje" className="mt-3.5 md:mt-4">
                    <textarea
                      className="contact-input-dark min-h-32 resize-none md:min-h-40"
                      placeholder="Contanos qué querés resolver o qué tipo de operación querés potenciar."
                    />
                  </Field>

                  <div className="mt-5 flex justify-end">
                    <button
                      type="button"
                      className="inline-flex w-full items-center justify-center rounded-2xl bg-secondary px-7 py-3.5 text-sm font-black text-background-dark shadow-[0_18px_44px_rgba(118,188,33,0.24)] transition hover:bg-lime-300 active:scale-[0.98] sm:w-auto sm:py-4"
                    >
                      Enviar
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </Reveal>
      </div>

      <style>{`
        .contact-input-dark {
          width: 100%;
          border-radius: 0.9rem;
          border: 1px solid rgba(255, 255, 255, 0.12);
          background: rgba(255, 255, 255, 0.08);
          padding: 0.85rem 0.95rem;
          font-size: 0.875rem;
          color: white;
          outline: none;
          transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }

        .contact-input-dark::placeholder {
          color: rgb(148 163 184);
        }

        .contact-input-dark:focus {
          border-color: rgba(118, 188, 33, 0.75);
          background: rgba(255, 255, 255, 0.12);
          box-shadow: 0 0 0 4px rgba(118, 188, 33, 0.10);
        }

        .contact-input-dark option {
          background: #06224a;
          color: white;
        }

        @media (min-width: 768px) {
          .contact-input-dark {
            border-radius: 1rem;
            padding: 0.95rem 1rem;
          }
        }
      `}</style>
    </section>
  );
}

function Field({ label, children, className = "" }) {
  return (
    <label className={`block ${className}`}>
      <span className="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-slate-300">
        {label}
      </span>

      {children}
    </label>
  );
}
