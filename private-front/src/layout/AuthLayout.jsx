import loginBg from "../assets/imagenparalogin.png";
import logo from "../assets/logoparafondoblanco.png";

export default function AuthLayout({ title, subtitle, children }) {
  return (
    <div className="bg-slate-50 min-h-[100dvh] overflow-x-hidden">
      <div className="flex min-h-[100dvh]">
        {/* LEFT (solo desktop) */}
        <div className="hidden lg:flex lg:w-3/5 relative overflow-hidden">
          <img
            src={loginBg}
            alt="Imagen de fondo"
            className="absolute inset-0 w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-br from-slate-950/90 to-slate-950/60 flex flex-col justify-center px-16 text-white">
            <div className="max-w-md">
              <h1 className="text-5xl font-bold mb-6 leading-tight">
                La nueva forma de intercambiar propiedades.
              </h1>
              <p className="text-xl text-slate-200 mb-8 leading-relaxed">
                Accedé a una red exclusiva de intercambios inmobiliarios. Encontrá tu próxima
                propiedad de manera inteligente y segura.
              </p>

              <div className="text-sm text-slate-200">
                <span className="font-semibold text-white">+500 profesionales</span> ya están
                intercambiando hoy.
              </div>
            </div>
          </div>

          <div className="absolute bottom-8 left-16 opacity-60 text-white">
            <span className="text-xs uppercase tracking-widest font-semibold">
              permuok.com © 2026
            </span>
          </div>
        </div>

        {/* RIGHT */}
        <div className="w-full lg:w-2/5 flex flex-col items-center justify-center bg-white px-6 sm:px-8 md:px-16 lg:px-20 relative overflow-y-auto">
          <div className="w-full max-w-sm py-10 sm:py-12">
            {/* Logo */}
            <div className="flex justify-center mb-8 sm:mb-10">
              <img
                src={logo}
                alt="permuok"
                className="h-10 w-auto"
                draggable={false}
              />
            </div>

            {/* Title */}
            <div className="mb-6 sm:mb-8 text-center">
              <h2 className="text-2xl font-bold text-slate-900">{title}</h2>
              {subtitle ? (
                <p className="text-slate-500 mt-2">{subtitle}</p>
              ) : null}
            </div>

            {/* Slot */}
            {children}

            {/* Footer mobile */}
            <div className="lg:hidden mt-10 text-center">
              <p className="text-xs text-slate-400">permuok.com © 2026</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}