import { useState } from "react";
import { Target, ChevronRight, ChevronDown, Rocket, Star, Info, ArrowRight } from "lucide-react";
import type { Page } from "../types";
import { testimonials } from "../data";
import { SectionHeader } from "../components/shared";

export default function ServiceDetailPage({ setPage }: { setPage: (p: Page) => void }) {
  const faqs = [
    { q: "¿Cuánto tiempo tarda en verse resultados con Google Ads?", a: "Los primeros resultados son casi inmediatos: en 24–72 horas tus anuncios ya aparecen. Sin embargo, para alcanzar un ROI óptimo, recomendamos entre 60 y 90 días de optimización continua." },
    { q: "¿Cuál es el presupuesto mínimo recomendado para campañas?", a: "Para el mercado mexicano recomendamos un mínimo de $15,000 MXN/mes en pauta publicitaria para que los algoritmos de Google tengan suficientes datos para optimizar." },
    { q: "¿Puedo pausar las campañas cuando quiera?", a: "Sí, tienes control total. No manejamos contratos de permanencia forzosa. Tu presupuesto y estrategia se adaptan a tus necesidades en todo momento." },
    { q: "¿Qué reportes recibiré?", a: "Recibirás un reporte semanal de métricas clave y uno mensual ejecutivo completo, además de acceso 24/7 a tu dashboard en tiempo real en RankDash Pro." },
  ];

  const [openFaq, setOpenFaq] = useState<number | null>(null);

  const process = [
    { step: "01", title: "Auditoría inicial",      desc: "Analizamos tu negocio, competidores y oportunidades de mercado para definir la estrategia ganadora." },
    { step: "02", title: "Configuración",          desc: "Configuramos y optimizamos todas las cuentas, píxeles de seguimiento y estructura de campañas." },
    { step: "03", title: "Lanzamiento",            desc: "Activamos las campañas con creatividades probadas y comenzamos la fase de aprendizaje de los algoritmos." },
    { step: "04", title: "Optimización continua",  desc: "Monitoreamos, testamos y ajustamos diariamente para maximizar el ROI y reducir el costo por conversión." },
    { step: "05", title: "Reportes y escalado",    desc: "Revisiones mensuales para escalar lo que funciona y pivotar estrategia basándonos en datos reales." },
  ];

  return (
    <>
      {/* Hero */}
      <section className="bg-gradient-to-br from-[#0F9D6E] to-[#059669] py-20 px-4 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: "radial-gradient(circle at 70% 30%, white 1px, transparent 1px)", backgroundSize: "40px 40px" }} />
        <div className="max-w-6xl mx-auto relative z-10 grid lg:grid-cols-2 gap-12 items-center">
          <div>
            <button onClick={() => setPage("servicios")} className="flex items-center gap-1.5 text-emerald-200 hover:text-white text-sm mb-6 transition-colors">
              <ChevronRight size={14} className="rotate-180" />Volver a Servicios
            </button>
            <div className="inline-flex items-center gap-2 bg-white/20 text-white text-xs font-semibold px-4 py-2 rounded-full mb-5">
              <Target size={13} />SEM & GOOGLE ADS
            </div>
            <h1 className="text-5xl font-extrabold text-white leading-tight mb-5">Campañas de Google Ads que generan ventas reales</h1>
            <p className="text-emerald-100 text-lg leading-relaxed mb-8">Diseñamos, lanzamos y optimizamos campañas de pago en Google con un enfoque 100% orientado al retorno de inversión. Sin presupuesto desperdiciado.</p>
            <div className="flex flex-wrap gap-3">
              <button onClick={() => setPage("agendar")} className="flex items-center gap-2 px-6 py-3.5 bg-white text-[#0F9D6E] font-bold rounded-xl hover:bg-emerald-50 transition-colors shadow-lg">
                Solicitar propuesta gratuita<ArrowRight size={16} />
              </button>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            {[["420%","ROI promedio"],["35%","Reducción de CPC"],["72h","Primeros resultados"],["24/7","Monitoreo activo"]].map(([v,l]) => (
              <div key={l} className="bg-white/10 backdrop-blur rounded-2xl p-6 border border-white/20 text-center">
                <div className="text-3xl font-extrabold text-white">{v}</div>
                <div className="text-emerald-200 text-sm mt-1">{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Process */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-6xl mx-auto">
          <SectionHeader badge="NUESTRO PROCESO" badgeIcon={Rocket} title={<>Cómo llevamos tu campaña al <span className="text-[#0F9D6E]">siguiente nivel</span></>} />
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

      {/* Testimonials */}
      <section className="py-16 px-4 bg-[#F5F7FA]">
        <div className="max-w-7xl mx-auto">
          <h2 className="text-3xl font-extrabold text-[#1A2332] text-center mb-10">Resultados que hablan por sí solos</h2>
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

      {/* FAQ */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-3xl mx-auto">
          <SectionHeader badge="PREGUNTAS FRECUENTES" badgeIcon={Info} title={<>Resolvemos tus <span className="text-[#0F9D6E]">dudas</span></>} />
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
              Solicitar propuesta sin costo
            </button>
          </div>
        </div>
      </section>
    </>
  );
}
