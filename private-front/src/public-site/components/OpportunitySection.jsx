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
    window.addEventListener("scroll", handleScroll, { passive: true });

    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  const activeIndex = useMemo(() => {
    const step = 1 / (items.length + 0.6);
    let index = -1;

    items.forEach((_, i) => {
      const threshold = 0.08 + i * step;
      if (progress >= threshold) index = i;
    });

    return index;
  }, [progress]);

  return (
    <section
      ref={sectionRef}
      className="relative h-[180vh] bg-[#f5f7fa]"
    >
      <div className="sticky top-0 flex h-screen items-center overflow-hidden">
        <div className="mx-auto w-full max-w-[1380px] px-6 lg:px-10">
          <div className="grid items-center gap-14 lg:grid-cols-[minmax(0,1fr)_480px] lg:gap-20">
            <LeftContent />

            <RightList progress={progress} activeIndex={activeIndex} />
          </div>
        </div>
      </div>
    </section>
  );
}

function LeftContent() {
  return (
    <div className="max-w-[760px]">
      <h2 className="text-balance text-[clamp(2.3rem,5vw,5.2rem)] font-bold leading-[0.95] tracking-[-0.06em] text-[#07101f]">
        Una oportunidad de permuta puede estar en cualquier lado.
      </h2>

      <div className="mt-10 max-w-[720px]">
        <h3 className="text-balance text-[clamp(1.8rem,3.5vw,3.4rem)] font-bold leading-[0.98] tracking-[-0.05em] text-[#07101f]">
          El problema no es que la oportunidad no exista.
        </h3>

        <p className="mt-2 text-balance text-[clamp(1.8rem,3.5vw,3.4rem)] font-bold leading-[0.98] tracking-[-0.05em] text-[#2166c2]">
          Es encontrarla.
        </p>
      </div>

      <div className="mt-10 max-w-[640px]">
        <p className="text-[18px] font-semibold leading-relaxed text-slate-700 md:text-[21px]">
          Permuok pone todas esas posibilidades a trabajar en una misma red
          inmobiliaria.
        </p>
      </div>
    </div>
  );
}

function RightList({ progress, activeIndex }) {
  return (
    <div className="relative">
      <div className="mb-5 flex items-center gap-3">
        <span className="h-[1px] w-10 bg-[#8acb88]" />
        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
          Puede aparecer...
        </p>
      </div>

      <div className="space-y-4">
        {items.map((item, index) => {
          const itemStart = 0.08 + index * 0.14;
          const itemEnd = itemStart + 0.12;
          const itemProgress = clamp((progress - itemStart) / (itemEnd - itemStart));

          const isVisible = itemProgress > 0;
          const isActive = index === activeIndex;

          return (
            <div
              key={item}
              className={`
                relative
                border
                px-5
                py-5
                transition-all
                duration-500
                md:px-6
                md:py-5
                ${
                  isActive
                    ? "border-[#72c16b] bg-[#eaf8e8] shadow-[0_14px_32px_rgba(114,193,107,0.18)]"
                    : "border-[#72c16b]/55 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]"
                }
              `}
              style={{
                opacity: itemProgress,
                transform: `
                  translateY(${mix(28, 0, itemProgress)}px)
                  scale(${mix(0.985, 1, itemProgress)})
                `,
              }}
            >
              <div className="flex items-start gap-4">
                <span
                  className={`
                    mt-[5px]
                    h-3
                    w-3
                    flex-shrink-0
                    border
                    ${
                      isActive
                        ? "border-[#4fa448] bg-[#4fa448]"
                        : "border-[#72c16b] bg-transparent"
                    }
                  `}
                />

                <p
                  className={`
                    text-[16px]
                    font-semibold
                    leading-snug
                    md:text-[18px]
                    ${
                      isVisible ? "text-slate-800" : "text-slate-400"
                    }
                  `}
                >
                  {item}
                </p>
              </div>

              {isActive && (
                <span className="absolute right-0 top-0 h-full w-[4px] bg-[#4fa448]" />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function clamp(value, min = 0, max = 1) {
  return Math.min(Math.max(value, min), max);
}

function mix(start, end, progress) {
  return start + (end - start) * progress;
}