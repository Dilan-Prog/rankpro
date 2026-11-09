import {
  LayoutDashboard, PieChart, Shield, Zap, BedDouble, Smile, Package, CheckCircle, ArrowRight, X
} from "lucide-react";
import type { Page } from "../types";
import { GreenBadge } from "../components/shared";

export default function ProductosPage({ setPage }: { setPage: (p: Page) => void }) {
  const prods = [
    { icon: LayoutDashboard, title: "RankDash Pro", tag: "PLATAFORMA PRINCIPAL", img: "https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=700&h=400&fit=crop&auto=format", desc: "Panel de control centralizado para gestionar todas tus campañas digitales en un solo lugar. Integra Google Ads, SEO, redes sociales y analíticas con reportes automáticos.", features: ["Dashboard en tiempo real","Alertas inteligentes por IA","Reportes white-label para clientes","Integración con +40 plataformas","Multi-cuenta y multi-usuario"], price: "Desde $1,200 MXN/mes", grad: "from-[#0F9D6E] to-[#34D399]", primary: true },
    { icon: PieChart,        title: "Analytics Suite", tag: "ANALÍTICA AVANZADA", img: "https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=700&h=400&fit=crop&auto=format", desc: "Suite completa de business intelligence para marketing digital. Conecta todas tus fuentes de datos y genera insights accionables automáticamente.", features: ["Atribución multicanal","Segmentación de audiencias","Análisis predictivo","Exportación a Google Sheets","API para integraciones custom"], price: "Desde $890 MXN/mes", grad: "from-teal-500 to-emerald-500", primary: false },
    { icon: Shield,          title: "SEO Audit Tool", tag: "AUDITORÍA TÉCNICA", img: "https://images.unsplash.com/photo-1432888498266-38ffec3eaf0a?w=700&h=400&fit=crop&auto=format", desc: "Herramienta de auditoría SEO técnica con más de 200 factores de análisis. Detecta problemas, prioriza mejoras y monitorea tu posicionamiento en tiempo real.", features: ["200+ factores de auditoría","Rastreo de posiciones diario","Análisis de backlinks","Detección de errores técnicos","Reportes PDF automáticos"], price: "Desde $590 MXN/mes", grad: "from-green-500 to-emerald-600", primary: false },
    { icon: Zap,             title: "Ads Manager",    tag: "GESTIÓN DE ANUNCIOS", img: "https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=700&h=400&fit=crop&auto=format", desc: "Plataforma centralizada para gestionar y optimizar campañas en Google, Meta, TikTok y LinkedIn desde un único panel. Reglas automáticas y alertas inteligentes.", features: ["Google + Meta + TikTok + LinkedIn","Reglas de optimización automáticas","Alertas de presupuesto","A/B testing integrado","ROI por canal y campaña"], price: "Desde $750 MXN/mes", grad: "from-emerald-400 to-teal-600", primary: false },
      { icon: BedDouble,       title: "PMS Hotelero",   tag: "GESTIÓN HOTELERA", img: "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=700&h=400&fit=crop&auto=format", desc: "Sistema de gestión hotelera (PMS) todo en uno diseñado para hoteles boutique, cadenas y resorts en México. Centraliza reservas, housekeeping, facturación y análisis de ocupación desde un panel intuitivo.", features: ["Motor de reservas en tiempo real","Control de habitaciones y housekeeping","Facturación y punto de venta integrado","Channel manager (Booking, Expedia, Airbnb)","Reportes de ocupación y RevPAR","App móvil para staff"], price: "Desde $2,400 MXN/mes", grad: "from-blue-500 to-emerald-500", primary: false },
    { icon: Smile,           title: "DPMS Odontológico", tag: "SOFTWARE DENTAL", img: "https://images.unsplash.com/photo-1588776814546-1ffbb3cd4e73?w=700&h=400&fit=crop&auto=format", desc: "Software de gestión clínica (DPMS) diseñado exclusivamente para consultorios y clínicas odontológicas en México. Administra pacientes, citas, expedientes digitales, radiografías y cobros desde un solo sistema.", features: ["Expediente clínico digital (odontograma)","Agenda inteligente con recordatorios SMS/WhatsApp","Radiografías y archivos DICOM integrados","Facturación electrónica CFDI 4.0","Control de inventario de materiales","App para pacientes con historial y citas"], price: "Desde $1,800 MXN/mes", grad: "from-cyan-500 to-teal-500", primary: false },
  ];

  return (
    <>
      <section className="bg-[#F5F7FA] py-20 px-4 border-b border-black/5">
        <div className="max-w-4xl mx-auto text-center">
          <GreenBadge><Package size={12} />NUESTROS PRODUCTOS</GreenBadge>
          <h1 className="text-5xl font-extrabold text-[#1A2332] leading-tight mb-5">Herramientas digitales<br /><span className="text-[#0F9D6E]">para resultados reales</span></h1>
          <p className="text-[#6B7280] text-lg leading-relaxed">Software propio desarrollado por y para agencias de marketing digital en México. Prueba gratuita de 14 días, sin tarjeta de crédito.</p>
        </div>
      </section>

      <section className="py-16 px-4 bg-white">
        <div className="max-w-7xl mx-auto space-y-20">
          {prods.map((prod, i) => (
            <div key={prod.title} className={`grid lg:grid-cols-2 gap-14 items-center ${i % 2 === 1 ? "" : ""}`}>
              <div className={i % 2 === 1 ? "lg:order-2" : ""}>
                <span className={`inline-block text-xs font-extrabold tracking-widest text-[#0F9D6E] bg-emerald-50 px-4 py-2 rounded-full mb-4`}>{prod.tag}</span>
                <div className={`w-14 h-14 rounded-2xl bg-gradient-to-br ${prod.grad} flex items-center justify-center mb-5`}>
                  <prod.icon size={26} className="text-white" />
                </div>
                <h2 className="text-3xl font-extrabold text-[#1A2332] mb-4">{prod.title}</h2>
                <p className="text-[#6B7280] leading-relaxed mb-6">{prod.desc}</p>
                <div className="space-y-3 mb-6">
                  {prod.features.map((f) => (
                    <div key={f} className="flex items-center gap-2.5">
                      <CheckCircle size={15} className="text-[#0F9D6E] shrink-0" />
                      <span className="text-[#6B7280] text-sm">{f}</span>
                    </div>
                  ))}
                </div>
                <div className="flex items-center gap-4 flex-wrap">
                  <div className="text-2xl font-extrabold text-[#1A2332]">{prod.price}</div>
                  <button
                    onClick={() =>
                      prod.title === "PMS Hotelero"        ? setPage("pms-hotelero") :
                      prod.title === "DPMS Odontológico"   ? setPage("dpms-odontologico") :
                      setPage("register")
                    }
                    className="flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-md shadow-emerald-200 hover:scale-105 transition-all duration-200 text-sm">
                    {(prod.title === "PMS Hotelero" || prod.title === "DPMS Odontológico") ? "Ver demo en vivo" : "Probar gratis 14 días"}<ArrowRight size={14} />
                  </button>
                </div>
              </div>
              <div className={i % 2 === 1 ? "lg:order-1" : ""}>
                <div className="rounded-2xl overflow-hidden shadow-2xl shadow-black/10 border border-black/5">
                  <div className="bg-[#1A2332] px-4 py-3 flex items-center gap-2">
                    <div className="w-3 h-3 rounded-full bg-red-400" /><div className="w-3 h-3 rounded-full bg-yellow-400" /><div className="w-3 h-3 rounded-full bg-emerald-400" />
                    <div className="ml-2 text-xs text-white/40 font-mono">{prod.title.toLowerCase().replace(/ /g,"-")}.rankpro.mx</div>
                  </div>
                  <img src={prod.img} alt={prod.title} className="w-full object-cover h-64" />
                </div>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* Compare */}
      <section className="py-20 px-4 bg-[#F5F7FA]">
        <div className="max-w-4xl mx-auto text-center mb-12">
          <h2 className="text-3xl font-extrabold text-[#1A2332]">¿Cuál producto necesitas?</h2>
          <p className="text-[#6B7280] mt-3">Todos los productos incluyen soporte en español y onboarding gratuito.</p>
        </div>
        <div className="max-w-5xl mx-auto overflow-x-auto">
          <table className="w-full bg-white rounded-2xl shadow-lg shadow-black/5 overflow-hidden border border-black/5">
            <thead>
              <tr className="bg-[#1A2332] text-white">
                <th className="text-left p-4 text-sm font-semibold">Característica</th>
                {prods.map((p) => <th key={p.title} className="p-4 text-sm font-semibold text-center">{p.title}</th>)}
              </tr>
            </thead>
            <tbody>
              {[
                ["Dashboard en tiempo real",          true,  true,  false, false, true,  true ],
                ["Auditoría SEO técnica",              false, false, true,  false, false, false],
                ["Gestión de anuncios multicanal",    false, false, false, true,  false, false],
                ["Análisis predictivo",               false, true,  false, false, true,  true ],
                ["Reportes white-label",              true,  true,  true,  true,  true,  true ],
                ["API pública",                       false, true,  false, true,  true,  true ],
                ["Channel manager hotelero",          false, false, false, false, true,  false],
                ["Motor de reservas online",          false, false, false, false, true,  true ],
                ["App móvil para staff / pacientes",  false, false, false, false, true,  true ],
                ["Expediente clínico digital",        false, false, false, false, false, true ],
                ["Facturación CFDI 4.0",              false, false, false, false, false, true ],
                ["Recordatorios WhatsApp / SMS",      false, false, false, false, false, true ],
              ].map(([feat, ...vals], i) => (
                <tr key={i as number} className={i % 2 === 0 ? "bg-white" : "bg-[#F5F7FA]"}>
                  <td className="p-4 text-sm text-[#1A2332] font-medium">{feat as string}</td>
                  {(vals as boolean[]).map((v, j) => (
                    <td key={j} className="p-4 text-center">
                      {v ? <CheckCircle size={16} className="text-[#0F9D6E] mx-auto" /> : <X size={16} className="text-[#6B7280]/40 mx-auto" />}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </>
  );
}
