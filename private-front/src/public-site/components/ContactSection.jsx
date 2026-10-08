// src/public-site/components/ContactSection.jsx

import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import Reveal from "./Reveal";

export default function ContactSection() {
  return (
    <section
      id="contacto"
className="
  relative
  overflow-hidden
  bg-[#f3f4f6]
  px-5
  py-16
  text-[#30363d]

  sm:px-8
  sm:py-20

  lg:px-10
  lg:py-24
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
          w-full
          max-w-[1120px]
          text-center
        "
      >
        {/* FRASE PRINCIPAL */}

        <Reveal>
          <h2
            className="
              mx-auto
              max-w-[980px]
              text-[40px]
              font-normal
              leading-[0.98]
              tracking-[-0.045em]
              text-[#30363d]

              sm:text-[50px]

              md:text-[58px]

              lg:text-[60px]
            "
          >
            El próximo gran activo inmobiliario no es una propiedad.
          </h2>
        </Reveal>

        {/* REMATE */}

        <Reveal delay={0.1}>
          <p
            className="
              mx-auto
              mt-5
              max-w-[860px]
              text-[26px]
              font-medium
              leading-[1.08]
              tracking-[-0.035em]
              text-[#4b5563]

              sm:mt-6
              sm:text-[34px]

              md:text-[40px]

              lg:text-[42px]
            "
          >
            Es la red que permite conectarlas.
          </p>
        </Reveal>

        {/* =====================================================
            CTA
        ====================================================== */}

        <Reveal delay={0.24}>
          <div
            className="
              mt-10
              flex
              flex-col
              items-center

              sm:mt-12
            "
          >
            <motion.div
              whileHover={{
                y: -2,
              }}
              whileTap={{
                scale: 0.985,
              }}
              transition={{
                duration: 0.25,
                ease: [0.22, 1, 0.36, 1],
              }}
            >
              <Link
                to="/register"
                className="
                  inline-flex
                  min-h-[56px]
                  items-center
                  justify-center
                  bg-[#76bc21]
                  px-9
                  text-[15px]
                  font-bold
                  text-white
                  shadow-[0_16px_40px_rgba(118,188,33,0.22)]
                  transition
                  duration-300

                  hover:bg-[#68aa18]
                  hover:shadow-[0_20px_48px_rgba(118,188,33,0.28)]

                  sm:min-h-[60px]
                  sm:px-11
                  sm:text-[16px]
                "
              >
                Sumarme a la red
              </Link>
            </motion.div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}