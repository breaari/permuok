import { Icon } from "../../icons/Index";

export function ProfileLicensesSection({
  licenses,
  isReview,
  isRejected,
  isActiveChangesPending,
  hasRealEstate,
  onOpenModal,
}) {
  const isLocked = isReview;
  const canManageLicenses = !isLocked && hasRealEstate;

  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-4 md:p-6 border-b border-slate-100">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-lg md:text-xl font-bold">
              Matrículas / Licencias
            </h2>

            {isActiveChangesPending ? (
              <p className="text-xs text-amber-700 mt-1">
                Los cambios en matrículas quedarán sujetos a revisión.
              </p>
            ) : isRejected ? (
              <p className="text-xs text-rose-700 mt-1">
                Revisá y corregí tus matrículas antes de volver a enviar el perfil.
              </p>
            ) : isLocked ? (
              <p className="text-xs text-slate-500 mt-1">
                No podés editar las matrículas mientras el perfil está en revisión.
              </p>
            ) : null}
          </div>

          <button
            type="button"
            disabled={!canManageLicenses}
            onClick={() => {
              if (!canManageLicenses) return;
              onOpenModal();
            }}
            className="w-full md:w-auto inline-flex items-center justify-center gap-2 px-4 py-3 md:py-2 bg-primary text-white rounded-lg font-bold text-sm hover:bg-primary/90 transition-colors disabled:opacity-60"
          >
            <span>+</span>
            Agregar Matrícula
          </button>
        </div>
      </div>

      {!hasRealEstate ? (
        <div className="p-8 md:p-12 text-center">
          <p className="text-base md:text-lg font-bold">Primero completá tu perfil</p>
          <p className="text-slate-500 text-sm mt-1">
            Guardá los datos de la inmobiliaria para poder cargar matrículas.
          </p>
        </div>
      ) : licenses.length === 0 ? (
        <div className="p-8 md:p-12 flex flex-col items-center justify-center text-center space-y-4">
          <div className="size-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
            <Icon name="badge" size={28} />
          </div>

          <div className="max-w-xs">
            <p className="text-base md:text-lg font-bold">
              No has cargado matrículas
            </p>
            <p className="text-slate-500 text-sm mt-1">
              Debés cargar al menos una matrícula profesional vigente para poder
              validar tu cuenta.
            </p>
          </div>
        </div>
      ) : (
        <div className="p-4 md:p-6 space-y-3">
          {licenses.map((l) => (
            <div
              key={l.id}
              className="rounded-xl border border-slate-200 p-4 flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
            >
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-bold text-slate-900 break-words">
                    {l.license_number}
                  </p>

                  {Number(l.is_primary) === 1 && (
                    <span className="text-[10px] font-black bg-primary text-white px-2 py-0.5 rounded whitespace-nowrap">
                      PRINCIPAL
                    </span>
                  )}
                </div>

                <p className="text-sm text-slate-500 mt-1 break-words">
                  {l.province ?? l.province_name ?? "—"}
                </p>
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}