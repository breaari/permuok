import { useEffect, useMemo, useState } from "react";
import { api, unwrap } from "../api/http";
const TABS = [
  { key: "pending", label: "Pendientes", path: "/admin/real-estates/pending" },
  { key: "approved", label: "Aprobadas", path: "/admin/real-estates/approved" },
  { key: "rejected", label: "Rechazadas", path: "/admin/real-estates/rejected" },
];

export default function AdminRealEstates() {
  const [tab, setTab] = useState("pending");
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [busyId, setBusyId] = useState(null);
  const [rejectingId, setRejectingId] = useState(null);
  const [rejectNote, setRejectNote] = useState("");

  if (loading) return null;

  const isAdmin = Number(user?.role) === 1;
  if (!isAdmin) return <Navigate to="/" replace />;

  const currentTab = useMemo(() => TABS.find(t => t.key === tab), [tab]);

  async function load() {
    setErr("");
    setLoading(true);
    try {
      const res = await api.get(currentTab.path);
      const data = unwrap(res);
      setItems(data?.items ?? []);
    } catch (e) {
      setErr(e?.data?.message || e?.message || "Error al cargar");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, [tab]);

  async function approve(id) {
    setBusyId(id);
    try {
      await api.post("/admin/real-estates/approve", {
        real_estate_id: id,
        action: "approve",
      });
      await load();
    } finally {
      setBusyId(null);
    }
  }

  async function reject(id) {
    if (!rejectNote.trim()) return;

    setBusyId(id);
    try {
      await api.post("/admin/real-estates/approve", {
        real_estate_id: id,
        action: "reject",
        note: rejectNote.trim(),
      });
      setRejectingId(null);
      setRejectNote("");
      await load();
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Inmobiliarias</h1>
      </div>

      <div className="flex gap-2">
        {TABS.map(t => (
          <button
            key={t.key}
            className={`px-3 py-2 rounded-lg border text-sm ${
              tab === t.key ? "bg-black text-white" : ""
            }`}
            onClick={() => setTab(t.key)}
          >
            {t.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div>Cargando...</div>
      ) : items.length === 0 ? (
        <div className="text-sm text-gray-600">Sin resultados</div>
      ) : (
        <div className="space-y-4">
          {items.map(re => (
            <div key={re.id} className="border rounded-2xl p-4 space-y-2">
              <div className="font-semibold">{re.name}</div>
              <div className="text-sm text-gray-600">{re.email}</div>

              {tab === "pending" && (
                <div className="flex gap-2">
                  <button
                    onClick={() => approve(re.id)}
                    className="bg-green-600 text-white px-3 py-2 rounded-lg text-sm"
                  >
                    Aprobar
                  </button>
                  <button
                    onClick={() => setRejectingId(re.id)}
                    className="bg-red-600 text-white px-3 py-2 rounded-lg text-sm"
                  >
                    Rechazar
                  </button>
                </div>
              )}

              {rejectingId === re.id && (
                <div className="space-y-2">
                  <textarea
                    className="w-full border rounded-lg p-2 text-sm"
                    value={rejectNote}
                    onChange={e => setRejectNote(e.target.value)}
                    placeholder="Motivo del rechazo"
                  />
                  <button
                    onClick={() => reject(re.id)}
                    className="bg-black text-white px-3 py-2 rounded-lg text-sm"
                  >
                    Confirmar rechazo
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}