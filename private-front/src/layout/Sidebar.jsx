// layout/Sidebar.jsx
import { NavLink } from "react-router-dom";
import { useAuth } from "../features/auth/components/AuthContext";
import LogoParaFondoAzul from "../assets/logoparafondoazul.png";
import { Icon } from "../ui/icons/Index";

function itemClass(isActive, highlight = false) {
  if (highlight) {
    return [
      "flex items-center gap-3 px-4 py-3 rounded-lg transition-colors",
      isActive
        ? "bg-emerald-500/10 text-emerald-400 border-l-4 border-emerald-400"
        : "text-emerald-400 hover:bg-emerald-500/10 hover:text-emerald-300",
    ].join(" ");
  }

  return [
    "flex items-center gap-3 px-4 py-3 rounded-lg transition-colors",
    isActive
      ? "bg-white/10 text-white border-l-4 border-primary"
      : "text-slate-300 hover:bg-white/5 hover:text-white",
  ].join(" ");
}

function Item({ to, icon, children, end = false, onClick, highlight = false }) {
  return (
    <NavLink
      to={to}
      end={end}
      onClick={onClick}
      className={({ isActive }) => itemClass(isActive, highlight)}
    >
      <span className={highlight ? "text-emerald-400" : "text-slate-300"}>
        {icon}
      </span>

      <span className="font-medium">{children}</span>
    </NavLink>
  );
}

function resolveDevelopmentPublishAccess(user, access) {
  const candidates = [
    user?.can_publish_projects,
    user?.membership?.can_publish_projects,

    access?.can_publish_projects,
    access?.membership?.can_publish_projects,

    access?.features?.can_publish_projects,
    access?.features?.publish_projects,
    access?.features?.developments,
    access?.features?.can_publish_developments,
    access?.features?.developments_publish,

    access?.membership?.publish_projects,
    access?.membership?.developments,
    access?.membership?.can_publish_developments,
  ];

  return candidates.some((value) => Number(value) === 1 || value === true);
}

export default function Sidebar({ mobile = false, onNavigate }) {
  const { user, access, permissions } = useAuth();

  const isAdmin = permissions?.isAdmin ?? Number(user?.role || 0) === 1;
  const isRealEstate =
    permissions?.isRealEstate ?? Number(user?.role || 0) === 2;
  const isAgent = permissions?.isAgent ?? Number(user?.role || 0) === 3;
  const isInvestor = permissions?.isInvestor ?? Number(user?.role || 0) === 4;

  const canPublishDevelopments = resolveDevelopmentPublishAccess(user, access);

  console.log("SIDEBAR AUTH DEBUG", {
    role: Number(user?.role || 0),
    user,
    access,
    permissions,
    featuresRaw: access?.features,
    membershipRaw: access?.membership,
    final: {
      canPublishDevelopments,
    },
  });

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
          <>
            <Item
              to="/admin"
              icon={<Icon name="layoutDashboard" />}
              end
              onClick={onNavigate}
            >
              Dashboard
            </Item>
            <Item
              to="/admin/real-estates"
              icon={<Icon name="shieldCheck" />}
              onClick={onNavigate}
            >
              Solicitudes de revisión
            </Item>

            <Item
              to="/admin/users"
              icon={<Icon name="users" />}
              onClick={onNavigate}
            >
              Usuarios
            </Item>

            <Item
              to="/admin/billing"
              icon={<Icon name="creditCard" />}
              onClick={onNavigate}
            >
              Membresías
            </Item>
          </>
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
              to="/compatibilities"
              icon={<Icon name="sparkles" />}
              onClick={onNavigate}
              highlight
            >
              Matches IA
            </Item>
            <Item
              to="/properties"
              icon={<Icon name="building2" />}
              onClick={onNavigate}
            >
              Mis publicaciones
            </Item>

            <Item
              to="/search-requests"
              icon={<Icon name="search" />}
              onClick={onNavigate}
            >
              Mis búsquedas
            </Item>

            {canPublishDevelopments && (
              <Item
                to="/developments"
                icon={<Icon name="building2" />}
                onClick={onNavigate}
              >
                Mis desarrollos
              </Item>
            )}

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
              to="/compatibilities"
              icon={<Icon name="sparkles" />}
              onClick={onNavigate}
              highlight
            >
              Matches IA
            </Item>
            <Item
              to="/properties"
              icon={<Icon name="building2" />}
              onClick={onNavigate}
            >
              Mis publicaciones
            </Item>

            <Item
              to="/search-requests"
              icon={<Icon name="search" />}
              onClick={onNavigate}
            >
              Mis búsquedas
            </Item>

            {canPublishDevelopments && (
              <Item
                to="/developments"
                icon={<Icon name="building2" />}
                onClick={onNavigate}
              >
                Mis desarrollos
              </Item>
            )}

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
