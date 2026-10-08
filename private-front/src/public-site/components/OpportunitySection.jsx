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
  if (progress < 0.18) return 0;
  if (progress < 0.36) return 1;
  if (progress < 0.54) return 2;
  if (progress < 0.72) return 3;

  return 4;
}, [progress]);

  return (
    <section
      ref={sectionRef}
      className="
    relative
    bg-transparent
    lg:h-[155vh]
  "
    >
      {/* Fondo punteado */}
  
      <div
        className="
    relative
    z-10

    lg:sticky
    lg:top-[var(--navbar-bottom)]
    lg:overflow-hidden
  "
        style={{
          "--navbar-bottom": `${navbarBottom}px`,
        }}
      >
        <div
          className="
  mx-auto
  flex
  w-full
  max-w-[1320px]

  px-5
  pb-16
  pt-8

  sm:px-8
  sm:pt-10

  lg:h-[calc(100svh-var(--navbar-bottom))]
  lg:items-center
  lg:px-10
  lg:py-0
"
        >
          <div className="w-full">
            <div
              className="
            grid
            w-full
            items-start
            gap-8

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
    </section>
  );
}

/* =========================================================
   IZQUIERDA
========================================================= */

function LeftContent() {
  return (
    <div className="max-w-[660px]">
      <p
        className="
          max-w-[620px]
          text-[18px]
          font-medium
          leading-[1.42]
          tracking-[-0.02em]
          text-[#30363d]

          sm:text-[19px]

          lg:text-[24px]
        "
      >
        Detrás de una permuta puede estar la operación que no estás pudiendo
        cerrar.
      </p>

      <p
        className="
          my-6
          max-w-[620px]
          text-[25px]
          font-semibold
          leading-[1.06]
          tracking-[-0.04em]
          text-[#0a192f]

          sm:text-[34px]

          lg:my-7
          lg:text-[clamp(1.7rem,2.35vw,2.45rem)]
          lg:leading-[1.12]
        "
      >
        Hoy, encontrarla implica buscar en{" "}
        <MarkerText>demasiados lugares.</MarkerText>
      </p>

      <p
        className="
          max-w-[620px]
          text-[18px]
          font-medium
          leading-[1.42]
          tracking-[-0.02em]
          text-[#30363d]

          sm:text-[19px]

          lg:text-[24px]
        "
      >
        Y aun así, puede existir sin que llegues a encontrarla.
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
    <div className="space-y-2 lg:space-y-2.5">
      {items.map((item, index) => {
        let itemProgress = 1;

        if (index > 0) {
const starts = [0, 0.12, 0.3, 0.48, 0.66];

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
              border-[#76bc21]
              bg-[#76bc21]
              px-4
              py-3

              lg:px-5
              lg:transition
              lg:duration-300

              ${
                isActive
                  ? "lg:border-[#86cc31] lg:bg-[#86cc31] lg:shadow-[0_10px_24px_rgba(118,188,33,0.20)]"
                  : ""
              }
            `}
            style={{
              "--item-opacity": itemProgress,
              "--item-y": `${mix(10, 0, itemProgress)}px`,
            }}
          >
            <p
              className="
                text-[14px]
                font-semibold
                leading-[1.25]
                tracking-[-0.015em]
                text-white

                sm:text-[15px]
                md:text-[16px]
              "
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
        mt-10
        max-w-[1300px]
        text-center

        lg:mt-[clamp(36px,5vh,58px)]
      "
    >
      <h2
        className="
          text-balance
          text-[25px]
          font-normal
          leading-[1.02]
          tracking-[-0.035em]
          text-[#0a192f]

          sm:text-[36px]

          lg:text-[clamp(2rem,3vw,3.2rem)]
        "
      >
        Permuok no solo centraliza esta información.
        <br className="" />
        <span>
          La conecta para detectar compatibilidades{" "}
          <br className="hidden lg:block" />y abrir nuevas posibilidades de
          operación.
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
