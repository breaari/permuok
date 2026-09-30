import { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";

const items = [
  "En un grupo de Facebook o WhatsApp.",
  "En una publicación de otra inmobiliaria.",
  "En estados o historias.",
  "En una conversación entre colegas.",
  "O simplemente de boca en boca.",
];

export default function OpportunitySection() {
  const sectionRef = useRef(null);

  const [progress, setProgress] = useState(0);
  const [navbarBottom, setNavbarBottom] = useState(80);

  /* =========================================================
     MEDIMOS EL FINAL REAL DE LA NAVBAR
  ========================================================= */

  useLayoutEffect(() => {
    let rafId = null;

    const navbar = document.querySelector("[data-public-navbar]");

    function updateNavbarBottom() {
      if (!navbar) return;

      const rect = navbar.getBoundingClientRect();

      /*
       * La navbar es fixed.
       * rect.bottom nos da exactamente dónde termina
       * dentro del viewport.
       */
      setNavbarBottom(Math.round(rect.bottom));
    }

    function scheduleUpdate() {
      if (rafId) {
        cancelAnimationFrame(rafId);
      }

      rafId = requestAnimationFrame(updateNavbarBottom);
    }

    scheduleUpdate();

    const resizeObserver = new ResizeObserver(scheduleUpdate);

    if (navbar) {
      resizeObserver.observe(navbar);
    }

    window.addEventListener("resize", scheduleUpdate);
    window.addEventListener("orientationchange", scheduleUpdate);

    return () => {
      if (rafId) {
        cancelAnimationFrame(rafId);
      }

      resizeObserver.disconnect();

      window.removeEventListener("resize", scheduleUpdate);
      window.removeEventListener("orientationchange", scheduleUpdate);
    };
  }, []);

  /* =========================================================
     PROGRESO DEL SCROLL
  ========================================================= */

  useEffect(() => {
    const handleScroll = () => {
      if (!sectionRef.current) return;

      const rect = sectionRef.current.getBoundingClientRect();
      const sectionHeight = sectionRef.current.offsetHeight;
      const viewportHeight = window.innerHeight;

      const maxScroll = Math.max(sectionHeight - viewportHeight, 1);

      const current = Math.min(Math.max(-rect.top, 0), maxScroll);

      setProgress(current / maxScroll);
    };

    handleScroll();

    window.addEventListener("scroll", handleScroll, {
      passive: true,
    });

    return () => {
      window.removeEventListener("scroll", handleScroll);
    };
  }, []);

  const activeIndex = useMemo(() => {
    if (progress < 0.14) return 0;
    if (progress < 0.27) return 1;
    if (progress < 0.4) return 2;
    if (progress < 0.53) return 3;

    return 4;
  }, [progress]);

  return (
    <section
      ref={sectionRef}
      className="
    relative
    h-[155vh]
    bg-[#f3f4f6]
  "
    >
      <div
        className="pointer-events-none absolute inset-0 z-0 opacity-65"
        style={{
          backgroundImage:
            "radial-gradient(rgba(71,85,105,0.13) 0.75px, transparent 0.75px)",
          backgroundSize: "18px 18px",
        }}
      />

      <div
        className="sticky z-10 overflow-hidden"
        style={{
          top: `${navbarBottom}px`,
          height: `calc(100svh - ${navbarBottom}px)`,
        }}
      >
      <div
        className="sticky overflow-hidden"
        style={{
          top: `${navbarBottom}px`,
          height: `calc(100svh - ${navbarBottom}px)`,
        }}
      >
        <div
          className="
    mx-auto
    flex
    h-full
    w-full
    max-w-[1320px]
    items-center
    px-6
    sm:px-8
    lg:px-10
  "
        >
          <div className="w-full">
            <div
              className="
    grid
    w-full
    items-start
    gap-12

    lg:grid-cols-[minmax(0,0.92fr)_minmax(430px,0.82fr)]
    lg:gap-24
  "
            >
              <LeftContent />

              <RightList progress={progress} activeIndex={activeIndex} />
            </div>

            <Conclusion />
          </div>
        </div>
      </div>
      </div>
    </section>
  );
}

/* =========================================================
   IZQUIERDA
========================================================= */

function LeftContent() {
  return (
    <div className="max-w-[660px]">
      {/* 
        Mismo lenguaje visual que la bajada del HERO:
        mismo color, tracking, peso e interlineado.
      */}

      <p
        className="
          max-w-[620px]
          text-[18px]
          font-medium
          leading-[1.4]
          tracking-[-0.02em]
          text-[#30363d]

          sm:text-[19px]

          lg:text-[22px]
          lg:leading-[1.42]
        "
      >
        Detrás de una permuta puede estar la operación que hoy no estás pudiendo
        cerrar.
      </p>

      {/* 
        NO es un h1/h2 para que NO herede Momo Trust Display.
        Esta frase queda en Manrope.
      */}

      <p
        className="
          mt-7
          max-w-[620px]
          text-[clamp(1.7rem,2.35vw,2.45rem)]
          font-semibold
          leading-[1.12]
          tracking-[-0.035em]
          text-[#0a192f]
        "
      >
        Y esa oportunidad puede estar en{" "}
        <MarkerText>cualquier lado.</MarkerText>
      </p>
    </div>
  );
}

/* =========================================================
   MARCADOR
========================================================= */

function MarkerText({ children }) {
  return (
    <span className="relative inline-block whitespace-nowrap">
      <span
        aria-hidden="true"
        className="
          absolute
          -left-[2%]
          -right-[2%]
          bottom-[1px]
          -z-10
          h-[37%]
          -rotate-[0.7deg]
          bg-[#9fc5ff]
        "
      />

      {children}
    </span>
  );
}

/* =========================================================
   LISTA DERECHA
========================================================= */

function RightList({ progress, activeIndex }) {
  return (
    <div className="space-y-3.5">
      {items.map((item, index) => {
        /*
         * El primero está visible desde el momento
         * en que aparece la sección.
         */
        let itemProgress = 1;

        if (index > 0) {
          const starts = [0, 0.04, 0.15, 0.26, 0.37];

          const itemStart = starts[index];
          const itemEnd = itemStart + 0.085;

          itemProgress = clamp((progress - itemStart) / (itemEnd - itemStart));
        }

        const isActive = index === activeIndex;

        return (
          <div
            key={item}
            className={`
              border
              px-6
              py-[18px]
              transition-[background-color,border-color,box-shadow]
              duration-500

              ${
                isActive
                  ? `
                    border-[#72bb61]
                    bg-[#eef8e9]
                    shadow-[0_14px_36px_rgba(79,164,72,0.11)]
                  `
                  : `
                    border-[#91c986]
                    bg-white/75
                  `
              }
            `}
            style={{
              opacity: itemProgress,

              transform: `
                translateY(
                  ${mix(10, 0, itemProgress)}px
                )
              `,
            }}
          >
            <p
              className={`
                text-[16px]
                font-medium
                leading-[1.35]
                tracking-[-0.015em]

                md:text-[18px]

                ${isActive ? "text-[#285f28]" : "text-[#172033]"}
              `}
            >
              {item}
            </p>
          </div>
        );
      })}
      
    </div>
  );
}

/* =========================================================
   CONCLUSIÓN
========================================================= */

function Conclusion() {
  return (
    <div
      className="
        mx-auto
        mt-[clamp(36px,5vh,58px)]
        max-w-[1300px]
        text-center
      "
    >
      {/*
        IMPORTANTE:

        Es h2 porque tu CSS actual tiene:

        .public-site h1,
        .public-site h2 {
          font-family: "Momo Trust Display", "Manrope", sans-serif;
          font-weight: 400;
        }

        Por lo tanto usa EXACTAMENTE la misma fuente
        que el título del HeroSection.
      */}

      <h2
        className="
    text-balance
    text-[clamp(2rem,3vw,3.2rem)]
    font-normal
    leading-[1.02]
    tracking-[-0.035em]
    text-[#0a192f]
  "
      >
        Permuok no solo centraliza esta información.
        <br className="hidden sm:block" />
        <span className="sm:ml-2">
          La conecta para detectar compatibilidades
          <br />y abrir nuevas posibilidades de operación.
        </span>
      </h2>
    </div>
  );
}

/* =========================================================
   HELPERS
========================================================= */

function clamp(value, min = 0, max = 1) {
  return Math.min(Math.max(value, min), max);
}

function mix(start, end, progress) {
  return start + (end - start) * progress;
}
