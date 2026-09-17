export function getPasswordRules(
  password,
) {
  return {
    length:
      password.length >= 8 &&
      password.length <= 72,

    uppercase:
      /[A-Z]/.test(password),

    lowercase:
      /[a-z]/.test(password),

    number:
      /[0-9]/.test(password),
  };
}

export function isPasswordValid(
  password,
) {
  return Object.values(
    getPasswordRules(password),
  ).every(Boolean);
}

function Requirement({
  valid,
  children,
}) {
  return (
    <p
      className={
        valid
          ? "text-emerald-600"
          : "text-slate-500"
      }
    >
      {valid ? "✓" : "○"}{" "}
      {children}
    </p>
  );
}

export default function PasswordRequirements({
  password,
  confirmation = "",
  showMatch = false,
}) {
  const rules =
    getPasswordRules(password);

  const hasConfirmation =
    confirmation.length > 0;

  const passwordsMatch =
    hasConfirmation &&
    password === confirmation;

  return (
    <div className="space-y-1 text-sm">
      <Requirement
        valid={rules.length}
      >
        Entre 8 y 72 caracteres
      </Requirement>

      <Requirement
        valid={rules.uppercase}
      >
        Una letra mayúscula
      </Requirement>

      <Requirement
        valid={rules.lowercase}
      >
        Una letra minúscula
      </Requirement>

      <Requirement
        valid={rules.number}
      >
        Un número
      </Requirement>

      {showMatch &&
        hasConfirmation && (
          <p
            className={
              passwordsMatch
                ? "pt-1 text-emerald-600"
                : "pt-1 text-red-600"
            }
            aria-live="polite"
          >
            {passwordsMatch
              ? "✓ Las contraseñas coinciden."
              : "Las contraseñas no coinciden."}
          </p>
        )}
    </div>
  );
}