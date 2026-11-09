import { useState } from "react";
import {
  ChevronRight, Eye, EyeOff, Lock, AtSign, Globe, TrendingUp, Users, Star, Rocket
} from "lucide-react";
import type { Page } from "../types";
import { Logo } from "../components/shared";

export default function LoginPage({ setPage }: { setPage: (p: Page) => void }) {
  const [showPass, setShowPass] = useState(false);
  return (
    <div className="min-h-screen flex">
      <div className="w-full lg:w-[42%] flex flex-col justify-center px-8 md:px-16 py-12 bg-white">
        <button onClick={() => setPage("home")} className="flex items-center gap-1.5 text-sm text-[#6B7280] hover:text-[#0F9D6E] mb-10 self-start transition-colors">
          <ChevronRight size={14} className="rotate-180" />Volver al inicio
        </button>
        <div className="max-w-sm w-full mx-auto">
          <Logo onClick={() => setPage("home")} />
          <h1 className="text-2xl font-extrabold text-[#1A2332] mt-8 mb-1">Inicia sesión en tu cuenta</h1>
          <p className="text-[#6B7280] text-sm mb-8">Bienvenido de vuelta. Gestiona tus campañas.</p>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Correo electrónico</label>
              <div className="relative"><AtSign size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]" /><input type="email" placeholder="tu@empresa.com" className="w-full pl-10 pr-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /></div>
            </div>
            <div>
              <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Contraseña</label>
              <div className="relative"><Lock size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]" /><input type={showPass ? "text" : "password"} placeholder="••••••••" className="w-full pl-10 pr-10 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /><button type="button" onClick={() => setShowPass(!showPass)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]">{showPass ? <EyeOff size={16} /> : <Eye size={16} />}</button></div>
            </div>
          </div>
          <div className="flex items-center justify-between mt-4 mb-6">
            <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" className="w-4 h-4 accent-[#0F9D6E]" /><span className="text-sm text-[#6B7280]">Recordarme</span></label>
            <button className="text-sm text-[#0F9D6E] font-semibold hover:underline">¿Olvidaste tu contraseña?</button>
          </div>
          <button onClick={() => setPage("dashboard")} className="w-full py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:shadow-xl hover:scale-[1.02] transition-all duration-200">Iniciar sesión</button>
        </div>
      </div>
      <div className="hidden lg:flex flex-1 relative overflow-hidden bg-gradient-to-br from-[#0F9D6E] to-[#059669]">
        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&h=900&fit=crop&auto=format" alt="Dashboard" className="absolute inset-0 w-full h-full object-cover opacity-20" />
        <div className="relative z-10 flex flex-col justify-center px-14">
          <div className="bg-white/10 backdrop-blur-sm rounded-3xl p-8 border border-white/20 max-w-md">
            <div className="flex items-center gap-2 mb-3"><Rocket size={20} className="text-white" /><span className="text-white font-bold text-lg">RankDash Pro</span></div>
            <h2 className="text-3xl font-extrabold text-white leading-snug mb-3">Gestiona todas tus campañas desde un solo lugar</h2>
            <p className="text-emerald-100 text-sm leading-relaxed mb-6">SEO, Ads, redes sociales y analíticas centralizadas. Reportes automáticos en tiempo real.</p>
            <div className="grid grid-cols-2 gap-3">
              {[{icon:TrendingUp,label:"ROI +420%",sub:"promedio clientes"},{icon:Users,label:"500+ clientes",sub:"confían en nosotros"},{icon:Star,label:"4.9/5 estrellas",sub:"satisfacción"},{icon:Globe,label:"Top 3 Google",sub:"posicionamiento"}].map((s) => (
                <div key={s.label} className="bg-white/10 rounded-xl p-4"><s.icon size={18} className="text-emerald-200 mb-2" /><div className="text-white font-bold text-sm">{s.label}</div><div className="text-emerald-200 text-xs">{s.sub}</div></div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
