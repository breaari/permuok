// pages/MyProfile.jsx
import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import { useGoogleMaps } from "../ui/maps/UseGoogleMaps";
import { useRealEstateProfile } from "../hooks/useRealEstateProfile";
import { LicenseModal } from "../ui/realEstate/LicenseModal";

import { ProfileBanner } from "../ui/realEstate/profile/ProfileBanner";
import { ProfileHeader } from "../ui/realEstate/profile/ProfileHeader";
import { ProfileFormSection } from "../ui/realEstate/profile/ProfileFormSection";
import { ProfileLicensesSection } from "../ui/realEstate/profile/ProfileLicensesSection";
import { ProfileActionBar } from "../ui/realEstate/profile/ProfileActionBar";

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
  const { access, loadMe } = useAuth();
  const nav = useNavigate();
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

  const validationStatus = Number(realEstate?.validation_status ?? 0);
  const isValidationPending =
    validationStatus === 0 && !!realEstate?.review_requested_at;
  const isValidationApproved = validationStatus === 1;
  const isValidationRejected = validationStatus === 2;

  const isUnpaid = level === "real_estate_unpaid";
  const isActive = level === "real_estate_active";

  const isReview = isValidationPending;
  const isRejected = isValidationRejected;
  const isApproved = isValidationApproved;

  const needsOnboarding =
    !isReview &&
    !isApproved &&
    !isRejected &&
    (level === "real_estate_not_linked" || level === "real_estate_draft");

  const hasRealEstate = !!realEstate?.id;

  const canSubmit =
    !isReview &&
    !isApproved &&
    !isActive &&
    profileOk &&
    hasLicenses;

  const requestedAt = useMemo(
    () => formatDate(realEstate?.review_requested_at || realEstate?.created_at),
    [realEstate]
  );

  const banner = useMemo(() => {
    if (isActive) {
      return {
        tone: "success",
        title: "Tu cuenta está activa",
        desc: "Tu inmobiliaria ya está activa y podés operar normalmente en la plataforma.",
        cta: { label: "Ir al panel", onClick: () => nav("/app") },
      };
    }

    if (isUnpaid && isApproved) {
      return {
        tone: "warn",
        title: "Tu perfil fue aprobado",
        desc: "La revisión fue aprobada, pero todavía necesitás activar la membresía para comenzar a operar.",
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
          ? `Revisá el motivo indicado y corregí tu perfil para volver a enviarlo. Motivo: ${realEstate.validation_note}`
          : "Tu revisión fue rechazada. Corregí la información necesaria para volver a enviarla.",
      };
    }

    if (needsOnboarding) {
      return {
        tone: "info",
        title: "Completá tu perfil",
        desc: "Completá tu perfil y agregá al menos una matrícula para poder solicitar revisión.",
      };
    }

    return {
      tone: "info",
      title: "Mi Perfil",
      desc: "Actualizá los datos de tu agencia y tus matrículas.",
    };
  }, [
    isActive,
    isUnpaid,
    isApproved,
    isReview,
    isRejected,
    needsOnboarding,
    realEstate,
    nav,
  ]);

  const footerHint = useMemo(() => {
    if (isActive) return { label: "Cuenta activa", tone: "success" };
    if (isUnpaid && isApproved) {
      return { label: "Aprobada, pendiente de membresía", tone: "warn" };
    }
    if (isReview) return { label: "En revisión", tone: "info" };
    if (isRejected) return { label: "Rechazada", tone: "warn" };
    if (!profileOk || !hasLicenses) {
      return { label: "Pendiente de completar", tone: "warn" };
    }
    return { label: "Listo para enviar", tone: "success" };
  }, [isActive, isUnpaid, isApproved, isReview, isRejected, profileOk, hasLicenses]);

  const submitBlockReason = useMemo(() => {
    if (isActive) return "";
    if (isUnpaid && isApproved) return "";
    if (isReview) return "";
    if (isRejected) return "";

    if (!profileValidation?.ok) return profileValidation.reason;
    if (!hasLicenses) return "Agregá al menos una matrícula.";

    return "";
  }, [
    isActive,
    isUnpaid,
    isApproved,
    isReview,
    isRejected,
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
          approvedByEmail={realEstate?.approved_by_email}
          validationStatus={validationStatus}
          validationNote={realEstate?.validation_note}
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
          mapsLoaded={mapsLoaded}
          mapsError={mapsError}
          onSubmit={actions.saveProfile}
        />

        <ProfileLicensesSection
          licenses={licenses}
          isReview={isReview}
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
        onClose={() => setLicenseOpen(false)}
        onSave={actions.addLicense}
        errorMessage={err}
      />

      {!licenseOpen && (
        <ProfileActionBar
          isReview={isReview}
          isApproved={isApproved}
          isUnpaid={isUnpaid}
          isActive={isActive}
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