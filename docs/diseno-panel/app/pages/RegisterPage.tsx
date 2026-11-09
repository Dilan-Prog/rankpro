import { useState } from "react";
import { ChevronRight, Eye, EyeOff, Lock, AtSign, Building2, CheckCircle, UserPlus } from "lucide-react";
import type { Page } from "../types";
import { Logo } from "../components/shared";

export default function RegisterPage({ setPage }: { setPage: (p: Page) => void }) {
  const [showPass, setShowPass] = useState(false);
  return (
    <div className="min-h-screen flex">
      <div className="w-full lg:w-[42%] flex flex-col justify-center px-8 md:px-16 py-12 bg-white overflow-y-auto">
        <button onClick={() => setPage("home")} className="flex items-center gap-1.5 text-sm text-[#6B7280] hover:text-[#0F9D6E] mb-8 self-start transition-colors"><ChevronRight size={14} className="rotate-180" />Volver al inicio</button>
        <div className="max-w-sm w-full mx-auto">
          <Logo onClick={() => setPage("home")} />
          <h1 className="text-2xl font-extrabold text-[#1A2332] mt-8 mb-1">Crea tu cuenta gratis</h1>
          <p className="text-[#6B7280] text-sm mb-7">14 días de prueba, sin tarjeta de crédito.</p>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div><label className="block text-xs font-semibold text-[#1A2332] mb-1.5">Nombre</label><input type="text" placeholder="Juan" className="w-full px-3.5 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /></div>
              <div><label className="block text-xs font-semibold text-[#1A2332] mb-1.5">Apellido</label><input type="text" placeholder="García" className="w-full px-3.5 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /></div>
            </div>
            <div><label className="block text-xs font-semibold text-[#1A2332] mb-1.5">Empresa</label><div className="relative"><Building2 size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]" /><input type="text" placeholder="Tu empresa S.A. de C.V." className="w-full pl-10 pr-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /></div></div>
            <div><label className="block text-xs font-semibold text-[#1A2332] mb-1.5">Correo electrónico</label><div className="relative"><AtSign size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]" /><input type="email" placeholder="tu@empresa.com" className="w-full pl-10 pr-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /></div></div>
            <div><label className="block text-xs font-semibold text-[#1A2332] mb-1.5">Contraseña</label><div className="relative"><Lock size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]" /><input type={showPass ? "text" : "password"} placeholder="Mín. 8 caracteres" className="w-full pl-10 pr-10 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /><button type="button" onClick={() => setShowPass(!showPass)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]">{showPass ? <EyeOff size={15} /> : <Eye size={15} />}</button></div></div>
            <div><label className="block text-xs font-semibold text-[#1A2332] mb-1.5">Confirmar contraseña</label><div className="relative"><Lock size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]" /><input type="password" placeholder="Repite tu contraseña" className="w-full pl-10 pr-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-[#F5F7FA]" /></div></div>
          </div>
          <label className="flex items-start gap-2.5 mt-5 cursor-pointer"><input type="checkbox" className="w-4 h-4 accent-[#0F9D6E] mt-0.5 shrink-0" /><span className="text-xs text-[#6B7280] leading-relaxed">Acepto los <span className="text-[#0F9D6E] font-semibold">términos y condiciones</span> y el <span className="text-[#0F9D6E] font-semibold">aviso de privacidad</span>.</span></label>
          <button onClick={() => setPage("dashboard")} className="w-full mt-6 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:shadow-xl hover:scale-[1.02] transition-all duration-200 flex items-center justify-center gap-2">
            <UserPlus size={17} />Crear cuenta gratis
          </button>
          <p className="text-center text-sm text-[#6B7280] mt-5">¿Ya tienes cuenta?{" "}<button onClick={() => setPage("login")} className="text-[#0F9D6E] font-semibold hover:underline">Inicia sesión</button></p>
        </div>
      </div>
      <div className="hidden lg:flex flex-1 relative overflow-hidden bg-gradient-to-br from-[#059669] to-[#0F9D6E]">
        <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&h=900&fit=crop&auto=format" alt="Crecimiento digital" className="absolute inset-0 w-full h-full object-cover opacity-20" />
        <div className="relative z-10 flex flex-col justify-center px-14">
          <div className="max-w-md space-y-5">
            <div className="text-white/60 text-sm font-semibold uppercase tracking-widest">¿Por qué RankPro?</div>
            <h2 className="text-4xl font-extrabold text-white leading-tight">Empieza a crecer digitalmente hoy mismo</h2>
            <div className="space-y-4">
              {["Panel unificado para todas tus campañas y métricas","Reportes automáticos listos para compartir","Soporte en español 24/7 de expertos certificados","Integraciones con Google, Meta, HubSpot y más"].map((t) => (
                <div key={t} className="flex items-start gap-3"><CheckCircle size={18} className="text-emerald-200 shrink-0 mt-0.5" /><span className="text-emerald-50 text-sm leading-relaxed">{t}</span></div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
