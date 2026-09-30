// src/public-site/components/HeroSection.jsx

import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import HeroCardsWebGL from "./HeroCardsWebGL";
import { Icon } from "../../ui/icons/Index";

export default function HeroSection() {
  return (
    <section
      data-hero-root
      className="
    relative
    isolate
    h-[100svh]
    min-h-[720px]
    overflow-hidden
    bg-[#f3f4f6]
        mt-[45px]

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
          WEBGL
      ====================================================== */}

      <div className="absolute inset-0 z-10">
        <HeroCardsWebGL />
      </div>

      {/* =====================================================
          TÍTULO
      ====================================================== */}

      <div
        className="
  pointer-events-none
  absolute
  left-1/2
  top-[105px]
  z-30
  w-full
  -translate-x-1/2
  px-5
  text-center
  sm:top-[128px]
  sm:px-6
  lg:top-[124px]
"
      >
        <motion.h1
          data-hero-title
          initial={{
            opacity: 0,
            y: 18,
          }}
          animate={{
            opacity: 1,
            y: 0,
          }}
          transition={{
            duration: 0.7,
            ease: [0.22, 1, 0.36, 1],
          }}
          className="
  mx-auto
  max-w-[1040px]
  text-[40px]
  font-normal
  leading-[0.98]
  tracking-[-0.035em]
  text-[#0a192f]
  sm:text-[48px]
  md:text-[54px]
  lg:text-[54px]
  xl:text-[56px]
"
        >
          La plataforma creada para revolucionar las permutas inmobiliarias.
        </motion.h1>
      </div>

      {/* =====================================================
          BAJADA + CTA
      ====================================================== */}

      <motion.div
        initial={{
          opacity: 0,
          y: 14,
        }}
        animate={{
          opacity: 1,
          y: 0,
        }}
        transition={{
          delay: 0.5,
          duration: 0.65,
        }}
        className="
  absolute
  bottom-[54px]
  left-1/2
  z-40
  w-[min(92vw,760px)]
  -translate-x-1/2
  text-center
  sm:bottom-[60px]
  lg:bottom-[clamp(110px,16vh,140px)]
"
      >
        {/* Bajada */}
        <div
          data-hero-subtitle
          className="
    mx-auto
    max-w-[610px]
    px-4
    sm:px-6

  "
        >
          <p
            className="
      text-[17px]
      font-medium
      leading-[1.4]
      tracking-[-0.02em]
      text-slate-600
      sm:text-[18px]
      lg:text-[20px]
    "
          >
            <span className="lg:hidden ">
              Una red inteligente sólo para inmobiliarias donde las
              oportunidades de permuta se centralizan, se cruzan y empiezan a
              encontrarse.
            </span>

            <span className="hidden lg:inline">
              Una red inteligente sólo para inmobiliarias
              <br />
              donde las oportunidades de permuta se centralizan,
              <br />
              se cruzan y empiezan a encontrarse.
            </span>
          </p>
        </div>

        {/* CTA */}
        <div className="mt-6 flex justify-center">
          <motion.div
            animate={{
              scale: [1, 1.018, 1],
              boxShadow: [
                "0 0 18px rgba(118,188,33,0.30), 0 10px 30px rgba(118,188,33,0.18)",
                "0 0 34px rgba(118,188,33,0.62), 0 12px 38px rgba(118,188,33,0.30)",
                "0 0 18px rgba(118,188,33,0.30), 0 10px 30px rgba(118,188,33,0.18)",
              ],
            }}
            transition={{
              duration: 2.4,
              repeat: Infinity,
              ease: "easeInOut",
            }}
            whileHover={{
              scale: 1.045,
              y: -3,
            }}
            whileTap={{
              scale: 0.98,
            }}
            className="rounded-[14px]"
          >
            <Link
              to="/register"
              className="
        group
        relative
        inline-flex
        min-w-[250px]
        items-center
        justify-between
        gap-6
        overflow-hidden
        rounded-[14px]
        border
        border-[#9ee34a]
        bg-[#76bc21]
        px-5
        py-3.5
        text-[15px]
        font-bold
        text-[#0a192f]
        transition
        duration-300
        hover:bg-[#82ca28]
      "
            >
              {/* Destello que cruza el botón */}
              <motion.span
                aria-hidden="true"
                className="
          pointer-events-none
          absolute
          inset-y-0
          w-20
          -skew-x-12
          bg-gradient-to-r
          from-transparent
          via-white/35
          to-transparent
        "
                initial={{
                  x: "-180%",
                }}
                animate={{
                  x: "420%",
                }}
                transition={{
                  duration: 1.2,
                  repeat: Infinity,
                  repeatDelay: 2.4,
                  ease: "easeInOut",
                }}
              />

              <span className="relative z-10">Sumarme a la red</span>

              <span
                className="
          relative
          z-10
          flex
          h-9
          w-9
          items-center
          justify-center
          rounded-full
          bg-[#0a192f]
          transition
          duration-300
          group-hover:translate-x-1
          group-hover:scale-105
        "
              >
                <Icon name="arrowRight" size={17} className="text-white" />
              </span>
            </Link>
          </motion.div>
        </div>
      </motion.div>
    </section>
  );
}
