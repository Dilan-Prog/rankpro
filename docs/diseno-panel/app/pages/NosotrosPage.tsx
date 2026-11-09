import { Target, Shield, Rocket, Heart, Star, Users, BookOpen, Linkedin, Twitter } from "lucide-react";
import type { Page } from "../types";
import { GreenBadge, SectionHeader } from "../components/shared";

const values = [
  { icon: Target,    title: "Orientación a resultados", desc: "Cada acción que tomamos tiene un objetivo medible. No trabajamos por tareas, trabajamos por metas." },
  { icon: Shield,    title: "Transparencia total",      desc: "Reportes claros, acceso a las cuentas, sin letra pequeña. Tu dinero, tus datos, siempre." },
  { icon: Rocket,    title: "Innovación constante",     desc: "Nos mantenemos a la vanguardia de las tendencias digitales para que tu ventaja competitiva nunca baje." },
  { icon: Heart,     title: "Pasión por el cliente",    desc: "Tu éxito es nuestro éxito. El equipo se involucra personalmente con cada proyecto." },
];

export default function NosotrosPage({ setPage }: { setPage: (p: Page) => void }) {
  return (
    <>
      {/* Hero */}
      <section className="bg-gradient-to-br from-[#0F9D6E] to-[#059669] py-24 px-4 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: "radial-gradient(circle at 30% 50%, white 1px, transparent 1px)", backgroundSize: "40px 40px" }} />
        <div className="max-w-4xl mx-auto text-center relative z-10">
          <GreenBadge><Users size={12} />NUESTRO EQUIPO</GreenBadge>
          <h1 className="text-5xl font-extrabold text-white leading-tight mb-6">Somos los arquitectos de<br />tu crecimiento digital</h1>
          <p className="text-emerald-100 text-lg leading-relaxed max-w-2xl mx-auto">Fundada en 2016 en Ciudad de México, RankPro Solutions nació con una misión clara: democratizar el acceso a estrategias de marketing digital de clase mundial para empresas mexicanas.</p>
        </div>
      </section>

      {/* Historia */}
      <section className="py-20 px-4 bg-white">
        <div className="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
          <div>
            <GreenBadge><BookOpen size={12} />NUESTRA HISTORIA</GreenBadge>
            <h2 className="text-4xl font-extrabold text-[#1A2332] mb-6">8 años construyendo el futuro digital de México</h2>
            <div className="space-y-5 text-[#6B7280] leading-relaxed">
              <p>En 2016, Ricardo Torres dejó su puesto como Google Partner Manager para fundar RankPro con una convicción: las empresas mexicanas merecían acceso a las mismas estrategias que usan las grandes corporaciones internacionales.</p>
              <p>Empezamos con 3 clientes y 2 colaboradores. Hoy somos un equipo de 45 expertos, con más de 500 clientes activos en toda la República Mexicana y una cartera acumulada de $120M+ MXN en ventas generadas.</p>
              <p>Somos Google Premier Partner, Meta Business Partner y parte del selecto grupo de agencias certificadas por HubSpot en México.</p>
            </div>
            <div className="grid grid-cols-2 gap-6 mt-8">
              {[["2016","Fundación de RankPro en CDMX"],["2019","Primeros 100 clientes activos"],["2021","Google Premier Partner"],["2024","500+ clientes en México"]].map(([year, label]) => (
                <div key={year} className="border-l-4 border-[#0F9D6E] pl-4">
                  <div className="text-2xl font-extrabold text-[#0F9D6E]">{year}</div>
                  <div className="text-sm text-[#6B7280]">{label}</div>
                </div>
              ))}
            </div>
          </div>
          <div className="relative">
            <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=600&h=450&fit=crop&auto=format" alt="Equipo RankPro" className="rounded-3xl w-full object-cover shadow-2xl shadow-emerald-100" />
            <div className="absolute -bottom-6 -left-6 bg-white rounded-2xl p-5 shadow-xl shadow-black/10 border border-black/5">
              <div className="text-3xl font-extrabold text-[#0F9D6E]">45+</div>
              <div className="text-sm text-[#6B7280]">Expertos en el equipo</div>
            </div>
          </div>
        </div>
      </section>

      {/* Valores */}
      <section className="py-20 px-4 bg-[#F5F7FA]">
        <div className="max-w-7xl mx-auto">
          <SectionHeader badge="NUESTROS VALORES" badgeIcon={Star} title={<>Lo que nos define como <span className="text-[#0F9D6E]">agencia</span></>} />
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            {values.map((v) => (
              <div key={v.title} className="bg-white rounded-2xl p-7 border border-black/5 hover:shadow-lg hover:shadow-emerald-50 hover:-translate-y-1 transition-all duration-300">
                <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center mb-5">
                  <v.icon size={22} className="text-white" />
                </div>
                <h3 className="font-bold text-[#1A2332] mb-2">{v.title}</h3>
                <p className="text-sm text-[#6B7280] leading-relaxed">{v.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Stats strip */}
      <section className="bg-gradient-to-r from-[#0F9D6E] to-[#34D399] py-16 px-4">
        <div className="max-w-5xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
          {[["500+","Clientes activos"],["$120M+","En ventas generadas"],["98%","Tasa de retención"],["8 años","De experiencia"]].map(([v, l]) => (
            <div key={l}><div className="text-4xl font-extrabold text-white">{v}</div><div className="text-emerald-100 text-sm mt-1">{l}</div></div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="py-20 px-4 bg-white text-center">
        <h2 className="text-3xl font-extrabold text-[#1A2332] mb-4">¿Quieres ser parte de nuestra historia de éxito?</h2>
        <p className="text-[#6B7280] mb-8">Agenda tu consultoría gratuita y descubre todo lo que podemos hacer por tu empresa.</p>
        <button onClick={() => setPage("agendar")} className="px-8 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:scale-105 transition-all duration-200">
          Agendar Consultoría Gratuita
        </button>
      </section>
    </>
  );
}
