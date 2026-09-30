// OpportunityNetworkSection.jsx

import { useEffect, useMemo, useRef, useState } from "react";

const channels = [
  {
    id: "facebook",
    label: "Facebook",
    detail: "Grupo de permutas",
    x: 15,
    y: 25,
    rotate: -5,
  },
  {
    id: "whatsapp",
    label: "WhatsApp",
    detail: "Mensaje en un grupo",
    x: 75,
    y: 22,
    rotate: 4,
  },
  {
    id: "inmobiliaria",
    label: "Otra inmobiliaria",
    detail: "Publicación de una propiedad",
    x: 18,
    y: 66,
    rotate: 4,
  },
  {
    id: "instagram",
    label: "Instagram",
    detail: "Historia que viste una vez",
    x: 78,
    y: 64,
    rotate: -4,
  },
  {
    id: "colegas",
    label: "Entre colegas",
    detail: "Una conversación",
    x: 48,
    y: 18,
    rotate: 2,
  },
  {
    id: "boca",
    label: "Boca a boca",
    detail: "Una oportunidad que nunca se publicó",
    x: 50,
    y: 76,
    rotate: -2,
  },
];

export default function OpportunitySection() {
  const sectionRef = useRef(null);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const onScroll = () => {
      if (!sectionRef.current) return;

      const rect = sectionRef.current.getBoundingClientRect();
      const height = sectionRef.current.offsetHeight;
      const viewport = window.innerHeight;

      const max = Math.max(height - viewport, 1);
      const current = Math.min(Math.max(-rect.top, 0), max);

      setProgress(current / max);
    };

    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });

    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <section
      ref={sectionRef}
      className="relative h-[185vh] bg-[#f7f9fc]"
    >
      <div className="sticky top-0 h-screen overflow-hidden">
        <div className="mx-auto h-full max-w-[1440px] px-6 lg:px-10">
          <Scene progress={progress} />
        </div>
      </div>
    </section>
  );
}

function Scene({ progress }) {
  const scatter = range(progress, 0, 0.28);
  const problem = range(progress, 0.25, 0.55);
  const network = range(progress, 0.55, 0.9);

  return (
    <div className="relative h-full w-full">
      <Header progress={progress} />

      <NetworkLines progress={network} />

      {channels.map((channel, index) => (
        <ChannelCard
          key={channel.id}
          channel={channel}
          index={index}
          scatter={scatter}
          network={network}
        />
      ))}

      <CenterCore progress={network} />

      <ProblemMessage progress={problem} />

      <FinalMessage progress={network} />
    </div>
  );
}

function Header({ progress }) {
  const opacity = inverseRange(progress, 0.32, 0.52);

  return (
    <div
      className="absolute left-1/2 top-[12%] z-20 w-full max-w-[820px] -translate-x-1/2 text-center"
      style={{ opacity }}
    >
      <p className="mb-3 text-[12px] font-bold uppercase tracking-[0.18em] text-slate-400">
        Oportunidades dispersas
      </p>

      <h2 className="text-balance text-[clamp(2rem,4vw,4rem)] font-black leading-[0.98] tracking-[-0.05em] text-slate-950">
        Una oportunidad de permuta puede estar en cualquier lado.
      </h2>
    </div>
  );
}

function ChannelCard({ channel, index, scatter, network }) {
  const entry = range(scatter, index * 0.07, 0.45 + index * 0.05);

  const centerX = 50;
  const centerY = 52;

  const x = lerp(channel.x, centerX, network * 0.72);
  const y = lerp(channel.y, centerY, network * 0.72);

  const scale = lerp(0.94, 1, entry);

  const float =
    Math.sin((index + 1) * 1.7 + scatter * Math.PI * 2) * 4 * (1 - network);

  return (
    <div
      className="absolute z-10 -translate-x-1/2 -translate-y-1/2"
      style={{
        left: `${x}%`,
        top: `${y}%`,
        opacity: entry,
        transform: `
          translate(-50%, -50%)
          translateY(${float}px)
          rotate(${lerp(channel.rotate, 0, network)}deg)
          scale(${scale})
        `,
      }}
    >
      <div className="min-w-[190px] rounded-[22px] border border-white/80 bg-white/90 px-5 py-4 shadow-[0_22px_50px_rgba(15,23,42,.08)] backdrop-blur-xl md:min-w-[230px]">
        <div className="mb-3 flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full bg-blue-500" />
          <span className="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
            {channel.label}
          </span>
        </div>

        <p className="max-w-[220px] text-[15px] font-semibold leading-snug text-slate-800 md:text-[16px]">
          {channel.detail}
        </p>
      </div>
    </div>
  );
}

function ProblemMessage({ progress }) {
  const opacity =
    range(progress, 0.18, 0.5) *
    inverseRange(progress, 0.62, 0.88);

  return (
    <div
      className="absolute left-1/2 top-1/2 z-30 w-full max-w-[680px] -translate-x-1/2 -translate-y-1/2 text-center"
      style={{
        opacity,
        transform: `
          translate(-50%, -50%)
          translateY(${lerp(30, 0, progress)}px)
        `,
      }}
    >
      <p className="mb-4 text-[15px] font-medium leading-relaxed text-slate-500 md:text-[18px]">
        Operaciones que podrían cerrarse.
        <br />
        Clientes que siguen esperando.
      </p>

      <h3 className="text-balance text-[clamp(2rem,3.8vw,3.7rem)] font-black leading-[1] tracking-[-0.045em] text-slate-950">
        El problema no es que la oportunidad no exista.
      </h3>

      <p className="mt-2 text-[clamp(2rem,3.8vw,3.7rem)] font-black leading-[1] tracking-[-0.045em] text-blue-600">
        Es encontrarla.
      </p>
    </div>
  );
}

function CenterCore({ progress }) {
  return (
    <div
      className="absolute left-1/2 top-[52%] z-20 -translate-x-1/2 -translate-y-1/2"
      style={{
        opacity: range(progress, 0.12, 0.55),
        transform: `
          translate(-50%, -50%)
          scale(${lerp(0.7, 1, progress)})
        `,
      }}
    >
      <div className="relative flex h-28 w-28 items-center justify-center rounded-full border border-blue-500/20 bg-white shadow-[0_0_80px_rgba(37,99,235,.16)]">
        <div className="absolute inset-3 rounded-full bg-blue-600/5" />

        <span className="relative text-[14px] font-black tracking-[-0.02em] text-slate-900">
          permuok
        </span>
      </div>
    </div>
  );
}

function NetworkLines({ progress }) {
  const paths = useMemo(
    () => [
      { x1: 15, y1: 25 },
      { x1: 75, y1: 22 },
      { x1: 18, y1: 66 },
      { x1: 78, y1: 64 },
      { x1: 48, y1: 18 },
      { x1: 50, y1: 76 },
    ],
    []
  );

  return (
    <svg
      className="pointer-events-none absolute inset-0 z-[5] h-full w-full"
      viewBox="0 0 100 100"
      preserveAspectRatio="none"
      style={{ opacity: progress }}
    >
      {paths.map((p, index) => (
        <path
          key={index}
          d={`M ${p.x1} ${p.y1} Q 50 ${p.y1} 50 52`}
          fill="none"
          stroke="rgba(37,99,235,.22)"
          strokeWidth="0.22"
          strokeDasharray="1.2 1.2"
          style={{
            pathLength: progress,
          }}
        />
      ))}
    </svg>
  );
}

function FinalMessage({ progress }) {
  const opacity = range(progress, 0.45, 0.88);

  return (
    <div
      className="absolute bottom-[8%] left-1/2 z-30 w-full max-w-[820px] -translate-x-1/2 text-center"
      style={{
        opacity,
        transform: `
          translateX(-50%)
          translateY(${lerp(28, 0, opacity)}px)
        `,
      }}
    >
      <p className="text-balance text-[18px] font-semibold leading-relaxed text-slate-700 md:text-[22px]">
        Permuok pone todas esas posibilidades a trabajar en una misma red
        inmobiliaria.
      </p>

      <div className="mt-4 inline-flex items-center gap-2 rounded-full border border-blue-500/15 bg-blue-500/[0.06] px-4 py-2">
        <span className="h-1.5 w-1.5 rounded-full bg-blue-500" />

        <span className="text-[11px] font-bold uppercase tracking-[0.15em] text-blue-600 md:text-[12px]">
          Una red profesional. Solo para inmobiliarias.
        </span>
      </div>
    </div>
  );
}

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