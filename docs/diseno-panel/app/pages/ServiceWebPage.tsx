import { useState } from "react";
import { Code2, ChevronRight, ChevronDown, Rocket, Star, Info, ArrowRight } from "lucide-react";
import type { Page } from "../types";
import { testimonials } from "../data";
import { SectionHeader } from "../components/shared";

export default function ServiceWebPage({ setPage }: { setPage: (p: Page) => void }) {
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  const faqs = [
    { q: "¿Cuánto tiempo tarda en desarrollarse un sitio web?", a: "Un sitio web corporativo tarda entre 4 y 8 semanas. Una tienda en línea o un proyecto más complejo puede tomar entre 8 y 16 semanas, dependiendo del alcance y la disponibilidad del cliente para revisiones." },
    { q: "¿Qué tecnologías utilizan?", a: "Desarrollamos principalmente con React y Next.js para proyectos modernos de alto rendimiento. También trabajamos con Shopify para e-commerce y WordPress para sitios de contenido. Siempre elegimos la tecnología más adecuada para tus objetivos." },
    { q: "¿Incluyen el diseño UI/UX?", a: "Sí. Nuestro proceso inicia con la fase de diseño: definición de arquitectura de información, wireframes y diseño visual completo en Figma antes de escribir una sola línea de código." },
    { q: "¿Me dan soporte después de que el sitio esté listo?", a: "Sí, ofrecemos planes de mantenimiento mensual que incluyen actualizaciones, correcciones, monitoreo de uptime y soporte técnico por email y WhatsApp." },
  ];

  const process = [
    { step: "01", title: "Discovery y estrategia", desc: "Definimos objetivos, audiencia, arquitectura de información y KPIs de conversión." },
    { step: "02", title: "Diseño UI/UX",            desc: "Wireframes y diseño visual completo en Figma. Aprobación antes de desarrollo." },
    { step: "03", title: "Desarrollo",               desc: "Codificación con React/Next.js o la tecnología más adecuada. Código limpio y escalable." },
    { step: "04", title: "Pruebas y optimización",   desc: "QA, pruebas de velocidad, compatibilidad de navegadores y dispositivos." },
    { step: "05", title: "Lanzamiento y soporte",    desc: "Publicación, configuración de dominios y traspaso con capacitación al equipo." },
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
              <Code2 size={13} />DESARROLLO WEB A MEDIDA
            </div>
            <h1 className="text-5xl font-extrabold text-white leading-tight mb-5">Sitios web rápidos, bellos y que convierten</h1>
            <p className="text-emerald-100 text-lg leading-relaxed mb-8">Diseñamos y desarrollamos sitios web y tiendas en línea de alto rendimiento con diseño único. Desde el concepto hasta el lanzamiento.</p>
            <button onClick={() => setPage("agendar")} className="flex items-center gap-2 px-6 py-3.5 bg-white text-[#0F9D6E] font-bold rounded-xl hover:bg-emerald-50 transition-colors shadow-lg">
              Cotizar mi proyecto<ArrowRight size={16} />
            </button>
          </div>
          <div className="grid grid-cols-2 gap-4">
            {[["+120%","Conversiones promedio"],["90+","Score PageSpeed"],["< 2s","Tiempo de carga"],["100%","Proyectos entregados a tiempo"]].map(([v,l]) => (
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
          <SectionHeader badge="NUESTRO PROCESO" badgeIcon={Rocket} title={<>De la idea al sitio <span className="text-[#0F9D6E]">en producción</span></>} />
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
          <SectionHeader badge="PREGUNTAS FRECUENTES" badgeIcon={Info} title={<>Resolvemos tus <span className="text-[#0F9D6E]">dudas sobre desarrollo web</span></>} />
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
              Cotizar mi proyecto web
            </button>
          </div>
        </div>
      </section>
    </>
  );
}
