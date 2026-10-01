// src/public-site/components/ProblemSection.jsx

import Reveal from "./Reveal";
import { Icon } from "../../ui/icons/Index";
import fotoAsesor from "../../assets/fotoasesor.jpg";

const features = [
  {
    icon: "layoutGrid",
    title: "Todo en un mismo lugar",
    text: "Propiedades, búsquedas y desarrollos que aceptan permuta.",
  },
  {
    icon: "sparkles",
    title: "Cruces automáticos",
    text: "Sobre tu cartera para detectar nuevas compatibilidades.",
  },
  {
    icon: "refresh",
    title: "Operaciones en cadena",
    text: "Descubrí combinaciones entre varias partes que permitan destrabar una operación que no se resolvería de forma directa.",
  },
  {
    icon: "plusCircle",
    title: "Más formas de hacer posible una operación",
    text: "Permuta total, diferencia en dinero, múltiples bienes o venta convencional.",
  },
  {
    icon: "shieldCheck",
    title: "Red segura",
    text: "Un entorno exclusivo para inmobiliarias verificadas.",
  },
];

export default function ProblemSection() {
  return (
    <section
      id="permutas"
      className="
        relative
        overflow-hidden
        bg-[#f3f4f6]
        px-5
        py-20
        text-[#0a192f]

        sm:px-8
        sm:py-24

        lg:px-10
        lg:py-28
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
          CONTENIDO
      ====================================================== */}

      <div
        className="
          relative
          z-10
          mx-auto
          grid
          w-full
          max-w-[1320px]
          gap-14

          lg:grid-cols-[1.05fr_0.95fr]
          lg:items-start
          lg:gap-20
        "
      >
        {/* =====================================================
            IZQUIERDA — MENSAJE + IMAGEN
        ====================================================== */}

        <div>
          <Reveal>
            <div>
              <h2
                className="
                  max-w-[680px]
                  text-[42px]
                  font-normal
                  leading-[0.97]
                  tracking-[-0.04em]
                  text-[#0a192f]

                  sm:text-[43px]

                  lg:text-[45px]
                "
              >
                Actualizá tu forma de trabajar.
              </h2>

              <p
                className="
                  mt-6
                  max-w-[620px]
                  text-[20px]
                  font-medium
                  leading-[1.35]
                  tracking-[-0.025em]
                  text-[#30363d]

                  sm:text-[22px]

                  lg:text-[25px]
                "
              >
                Menos tiempo buscando.{" "}
                <span className="text-[#0a192f]">
                  Más tiempo donde realmente aportás valor.
                </span>
              </p>
            </div>
          </Reveal>

          {/* ===================================================
              IMAGEN
          =================================================== */}

          <Reveal delay={0.1}>
            <div
              className="
                group
                relative
                mt-10
                overflow-hidden
                bg-[#e6e9ee]

                sm:mt-12
              "
            >
              <img
                src={fotoAsesor}
                alt="Asesor inmobiliario trabajando con un cliente"
                className="
                  h-[340px]
                  w-full
                  object-cover
                  object-center
                  transition-transform
                  duration-[900ms]
                  ease-out

                  group-hover:scale-[1.025]

                  sm:h-[390px]

                  lg:h-[430px]
                "
              />

              {/* sombreado mínimo para integrar la imagen */}
              <div
                className="
                  pointer-events-none
                  absolute
                  inset-0
                  bg-gradient-to-t
                  from-[#07182c]/10
                  via-transparent
                  to-transparent
                "
              />
            </div>
          </Reveal>
        </div>

        {/* =====================================================
            DERECHA — FUNCIONALIDADES
        ====================================================== */}

        <div className="lg:pt-2">
          <div className="border-t border-[#cfd5dd]">
            {features.map((feature, index) => (
              <Reveal
                key={feature.title}
                delay={index * 0.06}
              >
                <FeatureItem {...feature} />
              </Reveal>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

/* =========================================================
   FEATURE ITEM
========================================================= */

function FeatureItem({ icon, title, text }) {
  return (
    <div
      className="
        group
        relative
        border-b
        border-[#cfd5dd]
        py-5
        sm:py-5
        lg:py-5
      "
    >
      {/* línea verde hover */}
      <div
        className="
          absolute
          bottom-0
          left-0
          h-px
          w-0
          bg-[#76bc21]
          transition-all
          duration-500
          ease-out

          group-hover:w-full
        "
      />

      <div
        className="
          grid
          grid-cols-[42px_1fr]
          gap-4

          sm:grid-cols-[48px_1fr]
          sm:gap-5
        "
      >
        {/* ICONO */}

        <div
          className="
            flex
            h-10
            w-10
            items-center
            justify-center
            border
            border-[#76bc21]/30
            bg-[#76bc21]/[0.07]
            text-[#76bc21]
            transition
            duration-300

            group-hover:border-[#76bc21]
            group-hover:bg-[#76bc21]
            group-hover:text-white

            sm:h-11
            sm:w-11
          "
        >
          <Icon
            name={icon}
            size={20}
          />
        </div>

        {/* TEXTO */}

        <div
          className="
            transition-transform
            duration-300

            group-hover:translate-x-1
          "
        >
          <h3
            className="
              text-[18px]
              font-bold
              leading-[1]
              tracking-[-0.025em]
              text-[#76bc21]

              sm:text-[20px]

              lg:text-[21px]
            "
          >
            {title}
          </h3>

          <p
            className="
              mt-2
              max-w-[560px]
              text-[17px]
              font-medium
              leading-[1.15]
              tracking-[-0.018em]
              text-[#30363d]

              sm:text-[18px]

              lg:text-[19px]
            "
          >
            {text}
          </p>
        </div>
      </div>
    </div>
  );
}