import { useEffect, useRef, useState } from "react";

const items = [
  {
    text: "En un grupo de Facebook.",
    x: -30,
    y: -18,
    r: -6,
    w: "w-[240px] md:w-[260px]",
  },
  {
    text: "En un grupo de WhatsApp.",
    x: 30,
    y: -12,
    r: 5,
    w: "w-[250px] md:w-[270px]",
  },
  {
    text: "En una publicación de otra inmobiliaria.",
    x: -24,
    y: 16,
    r: 4,
    w: "w-[290px] md:w-[340px]",
  },
  {
    text: "En una historia de Instagram que viste una vez y después perdiste.",
    x: 24,
    y: 18,
    r: -4,
    w: "w-[300px] md:w-[360px]",
  },
  {
    text: "En una conversación entre colegas.",
    x: 0,
    y: -28,
    r: 2,
    w: "w-[270px] md:w-[320px]",
  },
  {
    text: "O en un simple boca a boca.",
    x: 0,
    y: 34,
    r: 0,
    w: "w-[240px] md:w-[280px]",
  },
];

export default function OpportunitySection() {
  const ref = useRef(null);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      if (!ref.current) return;

      const rect = ref.current.getBoundingClientRect();
      const sectionHeight = ref.current.offsetHeight;
      const viewportHeight = window.innerHeight;

      const maxScroll = sectionHeight - viewportHeight;
      const current = Math.min(Math.max(-rect.top, 0), maxScroll);
      const next = maxScroll > 0 ? current / maxScroll : 0;

      setProgress(next);
    };

    handleScroll();
    window.addEventListener("scroll", handleScroll, { passive: true });

    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  const titleOpacity = inverseRange(progress, 0.52, 0.78);
  const chipsOpacity =
    range(progress, 0.08, 0.26) * inverseRange(progress, 0.82, 1);
  const closingOpacity = range(progress, 0.52, 0.76);

  return (
    <section ref={ref} className="relative bg-background-light">
      <div className="mx-auto max-w-[1280px] px-6 pb-20 pt-6 md:px-10 md:pb-28 md:pt-10">
        <div className="relative h-[220vh]">
          <div className="sticky top-24 h-[calc(100vh-7rem)] overflow-hidden rounded-[32px]">
            <div className="absolute inset-0 rounded-[32px] bg-[radial-gradient(circle_at_top,rgba(37,99,235,0.05),transparent_45%)]" />

            {/* TITULO + BAJADA */}
            <div
              className="absolute inset-x-0 top-0 z-10 mx-auto flex max-w-[980px] flex-col items-center px-4 pt-2 text-center md:pt-6"
              style={{
                opacity: titleOpacity,
                transform: `translateY(${lerp(0, -24, progress)}px)`,
              }}
            >
              <h2 className="max-w-[900px] text-balance text-[clamp(2rem,4.2vw,4.3rem)] font-black leading-[0.98] tracking-[-0.05em] text-slate-950">
                Una oportunidad de permuta puede estar en cualquier lado.
              </h2>

              <p className="mt-5 max-w-[760px] text-balance text-[15px] font-medium leading-relaxed text-slate-500 md:text-[18px]">
                Puede aparecer en una publicación, en un chat o en una
                conversación. El problema es que hoy esas oportunidades están
                dispersas.
              </p>
            </div>

            {/* CHIPS */}
            <div className="absolute inset-0" style={{ opacity: chipsOpacity }}>
              <div className="relative mx-auto h-full max-w-[1100px]">
                {items.map((item, index) => {
                  const entry = range(
                    progress,
                    0.1 + index * 0.04,
                    0.24 + index * 0.04,
                  );
                  const settle = range(progress, 0.28, 0.54);

                  const x = item.x * (1 - settle * 0.18);
                  const y = item.y * (1 - settle * 0.18);

                  return (
                    <div
                      key={item.text}
                      className={`absolute left-1/2 top-1/2 ${item.w} -translate-x-1/2 -translate-y-1/2 rounded-[24px] border border-white/80 bg-white/88 px-5 py-4 shadow-[0_18px_45px_rgba(15,23,42,0.06)] backdrop-blur-md md:px-6 md:py-5`}
                      style={{
                        opacity: entry,
                        transform: `
                          translate(-50%, -50%)
                          translate(${x}vw, ${y}vh)
                          rotate(${item.r}deg)
                          scale(${lerp(0.92, 1, entry)})
                        `,
                      }}
                    >
                      <p className="text-[15px] font-semibold leading-snug text-slate-700 md:text-[17px]">
                        {item.text}
                      </p>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* CIERRE */}
            <div
              className="absolute inset-x-0 bottom-0 z-20 mx-auto flex max-w-[920px] flex-col items-center px-4 pb-6 text-center md:pb-10"
              style={{
                opacity: closingOpacity,
                transform: `translateY(${lerp(24, 0, closingOpacity)}px)`,
              }}
            >
              <p className="max-w-[760px] text-balance text-[15px] font-medium leading-relaxed text-slate-500 md:text-[18px]">
                Mientras esas oportunidades quedan dispersas, también se pierden
                operaciones y clientes siguen esperando encontrar lo que buscan.
              </p>

              <h3 className="mt-6 max-w-[900px] text-balance text-[clamp(1.9rem,4vw,4rem)] font-black leading-[0.98] tracking-[-0.05em] text-slate-950">
                El problema no es que la oportunidad no exista.
              </h3>

              <p className="mt-2 text-balance text-[clamp(1.9rem,4vw,4rem)] font-black leading-[0.98] tracking-[-0.05em] text-primary">
                Es encontrarla.
              </p>

              <p className="mt-5 max-w-[760px] text-balance text-[16px] font-semibold leading-relaxed text-slate-600 md:text-[20px]">
                Permuok pone todas esas posibilidades a trabajar en una misma
                red inmobiliaria.
              </p>

              <div className="mt-5 inline-flex rounded-full border border-primary/15 bg-primary/8 px-4 py-2">
                <span className="text-[12px] font-bold uppercase tracking-[0.14em] text-primary md:text-[13px]">
                  Una red profesional. Solo para inmobiliarias.
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

/* helpers */
function clamp(value, min = 0, max = 1) {
  return Math.min(Math.max(value, min), max);
}

function range(value, start, end) {
  return clamp((value - start) / (end - start));
}

function inverseRange(value, start, end) {
  return 1 - range(value, start, end);
}

function lerp(start, end, progress) {
  return start + (end - start) * progress;
}
