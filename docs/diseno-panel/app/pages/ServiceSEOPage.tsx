import { useState } from "react";
import { Search, ChevronRight, ChevronDown, Rocket, Star, Info, ArrowRight } from "lucide-react";
import type { Page } from "../types";
import { testimonials } from "../data";
import { SectionHeader } from "../components/shared";

export default function ServiceSEOPage({ setPage }: { setPage: (p: Page) => void }) {
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  const faqs = [
    { q: "¿Cuánto tiempo tarda el SEO en dar resultados?", a: "Los primeros movimientos en posiciones suelen notarse entre el mes 2 y 3, pero resultados sólidos y sostenibles se consolidan entre los 4 y 6 meses. La velocidad depende de la competencia del sector y el estado técnico inicial del sitio." },
    { q: "¿Qué incluye la auditoría técnica SEO?", a: "Analizamos más de 200 factores: estructura de URLs, velocidad de carga, Core Web Vitals, errores de rastreo, etiquetas meta, datos estructurados y perfil de backlinks. Recibes un informe priorizado con las acciones de mayor impacto." },
    { q: "¿Garantizan el puesto #1 en Google?", a: "Ninguna agencia seria puede garantizar el #1 ya que Google actualiza constantemente sus algoritmos. Garantizamos aplicar las mejores prácticas y estrategias que han llevado a nuestros clientes al Top 3 en un promedio de 6 meses." },
    { q: "¿El SEO local es diferente al SEO nacional?", a: "Sí. El SEO local prioriza aparecer en búsquedas geolocalizadas y en Google Maps dentro de tu ciudad o zona. Incluye optimización del perfil de Google Business, citations locales y keywords con intención local." },
  ];

  const process = [
    { step: "01", title: "Auditoría técnica",      desc: "Análisis completo de más de 200 factores técnicos, de contenido y de autoridad de tu sitio web." },
    { step: "02", title: "Estrategia de keywords", desc: "Investigación de palabras clave con alto volumen e intención de compra para tu sector en México." },
    { step: "03", title: "Optimización on-page",   desc: "Mejora de meta etiquetas, estructura de contenido, velocidad y datos estructurados." },
    { step: "04", title: "Link building",           desc: "Construcción de enlaces de alta calidad mediante contenido, alianzas y menciones editoriales." },
    { step: "05", title: "Seguimiento y reporte",  desc: "Monitoreo semanal de posiciones, tráfico orgánico y conversiones con reporte mensual ejecutivo." },
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
              <Search size={13} />SEO ORGÁNICO
            </div>
            <h1 className="text-5xl font-extrabold text-white leading-tight mb-5">Posiciónate en el Top 3 de Google de forma orgánica</h1>
            <p className="text-emerald-100 text-lg leading-relaxed mb-8">Estrategia SEO completa: técnica, contenido y link building adaptada al mercado mexicano. Tráfico de calidad que llega solo.</p>
            <button onClick={() => setPage("agendar")} className="flex items-center gap-2 px-6 py-3.5 bg-white text-[#0F9D6E] font-bold rounded-xl hover:bg-emerald-50 transition-colors shadow-lg">
              Solicitar auditoría gratuita<ArrowRight size={16} />
            </button>
          </div>
          <div className="grid grid-cols-2 gap-4">
            {[["Top 3","Posición promedio en 6 meses"],["+180%","Tráfico orgánico"],["200+","Factores auditados"],["24/7","Seguimiento de posiciones"]].map(([v,l]) => (
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
          <SectionHeader badge="NUESTRO PROCESO" badgeIcon={Rocket} title={<>Cómo llevamos tu sitio al <span className="text-[#0F9D6E]">Top de Google</span></>} />
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
          <SectionHeader badge="PREGUNTAS FRECUENTES" badgeIcon={Info} title={<>Resolvemos tus <span className="text-[#0F9D6E]">dudas sobre SEO</span></>} />
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
              Solicitar auditoría SEO gratuita
            </button>
          </div>
        </div>
      </section>
    </>
  );
}
