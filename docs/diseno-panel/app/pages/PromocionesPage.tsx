import { useState, useEffect } from "react";
import { Gift, TrendingUp, Tag, Clock, Shield, ThumbsUp, Headphones, Rocket } from "lucide-react";
import type { Page } from "../types";
import { GreenBadge } from "../components/shared";

export default function PromocionesPage({ setPage }: { setPage: (p: Page) => void }) {
  const promos = [
    { tag: "OFERTA LIMITADA", title: "Pack Lanzamiento Digital", desc: "Sitio web + SEO básico + 1 mes de Google Ads gestionado. Todo lo que necesitas para empezar.", price: "$18,900 MXN", original: "$28,500 MXN", save: "Ahorra $9,600", icon: Rocket, expires: "30 Jun 2024", grad: "from-[#0F9D6E] to-[#34D399]", hot: true },
    { tag: "PLAN ESPECIAL",   title: "SEO + SEM Combo",          desc: "Estrategia integrada de posicionamiento orgánico y campañas pagadas con descuento de temporada.", price: "$14,500 MXN/mes", original: "$22,000 MXN/mes", save: "Ahorra 34%",   icon: TrendingUp, expires: "15 Jul 2024", grad: "from-teal-500 to-emerald-500", hot: false },
    { tag: "NUEVOS CLIENTES", title: "Mes 1 sin costo de gestión", desc: "El primer mes de gestión de tus campañas de Google Ads es completamente gratuito. Solo pagas la pauta.", price: "$0 gestión", original: "$6,500 MXN",  save: "Mes gratis",  icon: Gift,       expires: "31 Jul 2024", grad: "from-green-400 to-emerald-500", hot: false },
    { tag: "COMBO ANUAL",    title: "Plan Anual con 20% OFF",    desc: "Contrata cualquiera de nuestros planes con pago anual y obtén 20% de descuento en todo el año.", price: "20% de descuento", original: "En todos los planes", save: "Mejor precio", icon: Tag,        expires: "Permanente", grad: "from-emerald-500 to-teal-600", hot: false },
  ];
  const [timeLeft, setTimeLeft] = useState({ d: 14, h: 8, m: 42, s: 17 });
  useEffect(() => {
    const t = setInterval(() => {
      setTimeLeft((prev) => {
        let { d, h, m, s } = prev;
        s--; if (s < 0) { s = 59; m--; } if (m < 0) { m = 59; h--; } if (h < 0) { h = 23; d = Math.max(0, d - 1); }
        return { d, h, m, s };
      });
    }, 1000);
    return () => clearInterval(t);
  }, []);

  return (
    <>
      {/* Hero */}
      <section className="bg-gradient-to-br from-[#0F9D6E] to-[#059669] py-20 px-4 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: "radial-gradient(circle at 20% 50%, white 1px, transparent 1px)", backgroundSize: "40px 40px" }} />
        <div className="max-w-4xl mx-auto text-center relative z-10">
          <GreenBadge><Gift size={12} />PROMOCIONES ESPECIALES</GreenBadge>
          <h1 className="text-5xl font-extrabold text-white leading-tight mb-5">Ofertas exclusivas para<br />impulsar tu negocio</h1>
          <p className="text-emerald-100 text-lg mb-8">Promociones de tiempo limitado. ¡No dejes pasar la oportunidad de comenzar tu transformación digital al mejor precio!</p>
          {/* Countdown */}
          <div className="inline-flex bg-white/10 backdrop-blur rounded-2xl p-6 border border-white/20 gap-6">
            {[["Días", timeLeft.d], ["Horas", timeLeft.h], ["Min", timeLeft.m], ["Seg", timeLeft.s]].map(([l, v]) => (
              <div key={l as string} className="text-center">
                <div className="text-4xl font-extrabold text-white tabular-nums">{String(v).padStart(2,"0")}</div>
                <div className="text-emerald-200 text-xs mt-1">{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Promos grid */}
      <section className="py-20 px-4 bg-[#F5F7FA]">
        <div className="max-w-7xl mx-auto grid md:grid-cols-2 gap-6">
          {promos.map((promo) => (
            <div key={promo.title} className={`relative bg-white rounded-2xl overflow-hidden border transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-100 hover:-translate-y-1 ${promo.hot ? "border-[#0F9D6E] shadow-lg shadow-emerald-100" : "border-black/5"}`}>
              {promo.hot && <div className="absolute top-4 right-4 bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-xs font-bold px-3 py-1.5 rounded-full">🔥 MÁS POPULAR</div>}
              <div className={`h-2 bg-gradient-to-r ${promo.grad}`} />
              <div className="p-8">
                <div className="flex items-start gap-4 mb-5">
                  <div className={`w-12 h-12 rounded-2xl bg-gradient-to-br ${promo.grad} flex items-center justify-center shrink-0`}>
                    <promo.icon size={22} className="text-white" />
                  </div>
                  <div>
                    <span className="text-xs font-extrabold text-[#0F9D6E] tracking-widest">{promo.tag}</span>
                    <h3 className="text-xl font-bold text-[#1A2332] mt-1">{promo.title}</h3>
                  </div>
                </div>
                <p className="text-[#6B7280] text-sm leading-relaxed mb-6">{promo.desc}</p>
                <div className="flex items-end gap-3 mb-2">
                  <span className="text-3xl font-extrabold text-[#0F9D6E]">{promo.price}</span>
                  <span className="text-sm text-[#6B7280] line-through mb-1">{promo.original}</span>
                </div>
                <div className="inline-flex items-center gap-1.5 bg-emerald-50 text-[#0F9D6E] text-xs font-semibold px-3 py-1.5 rounded-full mb-5">
                  <Tag size={11} />{promo.save}
                </div>
                <div className="flex items-center gap-2 text-xs text-[#6B7280] mb-6">
                  <Clock size={13} />Válido hasta: {promo.expires}
                </div>
                <button onClick={() => setPage("agendar")} className={`w-full py-3.5 rounded-xl font-semibold text-sm transition-all duration-200 ${promo.hot ? "bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white shadow-md shadow-emerald-200 hover:shadow-lg" : "border-2 border-[#0F9D6E] text-[#0F9D6E] hover:bg-emerald-50"}`}>
                  Aprovechar esta oferta
                </button>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* Guarantee */}
      <section className="py-16 px-4 bg-white">
        <div className="max-w-5xl mx-auto">
          <div className="grid md:grid-cols-3 gap-6 text-center">
            {[
              { icon: Shield,      title: "Sin contratos de permanencia", desc: "Cancela cuando quieras, sin penalizaciones ni letras pequeñas." },
              { icon: ThumbsUp,    title: "Garantía de satisfacción",     desc: "Si en los primeros 30 días no ves resultados, te devolvemos el dinero." },
              { icon: Headphones,  title: "Soporte en español 24/7",      desc: "Un equipo real de expertos disponible en todo momento para ti." },
            ].map((g) => (
              <div key={g.title} className="bg-[#F5F7FA] rounded-2xl p-8 border border-black/5">
                <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center mx-auto mb-5">
                  <g.icon size={22} className="text-white" />
                </div>
                <h3 className="font-bold text-[#1A2332] mb-2">{g.title}</h3>
                <p className="text-sm text-[#6B7280] leading-relaxed">{g.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}
