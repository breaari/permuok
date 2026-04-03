import { useEffect, useMemo, useState, useCallback } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { api, unwrap, getErrorMessage } from "../../../api/http.js";
import { useAuth } from "../../auth/components/AuthContext";

import AdminDetailHeader from "../components/detail/AdminDetailHeader";
import AdminDetailLoading from "../components/detail/AdminDetailLoading";
import AdminDetailEmpty from "../components/detail/AdminDetailEmpty";
import AdminUserSection from "../components/detail/AdminUserSection.jsx";
import AdminRealEstateSection from "../components/detail/AdminRealEstateSection";
import AdminLinkedProfilesSection from "../components/detail/AdminLinkedProfilesSection";
import AdminAdministrativeStatusCard from "../components/detail/AdminAdministrativeStatusCard";
import AdminMembershipCard from "../components/detail/AdminMembershipCard";
import AdminUserStatusModal from "../components/users/AdminUsersStatusModal.jsx";

import {
  formatDate,
  roleLabel,
  statusLabel,
  membershipStatusLabel,
  membershipStatusClasses,
} from "../components/detail/AdminDetailHelpers";

export default function AdminUserDetail() {
  const { user } = useAuth();
  const isAdmin = Number(user?.role) === 1;
  const navigate = useNavigate();
  const { id } = useParams();

  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [detail, setDetail] = useState(null);
  const [children, setChildren] = useState([]);
  const [childrenSummary, setChildrenSummary] = useState({
    agents: 0,
    investors: 0,
    total: 0,
  });

  const [statusModalOpen, setStatusModalOpen] = useState(false);
  const [statusBusy, setStatusBusy] = useState(false);

  if (!isAdmin) return <Navigate to="/" replace />;

  const loadDetail = useCallback(async () => {
    setErr("");
    setLoading(true);

    try {
      const res = await api.get(`/admin/users/${id}`);
      const data = unwrap(res);

      setDetail(data?.user ?? null);
      setChildren(Array.isArray(data?.children) ? data.children : []);
      setChildrenSummary(
        data?.children_summary ?? {
          agents: 0,
          investors: 0,
          total: 0,
        },
      );
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo cargar el detalle del usuario"));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadDetail();
  }, [loadDetail]);

  const fullName = useMemo(() => {
    const first = detail?.first_name || "";
    const last = detail?.last_name || "";
    return `${first} ${last}`.trim();
  }, [detail]);

  const isRealEstateUser = Number(detail?.role) === 2;
  const membershipLabel = membershipStatusLabel(detail?.membership_status);
  const membershipTone = membershipStatusClasses(detail?.membership_status);

  const adminActionLabel =
    Number(detail?.is_active) === 1 ? "Desactivar" : "Activar";

  function getHeaderSubtitle() {
    if (!detail) return "Cargando detalle...";

    if (fullName && detail?.email) {
      return `${fullName} · ${detail.email}`;
    }

    if (fullName) {
      return fullName;
    }

    if (detail?.email) {
      return detail.email;
    }

    return "Detalle de usuario";
  }

  function openStatusModal() {
    setStatusModalOpen(true);
  }

  function closeStatusModal() {
    if (statusBusy) return;
    setStatusModalOpen(false);
  }

  async function handleConfirmStatusChange({ is_active, reason }) {
    if (!detail?.id) return;

    setStatusBusy(true);
    setErr("");

    try {
      await api.post("/admin/users/status", {
        user_id: detail.id,
        is_active,
        reason,
      });

      setStatusModalOpen(false);
      await loadDetail();
    } catch (e) {
      setErr(getErrorMessage(e, "No se pudo actualizar el estado del usuario"));
    } finally {
      setStatusBusy(false);
    }
  }

  return (
    <>
      <div className="space-y-8">
        {loading ? (
          <AdminDetailHeader
            backLabel="Volver a usuarios"
            onBack={() => navigate("/admin/users")}
            subtitle="Cargando detalle..."
          />
        ) : (
          <AdminDetailHeader
            backLabel="Volver a usuarios"
            onBack={() => navigate("/admin/users")}
            badge={
              <span className="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider">
                {roleLabel(detail?.role)}
              </span>
            }
            subtitle={getHeaderSubtitle()}
          />
        )}

        {err && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}

        {loading ? (
          <AdminDetailLoading />
        ) : !detail ? (
          <AdminDetailEmpty message="No se encontró el usuario." />
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 flex flex-col gap-6">
              <AdminUserSection
                fullName={fullName}
                detail={detail}
                roleLabel={roleLabel}
                formatDate={formatDate}
              />

              <AdminRealEstateSection
                realEstate={{
                  name: detail?.real_estate_name,
                  legal_name: detail?.real_estate_legal_name,
                  cuit: detail?.real_estate_cuit,
                  email: detail?.real_estate_email,
                  phone: detail?.real_estate_phone,
                  address: detail?.real_estate_address,
                  website: detail?.real_estate_website,
                  instagram: detail?.real_estate_instagram,
                  facebook: detail?.real_estate_facebook,
                }}
                showLinks={true}
              />

              {isRealEstateUser && (
                <AdminLinkedProfilesSection
                  children={children}
                  childrenSummary={childrenSummary}
                  navigate={navigate}
                  roleLabel={roleLabel}
                />
              )}
            </div>

            <div className="flex flex-col gap-6">
             <AdminAdministrativeStatusCard
  isActive={detail?.is_active}
  deactivatedByEmail={detail?.deactivated_by_email}
  deactivatedAt={detail?.deactivated_at}
  deactivationReason={detail?.deactivation_reason}
  formatDate={formatDate}
  statusLabel={statusLabel}
  actionLabel={Number(detail?.is_active) === 1 ? "Desactivar" : "Activar"}
  actionDisabled={false}
  onAction={openStatusModal}
  activeNote="La cuenta del usuario se encuentra operativa."
  actionClassName={
    Number(detail?.is_active) === 1
      ? "bg-slate-200 hover:bg-red-100 text-slate-700 hover:text-red-700"
      : "bg-primary hover:bg-primary/90 text-white"
  }
/>

              <AdminMembershipCard
                membership={detail?.membership}
                membershipStatus={detail?.membership_status}
                membershipLabel={membershipLabel}
                membershipTone={membershipTone}
                formatDate={formatDate}
                onViewMore={() => navigate("/admin/users")}
                emptyMessage="Este usuario no tiene una membresía activa asociada."
              />
            </div>
          </div>
        )}
      </div>

      <AdminUserStatusModal
        open={statusModalOpen}
        user={detail}
        busy={statusBusy}
        onClose={closeStatusModal}
        onConfirm={handleConfirmStatusChange}
      />
    </>
  );
}