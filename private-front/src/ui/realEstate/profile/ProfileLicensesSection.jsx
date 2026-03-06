// ui/realEstate/profile/ProfileLicensesSection.jsx
import { Icon } from "../../icons/Index";

export function ProfileLicensesSection({
  licenses,
  isReview,
  hasRealEstate,
  onOpenModal,
}) {
  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 className="text-xl font-bold">Matrículas / Licencias</h2>

        <button
          type="button"
          disabled={isReview || !hasRealEstate}
          onClick={() => {
            if (!hasRealEstate) return;
            onOpenModal();
          }}
          className="hidden md:flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg font-bold text-sm hover:bg-primary/90 transition-colors disabled:opacity-60"
        >
          <span>+</span>
          Agregar Matrícula
        </button>
      </div>

      {!hasRealEstate ? (
        <div className="p-12 text-center">
          <p className="text-lg font-bold">Primero completá tu perfil</p>
          <p className="text-slate-500 text-sm mt-1">
            Guardá los datos de la inmobiliaria para poder cargar matrículas.
          </p>
        </div>
      ) : licenses.length === 0 ? (
        <div className="p-12 flex flex-col items-center justify-center text-center space-y-4">
          <div className="size-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
            <Icon name="badge" size={28} />
          </div>

          <div className="max-w-xs">
            <p className="text-lg font-bold">No has cargado matrículas</p>
            <p className="text-slate-500 text-sm mt-1">
              Debes cargar al menos una matrícula profesional vigente para poder
              validar tu cuenta.
            </p>
          </div>

          <button
            type="button"
            disabled={isReview || !hasRealEstate}
            onClick={() => {
              if (!hasRealEstate) return;
              onOpenModal();
            }}
            className="md:hidden flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-lg font-bold text-sm disabled:opacity-60"
          >
            <span>+</span>
            Agregar Matrícula
          </button>
        </div>
      ) : (
        <div className="p-6 space-y-3">
          {licenses.map((l) => (
            <div
              key={l.id}
              className="rounded-xl border border-slate-200 p-4 flex items-start justify-between gap-4"
            >
              <div className="min-w-0">
                <p className="font-bold text-slate-900 truncate">
                  {l.license_number}{" "}
                  {Number(l.is_primary) === 1 && (
                    <span className="ml-2 text-[10px] font-black bg-primary text-white px-2 py-0.5 rounded">
                      PRINCIPAL
                    </span>
                  )}
                </p>

                <p className="text-sm text-slate-500">
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