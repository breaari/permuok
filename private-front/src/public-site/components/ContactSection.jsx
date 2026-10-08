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
        flex
        min-h-[78svh]
        items-center
        overflow-hidden
        bg-[#f3f4f6]
        px-5
        py-24
        text-[#30363d]

        sm:px-8
        sm:py-28

        lg:min-h-[88svh]
        lg:px-10
        lg:py-32
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

      {/* halo muy sutil */}
      <div
        className="
          pointer-events-none
          absolute
          left-1/2
          top-1/2
          z-0
          h-[520px]
          w-[520px]
          -translate-x-1/2
          -translate-y-1/2
          rounded-full
          bg-[#9fc5ff]/20
          blur-[120px]

          lg:h-[720px]
          lg:w-[720px]
        "
      />

      <div
        className="
          relative
          z-10
          mx-auto
          w-full
          max-w-[1180px]
          text-center
        "
      >
        <Reveal>
          <h2
            className="
              mx-auto
              max-w-[1050px]
              text-[42px]
              font-normal
              leading-[0.98]
              tracking-[-0.045em]
              text-[#30363d]

              sm:text-[52px]

              md:text-[62px]

              lg:text-[62px]
            "
          >
            El próximo gran activo inmobiliario no es una propiedad.
          </h2>
        </Reveal>

        <Reveal delay={0.1}>
          <p
            className="
              mx-auto
              mt-6
              max-w-[1000px]
              text-[32px]
              font-normal
              leading-[1]
              tracking-[-0.04em]
              text-[#687384]

              sm:mt-7
              sm:text-[42px]

              md:text-[50px]

              lg:text-[50px]
            "
          >
            Es la red que permite conectarlas.
          </p>
        </Reveal>

        {/* CTA */}

        <Reveal delay={0.28}>
          <motion.div
            className="
              mt-12
              flex
              justify-center

              sm:mt-14
            "
            whileHover={{
              y: -2,
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
                min-h-[54px]
                items-center
                justify-center
                bg-[#76bc21]
                px-8
                text-[15px]
                font-bold
                text-white
                shadow-[0_16px_40px_rgba(118,188,33,0.22)]
                transition
                duration-300

                hover:bg-[#68aa18]

                sm:min-h-[58px]
                sm:px-10
                sm:text-[16px]
              "
            >
              Sumarme a la red
            </Link>
          </motion.div>
        </Reveal>
      </div>
    </section>
  );
}
