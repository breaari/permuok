import { useMemo, useState } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { useGoogleMaps } from "../ui/maps/UseGoogleMaps";
import { useRealEstateProfile } from "../hooks/useRealEstateProfile";
import { LicenseModal } from "../ui/realEstate/LicenseModal";

import { ProfileBanner } from "../ui/realEstate/profile/ProfileBanner";
import { ProfileHeader } from "../ui/realEstate/profile/ProfileHeader";
import { ProfileFormSection } from "../ui/realEstate/profile/ProfileFormSection";
import { ProfileLicensesSection } from "../ui/realEstate/profile/ProfileLicensesSection";
import { ProfileActionBar } from "../ui/realEstate/profile/ProfileActionBar";
import MyUserProfile from "./MyUserProfile";

function formatDate(value) {
  if (!value) return "—";
  try {
    return new Date(value).toLocaleDateString("es-AR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

export default function MyProfile() {
  const { user, access, loadMe } = useAuth();
  const nav = useNavigate();

  const role = Number(user?.role || 0);

  if (![2, 3, 4].includes(role)) {
    return <Navigate to="/app" replace />;
  }

  if (role === 3 || role === 4) {
    return <MyUserProfile />;
  }

  const { isLoaded: mapsLoaded, loadError: mapsError } = useGoogleMaps();

  const {
    loading,
    err,
    ok,
    realEstate,
    profile,
    setProfile,
    licenses,
    profileOk,
    hasLicenses,
    actions,
    profileValidation,
  } = useRealEstateProfile({ loadMe });

  const [licenseOpen, setLicenseOpen] = useState(false);

  const level = access?.level;
  const profileStatus = Number(realEstate?.profile_status ?? 0);

  const isDraft =
    level === "real_estate_not_linked" || level === "real_estate_draft";

  const isReview = level === "real_estate_review";
  const isRejected = level === "real_estate_rejected";
  const isUnpaid = level === "real_estate_unpaid";
  const isUnpaidChangesPending = level === "real_estate_unpaid_changes_pending";
  const isActive = level === "real_estate_active";
  const isActiveChangesPending = level === "real_estate_active_changes_pending";

  const hasRealEstate = !!realEstate?.id;

  const canSubmit =
    !isReview &&
    !isUnpaid &&
    !isUnpaidChangesPending &&
    !isActive &&
    !isActiveChangesPending &&
    profileOk &&
    hasLicenses;

  const requestedAt = useMemo(
    () =>
      formatDate(
        realEstate?.changes_requested_at ||
          realEstate?.review_requested_at ||
          realEstate?.created_at,
      ),
    [realEstate],
  );

  const banner = useMemo(() => {
    if (isActive) {
      return {
        tone: "success",
        title: "Tu cuenta está activa",
        desc: "Tu inmobiliaria está aprobada y con membresía activa. Podés operar normalmente.",
        cta: { label: "Ir al panel", onClick: () => nav("/app") },
      };
    }

    if (isActiveChangesPending) {
      return {
        tone: "warn",
        title: "Cambios pendientes",
        desc: "Realizaste modificaciones luego de la aprobación. Tu cuenta sigue activa, pero los cambios del perfil están pendientes de revisión.",
        cta: { label: "Ir al panel", onClick: () => nav("/app") },
      };
    }

    if (isUnpaidChangesPending) {
      return {
        tone: "warn",
        title: "Cambios pendientes",
        desc: "Realizaste modificaciones luego de la aprobación. Los cambios del perfil están pendientes de revisión y además necesitás una membresía activa para operar.",
        cta: { label: "Ir a membresía", onClick: () => nav("/billing") },
      };
    }

    if (isUnpaid) {
      return {
        tone: "warn",
        title: "Perfil aprobado",
        desc: "Tu perfil fue aprobado. Activá tu membresía para comenzar a operar.",
        cta: { label: "Ir a membresía", onClick: () => nav("/billing") },
      };
    }

    if (isReview) {
      return {
        tone: "info",
        title: "En revisión",
        desc: "Tu perfil fue enviado a revisión. La edición queda bloqueada.",
      };
    }

    if (isRejected) {
      return {
        tone: "warn",
        title: "Revisión rechazada",
        desc: realEstate?.validation_note
          ? `Motivo: ${realEstate.validation_note}`
          : "Tu perfil fue rechazado. Revisá la información y volvé a enviarlo.",
      };
    }

    if (isDraft || profileStatus === 0) {
      return {
        tone: "info",
        title: "Completá tu perfil",
        desc: "Completá la información y agregá al menos una matrícula para solicitar revisión.",
      };
    }

    return {
      tone: "info",
      title: "Mi Perfil",
      desc: "Actualizá los datos de tu agencia y tus matrículas.",
    };
  }, [
    isActive,
    isActiveChangesPending,
    isUnpaid,
    isUnpaidChangesPending,
    isReview,
    isRejected,
    isDraft,
    profileStatus,
    realEstate,
    nav,
  ]);

  const footerHint = useMemo(() => {
    if (isActiveChangesPending) {
      return { label: "Cambios pendientes de revisión", tone: "warn" };
    }
    if (isActive) return { label: "Cuenta activa", tone: "success" };
    if (isUnpaidChangesPending) {
      return {
        label: "Cambios pendientes y membresía pendiente",
        tone: "warn",
      };
    }
    if (isUnpaid) {
      return { label: "Aprobada, pendiente de membresía", tone: "warn" };
    }
    if (isReview) return { label: "En revisión", tone: "info" };
    if (isRejected) return { label: "Rechazada", tone: "warn" };
    if (!profileOk || !hasLicenses) {
      return { label: "Pendiente de completar", tone: "warn" };
    }
    return { label: "Listo para enviar", tone: "success" };
  }, [
    isActive,
    isActiveChangesPending,
    isUnpaid,
    isUnpaidChangesPending,
    isReview,
    isRejected,
    profileOk,
    hasLicenses,
  ]);

  const submitBlockReason = useMemo(() => {
    if (
      isActive ||
      isActiveChangesPending ||
      isUnpaid ||
      isUnpaidChangesPending ||
      isReview
    ) {
      return "";
    }

    if (!profileValidation?.ok) return profileValidation.reason;
    if (!hasLicenses) return "Agregá al menos una matrícula.";

    return "";
  }, [
    isActive,
    isActiveChangesPending,
    isUnpaid,
    isUnpaidChangesPending,
    isReview,
    profileValidation,
    hasLicenses,
  ]);

  if (loading) {
    return <div className="p-6 text-sm text-slate-500">Cargando...</div>;
  }

  return (
    <div className="bg-background-light min-h-[calc(100vh-64px)]">
      <main className="flex-1 max-w-5xl mx-auto w-full px-4 md:px-6 py-8 pb-32">
        <ProfileBanner banner={banner} />

        <ProfileHeader
          requestedAt={requestedAt}
          approvedAt={formatDate(realEstate?.approved_at)}
          profileStatus={profileStatus}
          validationNote={realEstate?.validation_note}
          approvedByEmail={realEstate?.approved_by_email}
        />

        {err && (
          <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {err}
          </div>
        )}

        {ok && (
          <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {ok}
          </div>
        )}

        <ProfileFormSection
          profile={profile}
          setProfile={setProfile}
          isReview={isReview}
          isRejected={isRejected}
          isActiveChangesPending={
            isActiveChangesPending || isUnpaidChangesPending
          }
          validationNote={realEstate?.validation_note}
          mapsLoaded={mapsLoaded}
          mapsError={mapsError}
          onSubmit={actions.saveProfile}
        />

        <ProfileLicensesSection
          licenses={licenses}
          isReview={isReview}
          isRejected={isRejected}
          isActiveChangesPending={
            isActiveChangesPending || isUnpaidChangesPending
          }
          hasRealEstate={hasRealEstate}
          onOpenModal={() => setLicenseOpen(true)}
        />

        {!canSubmit && submitBlockReason && (
          <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            {submitBlockReason}
          </div>
        )}
      </main>

      <LicenseModal
        open={licenseOpen}
        disabled={isReview}
        isRejected={isRejected}
        isActiveChangesPending={
          isActiveChangesPending || isUnpaidChangesPending
        }
        onClose={() => setLicenseOpen(false)}
        onSave={actions.addLicense}
        errorMessage={err}
      />

      {!licenseOpen && (
        <ProfileActionBar
          isReview={isReview}
          isRejected={isRejected}
          isUnpaid={isUnpaid}
          isUnpaidChangesPending={isUnpaidChangesPending}
          isActive={isActive}
          isActiveChangesPending={isActiveChangesPending}
          canSubmit={canSubmit}
          footerHint={footerHint}
          submitBlockReason={submitBlockReason}
          onSubmitReview={actions.submitReview}
          onGoToBilling={() => nav("/billing")}
        />
      )}
    </div>
  );
}