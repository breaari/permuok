import { Icon } from "../../icons/Index";
import { resolveStage } from "./AdminDetailHelpers";

function stageLabel(realEstate) {
  const stage = resolveStage(realEstate);

  if (stage === "incomplete") return "Incompleta";
  if (stage === "ready_for_review") return "Lista para revisión";
  if (stage === "initial_review") return "En revisión inicial";
  if (stage === "changes_pending") return "Cambios pendientes";
  if (stage === "approved") return "Aprobada";
  if (stage === "rejected") return "Rechazada";
  return "—";
}

function stageTone(realEstate) {
  const stage = resolveStage(realEstate);

  if (stage === "approved") return "bg-emerald-100 text-emerald-700";
  if (stage === "rejected") return "bg-rose-100 text-rose-700";
  if (stage === "initial_review") return "bg-amber-100 text-amber-700";
  if (stage === "changes_pending") return "bg-sky-100 text-sky-700";
  if (stage === "ready_for_review") return "bg-indigo-100 text-indigo-700";
  return "bg-slate-100 text-slate-700";
}

export default function AdminReviewContextCard({ realEstate, formatDate }) {
  const label = stageLabel(realEstate);
  const tone = stageTone(realEstate);

  return (
    <section className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-fit">
      <div className="p-6 border-b border-slate-100 flex items-center gap-2">
        <span className="text-primary">
          <Icon name="clipboardList" size={20} />
        </span>
        <h2 className="text-xl font-bold text-slate-900">Estado de revisión</h2>
      </div>

      <div className="p-6 space-y-4 text-sm">
        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Estado
          </span>
          <span
            className={`px-2 py-1 rounded text-[10px] font-extrabold uppercase tracking-widest ${tone}`}
          >
            {label}
          </span>
        </div>

        <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
            Solicitado
          </span>
          <span className="text-slate-900 font-bold">
            {formatDate(
              realEstate?.changes_requested_at ||
                realEstate?.review_requested_at ||
                realEstate?.created_at,
            )}
          </span>
        </div>

        {!!realEstate?.approved_at && (
          <div className="flex justify-between items-center py-2.5 border-b border-slate-100">
            <span className="text-slate-500 font-bold uppercase text-[10px] tracking-widest">
              Última aprobación
            </span>
            <span className="text-slate-900 font-bold">
              {formatDate(realEstate?.approved_at)}
            </span>
          </div>
        )}

        {resolveStage(realEstate) === "rejected" && !!realEstate?.validation_note && (
          <div className="pt-1 text-xs text-rose-700 bg-rose-50 border border-rose-100 rounded p-3">
            {realEstate.validation_note}
          </div>
        )}
      </div>
    </section>
  );
}