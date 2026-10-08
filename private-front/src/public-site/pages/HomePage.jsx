// src/public-site/pages/HomePage.jsx

import PublicLayout from "../layout/PublicLayout";
import HeroSection from "../components/HeroSection";
import OpportunitySection from "../components/OpportunitySection";
import ProblemSection from "../components/ProblemSection";
import HowItWorksSection from "../components/HowItWorksSection";
import MembershipsSection from "../components/MembershipsSection";
import FaqSection from "../components/FaqSection";
import CTASection from "../components/CTASection";

export default function HomePage() {
  return (
    <PublicLayout>
      <div
        className="
          relative
          bg-[#f3f4f6]
        "
      >
        {/* =====================================================
            FONDO ÚNICO DE TODA LA LANDING
        ====================================================== */}

        <div
          className="
            pointer-events-none
            absolute
            inset-0
            z-0
            opacity-65
          "
          style={{
            backgroundImage:
              "radial-gradient(rgba(71,85,105,0.13) 0.75px, transparent 0.75px)",
            backgroundSize: "18px 18px",
          }}
        />

        {/* =====================================================
            SECCIONES
        ====================================================== */}

        <div className="relative z-10">
          <HeroSection />
          <OpportunitySection />
          <ProblemSection />
          <HowItWorksSection />
          <MembershipsSection />
          <FaqSection />
          <CTASection />
        </div>
      </div>
    </PublicLayout>
  );
}