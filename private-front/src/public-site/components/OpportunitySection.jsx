import { useEffect, useMemo, useRef, useState } from "react";

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
    if (progress < 0.16) return 0;
    if (progress < 0.29) return 1;
    if (progress < 0.42) return 2;
    if (progress < 0.55) return 3;

    return 4;
  }, [progress]);

  return (
    <section
      ref={sectionRef}
      className="relative h-[165vh] bg-[var(--landing-bg,#f5f7fa)]"
    >
      <div className="sticky top-[96px] h-[calc(100vh-96px)] overflow-hidden">
        <div
          className="
      mx-auto
      flex
      h-full
      w-full
      max-w-[1320px]
      flex-col
      px-6
      pt-[8vh]
      lg:px-10
      lg:pt-[7vh]
    "
        >
          <div
            className="
              grid
              w-full
              items-center
              gap-14
              lg:grid-cols-[minmax(0,0.95fr)_minmax(420px,0.8fr)]
              lg:gap-24
            "
          >
            <LeftContent />

            <RightList progress={progress} activeIndex={activeIndex} />
          </div>

          <Conclusion />
        </div>
      </div>
    </section>
  );
}

function LeftContent() {
  return (
    <div className="max-w-[680px]">
      <p
        className="
          max-w-[610px]
          font-sans
          text-[clamp(1.15rem,1.55vw,1.4rem)]
          font-normal
          leading-[1.45]
          tracking-[-0.015em]
          text-slate-600
        "
      >
        Detrás de una permuta puede estar la operación que hoy no estás pudiendo
        cerrar.
      </p>

      <h2
        className="
          mt-7
          max-w-[650px]
          font-sans
          text-[clamp(2rem,3vw,3.15rem)]
          font-semibold
          leading-[1.08]
          tracking-[-0.04em]
          text-[#0c1628]
        "
      >
        Y esa oportunidad puede estar en{" "}
        <MarkerText>cualquier lado.</MarkerText>
      </h2>
    </div>
  );
}

function MarkerText({ children }) {
  return (
    <span className="relative inline-block whitespace-nowrap">
      <span
        aria-hidden="true"
        className="
          absolute
          -left-[2%]
          -right-[2%]
          bottom-[2px]
          h-[42%]
          -z-10
          -rotate-[0.7deg]
          bg-[#9fc5ff]
        "
      />

      {children}
    </span>
  );
}

function RightList({ progress, activeIndex }) {
  return (
    <div className="space-y-3.5">
      {items.map((item, index) => {
        let itemProgress = 1;

        if (index > 0) {
          const starts = [0, 0.045, 0.16, 0.275, 0.39];
          const itemStart = starts[index];
          const itemEnd = itemStart + 0.09;

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
                  ? "border-[#72bb61] bg-[#eef8e9] shadow-[0_14px_36px_rgba(79,164,72,0.11)]"
                  : "border-[#91c986] bg-white/75"
              }
            `}
            style={{
              opacity: itemProgress,
              transform: `translateY(${mix(12, 0, itemProgress)}px)`,
            }}
          >
            <p
              className={`
                font-sans
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

function Conclusion() {
  return (
    <div className="mx-auto mt-14 max-w-[1080px] text-center md:mt-16">
      <p
        className="
          font-display
          text-balance
          text-[clamp(1.8rem,3vw,3.15rem)]
          font-bold
          leading-[1.03]
          tracking-[-0.045em]
          text-[#07101f]
        "
      >
        Permuok no solo centraliza esta información. La conecta para detectar
        compatibilidades y abrir nuevas posibilidades de operación.
      </p>
    </div>
  );
}

function clamp(value, min = 0, max = 1) {
  return Math.min(Math.max(value, min), max);
}

function mix(start, end, progress) {
  return start + (end - start) * progress;
}
