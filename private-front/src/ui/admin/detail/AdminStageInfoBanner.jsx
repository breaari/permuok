import { resolveStage } from "./AdminDetailHelpers";

export default function AdminStageInfoBanner({ realEstate }) {
  const stage = resolveStage(realEstate);

  if (stage === "incomplete") {
    return (
      <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
        Este perfil todavía está incompleto. Faltan datos obligatorios o una
        matrícula para poder enviarlo a revisión.
      </div>
    );
  }

  if (stage === "ready_for_review") {
    return (
      <div className="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800">
        El perfil está completo y con matrícula cargada, pero todavía no fue
        enviado a revisión por la inmobiliaria.
      </div>
    );
  }

  return null;
}