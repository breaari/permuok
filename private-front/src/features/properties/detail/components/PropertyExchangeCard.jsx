import { Icon } from "../../../../ui/icons/Index";
import {
  exchangeModeLabel,
  joinLocation,
  normalizeRequirementType,
  propertyTypeLabel,
} from "../propertyDetail.helpers";

function SoftTag({ children, dark = false }) {
  return (
    <span
      className={
        dark
          ? "inline-flex rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90"
          : "inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"
      }
    >
      {children}
    </span>
  );
}

function isTrue(value) {
  return value === true || value === 1 || value === "1";
}

function getSearchModeLabel(requirements) {
  if (!requirements) return "Sin criterios cargados";

  if (requirements.criteria_mode === "criteria") {
    return "Busca con criterios específicos";
  }

  return "Abierto a propuestas";
}

export default function PropertyExchangeCard({
  requirements,
  requirementTypes = [],
  requirementLocations = [],
}) {
  const acceptedModes = requirements
    ? [
        isTrue(requirements.accepts_open_proposals)
          ? "Abierto a propuestas"
          : null,
        isTrue(requirements.accepts_total_swap) ? "Permuta total" : null,
        isTrue(requirements.accepts_swap_plus_cash)
          ? "Permuta + diferencia"
          : null,
        isTrue(requirements.accepts_multiple_swap)
          ? "Permuta múltiple"
          : null,
        isTrue(requirements.accepts_cash_only) ? "Acepta dinero" : null,
      ].filter(Boolean)
    : [];

  const hasRequirementNotes = !!String(requirements?.notes || "").trim();

  return (
    <div className="relative overflow-hidden rounded-2xl bg-slate-900 p-8 text-white shadow-2xl">
      <div className="relative z-10 space-y-8">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-bold tracking-tight">
            Criterios de Permuta
          </h2>
          <Icon name="refresh" size={28} className="text-emerald-300" />
        </div>

        {!requirements ? (
          <div className="text-sm text-slate-300">
            No hay criterios cargados.
          </div>
        ) : (
          <>
            <div className="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur-md">
              <p className="mb-1 text-[10px] font-bold uppercase tracking-widest text-white/60">
                Modalidad de búsqueda
              </p>
              <p className="text-lg font-bold text-white">
                {getSearchModeLabel(requirements)}
              </p>
            </div>

            {acceptedModes.length > 0 ? (
              <div className="space-y-4">
                <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                  Opciones de flexibilidad
                </p>

                <div className="flex flex-wrap gap-2">
                  {acceptedModes.map((mode) => (
                    <SoftTag key={mode} dark>
                      {mode}
                    </SoftTag>
                  ))}
                </div>
              </div>
            ) : null}

            {(requirementTypes.length > 0 ||
              requirementLocations.length > 0) && (
              <div className="space-y-4">
                <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                  Interés específico
                </p>

                <div className="flex flex-wrap gap-2">
                  {requirementTypes.length > 0 ? (
                    <SoftTag dark>
                      Tipos deseados:{" "}
                      {requirementTypes
                        .map((type) =>
                          propertyTypeLabel(normalizeRequirementType(type))
                        )
                        .join(", ")}
                    </SoftTag>
                  ) : null}

                  {requirementLocations.length > 0 ? (
                    <SoftTag dark>
                      Ubicaciones deseadas:{" "}
                      {requirementLocations
                        .map((loc) =>
                          joinLocation([
                            loc?.city,
                            loc?.zone,
                            loc?.province,
                            loc?.country,
                          ])
                        )
                        .filter(Boolean)
                        .join(" · ")}
                    </SoftTag>
                  ) : null}
                </div>
              </div>
            )}

            {hasRequirementNotes ? (
              <div className="border-t border-white/10 pt-4">
                <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-white/60">
                  Notas del propietario
                </p>
                <p className="text-sm italic text-white/70">
                  {requirements.notes}
                </p>
              </div>
            ) : null}
          </>
        )}
      </div>

      <div className="absolute -bottom-20 -right-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-[80px]" />
    </div>
  );
}