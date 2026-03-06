// layout/Sidebar.jsx
import { NavLink } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import LogoParaFondoAzul from "../assets/logoparafondoazul.png"; // ajustá el path según tu proyecto
import { Icon } from "../ui/icons/Index";

function itemClass(isActive) {
  return [
    "flex items-center gap-3 px-4 py-3 rounded-lg transition-colors",
    isActive
      ? "bg-white/10 text-white border-l-4 border-primary"
      : "text-slate-300 hover:bg-white/5 hover:text-white",
  ].join(" ");
}

function Item({ to, icon, children, end = false }) {
  return (
    <NavLink to={to} end={end} className={({ isActive }) => itemClass(isActive)}>
      <span className="text-slate-300">
        {icon}
      </span>
      <span className="font-medium">{children}</span>
    </NavLink>
  );
}

export default function Sidebar() {
  const { user, access } = useAuth();
  const role = Number(user?.role || 0);
  const level = access?.level;

  const isAdmin = role === 1;
  const isRealEstate = role === 2;
  const isAgent = role === 3;
  const isInvestor = role === 4;

  // si querés “bloquear” algunas secciones según estado:
  const isActive = level === "real_estate_active";
  const isUnpaid = level === "real_estate_unpaid";
  const isReview = level === "real_estate_review";
  const needsOnboarding = level === "real_estate_not_linked" || level === "real_estate_draft";

  return (
    <aside className="hidden md:flex flex-col w-64 bg-slate-900 text-slate-300 border-r border-slate-800 min-h-screen">
      {/* Header: SOLO LOGO */}
      <div className="p-6 flex items-center justify-center border-b border-slate-800">
        <div className="w-48 h-auto">
          <img src={LogoParaFondoAzul} alt="Permuok" className="w-48 h-auto object-contain" />
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto py-6 px-4 space-y-2">
        {isAdmin && (
          <>
            <Item to="/admin/real-estates" icon={<Icon name="layoutDashboard" />}>
              Solicitudes de revisión
            </Item>
            {/* después */}
            {/* <Item to="/admin/users" icon={<Icon name="users" />}>Usuarios</Item> */}
            {/* <Item to="/admin/properties" icon={<Icon name="home" />}>Propiedades</Item> */}
            {/* <Item to="/admin/searches" icon={<Icon name="search" />}>Búsquedas</Item> */}
            {/* <Item to="/admin/payments" icon={<Icon name="creditCard" />}>Pagos</Item> */}
          </>
        )}

        {isRealEstate && (
          <>
            <Item to="/app" icon={<Icon name="dashboard" />}>
              Panel
            </Item>

            {needsOnboarding && (
              <Item to="/my-profile" icon={<Icon name="clipboardList" />}>
                Mi perfil
              </Item>
            )}

            {isReview && (
              <Item to="/status/review" icon={<Icon name="shieldCheck" />}>
                Revisión
              </Item>
            )}

            {isUnpaid && (
              <Item to="/billing" icon={<Icon name="creditCard" />}>
                Membresía
              </Item>
            )}

            {/* cuando esté activa, acá van tus secciones reales */}
            {isActive && (
              <>
                {/* <Item to="/properties" icon={<Icon name="home" />}>Propiedades</Item> */}
                {/* <Item to="/searches" icon={<Icon name="search" />}>Búsquedas</Item> */}
              </>
            )}
          </>
        )}

        {isAgent && (
          <>
            <Item to="/app" icon={<Icon name="layoutDashboard" />}>Panel</Item>
            {/* <Item to="/properties" icon={<Icon name="home" />}>Propiedades</Item> */}
            {/* <Item to="/searches" icon={<Icon name="search" />}>Búsquedas</Item> */}
            {/* <Item to="/messages" icon={<Icon name="messagesSquare" />}>Mensajes</Item> */}
          </>
        )}

        {isInvestor && (
          <>
            <Item to="/app" icon={<Icon name="layoutDashboard" />}>Panel</Item>
            {/* <Item to="/searches" icon={<Icon name="search" />}>Búsquedas</Item> */}
            {/* <Item to="/favorites" icon={<Icon name="heart" />}>Favoritos</Item> */}
            {/* <Item to="/messages" icon={<Icon name="messagesSquare" />}>Mensajes</Item> */}
          </>
        )}
      </nav>
    </aside>
  );
}