// src/public-site/components/HeroSection.jsx

import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import HeroCardsWebGL from "./HeroCardsWebGL";
import { Icon } from "../../ui/icons/Index";
import { useLayoutEffect, useRef, useState } from "react";

export default function HeroSection() {
  const sectionRef = useRef(null);

  const [mobileShiftY, setMobileShiftY] = useState(0);

  const currentShiftRef = useRef(0);

  useLayoutEffect(() => {
    const section = sectionRef.current;

    if (!section) {
      return undefined;
    }

    let rafId = null;

    function calculateMobileCenter() {
      /*
       * Desktop queda completamente bloqueado.
       */
      if (window.innerWidth >= 1024) {
        currentShiftRef.current = 0;

        setMobileShiftY(0);

        return;
      }

      const navbar = document.querySelector("[data-public-navbar]");

      const title = section.querySelector("[data-hero-title]");

      const cta = section.querySelector("[data-hero-cta]");

      if (!navbar || !title || !cta) {
        return;
      }

      const sectionRect = section.getBoundingClientRect();

      const navbarRect = navbar.getBoundingClientRect();

      const titleRect = title.getBoundingClientRect();

      const ctaRect = cta.getBoundingClientRect();

      /*
       * Espacio real disponible:
       *
       * NAVBAR
       * ───────────────
       * + 12px
       *
       *      HERO
       *
       * - 12px
       * ───────────────
       * FIN DE PANTALLA
       */

      const SAFE_GAP = 12;

      const availableTop = Math.max(
        navbarRect.bottom - sectionRect.top + SAFE_GAP,
        0,
      );

      /*
       * Si min-h-[720px] hace que el hero sea
       * mayor que la pantalla, tomamos únicamente
       * la parte realmente visible.
       */
      const visibleViewportBottom = Math.min(
        sectionRect.bottom,
        window.innerHeight,
      );

      const availableBottom =
        visibleViewportBottom - sectionRect.top - SAFE_GAP;

      const availableCenter = (availableTop + availableBottom) / 2;

      /*
       * Límites reales de nuestra composición:
       *
       * TOP DEL TÍTULO
       * ↓
       *
       * título
       * cards
       * bajada
       * botones
       *
       * ↓
       * BOTTOM DE LOS CTA
       */

      const measuredContentTop = titleRect.top - sectionRect.top;

      const measuredContentBottom = ctaRect.bottom - sectionRect.top;

      const measuredContentCenter =
        (measuredContentTop + measuredContentBottom) / 2;

      /*
       * Como el grupo ya puede estar trasladado,
       * quitamos el desplazamiento anterior para
       * recuperar su centro original.
       */

      const originalContentCenter =
        measuredContentCenter - currentShiftRef.current;

      /*
       * Cantidad exacta que hay que mover TODO.
       */

      const nextShift = Math.round(availableCenter - originalContentCenter);

      currentShiftRef.current = nextShift;

      setMobileShiftY(nextShift);
    }

    function scheduleCalculation() {
      if (rafId) {
        cancelAnimationFrame(rafId);
      }

      rafId = requestAnimationFrame(calculateMobileCenter);
    }

    /*
     * Recalcular si cambia la pantalla,
     * orientación, textos o navbar.
     */

    const resizeObserver = new ResizeObserver(scheduleCalculation);

    resizeObserver.observe(section);

    const navbar = document.querySelector("[data-public-navbar]");

    const title = section.querySelector("[data-hero-title]");

    const cta = section.querySelector("[data-hero-cta]");

    if (navbar) {
      resizeObserver.observe(navbar);
    }

    if (title) {
      resizeObserver.observe(title);
    }

    if (cta) {
      resizeObserver.observe(cta);
    }

    window.addEventListener("resize", scheduleCalculation);

    window.addEventListener("orientationchange", scheduleCalculation);

    scheduleCalculation();

    document.fonts?.ready.then(scheduleCalculation);

    return () => {
      if (rafId) {
        cancelAnimationFrame(rafId);
      }

      resizeObserver.disconnect();

      window.removeEventListener("resize", scheduleCalculation);

      window.removeEventListener("orientationchange", scheduleCalculation);
    };
  }, []);

  return (
    <section
      ref={sectionRef}
      data-hero-root
      className="
    relative
    isolate
    h-[100svh]
    min-h-[720px]
    overflow-hidden
    bg-[#f3f4f6]

    mt-0
    lg:mt-[45px]
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

      <div
        className="
    absolute
    inset-0
  "
        style={{
          transform: `translate3d(
      0,
      ${mobileShiftY}px,
      0
    )`,
        }}
      >

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
  top-[115px]
  z-30
  w-full
  -translate-x-1/2
  px-5
  text-center
  sm:top-[108px]
  sm:px-6
  md:top-[116px]
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
  max-w-[350px]
  text-[36px]
  font-normal
  leading-[0.97]
  tracking-[-0.035em]
  text-[#0a192f]

  sm:max-w-[520px]
  sm:text-[44px]

  md:max-w-[760px]
  md:text-[50px]

  lg:max-w-[1040px]
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
      data-hero-cta
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
  bottom-[64px]
  left-1/2
  z-40
  w-[min(92vw,760px)]
  -translate-x-1/2
  text-center
sm:bottom-[64px]
lg:bottom-[clamp(110px,16vh,140px)]
"
      >
        {/* Bajada */}
        <div
          data-hero-subtitle
          className="
    relative
    top-0
    mx-auto
    max-w-[350px]
    px-3

    sm:max-w-[460px]
    sm:px-5

    lg:top-[24px]
    lg:max-w-[610px]
    lg:px-6
  "
        >
          <p
            className="
  text-[16px]
  font-medium
  leading-[1.38]
  tracking-[-0.02em]
  text-[#30363d]

  sm:text-[17px]

  lg:text-[20px]
  lg:leading-[1.4]
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
        <div
          className="
  mt-7
  flex
  flex-col
  items-center
  justify-center
  gap-3

  lg:mt-[60px]
  lg:flex-row
  lg:gap-3
"
        >
          {/* CTA PRINCIPAL */}
          <motion.div
            animate={{
              boxShadow: [
                "0 0 10px rgba(118,188,33,0.20)",
                "0 0 28px rgba(118,188,33,0.52)",
                "0 0 10px rgba(118,188,33,0.20)",
              ],
            }}
            transition={{
              duration: 2.6,
              repeat: Infinity,
              ease: "easeInOut",
            }}
            whileHover={{
              y: -2,
            }}
            whileTap={{
              scale: 0.98,
            }}
            className="
  w-[300px]
  max-w-[calc(100vw-48px)]

  lg:w-[270px]
  lg:max-w-full
"
          >
            <Link
              to="/register"
              className="
    group
    flex
    min-h-[56px]
    w-full
    items-center
    justify-center
    gap-3
    rounded-none
    border
    border-[#76bc21]
    bg-[#76bc21]
    px-7
    text-[16px]
    font-bold
    text-white
    transition
    duration-300
    hover:border-[#86cc31]
    hover:bg-[#86cc31]
  "
            >
              <span>Sumarme a la red</span>

              <Icon
                name="arrowRight"
                size={18}
                className="
      text-white
      transition-transform
      duration-300
      group-hover:translate-x-1
    "
              />
            </Link>
          </motion.div>

          {/* CTA SECUNDARIO */}
          <motion.div
            whileHover={{
              y: -2,
            }}
            whileTap={{
              scale: 0.98,
            }}
            className="
      w-[220px]
  max-w-[calc(100vw-48px)]

  lg:w-[220px]
    "
          >
            <a
              href="#como-funciona"
              className="
    flex
    min-h-[54px]
    w-full
    items-center
    justify-center
    rounded-none
    border
    border-[#30363d]
    bg-white
    px-7
    text-[16px]
    font-semibold
    text-[#30363d]
    transition
    duration-300
    hover:bg-[#30363d]
    hover:text-white
  "
            >
              Cómo funciona
            </a>
          </motion.div>
        </div>
      </motion.div>
      </div>
    </section>
  );
}
