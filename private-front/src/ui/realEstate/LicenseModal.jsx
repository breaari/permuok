import { useEffect, useMemo, useState } from "react";
import { api, unwrap, getErrorMessage } from "../../api/http";
import { Icon } from "../icons/Index";

export function LicenseModal({
  open,
  disabled,
  onClose,
  onSave,
  errorMessage,
}) {
  const [busy, setBusy] = useState(false);
  const [saveErr, setSaveErr] = useState("");
  const [provLoading, setProvLoading] = useState(false);
  const [provErr, setProvErr] = useState("");
  const [provinces, setProvinces] = useState([]);

  const [form, setForm] = useState({
    license_number: "",
    province_id: "",
    is_primary: true,
  });

  useEffect(() => {
    if (!open) return;

    setSaveErr("");
    setProvErr("");

    if (provinces.length > 0) return;

    let alive = true;
    setProvLoading(true);

    api
      .get("/provinces")
      .then(unwrap)
      .then((data) => {
        if (!alive) return;
        const items = Array.isArray(data?.items)
          ? data.items
          : Array.isArray(data)
            ? data
            : [];
        setProvinces(items);
      })
      .catch((e) => {
        if (!alive) return;
        setProvErr(
          getErrorMessage
            ? getErrorMessage(e, "No se pudieron cargar las provincias.")
            : "No se pudieron cargar las provincias."
        );
      })
      .finally(() => {
        if (!alive) return;
        setProvLoading(false);
      });

    return () => {
      alive = false;
    };
  }, [open, provinces.length]);

  const canSubmit = useMemo(() => {
    const lnOk = String(form.license_number || "").trim().length > 0;
    const provOk = String(form.province_id || "").trim().length > 0;
    return !disabled && !busy && lnOk && provOk;
  }, [disabled, busy, form]);

  if (!open) return null;

  async function handleSubmit(e) {
    e.preventDefault();
    e.stopPropagation();

    if (!canSubmit) return;

    if (typeof onSave !== "function") {
      setSaveErr("Error interno: onSave no fue provisto al modal.");
      return;
    }

    setSaveErr("");
    setBusy(true);

    const payload = {
      license_number: form.license_number.trim(),
      province_id: Number(form.province_id),
      is_primary: !!form.is_primary,
    };

    try {
      const ok = await onSave(payload);

      if (ok) {
        setForm({
          license_number: "",
          province_id: "",
          is_primary: false,
        });

        setTimeout(() => {
          onClose?.();
        }, 0);
      } else {
        setSaveErr("No se pudo guardar la matrícula. Revisá los datos.");
      }
    } catch (e2) {
      setSaveErr(
        getErrorMessage
          ? getErrorMessage(e2, "No se pudo guardar la matrícula.")
          : "No se pudo guardar la matrícula."
      );
    } finally {
      setBusy(false);
    }
  }

  function handleSafeClose() {
    if (busy) return;
    onClose?.();
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
      <button
        type="button"
        aria-label="Cerrar modal"
        className="absolute inset-0 cursor-default"
        onClick={handleSafeClose}
      />

      <div
        className="relative z-10 w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden border border-slate-200"
        onClick={(e) => e.stopPropagation()}
        onMouseDown={(e) => e.stopPropagation()}
      >
        <div className="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
          <h2 className="text-xl font-bold text-slate-900 flex items-center gap-2">
            <Icon name="badge" size={20} className="text-primary" />
            Nueva Matrícula
          </h2>

          <button
            type="button"
            onClick={handleSafeClose}
            className="text-slate-400 hover:text-slate-600 transition-colors"
            aria-label="Cerrar"
          >
            <span className="text-xl leading-none">×</span>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {!!errorMessage && (
            <div className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
              {errorMessage}
            </div>
          )}

          {!!saveErr && (
            <div className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
              {saveErr}
            </div>
          )}

          <div className="flex flex-col gap-2">
            <label className="text-sm font-semibold text-slate-700">
              N° de Matrícula
            </label>

            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                <Icon name="badge" size={18} />
              </span>

              <input
                value={form.license_number}
                onChange={(e) =>
                  setForm((s) => ({ ...s, license_number: e.target.value }))
                }
                placeholder="Ej: 123456"
                className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
              />
            </div>
          </div>

          <div className="flex flex-col gap-2">
            <label className="text-sm font-semibold text-slate-700">
              Provincia / Jurisdicción
            </label>

            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                <Icon name="globe" size={18} />
              </span>

              <select
                value={form.province_id}
                onChange={(e) =>
                  setForm((s) => ({ ...s, province_id: e.target.value }))
                }
                disabled={provLoading}
                className="w-full pl-11 pr-10 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none appearance-none transition-all disabled:opacity-60"
              >
                <option value="">
                  {provLoading
                    ? "Cargando provincias..."
                    : "Seleccione una provincia"}
                </option>
                {provinces.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>

              <span className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <Icon name="chevronRight" size={18} className="rotate-90" />
              </span>
            </div>

            {!!provErr && <p className="text-xs text-rose-600">{provErr}</p>}
          </div>

          <div className="flex items-center justify-between p-4 bg-primary/5 rounded-lg border border-primary/20">
            <div className="flex flex-col">
              <span className="text-sm font-bold text-slate-900">
                Matrícula Principal
              </span>
              <span className="text-xs text-slate-500">
                Se mostrará como destacada en su perfil público
              </span>
            </div>

            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={form.is_primary}
                onChange={(e) =>
                  setForm((s) => ({ ...s, is_primary: e.target.checked }))
                }
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-slate-300 rounded-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full" />
            </label>
          </div>

          <div className="flex flex-col sm:flex-row gap-3 pt-2">
            <button
              type="button"
              disabled={busy}
              onClick={handleSafeClose}
              className="flex-1 px-6 py-3 border border-slate-200 text-slate-600 font-bold rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-60"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={!canSubmit}
              className="flex-1 px-6 py-3 bg-primary text-white font-bold rounded-lg hover:bg-primary/90 shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2 disabled:opacity-60"
            >
              <Icon name="checkCircle" size={16} />
              {busy ? "Guardando..." : "Agregar Matrícula"}
            </button>
          </div>
        </form>

        <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 text-center">
          <p className="text-xs text-slate-400">
            Al agregar una matrícula, declara que los datos son verídicos y
            están vigentes ante el colegio correspondiente.
          </p>
        </div>
      </div>
    </div>
  );
}