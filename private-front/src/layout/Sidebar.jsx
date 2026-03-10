// layout/Sidebar.jsx
import { NavLink } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";
import LogoParaFondoAzul from "../assets/logoparafondoazul.png";
import { Icon } from "../ui/icons/Index";

function itemClass(isActive) {
  return [
    "flex items-center gap-3 px-4 py-3 rounded-lg transition-colors",
    isActive
      ? "bg-white/10 text-white border-l-4 border-primary"
      : "text-slate-300 hover:bg-white/5 hover:text-white",
  ].join(" ");
}

function Item({ to, icon, children, end = false, onClick }) {
  return (
    <NavLink
      to={to}
      end={end}
      onClick={onClick}
      className={({ isActive }) => itemClass(isActive)}
    >
      <span className="text-slate-300">{icon}</span>
      <span className="font-medium">{children}</span>
    </NavLink>
  );
}

export default function Sidebar({ mobile = false, onNavigate }) {
  const { user } = useAuth();
  const role = Number(user?.role || 0);

  const isAdmin = role === 1;
  const isRealEstate = role === 2;
  const isAgent = role === 3;
  const isInvestor = role === 4;

  return (
    <aside
      className={
        mobile
          ? "flex flex-col text-slate-300"
          : "hidden md:flex flex-col w-64 bg-slate-900 text-slate-300 border-r border-slate-800 min-h-screen"
      }
    >
      <div
        className={
          mobile
            ? "pb-4 mb-4 flex items-center justify-center border-b border-slate-800"
            : "p-6 flex items-center justify-center border-b border-slate-800"
        }
      >
        <div className="w-48 h-auto">
          <img
            src={LogoParaFondoAzul}
            alt="Permuok"
            className="w-48 h-auto object-contain"
          />
        </div>
      </div>

      <nav
        className={
          mobile ? "space-y-2" : "flex-1 overflow-y-auto py-6 px-4 space-y-2"
        }
      >
        {isAdmin && (
          <Item
            to="/admin/real-estates"
            icon={<Icon name="layoutDashboard" />}
            onClick={onNavigate}
          >
            Solicitudes de revisión
          </Item>
        )}

        {isRealEstate && (
          <>
            <Item
              to="/app"
              icon={<Icon name="dashboard" />}
              onClick={onNavigate}
            >
              Panel
            </Item>

            <Item
              to="/my-profile"
              icon={<Icon name="clipboardList" />}
              onClick={onNavigate}
            >
              Mi perfil
            </Item>

            <Item
              to="/billing"
              icon={<Icon name="creditCard" />}
              onClick={onNavigate}
            >
              Membresía
            </Item>

            <Item to="/users" icon={<Icon name="users" />} onClick={onNavigate}>
              Mis usuarios
            </Item>
          </>
        )}

        {isAgent && (
          <>
          <Item
            to="/app"
            icon={<Icon name="layoutDashboard" />}
            onClick={onNavigate}
          >
            Panel
          </Item>

          <Item
              to="/my-profile"
              icon={<Icon name="clipboardList" />}
              onClick={onNavigate}
            >
              Mi perfil
            </Item>
            </>
        )}

        {isInvestor && (
          <>
          <Item
            to="/app"
            icon={<Icon name="layoutDashboard" />}
            onClick={onNavigate}
          >
            Panel
          </Item>

          <Item
              to="/my-profile"
              icon={<Icon name="clipboardList" />}
              onClick={onNavigate}
            >
              Mi perfil
            </Item>
            </>
        )}

        
      </nav>
    </aside>
  );
}
