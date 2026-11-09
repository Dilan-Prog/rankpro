import { Target, Search, Code2, Gauge, BarChart2, Users, Workflow, Zap, CheckCircle, ArrowRight } from "lucide-react";
import type { Page } from "../types";
import { GreenBadge } from "../components/shared";

export default function ServiciosPage({ setPage }: { setPage: (p: Page) => void }) {
  const detailed = [
    { icon: Target,    title: "SEM & Google Ads",         grad: "from-emerald-400 to-emerald-600", img: "https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=500&h=300&fit=crop&auto=format", features: ["Campañas de búsqueda, display y shopping","Remarketing dinámico","Optimización de pujas con IA","A/B testing de creatividades","Reportes semanales detallados"], result: "ROI promedio 420% · CPC reducido 35%" },
    { icon: Search,    title: "SEO Orgánico",              grad: "from-teal-400 to-emerald-500",    img: "https://images.unsplash.com/photo-1432888498266-38ffec3eaf0a?w=500&h=300&fit=crop&auto=format", features: ["Auditoría técnica completa","Estrategia de contenido y keywords","Link building white hat","SEO local para México","Seguimiento de posiciones 24/7"],  result: "Top 3 en 6 meses · +180% tráfico orgánico" },
    { icon: Code2,     title: "Desarrollo Web a Medida",  grad: "from-green-400 to-emerald-500",   img: "https://images.unsplash.com/photo-1547658719-da2b51169166?w=500&h=300&fit=crop&auto=format", features: ["Diseño UI/UX personalizado","Desarrollo React / Next.js","Tiendas Shopify y WooCommerce","Integración con CRM y ERP","Mantenimiento y soporte continuo"],  result: "Conversiones +120% · Carga < 2 seg" },
    { icon: Gauge,     title: "PageSpeed & Performance",  grad: "from-emerald-500 to-teal-500",    img: "https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=500&h=300&fit=crop&auto=format", features: ["Análisis Lighthouse / CWV","Optimización de imágenes y assets","CDN y caché avanzado","Lazy loading y code splitting","Garantía de 90+ en PageSpeed"],   result: "Core Web Vitals aprobados · 90+ Score" },
    { icon: BarChart2, title: "Analytics & Business Intelligence", grad: "from-teal-500 to-emerald-600", img: "https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=500&h=300&fit=crop&auto=format", features: ["Implementación GA4 y GTM","Dashboards en Looker Studio","Seguimiento de conversiones","Segmentación de audiencias","Análisis de funnel y comportamiento"], result: "Visibilidad 100% de tu embudo" },
    { icon: Users,     title: "Social Media Management", grad: "from-emerald-400 to-teal-600",    img: "https://images.unsplash.com/photo-1611162616305-c69b3fa7fbe0?w=500&h=300&fit=crop&auto=format", features: ["Estrategia de contenido mensual","Diseño gráfico y video","Community management","Campañas pagadas Meta & TikTok","Reportes de crecimiento mensual"],  result: "+320% alcance orgánico · 5x engagement" },
    { icon: Workflow,  title: "Automatización de Procesos", grad: "from-emerald-500 to-green-400", img: "https://images.unsplash.com/photo-1518186285589-2f7649de83e0?w=500&h=300&fit=crop&auto=format", features: ["Mapeo de procesos y cuellos de botella","Flujos automáticos entre tus sistemas","Integración de CRM, ERP y facturación","Bots de WhatsApp y correo con IA","Tableros de seguimiento en tiempo real"], result: "Hasta 32 h/mes recuperadas por equipo" },
  ];

  return (
    <>
      <section className="bg-[#F5F7FA] py-20 px-4 border-b border-black/5">
        <div className="max-w-4xl mx-auto text-center">
          <GreenBadge><Zap size={12} />SERVICIOS COMPLETOS</GreenBadge>
          <h1 className="text-5xl font-extrabold text-[#1A2332] leading-tight mb-5">Servicios de Marketing Digital<br /><span className="text-[#0F9D6E]">diseñados para México</span></h1>
          <p className="text-[#6B7280] text-lg leading-relaxed">Cada servicio está desarrollado con metodologías probadas y adaptado a las particularidades del mercado digital mexicano.</p>
        </div>
      </section>

      <section className="py-16 px-4 bg-white">
        <div className="max-w-7xl mx-auto space-y-12">
          {detailed.map((svc, i) => (
            <div key={svc.title} className={`grid lg:grid-cols-2 gap-10 items-center ${i % 2 === 1 ? "lg:flex-row-reverse" : ""}`}>
              <div className={i % 2 === 1 ? "lg:order-2" : ""}>
                <div className={`w-14 h-14 rounded-2xl bg-gradient-to-br ${svc.grad} flex items-center justify-center mb-5`}>
                  <svc.icon size={26} className="text-white" />
                </div>
                <h2 className="text-3xl font-extrabold text-[#1A2332] mb-4">{svc.title}</h2>
                <div className="space-y-3 mb-6">
                  {svc.features.map((f) => (
                    <div key={f} className="flex items-center gap-2.5">
                      <CheckCircle size={16} className="text-[#0F9D6E] shrink-0" />
                      <span className="text-[#6B7280] text-sm">{f}</span>
                    </div>
                  ))}
                </div>
                <div className="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 mb-6">
                  <span className="text-xs font-semibold text-[#0F9D6E] uppercase tracking-wide">Resultado típico: </span>
                  <span className="text-sm text-[#1A2332] font-medium">{svc.result}</span>
                </div>
                <button onClick={() => setPage("agendar")} className="flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-md shadow-emerald-200 hover:shadow-lg hover:scale-105 transition-all duration-200">
                  Consultar este servicio<ArrowRight size={15} />
                </button>
              </div>
              <div className={i % 2 === 1 ? "lg:order-1" : ""}>
                <img src={svc.img} alt={svc.title} className="rounded-2xl w-full object-cover shadow-xl shadow-black/5 h-64 lg:h-80" />
              </div>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}
