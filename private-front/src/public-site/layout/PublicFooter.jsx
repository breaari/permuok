// src/public-site/layout/PublicFooter.jsx

import { Link } from "react-router-dom";
import logo from "../../assets/logoparafondoazul.png";

const mainLinks = [
  { label: "Permutas", href: "#permutas" },
  { label: "Inteligencia artificial", href: "#ia" },
  { label: "Cómo funciona", href: "#funciona" },
  { label: "Beneficios", href: "#beneficios" },
];

const platformLinks = [
  { label: "Membresías", href: "#membresias" },
  { label: "Preguntas frecuentes", href: "#faq" },
  { label: "Contacto", href: "#contacto" },
  { label: "Crear cuenta", to: "/register" },
  { label: "Acceso a usuarios", to: "/login" },
];

export default function PublicFooter() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="border-t border-white/10 bg-[#07111f] px-5 py-10 text-white sm:px-6 md:py-12">
      <div className="mx-auto max-w-7xl">
        <div className="grid gap-8 md:grid-cols-[1.35fr_0.8fr_0.8fr] md:gap-10">
          {/* Marca */}
          <div>
            <Link to="/" className="inline-flex items-center">
              <img
                src={logo}
                alt="PermuOK"
                className="h-10 w-auto object-contain md:h-11"
              />
            </Link>

            <p className="mt-4 max-w-xl text-sm leading-6 text-slate-400">
              Plataforma privada para inmobiliarias enfocada en permutas,
              operaciones cruzadas, proyectos inmobiliarios y oportunidades
              potenciadas por inteligencia artificial.
            </p>
          </div>

          {/* Links mobile agrupados */}
          <div className="grid grid-cols-2 gap-8 md:contents">
            {/* Secciones */}
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-300 md:text-sm">
                Secciones
              </p>

              <nav className="mt-4 flex flex-col gap-3 text-sm text-slate-400">
                {mainLinks.map((link) => (
                  <a
                    key={link.href}
                    href={link.href}
                    className="transition hover:text-white"
                  >
                    {link.label}
                  </a>
                ))}
              </nav>
            </div>

            {/* Plataforma */}
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.16em] text-slate-300 md:text-sm">
                Plataforma
              </p>

              <nav className="mt-4 flex flex-col gap-3 text-sm text-slate-400">
                {platformLinks.map((link) =>
                  link.to ? (
                    <Link
                      key={link.label}
                      to={link.to}
                      className="transition hover:text-white"
                    >
                      {link.label}
                    </Link>
                  ) : (
                    <a
                      key={link.href}
                      href={link.href}
                      className="transition hover:text-white"
                    >
                      {link.label}
                    </a>
                  ),
                )}
              </nav>
            </div>
          </div>
        </div>

        <div className="mt-8 flex flex-col gap-3 border-t border-white/10 pt-6 text-sm leading-6 text-slate-500 md:mt-10 md:flex-row md:items-center md:justify-between">
          <p>© {currentYear} PermuOK. Todos los derechos reservados.</p>

          <p>
            Plataforma desarrollada por{" "}
            <a
              href="https://wa.me/5491136002250"
              target="_blank"
              rel="noreferrer"
              className="font-semibold text-slate-300 transition hover:text-white"
            >
              Ariana Brea
            </a>
          </p>
        </div>
      </div>
    </footer>
  );
}
