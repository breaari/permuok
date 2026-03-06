export default function InitialsAvatar({ name, size = 40 }) {
  const initials = (name || "U")
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join("");

  return (
    <div
      className="rounded-full bg-primary/10 text-primary border-2 border-primary/20 grid place-items-center font-bold"
      style={{ width: size, height: size }}
      aria-label={`Avatar ${name || ""}`}
      title={name || ""}
    >
      {initials || "U"}
    </div>
  );
}