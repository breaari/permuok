import { Icon } from "../../../../ui/icons/Index";

export default function AdminLicensesSection({
  licenses = [],
  sticky = false,
}) {
  return (
    <section
      className={`bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-fit ${
        sticky ? "sticky top-24" : ""
      }`}
    >
      <div className="p-6 border-b border-slate-100 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="text-primary">
            <Icon name="badge" size={20} />
          </span>
          <h2 className="text-xl font-bold text-slate-900">Matrículas</h2>
        </div>

        <span className="bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1 rounded-full uppercase">
          {licenses.length}
        </span>
      </div>

      <div className="p-6 space-y-4">
        {!licenses.length ? (
          <div className="text-sm text-slate-500">Sin matrículas cargadas.</div>
        ) : (
          licenses.map((lic, idx) => {
            const isPrimary = Number(lic?.is_primary) === 1;

            return (
              <div
                key={lic?.id ?? idx}
                className={
                  isPrimary
                    ? "p-4 rounded-lg border border-primary/30 bg-primary/[0.03]"
                    : "p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-all"
                }
              >
                <div className="flex items-center justify-between mb-3">
                  {isPrimary ? (
                    <span className="text-[10px] font-black bg-primary text-white px-2 py-0.5 rounded flex items-center gap-1">
                      <span className="opacity-90">★</span>
                      PRINCIPAL
                    </span>
                  ) : (
                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                      Provincia
                    </span>
                  )}

                  {!!lic?.file_url && (
                    <a
                      href={lic.file_url}
                      target="_blank"
                      rel="noreferrer"
                      className="text-slate-400 hover:text-primary transition-colors"
                      title="Ver archivo"
                    >
                      <Icon name="eye" size={18} />
                    </a>
                  )}
                </div>

                <div className="space-y-3">
                  <div>
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                      Provincia
                    </p>
                    <p className="text-sm font-bold text-slate-800">
                      {lic?.province_name || "—"}
                    </p>
                  </div>

                  <div>
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                      Número de matrícula
                    </p>
                    <p
                      className={
                        isPrimary
                          ? "text-xl font-black text-primary tracking-tight"
                          : "text-lg font-bold text-slate-900 tracking-tight"
                      }
                    >
                      {lic?.license_number || "—"}
                    </p>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>
    </section>
  );
}
