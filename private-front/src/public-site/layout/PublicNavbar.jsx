// src/public-site/layout/PublicNavbar.jsx

import { useState } from "react";
import { Link } from "react-router-dom";
import logo from "../../assets/logoparafondoazul.png";

const navItems = [
  { label: "Permutas", href: "#permutas" },
  { label: "IA", href: "#ia" },
  { label: "Cómo funciona", href: "#funciona" },
  { label: "Membresías", href: "#membresias" },
  { label: "FAQ", href: "#faq" },
  { label: "Contacto", href: "#contacto" },
];

export default function PublicNavbar() {
  const [open, setOpen] = useState(false);

  function closeMenu() {
    setOpen(false);
  }

  return (
    <header className="fixed left-0 top-0 z-50 w-full border-b border-white/10 bg-brand-dark/75 backdrop-blur-2xl">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
        {/* Logo */}
        <Link
          to="/"
          className="group flex items-center gap-3"
          onClick={closeMenu}
        >
          <img
            src={logo}
            alt="PermuOK"
            className="h-10 w-auto object-contain transition duration-300 group-hover:scale-[1.03] md:h-11"
          />
        </Link>

        {/* Desktop nav */}
        <nav className="hidden items-center gap-1 rounded-full border border-white/10 bg-white/[0.045] p-1 backdrop-blur-xl lg:flex">
          {navItems.map((item) => (
            <a
              key={item.href}
              href={item.href}
              className="rounded-full px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"
            >
              {item.label}
            </a>
          ))}
        </nav>

        {/* Actions */}
        <div className="hidden items-center gap-3 md:flex">
          <Link
            to="/login"
            className="rounded-full px-5 py-2 text-sm font-bold text-slate-300 transition hover:bg-white/10 hover:text-white"
          >
            Ingresar
          </Link>

          <Link
            to="/register"
            className="rounded-full bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-[0_14px_36px_rgba(0,86,179,0.35)] transition hover:bg-[#004996] active:scale-[0.98]"
          >
            Registrarme
          </Link>
        </div>

        {/* Mobile button */}
        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/[0.06] text-white transition hover:bg-white/10 md:hidden"
          aria-label={open ? "Cerrar menú" : "Abrir menú"}
          aria-expanded={open}
        >
          <span className="relative h-5 w-5">
            <span
              className={`absolute left-0 top-1 h-0.5 w-5 rounded-full bg-white transition ${
                open ? "translate-y-1.5 rotate-45" : ""
              }`}
            />
            <span
              className={`absolute left-0 top-2.5 h-0.5 w-5 rounded-full bg-white transition ${
                open ? "opacity-0" : ""
              }`}
            />
            <span
              className={`absolute left-0 top-4 h-0.5 w-5 rounded-full bg-white transition ${
                open ? "-translate-y-1.5 -rotate-45" : ""
              }`}
            />
          </span>
        </button>
      </div>

      {/* Mobile menu */}
      {open && (
        <div className="border-t border-white/10 bg-brand-dark/95 px-6 pb-6 pt-3 backdrop-blur-2xl md:hidden">
          <nav className="mx-auto flex max-w-7xl flex-col gap-2">
            {navItems.map((item) => (
              <a
                key={item.href}
                href={item.href}
                onClick={closeMenu}
                className="rounded-2xl border border-white/10 bg-white/[0.045] px-4 py-3 text-sm font-bold text-slate-200 transition hover:bg-white/10 hover:text-white"
              >
                {item.label}
              </a>
            ))}

            <div className="mt-3 grid grid-cols-2 gap-3">
              <Link
                to="/login"
                onClick={closeMenu}
                className="rounded-full border border-white/10 px-5 py-3 text-center text-sm font-bold text-white transition hover:bg-white/10"
              >
                Ingresar
              </Link>

              <Link
                to="/register"
                onClick={closeMenu}
                className="rounded-full bg-primary px-5 py-3 text-center text-sm font-bold text-white shadow-[0_14px_36px_rgba(0,86,179,0.35)] transition hover:bg-[#004996]"
              >
                Registrarme
              </Link>
            </div>
          </nav>
        </div>
      )}
    </header>
  );
}