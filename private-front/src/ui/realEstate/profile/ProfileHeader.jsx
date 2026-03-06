// ui/realEstate/profile/ProfileHeader.jsx
export function ProfileHeader({
  requestedAt,
  approvedAt,
  approvedByEmail,
  validationStatus,
  validationNote,
}) {
  const isRejected = Number(validationStatus) === 2;

  return (
    <div className="space-y-2 mb-8">
      <h1 className="text-3xl font-black tracking-tight">Mi Perfil</h1>
      <p className="text-slate-500">
        Completá los datos de tu agencia para comenzar a operar en la plataforma.
      </p>

      <div className="text-xs text-slate-500 flex flex-wrap gap-x-6 gap-y-1 pt-2">
        <span>
          <b>Solicitado:</b> {requestedAt}
        </span>

        <span>
          <b>Aprobado:</b> {approvedAt}
        </span>

        {!!approvedByEmail && (
          <span>
            <b>Aprobado por:</b> {approvedByEmail}
          </span>
        )}

        {isRejected && !!validationNote && (
          <span className="text-rose-700">
            <b>Motivo rechazo:</b> {validationNote}
          </span>
        )}
      </div>
    </div>
  );
}