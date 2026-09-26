// src/public-site/layout/PublicNavbar.jsx

import { useEffect, useState } from "react";
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
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    function handleScroll() {
      setScrolled(window.scrollY > 20);
    }

    handleScroll();

    window.addEventListener("scroll", handleScroll, {
      passive: true,
    });

    return () => {
      window.removeEventListener("scroll", handleScroll);
    };
  }, []);

  function closeMenu() {
    setOpen(false);
  }

  return (
    <header
      className={`
        fixed
        left-3
        right-3
        top-3
        z-50
        transition-all
        duration-300
        sm:left-4
        sm:right-4
        sm:top-4
        ${scrolled ? "translate-y-0" : ""}
      `}
    >
      <div
        className="
          relative
          mx-auto
          flex
          min-h-[64px]
          w-full
          items-center
          justify-between
          rounded-[16px]
          border
          border-white/[0.09]
          bg-[#0b1628]/95
          px-4
          shadow-[0_14px_45px_rgba(10,25,47,0.20)]
          backdrop-blur-xl
          sm:px-5
          lg:px-6
        "
      >
        {/* =================================================
            LOGO
        ================================================== */}

        <Link
          to="/"
          onClick={closeMenu}
          className="
            group
            relative
            z-20
            flex
            shrink-0
            items-center
          "
        >
          <img
            src={logo}
            alt="PermuOK"
            className="
              h-9
              w-auto
              object-contain
              transition
              duration-300
              group-hover:scale-[1.02]
              sm:h-10
            "
          />
        </Link>

        {/* =================================================
            NAVEGACIÓN DESKTOP
        ================================================== */}

        <nav
          className="
            absolute
            left-1/2
            top-1/2
            hidden
            -translate-x-1/2
            -translate-y-1/2
            items-center
            lg:flex
          "
        >
          {navItems.map((item) => (
            <a
              key={item.href}
              href={item.href}
              className="
                relative
                whitespace-nowrap
                px-3.5
                py-2
                text-[13px]
                font-semibold
                text-slate-300
                transition
                duration-200
                hover:text-white
                xl:px-4
                xl:text-sm
              "
            >
              {item.label}

              <span
                className="
                  absolute
                  bottom-0
                  left-1/2
                  h-px
                  w-0
                  -translate-x-1/2
                  bg-white/70
                  transition-all
                  duration-300
                  group-hover:w-full
                "
              />
            </a>
          ))}
        </nav>

        {/* =================================================
            ACCIONES DESKTOP
        ================================================== */}

        <div
          className="
            relative
            z-20
            hidden
            shrink-0
            items-center
            gap-2
            md:flex
          "
        >
          <Link
            to="/login"
            className="
              rounded-xl
              px-4
              py-2.5
              text-sm
              font-bold
              text-slate-200
              transition
              duration-200
              hover:bg-white/[0.07]
              hover:text-white
            "
          >
            Ingresar
          </Link>

          <Link
            to="/register"
            className="
              rounded-xl
              bg-primary
              px-5
              py-2.5
              text-sm
              font-extrabold
              text-white
              shadow-[0_10px_28px_rgba(0,86,179,0.30)]
              transition
              duration-300
              hover:-translate-y-0.5
              hover:bg-[#004b9d]
              hover:shadow-[0_14px_34px_rgba(0,86,179,0.36)]
              active:translate-y-0
            "
          >
            Registrarme
          </Link>
        </div>

        {/* =================================================
            BOTÓN MOBILE
        ================================================== */}

        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          className="
            relative
            z-20
            inline-flex
            h-10
            w-10
            items-center
            justify-center
            rounded-xl
            border
            border-white/10
            bg-white/[0.06]
            text-white
            transition
            hover:bg-white/10
            md:hidden
          "
          aria-label={open ? "Cerrar menú" : "Abrir menú"}
          aria-expanded={open}
        >
          <span className="relative h-5 w-5">
            <span
              className={`
                absolute
                left-0
                top-1
                h-0.5
                w-5
                rounded-full
                bg-white
                transition
                duration-300
                ${open ? "translate-y-1.5 rotate-45" : ""}
              `}
            />

            <span
              className={`
                absolute
                left-0
                top-2.5
                h-0.5
                w-5
                rounded-full
                bg-white
                transition
                duration-300
                ${open ? "opacity-0" : ""}
              `}
            />

            <span
              className={`
                absolute
                left-0
                top-4
                h-0.5
                w-5
                rounded-full
                bg-white
                transition
                duration-300
                ${open ? "-translate-y-1.5 -rotate-45" : ""}
              `}
            />
          </span>
        </button>
      </div>

      {/* ===================================================
          MENÚ MOBILE
      ==================================================== */}

      {open && (
        <div
          className="
            mt-2
            overflow-hidden
            rounded-[16px]
            border
            border-white/[0.09]
            bg-[#0b1628]/98
            p-3
            shadow-[0_18px_50px_rgba(10,25,47,0.28)]
            backdrop-blur-2xl
            md:hidden
          "
        >
          <nav className="flex flex-col gap-1">
            {navItems.map((item) => (
              <a
                key={item.href}
                href={item.href}
                onClick={closeMenu}
                className="
                  rounded-xl
                  px-4
                  py-3
                  text-sm
                  font-bold
                  text-slate-200
                  transition
                  hover:bg-white/[0.07]
                  hover:text-white
                "
              >
                {item.label}
              </a>
            ))}
          </nav>

          <div
            className="
              mt-3
              grid
              grid-cols-2
              gap-2
              border-t
              border-white/10
              pt-3
            "
          >
            <Link
              to="/login"
              onClick={closeMenu}
              className="
                rounded-xl
                border
                border-white/10
                px-4
                py-3
                text-center
                text-sm
                font-bold
                text-white
                transition
                hover:bg-white/[0.07]
              "
            >
              Ingresar
            </Link>

            <Link
              to="/register"
              onClick={closeMenu}
              className="
                rounded-xl
                bg-primary
                px-4
                py-3
                text-center
                text-sm
                font-extrabold
                text-white
                shadow-[0_10px_28px_rgba(0,86,179,0.28)]
                transition
                hover:bg-[#004b9d]
              "
            >
              Registrarme
            </Link>
          </div>
        </div>
      )}
    </header>
  );
}
