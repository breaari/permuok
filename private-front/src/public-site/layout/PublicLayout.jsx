// src/public-site/layout/PublicLayout.jsx

import PublicNavbar from "./PublicNavbar";
import PublicFooter from "./PublicFooter";

export default function PublicLayout({ children }) {
  return (
    <div className="min-h-screen bg-slate-950 text-white">
      <PublicNavbar />
      <main>{children}</main>
      <PublicFooter />
    </div>
  );
}