import {
  Target, Search, Code2, Gauge, BarChart2, Users, Zap, Award,
  ArrowRight, CheckCircle, Star, Shield, Play, Phone, MessageCircle
} from "lucide-react";
import type { Page } from "../types";
import { globalStats, testimonials } from "../data";
import { GreenBadge, SectionHeader } from "../components/shared";

const heroCards = [
  { icon: Target,   title: "SEM · Ads",     desc: "Campañas que convierten desde el día uno", progress: 92 },
  { icon: Search,   title: "SEO Orgánico",  desc: "Top 3 en Google en menos de 6 meses",      progress: 87 },
  { icon: Code2,    title: "Web a Medida",  desc: "Sitios rápidos, bellos y funcionales",       progress: 95 },
  { icon: Gauge,    title: "PageSpeed 90+", desc: "Rendimiento técnico de élite",               progress: 94 },
];
const plans = [
  { name: "Starter",    price: "$8,900",   period: "/mes", desc: "Para PYMEs que inician su transformación", features: ["SEO On-Page básico","1 campaña Google Ads","Reporte mensual","Soporte por email","Auditoría inicial gratuita"], cta: "Comenzar ahora",   highlight: false },
  { name: "Growth",     price: "$18,500",  period: "/mes", desc: "Para empresas que quieren acelerar su crecimiento", features: ["SEO técnico avanzado","3 campañas multicanal","Dashboard en tiempo real","Soporte prioritario 24/7","Consultoría estratégica mensual","Gestión de redes sociales"], cta: "Elegir Growth",     highlight: true  },
  { name: "Enterprise", price: "A medida", period: "",     desc: "Soluciones personalizadas para grandes marcas",     features: ["Estrategia 360° completa","Campañas ilimitadas","Account Manager dedicado","Integración con CRM","Reportes personalizados","SLA garantizado"],                       cta: "Hablar con ventas", highlight: false },
];

function HeroSection({ setPage }: { setPage: (p: Page) => void }) {
  return (
    <section className="bg-white pt-16 pb-24 px-4 overflow-hidden">
      <div className="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
        <div className="space-y-7">
          <GreenBadge><Award size={13} />AGENCIA CERTIFICADA · MÉXICO</GreenBadge>
          <div>
            <h1 className="text-5xl xl:text-6xl font-extrabold text-[#1A2332] leading-[1.1] tracking-tight">
              Agencia de<br />Marketing <span className="text-[#0F9D6E]">Digital</span>
            </h1>
            <p className="mt-3 text-lg font-semibold text-[#10B981]">Tu Socio Estratégico para el Éxito Digital</p>
          </div>
          <p className="text-[#6B7280] text-base leading-relaxed max-w-lg">
            Impulsamos marcas mexicanas con Google Ads, SEO orgánico, desarrollo web y optimización de velocidad. Resultados medibles, crecimiento real.
          </p>
          <div className="flex flex-wrap gap-3">
            <button onClick={() => setPage("agendar")} className="flex items-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:shadow-xl hover:scale-105 transition-all duration-200">
              Agendar Consultoría Gratuita<ArrowRight size={16} />
            </button>
            <button onClick={() => setPage("contacto")} className="flex items-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-[#0F9D6E] border-2 border-[#0F9D6E] hover:bg-emerald-50 transition-all duration-200">
              <Phone size={15} />Escríbenos
            </button>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-black/5">
            {globalStats.map((s) => (
              <div key={s.label}>
                <div className="text-2xl font-extrabold text-[#0F9D6E]">{s.value}</div>
                <div className="text-xs text-[#6B7280] mt-0.5">{s.label}</div>
              </div>
            ))}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          {heroCards.map((card) => (
            <div key={card.title} className="bg-white rounded-2xl p-5 shadow-xl shadow-black/5 border border-black/5 hover:shadow-2xl hover:shadow-emerald-100 hover:-translate-y-1 transition-all duration-300">
              <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 flex items-center justify-center mb-3">
                <card.icon size={20} className="text-[#0F9D6E]" />
              </div>
              <div className="font-bold text-[#1A2332] text-sm">{card.title}</div>
              <div className="text-xs text-[#6B7280] mt-1 leading-relaxed">{card.desc}</div>
              <div className="mt-3">
                <div className="flex justify-between text-xs text-[#6B7280] mb-1">
                  <span>Rendimiento</span><span className="text-[#0F9D6E] font-semibold">{card.progress}%</span>
                </div>
                <div className="h-1.5 bg-emerald-50 rounded-full overflow-hidden">
                  <div className="h-full bg-gradient-to-r from-[#0F9D6E] to-[#34D399] rounded-full" style={{ width: `${card.progress}%` }} />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function PartnersBar() {
  const logos = ["Google Partner","Meta Business","HubSpot","Shopify","Semrush","Klaviyo"];
  return (
    <section className="bg-[#F5F7FA] py-10 px-4 border-y border-black/5">
      <div className="max-w-5xl mx-auto">
        <p className="text-center text-xs font-semibold text-[#6B7280] uppercase tracking-widest mb-7">Certificados y Partners Oficiales</p>
        <div className="flex flex-wrap items-center justify-center gap-8">
          {logos.map((n) => <div key={n} className="text-[#1A2332] font-bold text-sm opacity-40 hover:opacity-100 transition-opacity duration-200">{n}</div>)}
        </div>
      </div>
    </section>
  );
}

function ServicesGrid({ setPage }: { setPage: (p: Page) => void }) {
  const items = [
    { icon: Target,    title: "SEM & Google Ads",        desc: "Campañas de búsqueda, display y shopping para maximizar tu ROI.", tags: ["Google Ads","Shopping","Display"],  grad: "from-emerald-400 to-emerald-600" },
    { icon: Search,    title: "SEO Orgánico",             desc: "Estrategia de contenido, link building y SEO técnico para el Top 3.",          tags: ["On-Page","Off-Page","Técnico"],  grad: "from-teal-400 to-emerald-500" },
    { icon: Code2,     title: "Desarrollo Web",           desc: "Sitios web y tiendas online de alto rendimiento con diseño único.",           tags: ["React","Shopify","WordPress"],  grad: "from-green-400 to-emerald-500" },
    { icon: Gauge,     title: "PageSpeed & Core Web Vitals", desc: "Optimización técnica para alcanzar 90+ en Lighthouse.",                 tags: ["Performance","CWV","Lighthouse"], grad: "from-emerald-500 to-teal-500" },
    { icon: BarChart2, title: "Analytics & Data",        desc: "GA4, Tag Manager y dashboards para decisiones basadas en datos reales.",       tags: ["GA4","Looker Studio","GTM"],    grad: "from-teal-500 to-emerald-600" },
    { icon: Users,     title: "Social Media",             desc: "Gestión profesional de redes: contenido, pauta y crecimiento de comunidad.",  tags: ["Instagram","Facebook","LinkedIn"], grad: "from-emerald-400 to-teal-600" },
  ];
  return (
    <section className="py-24 px-4 bg-white">
      <div className="max-w-7xl mx-auto">
        <SectionHeader badge="NUESTROS SERVICIOS" badgeIcon={Zap}
          title={<>Todo lo que necesitas para <span className="text-[#0F9D6E]">dominar el mundo digital</span></>}
          sub="Combinamos estrategia, creatividad y tecnología para llevar tu marca al siguiente nivel en México." />
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {items.map((svc) => (
            <div key={svc.title} onClick={() => setPage("service-detail")}
              className="group bg-white border border-black/5 rounded-2xl p-7 hover:shadow-2xl hover:shadow-emerald-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
              <div className={`w-12 h-12 rounded-2xl bg-gradient-to-br ${svc.grad} flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300`}>
                <svc.icon size={22} className="text-white" />
              </div>
              <h3 className="font-bold text-[#1A2332] text-lg mb-2">{svc.title}</h3>
              <p className="text-[#6B7280] text-sm leading-relaxed mb-5">{svc.desc}</p>
              <div className="flex flex-wrap gap-2 mb-5">
                {svc.tags.map((t) => <span key={t} className="text-xs font-medium bg-emerald-50 text-[#0F9D6E] px-3 py-1 rounded-full">{t}</span>)}
              </div>
              <div className="flex items-center gap-1 text-[#0F9D6E] text-sm font-semibold group-hover:gap-2 transition-all duration-200">Ver más <ArrowRight size={14} /></div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function DashboardCTA({ setPage }: { setPage: (p: Page) => void }) {
  return (
    <section className="py-20 px-4 bg-[#F5F7FA]">
      <div className="max-w-7xl mx-auto">
        <div className="bg-gradient-to-br from-[#0F9D6E] to-[#34D399] rounded-3xl p-10 lg:p-16 relative overflow-hidden">
          <div className="absolute -top-20 -right-20 w-80 h-80 bg-white/10 rounded-full" />
          <div className="absolute -bottom-16 -left-16 w-60 h-60 bg-white/10 rounded-full" />
          <div className="relative grid lg:grid-cols-2 gap-10 items-center">
            <div>
              <p className="text-emerald-100 text-sm font-semibold uppercase tracking-widest mb-3">RankDash Pro</p>
              <h2 className="text-4xl font-extrabold text-white leading-tight mb-5">Gestiona todas tus campañas desde un solo lugar</h2>
              <p className="text-emerald-100 leading-relaxed mb-8">Panel centralizado con visibilidad total sobre SEO, Ads, redes sociales y analíticas. Reportes automáticos, alertas inteligentes.</p>
              <div className="flex flex-wrap gap-3">
                <button onClick={() => setPage("dashboard")} className="flex items-center gap-2 px-6 py-3.5 bg-white text-[#0F9D6E] font-bold rounded-xl hover:bg-emerald-50 transition-colors shadow-lg">
                  <Play size={16} />Ver demo gratuita
                </button>
                <button onClick={() => setPage("register")} className="flex items-center gap-2 px-6 py-3.5 bg-white/20 text-white font-semibold rounded-xl hover:bg-white/30 transition-colors border border-white/30">
                  Empezar gratis 14 días
                </button>
              </div>
            </div>
            <div className="bg-white/10 backdrop-blur rounded-2xl p-5 border border-white/20">
              <div className="flex items-center gap-2 mb-4">
                <div className="w-3 h-3 rounded-full bg-red-400" /><div className="w-3 h-3 rounded-full bg-yellow-400" /><div className="w-3 h-3 rounded-full bg-emerald-300" />
                <div className="ml-3 text-xs text-white/60 font-mono">rankpro.mx/dashboard</div>
              </div>
              <div className="space-y-3">
                {[
                  { label: "Posición SEO promedio", value: "#2.4", change: "↑ +0.8" },
                  { label: "ROI Google Ads", value: "420%", change: "↑ +35%" },
                  { label: "Tráfico orgánico/mes", value: "42,891", change: "↑ +18%" },
                  { label: "Costo por lead", value: "$85 MXN", change: "↓ -22%" },
                ].map((row) => (
                  <div key={row.label} className="flex items-center justify-between bg-white/10 rounded-xl px-4 py-3">
                    <span className="text-white/80 text-xs">{row.label}</span>
                    <div className="flex items-center gap-3">
                      <span className="text-white font-bold text-sm">{row.value}</span>
                      <span className="text-xs font-medium text-emerald-200">{row.change}</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function TestimonialsSection() {
  return (
    <section className="py-24 px-4 bg-white">
      <div className="max-w-7xl mx-auto">
        <SectionHeader badge="CASOS DE ÉXITO" badgeIcon={Star}
          title={<>Lo que dicen nuestros <span className="text-[#0F9D6E]">clientes</span></>} />
        <div className="grid md:grid-cols-3 gap-6">
          {testimonials.map((t) => (
            <div key={t.name} className="bg-[#F5F7FA] rounded-2xl p-7 hover:shadow-lg hover:shadow-emerald-50 hover:-translate-y-1 transition-all duration-300 border border-black/5">
              <div className="flex gap-1 mb-4">{Array.from({ length: t.stars }).map((_, i) => <Star key={i} size={14} className="text-amber-400 fill-amber-400" />)}</div>
              <p className="text-[#1A2332] text-sm leading-relaxed mb-6">&ldquo;{t.text}&rdquo;</p>
              <div className="flex items-center gap-3">
                <img src={t.photo} alt={t.name} className="w-11 h-11 rounded-full object-cover bg-emerald-100" />
                <div><div className="font-bold text-[#1A2332] text-sm">{t.name}</div><div className="text-xs text-[#6B7280]">{t.role}</div></div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function PricingSection({ setPage }: { setPage: (p: Page) => void }) {
  return (
    <section className="py-24 px-4 bg-[#F5F7FA]">
      <div className="max-w-6xl mx-auto">
        <SectionHeader badge="PLANES Y PRECIOS" badgeIcon={Shield}
          title="Invierte en crecimiento real"
          sub="Sin contratos de permanencia. Resultados medibles desde el primer mes." />
        <div className="grid md:grid-cols-3 gap-6 items-start">
          {plans.map((plan) => (
            <div key={plan.name}
              className={`rounded-2xl p-8 border transition-all duration-300 ${plan.highlight ? "bg-gradient-to-b from-[#0F9D6E] to-[#059669] border-transparent text-white shadow-2xl shadow-emerald-200 scale-105" : "bg-white border-black/5 hover:shadow-xl hover:shadow-emerald-50 hover:-translate-y-1"}`}>
              {plan.highlight && <div className="inline-flex items-center gap-1 bg-white/20 text-white text-xs font-semibold px-3 py-1.5 rounded-full mb-4"><Star size={11} className="fill-white" />MÁS POPULAR</div>}
              <h3 className={`text-xl font-bold mb-1 ${plan.highlight ? "text-white" : "text-[#1A2332]"}`}>{plan.name}</h3>
              <div className={`flex items-baseline gap-1 mb-2 ${plan.highlight ? "text-white" : "text-[#1A2332]"}`}>
                <span className="text-3xl font-extrabold">{plan.price}</span>
                <span className={`text-sm font-medium ${plan.highlight ? "text-emerald-200" : "text-[#6B7280]"}`}>{plan.period}</span>
              </div>
              <p className={`text-sm mb-6 leading-relaxed ${plan.highlight ? "text-emerald-100" : "text-[#6B7280]"}`}>{plan.desc}</p>
              <div className="space-y-3 mb-8">
                {plan.features.map((f) => (
                  <div key={f} className="flex items-start gap-2.5">
                    <CheckCircle size={15} className={`shrink-0 mt-0.5 ${plan.highlight ? "text-emerald-200" : "text-[#0F9D6E]"}`} />
                    <span className={`text-sm ${plan.highlight ? "text-emerald-50" : "text-[#6B7280]"}`}>{f}</span>
                  </div>
                ))}
              </div>
              <button onClick={() => setPage("agendar")}
                className={`w-full py-3.5 rounded-xl font-semibold text-sm transition-all duration-200 ${plan.highlight ? "bg-white text-[#0F9D6E] hover:bg-emerald-50" : "bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white hover:shadow-lg hover:shadow-emerald-200"}`}>
                {plan.cta}
              </button>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

export default function HomePage({ setPage }: { setPage: (p: Page) => void }) {
  return (
    <>
      <HeroSection setPage={setPage} />
      <PartnersBar />
      <ServicesGrid setPage={setPage} />
      <DashboardCTA setPage={setPage} />
      <TestimonialsSection />
      <PricingSection setPage={setPage} />
      <section className="py-20 px-4 bg-white">
        <div className="max-w-3xl mx-auto text-center">
          <h2 className="text-4xl font-extrabold text-[#1A2332] mb-4">¿Listo para transformar tu presencia digital?</h2>
          <p className="text-[#6B7280] mb-8 leading-relaxed">Agenda una consultoría gratuita de 30 minutos con nuestros expertos.</p>
          <div className="flex flex-wrap justify-center gap-4">
            <button onClick={() => setPage("agendar")} className="flex items-center gap-2 px-8 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-xl shadow-emerald-200 hover:scale-105 transition-all duration-200">
              Agendar Consultoría Gratuita<ArrowRight size={17} />
            </button>
            <button onClick={() => setPage("contacto")} className="flex items-center gap-2 px-8 py-4 rounded-xl font-semibold text-[#0F9D6E] border-2 border-[#0F9D6E] hover:bg-emerald-50 transition-colors">
              <MessageCircle size={17} />Escribir por WhatsApp
            </button>
          </div>
        </div>
      </section>
    </>
  );
}
