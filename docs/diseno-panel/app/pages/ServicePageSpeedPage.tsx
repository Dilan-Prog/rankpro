import { useState } from "react";
import { Gauge, ChevronRight, ChevronDown, Rocket, Star, Info, ArrowRight } from "lucide-react";
import type { Page } from "../types";
import { testimonials } from "../data";
import { SectionHeader } from "../components/shared";

export default function ServicePageSpeedPage({ setPage }: { setPage: (p: Page) => void }) {
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  const faqs = [
    { q: "¿Por qué importa la velocidad de mi sitio web?", a: "Google usa la velocidad como factor de posicionamiento. Además, estudios de Google muestran que por cada segundo adicional de carga, las conversiones caen entre un 7% y 20%. Un sitio rápido gana en SEO y en ventas." },
    { q: "¿Qué es el score de PageSpeed Insights?", a: "Es una puntuación de 0 a 100 que Google asigna a tu sitio según las Core Web Vitals: LCP (carga), CLS (estabilidad visual) e INP (interactividad). Un score de 90+ en móvil es lo que perseguimos." },
    { q: "¿Cuánto tiempo tarda la optimización?", a: "Una auditoría inicial y las mejoras prioritarias se pueden implementar en 1-2 semanas. Las optimizaciones avanzadas (migraciones a CDN, server-side rendering) pueden tomar hasta 4 semanas dependiendo del stack." },
    { q: "¿Necesito cambiar mi sitio web completo?", a: "No necesariamente. La mayoría de los problemas de velocidad se corrigen sin rediseñar el sitio: optimización de imágenes, caching, minificación de código y configuración del servidor son los primeros pasos." },
  ];

  const process = [
    { step: "01", title: "Diagnóstico completo",       desc: "Medición de Core Web Vitals, PageSpeed score y análisis de waterfall para identificar los cuellos de botella." },
    { step: "02", title: "Plan de optimización",         desc: "Priorización de mejoras por impacto: imágenes, scripts, fuentes, caché y configuración del servidor." },
    { step: "03", title: "Implementación técnica",      desc: "Optimización de imágenes (WebP/AVIF), lazy loading, CDN, minificación y compresión Gzip/Brotli." },
    { step: "04", title: "Optimización avanzada",       desc: "Eliminación de render-blocking resources, preconnect, critical CSS y optimización de JavaScript." },
    { step: "05", title: "Verificación y monitoreo",    desc: "Nueva medición de scores, validación en dispositivos móviles y reales, y monitoreo continuo de Core Web Vitals." },
  ];

  return (
    <>
      <section className="bg-gradient-to-br from-[#0F9D6E] to-[#059669] py-20 px-4 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: "radial-gradient(circle at 70% 30%, white 1px, transparent 1px)", backgroundSize: "40px 40px" }} />
        <div className="max-w-6xl mx-auto relative z-10 grid lg:grid-cols-2 gap-12 items-center">
          <div>
            <button onClick={() => setPage("servicios")} className="flex items-center gap-1.5 text-emerald-200 hover:text-white text-sm mb-6 transition-colors">
              <ChevronRight size={14} className="rotate-180" />Volver a Servicios
            </button>
            <div className="inline-flex items-center gap-2 bg-white/20 text-white text-xs font-semibold px-4 py-2 rounded-full mb-5">
              <Gauge size={13} />PAGESPEED & PERFORMANCE
            </div>
            <h1 className="text-5xl font-extrabold text-white leading-tight mb-5">Tu sitio en 90+ de PageSpeed, garantizado</h1>
            <p className="text-emerald-100 text-lg leading-relaxed mb-8">Optimizamos la velocidad de tu sitio web para alcanzar las Core Web Vitals de Google y mejorar tu posicionamiento y tasa de conversión simultáneamente.</p>
            <button onClick={() => setPage("agendar")} className="flex items-center gap-2 px-6 py-3.5 bg-white text-[#0F9D6E] font-bold rounded-xl hover:bg-emerald-50 transition-colors shadow-lg">
              Medir mi sitio gratis<ArrowRight size={16} />
            </button>
          </div>
          <div className="grid grid-cols-2 gap-4">
            {[["90+","Score PageSpeed garantizado"],["3×","Velocidad de carga promedio"],["20%","Aumento en conversiones"],["< 1s","LCP objetivo en móvil"]].map(([v,l]) => (
              <div key={l} className="bg-white/10 backdrop-blur rounded-2xl p-6 border border-white/20 text-center">
                <div className="text-3xl font-extrabold text-white">{v}</div>
                <div className="text-emerald-200 text-sm mt-1">{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="py-20 px-4 bg-white">
        <div className="max-w-6xl mx-auto">
          <SectionHeader badge="NUESTRO PROCESO" badgeIcon={Rocket} title={<>De lento a <span className="text-[#0F9D6E]">velocidad de élite</span></>} />
          <div className="relative">
            <div className="absolute left-8 top-0 bottom-0 w-px bg-emerald-100 hidden lg:block" />
            <div className="space-y-6">
              {process.map((step) => (
                <div key={step.step} className="relative flex gap-6 lg:gap-10 items-start">
                  <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center shrink-0 shadow-lg shadow-emerald-200 relative z-10">
                    <span className="text-white font-extrabold text-lg">{step.step}</span>
                  </div>
                  <div className="bg-[#F5F7FA] rounded-2xl p-6 flex-1 border border-black/5 hover:shadow-md transition-shadow">
                    <h3 className="font-bold text-[#1A2332] text-lg mb-2">{step.title}</h3>
                    <p className="text-[#6B7280] text-sm leading-relaxed">{step.desc}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="py-16 px-4 bg-[#F5F7FA]">
        <div className="max-w-7xl mx-auto">
          <h2 className="text-3xl font-extrabold text-[#1A2332] text-center mb-10">Lo que dicen nuestros clientes</h2>
          <div className="grid md:grid-cols-3 gap-6">
            {testimonials.map((t) => (
              <div key={t.name} className="bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
                <div className="flex gap-1 mb-3">{Array.from({length: t.stars}).map((_,i) => <Star key={i} size={13} className="text-amber-400 fill-amber-400" />)}</div>
                <p className="text-[#1A2332] text-sm leading-relaxed mb-4">&ldquo;{t.text}&rdquo;</p>
                <div className="flex items-center gap-3">
                  <img src={t.photo} alt={t.name} className="w-10 h-10 rounded-full object-cover" />
                  <div><div className="font-bold text-[#1A2332] text-sm">{t.name}</div><div className="text-xs text-[#6B7280]">{t.role}</div></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="py-20 px-4 bg-white">
        <div className="max-w-3xl mx-auto">
          <SectionHeader badge="PREGUNTAS FRECUENTES" badgeIcon={Info} title={<>Resolvemos tus <span className="text-[#0F9D6E]">dudas sobre velocidad web</span></>} />
          <div className="space-y-3">
            {faqs.map((faq, i) => (
              <div key={i} className="border border-black/8 rounded-2xl overflow-hidden">
                <button onClick={() => setOpenFaq(openFaq === i ? null : i)}
                  className="w-full flex items-center justify-between p-5 text-left hover:bg-emerald-50 transition-colors">
                  <span className="font-semibold text-[#1A2332] text-sm pr-4">{faq.q}</span>
                  <ChevronDown size={18} className={`text-[#0F9D6E] shrink-0 transition-transform duration-200 ${openFaq === i ? "rotate-180" : ""}`} />
                </button>
                {openFaq === i && <div className="px-5 pb-5 text-sm text-[#6B7280] leading-relaxed border-t border-black/5 pt-4">{faq.a}</div>}
              </div>
            ))}
          </div>
          <div className="mt-10 text-center">
            <button onClick={() => setPage("agendar")} className="px-8 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:scale-105 transition-all duration-200">
              Auditar la velocidad de mi sitio
            </button>
          </div>
        </div>
      </section>
    </>
  );
}
