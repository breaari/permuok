export default function DetailSection({
  title,
  description,
  children,
  noCard = false,
}) {
  if (noCard) {
    return (
      <section className="rounded-2xl border border-slate-200">
        {title && (
          <h3 className="mb-4 text-lg font-bold text-slate-900">
            {title}
          </h3>
        )}

        {children}
      </section>
    );
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      {title && (
        <h3 className="mb-1 text-lg font-bold text-slate-900">
          {title}
        </h3>
      )}

      {description && (
        <p className="mb-4 text-sm text-slate-500">{description}</p>
      )}

      {children}
    </section>
  );
}