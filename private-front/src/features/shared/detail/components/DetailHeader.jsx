import { Icon } from "../../../../ui/icons/Index";

export default function DetailHeader({
  title,
  reference,
  onBack,
  backLabel = "Volver",
}) {
  return (
    <>
      <header className="border-b border-slate-200/70 bg-white px-4 py-6 sm:px-6 lg:px-10">
        <div className="mx-auto max-w-7xl">
          <button
            type="button"
            onClick={onBack}
            className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-primary transition-colors hover:text-emerald-700"
          >
            <Icon name="arrowLeft" size={16} />
            {backLabel}
          </button>
        </div>
      </header>

      <section className="bg-slate-900 px-4 py-12 sm:px-6 lg:px-10">
        <div className="mx-auto max-w-7xl">
          <p className="mb-4 text-xs font-semibold uppercase tracking-widest text-emerald-300">
            Ref #{reference || "—"}
          </p>

          <h1 className="max-w-4xl text-3xl font-black leading-tight tracking-tighter text-white md:text-5xl">
            {title || "Sin título"}
          </h1>
        </div>
      </section>
    </>
  );
}