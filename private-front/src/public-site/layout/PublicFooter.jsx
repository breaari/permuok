// src/public-site/layout/PublicFooter.jsx

import { Link } from "react-router-dom";
import logo from "../../assets/logoparafondoazul.png";

const sectionLinks = [
  { label: "Permutas", href: "#permutas" },
  { label: "Cómo funciona", href: "#funciona" },
  { label: "Planes", href: "#membresias" },
  { label: "Preguntas frecuentes", href: "#faq" },
];

const accessLinks = [
  { label: "Ingresar", to: "/login" },
  { label: "Sumarme a la red", to: "/register" },
];

export default function PublicFooter() {
  const currentYear = new Date().getFullYear();

  return (
    <footer
      className="
        border-t
        border-white/10
        bg-[#0b1628]
        px-5
        py-10
        text-white

        sm:px-8
        sm:py-12

        lg:px-10
        lg:py-14
      "
    >
      <div className="mx-auto w-full max-w-[1320px]">
        {/* =====================================================
            PRINCIPAL
        ====================================================== */}

        <div
          className="
            grid
            gap-10

            md:grid-cols-[1.4fr_0.8fr_0.8fr]
            md:gap-12
          "
        >
          {/* MARCA */}

          <div>
            <Link
              to="/"
              className="
                inline-flex
                items-center
              "
            >
              <img
                src={logo}
                alt="Permuok"
                className="
                  h-10
                  w-auto
                  object-contain

                  md:h-11
                "
              />
            </Link>

            <p
              className="
                mt-5
                max-w-[470px]
                text-[15px]
                font-medium
                leading-[1.55]
                text-slate-400
              "
            >
              Una red inteligente sólo para inmobiliarias donde las
              oportunidades de permuta se centralizan, se cruzan y empiezan a
              encontrarse.
            </p>
          </div>

          {/* NAVEGACIÓN */}

          <div>
            <p
              className="
                text-[12px]
                font-bold
                uppercase
                tracking-[0.14em]
                text-slate-300
              "
            >
              Explorar
            </p>

            <nav
              className="
                mt-5
                flex
                flex-col
                gap-3
                text-[14px]
                font-medium
                text-slate-400
              "
            >
              {sectionLinks.map((link) => (
                <a
                  key={link.href}
                  href={link.href}
                  className="
                    w-fit
                    transition
                    duration-200

                    hover:text-white
                  "
                >
                  {link.label}
                </a>
              ))}
            </nav>
          </div>

          {/* ACCESO */}

          <div>
            <p
              className="
                text-[12px]
                font-bold
                uppercase
                tracking-[0.14em]
                text-slate-300
              "
            >
              Plataforma
            </p>

            <nav
              className="
                mt-5
                flex
                flex-col
                gap-3
                text-[14px]
                font-medium
                text-slate-400
              "
            >
              {accessLinks.map((link) => (
                <Link
                  key={link.label}
                  to={link.to}
                  className={`
                    w-fit
                    transition
                    duration-200

                    ${
                      link.to === "/register"
                        ? "font-semibold text-[#76bc21] hover:text-[#86cc31]"
                        : "hover:text-white"
                    }
                  `}
                >
                  {link.label}
                </Link>
              ))}
            </nav>
          </div>
        </div>

        {/* =====================================================
            PIE
        ====================================================== */}

        <div
          className="
            mt-10
            flex
            flex-col
            gap-3
            border-t
            border-white/10
            pt-6
            text-[13px]
            font-medium
            leading-6
            text-slate-500

            md:mt-12
            md:flex-row
            md:items-center
            md:justify-between
          "
        >
          <p>© {currentYear} Permuok. Todos los derechos reservados.</p>

          <p>
            Desarrollo por{" "}
            <a
              href="https://wa.me/5491136002250"
              target="_blank"
              rel="noreferrer"
              className="
                font-semibold
                text-slate-300
                transition

                hover:text-white
              "
            >
              Ariana Brea
            </a>
          </p>
        </div>
      </div>
    </footer>
  );
}
