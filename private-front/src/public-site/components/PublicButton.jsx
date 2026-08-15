// src/public-site/components/PublicButton.jsx

import { Link } from "react-router-dom";

export default function PublicButton({
  to,
  href,
  children,
  variant = "primary",
  className = "",
}) {
  const base =
    "inline-flex items-center justify-center rounded-full px-6 py-3 text-sm font-bold transition active:scale-[0.98]";

  const variants = {
    primary:
      "bg-lime-400 text-slate-950 shadow-[0_0_32px_rgba(163,230,53,0.22)] hover:bg-lime-300",
    secondary:
      "border border-white/15 bg-white/5 text-white hover:bg-white/10",
    dark: "bg-slate-950 text-white hover:bg-slate-800",
  };

  const classes = `${base} ${variants[variant]} ${className}`;

  if (to) {
    return (
      <Link to={to} className={classes}>
        {children}
      </Link>
    );
  }

  return (
    <a href={href} className={classes}>
      {children}
    </a>
  );
}