import { Icon } from "../../../../ui/icons/Index";
import {
  formatAreaRange,
  formatUnitPrice,
  unitTypeLabel,
} from "../developmentDetail.helpers";

function SoftTag({ children }) {
  return (
    <span className="inline-flex rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-100">
      {children}
    </span>
  );
}

function UnitInfo({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-700 bg-slate-800/70 p-3">
      <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">
        {label}
      </p>
      <p className="mt-1 text-sm font-bold text-white">{value || "—"}</p>
    </div>
  );
}

export default function DevelopmentUnitTypesCard({ unitTypes = [] }) {
  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900 p-6 text-white shadow-sm">
      <div className="mb-6 flex items-start justify-between gap-4">
        <div>
          <p className="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-300">
            Unidades
          </p>

          <h2 className="mt-1 text-xl font-black tracking-tight text-white">
            Tipologías disponibles
          </h2>
        </div>

        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-emerald-300">
          <Icon name="building2" size={22} />
        </div>
      </div>

      {!unitTypes.length ? (
        <div className="rounded-xl border border-slate-800 bg-slate-800/60 p-4 text-sm font-semibold text-slate-300">
          No hay tipologías cargadas.
        </div>
      ) : (
        <div className="space-y-4">
          {unitTypes.map((item) => (
            <article
              key={item.id}
              className="rounded-2xl border border-slate-700 bg-slate-800/50 p-4"
            >
              <p className="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-300">
                {unitTypeLabel(item?.unit_type)}
              </p>

              <h3 className="mt-1 text-lg font-black text-white">
                {item?.label || "Sin nombre comercial"}
              </h3>

              <div className="mt-4 flex flex-wrap gap-2">
                {item?.rooms ? (
                  <SoftTag>{item.rooms} ambientes</SoftTag>
                ) : null}

                {item?.bedrooms ? (
                  <SoftTag>{item.bedrooms} dorm.</SoftTag>
                ) : null}

                {item?.bathrooms ? (
                  <SoftTag>{item.bathrooms} baños</SoftTag>
                ) : null}

                {item?.garages ? (
                  <SoftTag>{item.garages} coch.</SoftTag>
                ) : null}

                {item?.available_units ? (
                  <SoftTag>{item.available_units} disponibles</SoftTag>
                ) : null}
              </div>

              <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <UnitInfo label="Superficie" value={formatAreaRange(item)} />
                <UnitInfo label="Precio" value={formatUnitPrice(item)} />
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}