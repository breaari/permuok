import { useEffect, useRef, useState } from "react";

const sources = [
  "En un grupo de Facebook.",
  "En un grupo de WhatsApp.",
  "En una publicación de otra inmobiliaria.",
  "En una historia de Instagram que viste una vez y después perdiste.",
  "En una conversación entre colegas.",
  "O en un simple boca a boca.",
];

export default function OpportunitySection() {
  const sectionRef = useRef(null);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      if (!sectionRef.current) return;

      const rect = sectionRef.current.getBoundingClientRect();
      const sectionHeight = sectionRef.current.offsetHeight;
      const viewportHeight = window.innerHeight;

      const totalScrollable = sectionHeight - viewportHeight;
      const scrolled = Math.min(Math.max(-rect.top, 0), totalScrollable);

      const nextProgress = totalScrollable > 0 ? scrolled / totalScrollable : 0;

      setProgress(nextProgress);
    };

    handleScroll();

    window.addEventListener("scroll", handleScroll, {
      passive: true,
    });

    return () => {
      window.removeEventListener("scroll", handleScroll);
    };
  }, []);

  return (
    <section
      ref={sectionRef}
      className="relative h-[520vh] bg-background-light"
    >
      <div className="sticky top-0 h-screen overflow-hidden">
        <div className="mx-auto w-full max-w-[1440px] px-6 lg:px-12">
          <OpportunityIntro progress={progress} />

          <OpportunitySources progress={progress} />

          <OpportunityImpact progress={progress} />

          <OpportunitySolution progress={progress} />
        </div>
      </div>
    </section>
  );
}

function OpportunityIntro({ progress }) {
  const opacity = range(progress, 0, 0.12);
  const exit = inverseRange(progress, 0.14, 0.22);

  return (
    <div
      className="
        pointer-events-none
        absolute
        inset-x-0
        top-0
        flex
        justify-center
        px-6
        pt-[8vh]
        md:pt-[10vh]
      "
      style={{
        opacity: opacity * exit,
        transform: `translateY(${lerp(24, -20, progress)}px)`,
      }}
    >
      <h2
        className="
          max-w-[1000px]
          text-center
          font-display
          text-[clamp(2.2rem,4.4vw,4.8rem)]
          font-bold
          leading-[1.03]
          tracking-[-0.045em]
          text-slate-950
        "
      >
        Una oportunidad de permuta puede estar en cualquier lado.
      </h2>
    </div>
  );
}

function OpportunitySources({ progress }) {
  const start = 0.16;
  const end = 0.58;

  const localProgress = clamp((progress - start) / (end - start));

  return (
    <div
      className="absolute inset-0 flex items-center justify-center"
      style={{
        opacity:
          range(progress, 0.15, 0.22) * inverseRange(progress, 0.56, 0.63),
      }}
    >
      <div className="relative h-full w-full max-w-[1300px]">
        {sources.map((source, index) => {
          const step = 1 / sources.length;

          const itemStart = index * step;
          const itemEnd = itemStart + step * 1.9;

          const itemProgress = range(localProgress, itemStart, itemEnd);

          const positions = [
            {
              left: "8%",
              top: "20%",
              rotate: -5,
            },
            {
              right: "7%",
              top: "28%",
              rotate: 4,
            },
            {
              left: "14%",
              bottom: "21%",
              rotate: 3,
            },
            {
              right: "10%",
              bottom: "17%",
              rotate: -4,
            },
            {
              left: "36%",
              top: "15%",
              rotate: 2,
            },
            {
              left: "38%",
              bottom: "12%",
              rotate: -2,
            },
          ];

          const position = positions[index];

          return (
            <article
              key={source}
              className="
                absolute
                max-w-[360px]
                rounded-[28px]
                border
                border-slate-200/80
                bg-white/90
                px-7
                py-6
                shadow-[0_25px_80px_rgba(15,23,42,0.08)]
                backdrop-blur-md
              "
              style={{
                ...position,

                opacity: itemProgress,

                transform: `
                  translateY(${lerp(60, 0, itemProgress)}px)
                  scale(${lerp(0.92, 1, itemProgress)})
                  rotate(${position.rotate}deg)
                `,
              }}
            >
              <p className="text-lg font-semibold leading-snug text-slate-900 md:text-xl">
                {source}
              </p>
            </article>
          );
        })}

        <div
          className="
            absolute
            left-1/2
            top-1/2
            -translate-x-1/2
            -translate-y-1/2
            text-center
          "
          style={{
            opacity: range(localProgress, 0.65, 0.98),
          }}
        >
          <span className="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">
            Oportunidades dispersas
          </span>
        </div>
      </div>
    </div>
  );
}

function OpportunityImpact({ progress }) {
  const enter = range(progress, 0.59, 0.67);
  const exit = inverseRange(progress, 0.79, 0.84);

  const opacity = enter * exit;

  return (
    <div
      className="
        pointer-events-none
        absolute
        inset-0
        flex
        items-center
        justify-center
        px-6
      "
      style={{
        opacity,
      }}
    >
      <div className="mx-auto max-w-5xl text-center">
        <p className="mb-8 text-lg font-medium text-slate-500 md:text-xl">
          Y mientras esas oportunidades quedan dispersas, también quedan
          operaciones sin cerrar.
        </p>

        <h3
          className="
            font-display
            text-[clamp(2.1rem,4.2vw,4.6rem)]
            font-bold
            leading-[1]
            tracking-[-0.05em]
            text-slate-950
          "
        >
          Cada oportunidad que no encontrás puede ser una operación que no
          cerrás.
        </h3>

        <div className="mx-auto mt-12 grid max-w-3xl gap-3 text-lg text-slate-500 md:text-xl">
          <p>Propiedades que podrían haberse conectado.</p>

          <p>Clientes que siguen esperando encontrar lo que buscan.</p>

          <p>
            Oportunidades que pasan todos los días sin llegar a encontrarse.
          </p>
        </div>
      </div>
    </div>
  );
}

function OpportunitySolution({ progress }) {
  const problemEnter = range(progress, 0.81, 0.87);
  const solutionEnter = range(progress, 0.9, 0.97);

  return (
    <div className="pointer-events-none absolute inset-0 flex items-center justify-center px-6">
      <div className="mx-auto max-w-6xl text-center">
        <div
          style={{
            opacity: problemEnter,
            transform: `translateY(${lerp(35, 0, problemEnter)}px)`,
          }}
        >
          <h3
            className="
              font-display
              text-[clamp(2.2rem,4.6vw,5rem)]
              font-bold
              leading-[0.95]
              tracking-[-0.055em]
              text-slate-950
            "
          >
            El problema no es que la oportunidad no exista.
          </h3>

          <p
            className="
              mt-4
              font-display
              text-[clamp(2.2rem,4.6vw,5rem)]
              font-bold
              leading-[0.95]
              tracking-[-0.055em]
              text-primary
            "
          >
            Es encontrarla.
          </p>
        </div>

        <p
          className="
            mx-auto
            mt-14
            max-w-3xl
            text-xl
            font-semibold
            leading-relaxed
            text-slate-600
            md:text-2xl
          "
          style={{
            opacity: solutionEnter,
            transform: `translateY(${lerp(25, 0, solutionEnter)}px)`,
          }}
        >
          Permuok pone todas esas posibilidades a trabajar en una misma red
          inmobiliaria.
        </p>
      </div>
    </div>
  );
}

/* Helpers */

function clamp(value, min = 0, max = 1) {
  return Math.min(Math.max(value, min), max);
}

function lerp(start, end, progress) {
  return start + (end - start) * progress;
}

function range(value, start, end) {
  return clamp((value - start) / (end - start));
}

function inverseRange(value, start, end) {
  return 1 - range(value, start, end);
}
