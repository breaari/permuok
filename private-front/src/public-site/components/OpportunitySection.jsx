import { useEffect, useRef, useState } from "react";

const opportunities = [
  {
    eyebrow: "WHATSAPP",
    text: "Busco 3 ambientes en Güemes",
    meta: "Hasta USD 130.000",
    x: 13,
    y: 35,
    rotate: -4,
  },
  {
    eyebrow: "OTRA INMOBILIARIA",
    text: "3 ambientes · Güemes",
    meta: "USD 125.000",
    x: 72,
    y: 32,
    rotate: 3,
  },
  {
    eyebrow: "FACEBOOK",
    text: "Permuto casa por departamento",
    meta: "Mar del Plata",
    x: 8,
    y: 67,
    rotate: 3,
  },
  {
    eyebrow: "INSTAGRAM",
    text: "Casa · Rumencó",
    meta: "Acepta propiedad en parte de pago",
    x: 75,
    y: 68,
    rotate: -3,
  },
  {
    eyebrow: "ENTRE COLEGAS",
    text: "Tengo un cliente buscando algo así",
    meta: "Conversación",
    x: 46,
    y: 74,
    rotate: 2,
  },
];

export default function OpportunitySection() {
  const sectionRef = useRef(null);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const update = () => {
      if (!sectionRef.current) return;

      const rect = sectionRef.current.getBoundingClientRect();
      const maxScroll =
        sectionRef.current.offsetHeight - window.innerHeight;

      const current = Math.min(
        Math.max(-rect.top, 0),
        Math.max(maxScroll, 1)
      );

      setProgress(current / Math.max(maxScroll, 1));
    };

    update();

    window.addEventListener("scroll", update, {
      passive: true,
    });

    return () => window.removeEventListener("scroll", update);
  }, []);

  return (
    <section
      ref={sectionRef}
      className="relative h-[165vh] bg-[#f5f7fa]"
    >
      <div className="sticky top-0 h-screen overflow-hidden">
        <div className="mx-auto h-full max-w-[1500px] px-6 lg:px-10">
          <StoryScene progress={progress} />
        </div>
      </div>
    </section>
  );
}

function StoryScene({ progress }) {
  const intro = 1 - smooth(progress, 0.25, 0.46);
  const problem = smooth(progress, 0.28, 0.47);
  const product = smooth(progress, 0.54, 0.82);

  return (
    <div className="relative h-full w-full">

      {/* texto principal */}
      <div
        className="absolute left-[4%] top-[14%] z-30 max-w-[570px]"
        style={{
          opacity: intro,
          transform: `translateY(${mix(0, -20, progress)}px)`,
        }}
      >
        <p className="mb-4 text-[12px] font-semibold uppercase tracking-[.18em] text-slate-400">
          Oportunidades que hoy están dispersas
        </p>

        <h2 className="text-[clamp(2.2rem,4.2vw,4.8rem)] font-bold leading-[.98] tracking-[-.055em] text-[#07101f]">
          Una oportunidad de permuta puede estar en cualquier lado.
        </h2>
      </div>

      {/* oportunidades dispersas */}
      {opportunities.map((item, index) => (
        <Opportunity
          key={item.text}
          item={item}
          index={index}
          progress={progress}
          product={product}
        />
      ))}

      {/* casi match */}
      <MissedMatch progress={problem} />

      {/* producto */}
      <ProductPanel progress={product} />

      {/* cierre */}
      <Closing progress={product} />

    </div>
  );
}

function Opportunity({ item, index, progress, product }) {
  const appear = smooth(
    progress,
    0.02 + index * 0.025,
    0.17 + index * 0.025
  );

  const drift = Math.sin(progress * 5 + index * 1.8) * 6;

  const centerX = 50;
  const centerY = 49;

  const x = mix(item.x, centerX, product * 0.7);
  const y = mix(item.y, centerY, product * 0.7);

  const opacity =
    appear * (1 - smooth(product, 0.55, 0.95));

  return (
    <div
      className="absolute z-10"
      style={{
        left: `${x}%`,
        top: `${y}%`,
        opacity,
        transform: `
          translate(-50%, -50%)
          translateY(${drift * (1 - product)}px)
          rotate(${mix(item.rotate, 0, product)}deg)
        `,
      }}
    >
      <div
        className="
          min-w-[220px]
          max-w-[290px]
          rounded-[20px]
          border
          border-slate-200/70
          bg-white/90
          px-5
          py-4
          shadow-[0_18px_55px_rgba(15,23,42,0.07)]
          backdrop-blur-xl
        "
      >
        <p className="mb-2 text-[10px] font-bold tracking-[.15em] text-slate-400">
          {item.eyebrow}
        </p>

        <p className="text-[15px] font-semibold leading-snug text-slate-800">
          {item.text}
        </p>

        <p className="mt-2 text-[12px] text-slate-400">
          {item.meta}
        </p>
      </div>
    </div>
  );
}

function MissedMatch({ progress }) {
  const opacity =
    smooth(progress, 0.05, 0.35) *
    (1 - smooth(progress, 0.7, 1));

  return (
    <div
      className="absolute left-1/2 top-[47%] z-20 w-full max-w-[520px] -translate-x-1/2 text-center"
      style={{
        opacity,
        transform: `
          translateX(-50%)
          translateY(${mix(30, 0, progress)}px)
        `,
      }}
    >
      <div className="mx-auto mb-7 flex w-max items-center gap-3">
        <span className="h-[1px] w-20 bg-slate-300" />

        <span className="text-[10px] font-bold uppercase tracking-[.18em] text-slate-400">
          nunca se encontraron
        </span>

        <span className="h-[1px] w-20 bg-slate-300" />
      </div>

      <p className="text-[15px] font-medium leading-relaxed text-slate-500">
        Operaciones que podrían cerrarse.
        <br />
        Clientes que siguen buscando.
      </p>

      <h3 className="mt-6 text-[clamp(2rem,3.7vw,3.8rem)] font-bold leading-[.98] tracking-[-.05em] text-[#07101f]">
        El problema no es que la oportunidad no exista.
      </h3>

      <p className="mt-2 text-[clamp(2rem,3.7vw,3.8rem)] font-bold leading-none tracking-[-.05em] text-[#2166c2]">
        Es encontrarla.
      </p>
    </div>
  );
}

function ProductPanel({ progress }) {
  return (
    <div
      className="absolute left-1/2 top-[46%] z-20 w-[min(860px,85vw)] -translate-x-1/2 -translate-y-1/2"
      style={{
        opacity: progress,
        transform: `
          translate(-50%, -50%)
          scale(${mix(0.92, 1, progress)})
        `,
      }}
    >
      <div
        className="
          overflow-hidden
          rounded-[30px]
          border
          border-white/10
          bg-[#121c2d]
          shadow-[0_50px_120px_rgba(15,23,42,.2)]
        "
      >
        {/* header */}
        <div className="flex items-center justify-between border-b border-white/[.07] px-7 py-5">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-[.18em] text-white/35">
              PERMUOK NETWORK
            </p>

            <p className="mt-1 text-[15px] font-semibold text-white">
              Oportunidades detectadas
            </p>
          </div>

          <span className="rounded-full bg-blue-500/10 px-3 py-1.5 text-[11px] font-semibold text-blue-300">
            Match encontrado
          </span>
        </div>

        {/* match */}
        <div className="grid gap-4 p-7 md:grid-cols-[1fr_auto_1fr] md:items-center">

          <MatchItem
            type="BÚSQUEDA"
            title="Departamento"
            details={[
              "3 ambientes",
              "Güemes",
              "Hasta USD 130.000",
            ]}
          />

          <div className="hidden flex-col items-center md:flex">
            <div className="h-10 w-[1px] bg-gradient-to-b from-transparent via-blue-400 to-transparent" />

            <div className="my-3 flex h-20 w-20 items-center justify-center rounded-full border border-blue-400/20 bg-blue-500/10 shadow-[0_0_45px_rgba(59,130,246,.15)]">
              <div className="text-center">
                <strong className="block text-[20px] font-bold text-white">
                  94%
                </strong>

                <span className="text-[9px] font-bold uppercase tracking-[.12em] text-blue-300">
                  match
                </span>
              </div>
            </div>

            <div className="h-10 w-[1px] bg-gradient-to-b from-blue-400 via-blue-400 to-transparent" />
          </div>

          <MatchItem
            type="PROPIEDAD"
            title="Departamento"
            details={[
              "3 ambientes",
              "Güemes",
              "USD 125.000",
            ]}
          />

        </div>
      </div>
    </div>
  );
}

function MatchItem({ type, title, details }) {
  return (
    <div className="rounded-[20px] border border-white/[.07] bg-white/[.035] p-5">
      <p className="text-[9px] font-bold tracking-[.18em] text-white/30">
        {type}
      </p>

      <h4 className="mt-3 text-xl font-semibold text-white">
        {title}
      </h4>

      <div className="mt-4 space-y-1.5">
        {details.map((detail) => (
          <p
            key={detail}
            className="text-[13px] text-white/45"
          >
            {detail}
          </p>
        ))}
      </div>
    </div>
  );
}

function Closing({ progress }) {
  const opacity = smooth(progress, 0.64, 0.94);

  return (
    <div
      className="absolute bottom-[7%] left-1/2 z-30 w-full max-w-[780px] -translate-x-1/2 text-center"
      style={{ opacity }}
    >
      <p className="text-[18px] font-medium leading-relaxed text-slate-700 md:text-[21px]">
        Permuok pone todas esas posibilidades a trabajar en una misma red inmobiliaria.
      </p>

      <p className="mt-3 text-[12px] font-bold uppercase tracking-[.15em] text-[#2166c2]">
        Una red profesional · Solo para inmobiliarias
      </p>
    </div>
  );
}

function clamp(value, min = 0, max = 1) {
  return Math.min(Math.max(value, min), max);
}

function smooth(value, start, end) {
  const x = clamp((value - start) / (end - start));
  return x * x * (3 - 2 * x);
}

function mix(start, end, progress) {
  return start + (end - start) * progress;
}