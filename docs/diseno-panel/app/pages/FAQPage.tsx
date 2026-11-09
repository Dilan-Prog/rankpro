import { useState } from "react";
import { Search, Target, BarChart2, DollarSign, Info, ArrowRight, MessageCircle, ChevronDown } from "lucide-react";
import type { Page } from "../types";
import { GreenBadge } from "../components/shared";

const faqCategories = [
  {
    category: "SEO & Posicionamiento",
    icon: Search,
    items: [
      { q: "¿Cuánto tiempo tarda en verse resultados con SEO?", a: "El SEO es una estrategia de mediano plazo. Los primeros movimientos en posiciones suelen notarse entre el mes 2 y 3, pero resultados sólidos y sostenibles se consolidan entre los 4 y 6 meses de trabajo constante. La velocidad depende de la competencia del sector y del estado técnico inicial del sitio." },
      { q: "¿En qué consiste una auditoría SEO técnica?", a: "Analizamos más de 200 factores: estructura de URLs, velocidad de carga, Core Web Vitals, errores de rastreo, etiquetas meta, datos estructurados, perfil de backlinks y experiencia de usuario. Al finalizar recibes un informe priorizado con las acciones de mayor impacto." },
      { q: "¿Garantizan el puesto #1 en Google?", a: "Ninguna agencia seria puede garantizar el puesto #1, ya que Google actualiza constantemente sus algoritmos. Lo que garantizamos es aplicar las mejores prácticas del mercado, seguimiento semanal de posiciones y estrategias probadas que han llevado a nuestros clientes al Top 3 en un promedio de 6 meses." },
    ],
  },
  {
    category: "Google Ads & SEM",
    icon: Target,
    items: [
      { q: "¿Cuál es el presupuesto mínimo recomendado?", a: "Para el mercado mexicano recomendamos un mínimo de $15,000 MXN/mes en pauta publicitaria para que los algoritmos de Google tengan suficientes datos para optimizar. A esto se suma la comisión de gestión de RankPro, que varía según el plan seleccionado." },
      { q: "¿Puedo pausar o cancelar mis campañas en cualquier momento?", a: "Sí, tienes control total sobre tu presupuesto y campañas. No manejamos contratos de permanencia forzosa. Puedes pausar, reducir o aumentar tu inversión con solo avisarnos con 5 días hábiles de anticipación." },
      { q: "¿Cómo miden el ROI de las campañas?", a: "Configuramos seguimiento de conversiones en Google Ads y GA4 para rastrear cada lead, llamada y venta originada por tus anuncios. Recibes un dashboard en tiempo real con métricas como CPC, CPL, ROAS y ROI real, no solo impresiones o clics." },
    ],
  },
  {
    category: "Planes y precios",
    icon: DollarSign,
    items: [
      { q: "¿Hay contrato de permanencia?", a: "No. Todos nuestros planes operan mes a mes sin contratos de permanencia. Creemos que nos debes contratar porque ves resultados, no porque estás atado a un papel. Eso nos obliga a rendir resultados todos los meses." },
      { q: "¿Qué incluye el onboarding inicial?", a: "El proceso de arranque incluye reunión de kick-off, acceso a cuentas, auditoría inicial del negocio y la competencia, configuración de seguimiento de conversiones, y definición de la estrategia para los primeros 90 días. Todo sin costo adicional." },
      { q: "¿Puedo cambiar de plan si mis necesidades crecen?", a: "Absolutamente. Puedes escalar o reducir tu plan en cualquier momento. Muchos clientes comienzan en el plan Starter y migran a Growth o Enterprise conforme crecen sus operaciones y presupuesto." },
    ],
  },
  {
    category: "Reportes y comunicación",
    icon: BarChart2,
    items: [
      { q: "¿Con qué frecuencia recibo reportes?", a: "Recibes un reporte semanal de métricas clave vía email, un reporte mensual ejecutivo completo en PDF, y acceso 24/7 a tu dashboard en tiempo real en RankDash Pro donde puedes ver el estado de tus campañas en cualquier momento." },
      { q: "¿Tengo un account manager dedicado?", a: "En los planes Growth y Enterprise tienes un account manager dedicado con quien puedes agendar videollamadas cuando lo necesites. En el plan Starter, la comunicación es por email y WhatsApp con un tiempo de respuesta de menos de 4 horas hábiles." },
    ],
  },
];

export default function FAQPage({ setPage }: { setPage: (p: Page) => void }) {
  const [openItem, setOpenItem] = useState<string | null>(null);

  return (
    <>
      <section className="bg-[#F5F7FA] py-20 px-4 border-b border-black/5">
        <div className="max-w-3xl mx-auto text-center">
          <GreenBadge><Info size={12} />PREGUNTAS FRECUENTES</GreenBadge>
          <h1 className="text-5xl font-extrabold text-[#1A2332] leading-tight mb-5">
            Resolvemos tus <span className="text-[#0F9D6E]">dudas</span>
          </h1>
          <p className="text-[#6B7280] text-lg leading-relaxed">
            Todo lo que necesitas saber sobre nuestros servicios, procesos y resultados antes de dar el primer paso.
          </p>
        </div>
      </section>

      <section className="py-20 px-4 bg-white">
        <div className="max-w-3xl mx-auto space-y-12">
          {faqCategories.map((cat) => (
            <div key={cat.category}>
              <div className="flex items-center gap-3 mb-6">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center">
                  <cat.icon size={18} className="text-white" />
                </div>
                <h2 className="text-xl font-extrabold text-[#1A2332]">{cat.category}</h2>
              </div>
              <div className="space-y-3">
                {cat.items.map((faq, i) => {
                  const key = `${cat.category}-${i}`;
                  const open = openItem === key;
                  return (
                    <div key={key} className="border border-black/8 rounded-2xl overflow-hidden">
                      <button
                        onClick={() => setOpenItem(open ? null : key)}
                        className="w-full flex items-center justify-between p-5 text-left hover:bg-emerald-50 transition-colors">
                        <span className="font-semibold text-[#1A2332] text-sm pr-4">{faq.q}</span>
                        <ChevronDown size={18} className={`text-[#0F9D6E] shrink-0 transition-transform duration-200 ${open ? "rotate-180" : ""}`} />
                      </button>
                      {open && (
                        <div className="px-5 pb-5 text-sm text-[#6B7280] leading-relaxed border-t border-black/5 pt-4">
                          {faq.a}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="py-16 px-4 bg-[#F5F7FA] border-t border-black/5">
        <div className="max-w-2xl mx-auto text-center">
          <h2 className="text-2xl font-extrabold text-[#1A2332] mb-3">¿No encontraste tu respuesta?</h2>
          <p className="text-[#6B7280] mb-7">Habla directamente con un experto. Sin costo, sin compromiso.</p>
          <div className="flex flex-wrap justify-center gap-4">
            <button onClick={() => setPage("agendar")}
              className="flex items-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:scale-105 transition-all duration-200">
              Agendar consultoría gratuita<ArrowRight size={15} />
            </button>
            <button onClick={() => setPage("contacto")}
              className="flex items-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-[#0F9D6E] border-2 border-[#0F9D6E] hover:bg-emerald-50 transition-colors">
              <MessageCircle size={15} />Escribir por WhatsApp
            </button>
          </div>
        </div>
      </section>
    </>
  );
}
