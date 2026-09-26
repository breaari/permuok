// src/public-site/components/HeroSection.jsx

import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import HeroCardsWebGL from "./HeroCardsWebGL";

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
        bg-[#f7f8f6]
        text-[#0a192f]
      "
    >
      {/* Fondo punteado */}
      <div
        className="pointer-events-none absolute inset-0 z-0 opacity-60"
        style={{
          backgroundImage:
            "radial-gradient(rgba(15,23,42,0.11) 0.75px, transparent 0.75px)",
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
          top-[125px]
          z-30
          w-full
          -translate-x-1/2
          px-5
          text-center
          sm:top-[128px]
          sm:px-6
          lg:top-[132px]
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
            max-w-[1120px]
            text-[40px]
            font-normal
            leading-[0.96]
            tracking-[-0.035em]
            text-[#0a192f]
            sm:text-[50px]
            md:text-[58px]
            lg:text-[62px]
            xl:text-[64px]
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
          bottom-[60px]
          left-1/2
          z-40
          w-[min(92vw,680px)]
          -translate-x-1/2
          text-center
          sm:bottom-[66px]
          lg:bottom-[70px]
        "
      >
        <p
          data-hero-subtitle
          className="
            mx-auto
            max-w-[620px]
            text-base
            font-medium
            leading-7
            text-slate-600
            sm:text-lg
          "
        >
          Una red inteligente donde las oportunidades de permuta se centralizan,
          se cruzan y empiezan a encontrarse.
        </p>

        <div className="mt-7 flex justify-center">
          <Link
            to="/register"
            className="
              group
              inline-flex
              min-w-[220px]
              items-center
              justify-between
              gap-5
              rounded-xl
              bg-[#0a192f]
              px-4
              py-3
              text-sm
              font-extrabold
              text-white
              shadow-[0_12px_30px_rgba(10,25,47,0.18)]
              transition
              duration-300
              hover:-translate-y-0.5
              hover:bg-primary
            "
          >
            <span>Sumarme a la red</span>

            <span
              className="
                flex
                h-8
                w-8
                items-center
                justify-center
                rounded-full
                bg-white/10
                transition
                group-hover:translate-x-0.5
              "
            >
              →
            </span>
          </Link>
        </div>
      </motion.div>
    </section>
  );
}
