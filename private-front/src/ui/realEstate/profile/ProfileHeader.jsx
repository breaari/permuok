function getProfileStatusLabel(profileStatus) {
  switch (Number(profileStatus)) {
    case 0:
      return "Borrador";
    case 1:
      return "En revisión inicial";
    case 2:
      return "Aprobado";
    case 3:
      return "Rechazado";
    case 4:
      return "Cambios pendientes";
    default:
      return "—";
  }
}

function getProfileStatusClass(profileStatus) {
  switch (Number(profileStatus)) {
    case 2:
      return "text-emerald-700";
    case 3:
      return "text-rose-700";
    case 1:
    case 4:
      return "text-amber-700";
    default:
      return "text-slate-700";
  }
}

export function ProfileHeader({
  requestedAt,
  approvedAt,
  profileStatus,
  validationNote,
  approvedByEmail,
}) {
  const statusLabel = getProfileStatusLabel(profileStatus);
  const statusClass = getProfileStatusClass(profileStatus);

  return (
    <div className="space-y-2 mb-8">
      <h1 className="text-3xl font-black tracking-tight">Mi Perfil</h1>

      <p className="text-slate-500">
        Completá los datos de tu agencia para comenzar a operar en la plataforma.
      </p>

      <div className="text-xs text-slate-500 flex flex-wrap gap-x-6 gap-y-1 pt-2">
        <span>
          <b>Estado de perfil:</b>{" "}
          <span className={`font-semibold ${statusClass}`}>{statusLabel}</span>
        </span>

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

        {!!validationNote && (
          <span className="text-rose-700">
            <b>Motivo rechazo:</b> {validationNote}
          </span>
        )}
      </div>
    </div>
  );
}