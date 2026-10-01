// src/public-site/pages/HomePage.jsx

import PublicLayout from "../layout/PublicLayout";
import HeroSection from "../components/HeroSection";
import ProblemSection from "../components/ProblemSection";
import HowItWorksSection from "../components/HowItWorksSection";
import BenefitsSection from "../components/BenefitsSection";
import MembershipsSection from "../components/MembershipsSection";
import FaqSection from "../components/FaqSection";
import ContactSection from "../components/ContactSection";
import OpportunitySection from "../components/OpportunitySection";

export default function HomePage() {
  return (
    <PublicLayout>
      <div className="relative bg-[#f3f4f6]">
        <div
          className="pointer-events-none absolute inset-0 z-0 opacity-65"
          style={{
            backgroundImage:
              "radial-gradient(rgba(71,85,105,0.13) 0.75px, transparent 0.75px)",
            backgroundSize: "18px 18px",
          }}
        />

        <div className="relative z-10">
          <HeroSection />
          <OpportunitySection />
        </div>
      </div>
      <ProblemSection />
      <HowItWorksSection />
      <BenefitsSection />
      <MembershipsSection />
      <FaqSection />
      <ContactSection />
    </PublicLayout>
  );
}
