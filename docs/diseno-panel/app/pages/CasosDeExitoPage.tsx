import { useState } from "react";
import { Award, ArrowRight, Star } from "lucide-react";
import type { Page } from "../types";
import { GreenBadge } from "../components/shared";

const casos = [
  {
    client: "FashionMX",
    industry: "E-commerce · Moda",
    logo: "FM",
    color: "from-purple-500 to-pink-500",
    img: "https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?w=700&h=420&fit=crop&auto=format",
    challenge: "FashionMX tenía un sitio web lento, sin presencia orgánica y un costo por venta de $480 MXN a través de Meta Ads.",
    solution: "Rediseñamos el sitio en React con PageSpeed 94+, implementamos SEO de producto y lanzamos campañas Google Shopping optimizadas con IA.",
    results: [
      { metric: "Tráfico orgánico", value: "+312%", period: "en 6 meses" },
      { metric: "Costo por venta", value: "-61%", period: "de $480 → $186 MXN" },
      { metric: "Ventas online", value: "+180%", period: "vs año anterior" },
      { metric: "ROI Google Ads", value: "520%", period: "sostenido" },
    ],
    testimonial: "RankPro transformó nuestra presencia digital. En 4 meses triplicamos el tráfico orgánico.",
    author: "Sofía Ramírez · CEO, FashionMX",
    avatar: "https://images.unsplash.com/photo-1580489944761-15a19d654956?w=80&h=80&fit=crop&auto=format",
    services: ["SEO Técnico", "Google Shopping", "PageSpeed", "Desarrollo Web"],
  },
  {
    client: "TechCorp MX",
    industry: "SaaS · B2B",
    logo: "TC",
    color: "from-blue-500 to-cyan-500",
    img: "https://images.unsplash.com/photo-1552664730-d307ca884978?w=700&h=420&fit=crop&auto=format",
    challenge: "Costo por lead de $380 MXN, baja calidad de prospectos y sin visibilidad orgánica en búsquedas B2B críticas.",
    solution: "Estrategia ABM con Google Ads segmentado por empresa, SEO de contenido técnico y automatización de nurturing con HubSpot.",
    results: [
      { metric: "Costo por lead", value: "-40%", period: "de $380 → $228 MXN" },
      { metric: "Leads calificados", value: "3x más", period: "MQL mensuales" },
      { metric: "ROI Google Ads", value: "520%", period: "en 12 meses" },
      { metric: "Tráfico orgánico B2B", value: "+220%", period: "keywords de alto valor" },
    ],
    testimonial: "Llevamos 2 años con RankPro y los resultados hablan solos: 40% menos costo por adquisición.",
    author: "Carlos Mendoza · Director de Marketing, TechCorp MX",
    avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&auto=format",
    services: ["Google Ads B2B", "SEO de Contenido", "HubSpot", "Analytics"],
  },
  {
    client: "Clínica Lumina",
    industry: "Salud · Local SEO",
    logo: "CL",
    color: "from-emerald-500 to-teal-500",
    img: "https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=700&h=420&fit=crop&auto=format",
    challenge: "Apenas 5 citas nuevas al mes, sin presencia en Google Maps y competencia agresiva de clínicas establecidas en la zona.",
    solution: "SEO local completo, optimización de Google Business Profile, campañas de búsqueda local y landing pages por tratamiento.",
    results: [
      { metric: "Citas nuevas/mes", value: "60+", period: "de 5 → 60+ citas" },
      { metric: "Posición Google Maps", value: "Top 3", period: "en todas las keywords clave" },
      { metric: "Reseñas Google", value: "4.9★", period: "de 12 → 89 reseñas" },
      { metric: "Costo por cita", value: "$92 MXN", period: "altamente rentable" },
    ],
    testimonial: "Antes teníamos 5 citas nuevas al mes. Ahora gestionamos más de 60 gracias a la estrategia de RankPro.",
    author: "Ana Gutiérrez · Fundadora, Clínica Estética Lumina",
    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&h=80&fit=crop&auto=format",
    services: ["SEO Local", "Google Business", "Google Ads", "Landing Pages"],
  },
];

export default function CasosDeExitoPage({ setPage }: { setPage: (p: Page) => void }) {
  const [active, setActive] = useState(0);
  const caso = casos[active];

  return (
    <>
      <section className="bg-gradient-to-br from-[#0F9D6E] to-[#059669] py-20 px-4 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: "radial-gradient(circle at 70% 30%, white 1px, transparent 1px)", backgroundSize: "40px 40px" }} />
        <div className="max-w-4xl mx-auto text-center relative z-10">
          <GreenBadge><Award size={12} />CASOS DE ÉXITO</GreenBadge>
          <h1 className="text-5xl font-extrabold text-white leading-tight mb-5">
            Resultados reales de<br />clientes reales
          </h1>
          <p className="text-emerald-100 text-lg leading-relaxed max-w-2xl mx-auto">
            No solo prometemos crecimiento — lo documentamos. Conoce las estrategias y métricas detrás de nuestros casos más exitosos.
          </p>
          <div className="grid grid-cols-3 gap-6 mt-12 max-w-2xl mx-auto">
            {[["500+","Clientes activos"],["$120M+","En ventas generadas"],["98%","Tasa de retención"]].map(([v,l]) => (
              <div key={l} className="bg-white/10 backdrop-blur rounded-2xl p-5 border border-white/20 text-center">
                <div className="text-3xl font-extrabold text-white">{v}</div>
                <div className="text-emerald-200 text-sm mt-1">{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="py-20 px-4 bg-white">
        <div className="max-w-6xl mx-auto">
          <div className="flex flex-wrap gap-3 mb-12 justify-center">
            {casos.map((c, i) => (
              <button key={c.client} onClick={() => setActive(i)}
                className={`flex items-center gap-3 px-5 py-3 rounded-2xl border-2 font-semibold text-sm transition-all duration-200 ${
                  active === i
                    ? "border-[#0F9D6E] bg-emerald-50 text-[#0F9D6E]"
                    : "border-black/10 text-[#6B7280] hover:border-[#0F9D6E] hover:text-[#0F9D6E]"
                }`}>
                <div className={`w-8 h-8 rounded-lg bg-gradient-to-br ${c.color} flex items-center justify-center text-white text-xs font-bold`}>{c.logo}</div>
                <div className="text-left">
                  <div>{c.client}</div>
                  <div className="text-xs font-normal opacity-60">{c.industry}</div>
                </div>
              </button>
            ))}
          </div>

          <div className="grid lg:grid-cols-2 gap-12 items-start">
            <div>
              <img src={caso.img} alt={caso.client} className="w-full h-64 object-cover rounded-2xl shadow-xl shadow-black/5 mb-8" />
              <div className="flex flex-wrap gap-2 mb-6">
                {caso.services.map(s => (
                  <span key={s} className="text-xs font-semibold bg-emerald-50 text-[#0F9D6E] px-3 py-1.5 rounded-full">{s}</span>
                ))}
              </div>
              <div className="bg-[#F5F7FA] rounded-2xl p-6 border border-black/5">
                <div className="flex gap-1 mb-3">{Array.from({length:5}).map((_,i) => <Star key={i} size={14} className="text-amber-400 fill-amber-400" />)}</div>
                <p className="text-[#1A2332] text-sm leading-relaxed mb-4 italic">"&thinsp;{caso.testimonial}&thinsp;"</p>
                <div className="flex items-center gap-3">
                  <img src={caso.avatar} alt={caso.author} className="w-10 h-10 rounded-full object-cover" />
                  <span className="text-xs font-semibold text-[#6B7280]">{caso.author}</span>
                </div>
              </div>
            </div>

            <div className="space-y-6">
              <div>
                <span className="text-xs font-extrabold text-[#0F9D6E] uppercase tracking-widest">El reto</span>
                <p className="mt-2 text-[#6B7280] leading-relaxed">{caso.challenge}</p>
              </div>
              <div>
                <span className="text-xs font-extrabold text-[#0F9D6E] uppercase tracking-widest">La solución</span>
                <p className="mt-2 text-[#6B7280] leading-relaxed">{caso.solution}</p>
              </div>
              <div>
                <span className="text-xs font-extrabold text-[#0F9D6E] uppercase tracking-widest mb-4 block">Resultados</span>
                <div className="grid grid-cols-2 gap-4">
                  {caso.results.map(r => (
                    <div key={r.metric} className="bg-[#F5F7FA] rounded-2xl p-5 border border-black/5 hover:shadow-md transition-shadow">
                      <div className="text-2xl font-extrabold text-[#0F9D6E]">{r.value}</div>
                      <div className="font-semibold text-[#1A2332] text-sm mt-1">{r.metric}</div>
                      <div className="text-xs text-[#6B7280] mt-0.5">{r.period}</div>
                    </div>
                  ))}
                </div>
              </div>
              <button onClick={() => setPage("agendar")}
                className="flex items-center gap-2 w-full justify-center py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:scale-[1.02] transition-all duration-200">
                Quiero resultados similares<ArrowRight size={15} />
              </button>
            </div>
          </div>
        </div>
      </section>

      <section className="py-16 px-4 bg-[#F5F7FA] border-t border-black/5">
        <div className="max-w-4xl mx-auto text-center">
          <p className="text-xs font-extrabold text-[#6B7280] uppercase tracking-widest mb-6">Industrias que han confiado en RankPro</p>
          <div className="flex flex-wrap justify-center gap-3">
            {["E-commerce","SaaS · B2B","Salud","Hospitalidad","Educación","Real Estate","Legal","Restaurantes","Manufactura","Fintech"].map(ind => (
              <span key={ind} className="text-sm font-medium text-[#6B7280] bg-white border border-black/5 px-4 py-2 rounded-full hover:text-[#0F9D6E] hover:border-[#0F9D6E] transition-colors cursor-default">{ind}</span>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}
