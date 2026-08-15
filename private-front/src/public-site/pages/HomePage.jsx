// src/public-site/pages/HomePage.jsx

import PublicLayout from "../layout/PublicLayout";
import HeroSection from "../components/HeroSection";
import ProblemSection from "../components/ProblemSection";
import AiSection from "../components/AiSection";
import HowItWorksSection from "../components/HowItWorksSection";
import BenefitsSection from "../components/BenefitsSection";
import MembershipsSection from "../components/MembershipsSection";
import FaqSection from "../components/FaqSection";
import ContactSection from "../components/ContactSection";

export default function HomePage() {
  return (
    <PublicLayout>
      <HeroSection />
      <ProblemSection />
      <AiSection />
      <HowItWorksSection />
      <BenefitsSection />
      <MembershipsSection />
      <FaqSection />
      <ContactSection />
    </PublicLayout>
  );
}