import { useState, useEffect } from "react";
import type { Page } from "./types";
import { Shell } from "./components/shared";
import AgencyDashboard, { AgencyModule } from "./AgencyDashboard";
import ITAutomationPage from "./pages/ITAutomationPage";
import AgencyOSPage from "./pages/AgencyOSPage";

import HomePage from "./pages/HomePage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import NosotrosPage from "./pages/NosotrosPage";
import ServiciosPage from "./pages/ServiciosPage";
import ServiceDetailPage from "./pages/ServiceDetailPage";
import ServiceSEMPage from "./pages/ServiceSEMPage";
import ServiceSEMConversionPage from "./pages/ServiceSEMConversionPage";
import ServiceSEOPage from "./pages/ServiceSEOPage";
import ServiceSEOConversionPage from "./pages/ServiceSEOConversionPage";
import ServiceWebPage from "./pages/ServiceWebPage";
import ServiceWebConversionPage from "./pages/ServiceWebConversionPage";
import ServicePageSpeedPage from "./pages/ServicePageSpeedPage";
import ServiceAnalyticsPage from "./pages/ServiceAnalyticsPage";
import ServiceSocialPage from "./pages/ServiceSocialPage";
import ProductosPage from "./pages/ProductosPage";
import PromocionesPage from "./pages/PromocionesPage";
import AgendarPage from "./pages/AgendarPage";
import AgendarAdminPage from "./pages/AgendarAdminPage";
import ContactoPage from "./pages/ContactoPage";
import BlogPage from "./pages/BlogPage";
import BlogPostPage from "./pages/BlogPostPage";
import PMSHoteleroPage from "./pages/PMSHoteleroPage";
import DPMSOdontologicoPage from "./pages/DPMSOdontologicoPage";
import FAQPage from "./pages/FAQPage";
import CasosDeExitoPage from "./pages/CasosDeExitoPage";
import PrivacidadPage from "./pages/PrivacidadPage";
import TerminosPage from "./pages/TerminosPage";

export default function App() {
  const initial = (typeof window !== "undefined" && (window as any).__rankproPage) as Page | undefined;
  const [appMode, setAppMode] = useState<"site" | "platform">(initial === "dashboard" ? "platform" : "site");
  const [page, setPage] = useState<Page>(initial && initial !== "dashboard" ? initial : "home");

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [page]);

  const soloModule = typeof window !== "undefined" ? (window as any).__rankproModule : undefined;
  if (soloModule) return <AgencyModule id={soloModule} />;

  const navigate = (p: Page) => {
    if (p === "dashboard") {
      setAppMode("platform");
    } else {
      setPage(p);
    }
  };

  if (appMode === "platform") {
    return (
      <AgencyDashboard onExit={() => {
        setAppMode("site");
        setPage("home");
      }} />
    );
  }

  // Full-page routes (no Shell wrapper)
  if (page === "login")            return <LoginPage setPage={navigate} />;
  if (page === "register")         return <RegisterPage setPage={navigate} />;
  if (page === "pms-hotelero")     return <PMSHoteleroPage setPage={navigate} />;
  if (page === "dpms-odontologico") return <DPMSOdontologicoPage setPage={navigate} />;
  if (page === "agencyos")          return <AgencyOSPage />;
  if (page === "agendar-admin")     return <AgendarAdminPage />;

  // Shell-wrapped routes
  const content = (() => {
    switch (page) {
      case "nosotros":          return <NosotrosPage setPage={navigate} />;
      case "servicios":         return <ServiciosPage setPage={navigate} />;
      case "it-automatizacion": return <ITAutomationPage setPage={navigate} />;
      case "service-detail":    return <ServiceDetailPage setPage={navigate} />;
      case "service-sem":       return <ServiceSEMPage setPage={navigate} />;
      case "service-sem-cta":   return <ServiceSEMConversionPage setPage={navigate} />;
      case "service-seo":       return <ServiceSEOPage setPage={navigate} />;
      case "service-seo-cta":   return <ServiceSEOConversionPage setPage={navigate} />;
      case "service-web":       return <ServiceWebPage setPage={navigate} />;
      case "service-web-cta":   return <ServiceWebConversionPage setPage={navigate} />;
      case "service-pagespeed": return <ServicePageSpeedPage setPage={navigate} />;
      case "service-analytics": return <ServiceAnalyticsPage setPage={navigate} />;
      case "service-social":    return <ServiceSocialPage setPage={navigate} />;
      case "productos":         return <ProductosPage setPage={navigate} />;
      case "promociones":       return <PromocionesPage setPage={navigate} />;
      case "agendar":           return <AgendarPage />;
      case "contacto":          return <ContactoPage />;
      case "blog":              return <BlogPage setPage={navigate} />;
      case "blog-post":         return <BlogPostPage setPage={navigate} />;
      case "faq":               return <FAQPage setPage={navigate} />;
      case "casos-de-exito":    return <CasosDeExitoPage setPage={navigate} />;
      case "privacidad":        return <PrivacidadPage />;
      case "terminos":          return <TerminosPage />;
      default:                  return <HomePage setPage={navigate} />;
    }
  })();

  return (
    <div style={{ backgroundColor: "#ffffff", minHeight: "100vh" }}>
      <Shell setPage={navigate}>{content}</Shell>
    </div>
  );
}
