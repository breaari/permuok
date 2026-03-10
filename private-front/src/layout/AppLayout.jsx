// layout/AppLayout.jsx
import { useState } from "react";
import { Outlet } from "react-router-dom";
import Sidebar from "./Sidebar";
import Navbar from "./Navbar";
import MobileSidebar from "./MobileSidebar";

export default function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="min-h-screen bg-background-light">
      <div className="flex">
        <Sidebar />

        <MobileSidebar open={mobileOpen} onClose={() => setMobileOpen(false)}>
          <Sidebar mobile onNavigate={() => setMobileOpen(false)} />
        </MobileSidebar>

        <div className="flex-1 min-w-0">
          <Navbar title="Panel" onOpenSidebar={() => setMobileOpen(true)} />
          <main className="p-4 md:p-6">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  );
}