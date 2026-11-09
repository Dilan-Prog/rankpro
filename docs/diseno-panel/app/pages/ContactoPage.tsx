import { useState } from "react";
import { Phone, Mail, MessageCircle, MapPin, CheckCircle, Send } from "lucide-react";
import { GreenBadge } from "../components/shared";

export default function ContactoPage() {
  const [sent, setSent] = useState(false);
  return (
    <>
      <section className="bg-[#F5F7FA] py-20 px-4 border-b border-black/5">
        <div className="max-w-4xl mx-auto text-center">
          <GreenBadge><Mail size={12} />CONTÁCTANOS</GreenBadge>
          <h1 className="text-5xl font-extrabold text-[#1A2332] mb-4">Hablemos de tu <span className="text-[#0F9D6E]">proyecto</span></h1>
          <p className="text-[#6B7280] text-lg">Nuestro equipo responde en menos de 2 horas durante horario laboral. También puedes escribirnos por WhatsApp para una respuesta inmediata.</p>
        </div>
      </section>

      <section className="py-16 px-4 bg-white">
        <div className="max-w-7xl mx-auto grid lg:grid-cols-3 gap-12">
          {/* Info */}
          <div className="space-y-6">
            <h2 className="text-2xl font-extrabold text-[#1A2332]">Información de contacto</h2>
            {[
              { icon: Phone,          label: "Teléfono",        value: "+52 55 1234 5678",    sub: "Lun–Vie 9:00–18:00 hrs" },
              { icon: MessageCircle,  label: "WhatsApp",        value: "+52 55 1234 5678",    sub: "Respuesta inmediata" },
              { icon: Mail,           label: "Email",           value: "hola@rankpro.mx",     sub: "Respuesta en < 2 horas" },
              { icon: MapPin,         label: "Oficina CDMX",    value: "Av. Paseo de la Reforma 250",  sub: "Col. Juárez, CDMX 06600" },
            ].map((c) => (
              <div key={c.label} className="flex items-start gap-4 p-4 bg-[#F5F7FA] rounded-2xl border border-black/5">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center shrink-0">
                  <c.icon size={18} className="text-white" />
                </div>
                <div>
                  <div className="text-xs text-[#6B7280] font-medium">{c.label}</div>
                  <div className="font-bold text-[#1A2332] text-sm">{c.value}</div>
                  <div className="text-xs text-[#6B7280]">{c.sub}</div>
                </div>
              </div>
            ))}

            <div className="bg-gradient-to-br from-[#0F9D6E] to-[#34D399] rounded-2xl p-6 text-white">
              <h3 className="font-bold text-lg mb-2">¿Prefieres WhatsApp?</h3>
              <p className="text-emerald-100 text-sm mb-4">Escríbenos directo y recibe respuesta en minutos.</p>
              <a href="https://wa.me/525512345678" className="flex items-center gap-2 bg-white text-[#0F9D6E] font-semibold px-5 py-3 rounded-xl hover:bg-emerald-50 transition-colors text-sm w-fit">
                <MessageCircle size={16} />Abrir WhatsApp
              </a>
            </div>
          </div>

          {/* Form */}
          <div className="lg:col-span-2">
            {sent ? (
              <div className="h-full flex flex-col items-center justify-center text-center py-20">
                <div className="w-16 h-16 rounded-full bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center mx-auto mb-5 shadow-xl shadow-emerald-200">
                  <CheckCircle size={32} className="text-white" />
                </div>
                <h3 className="text-2xl font-extrabold text-[#1A2332] mb-2">¡Mensaje enviado!</h3>
                <p className="text-[#6B7280]">Te contactaremos en menos de 2 horas hábiles.</p>
                <button onClick={() => setSent(false)} className="mt-6 px-6 py-3 rounded-xl font-semibold text-[#0F9D6E] border-2 border-[#0F9D6E] hover:bg-emerald-50 transition-colors">
                  Enviar otro mensaje
                </button>
              </div>
            ) : (
              <div className="bg-[#F5F7FA] rounded-2xl p-8 border border-black/5">
                <h2 className="text-2xl font-extrabold text-[#1A2332] mb-6">Envíanos un mensaje</h2>
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Nombre</label>
                      <input type="text" placeholder="Tu nombre" className="w-full px-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-white" />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Empresa</label>
                      <input type="text" placeholder="Tu empresa" className="w-full px-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-white" />
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Correo electrónico</label>
                    <input type="email" placeholder="tu@email.com" className="w-full px-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-white" />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Teléfono / WhatsApp</label>
                    <input type="tel" placeholder="+52 55 1234 5678" className="w-full px-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-white" />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Servicio de interés</label>
                    <select className="w-full px-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-white text-[#6B7280]">
                      <option>Selecciona un servicio</option>
                      {["SEO Orgánico","Google Ads / SEM","Desarrollo Web","PageSpeed","Analytics","Social Media","Consultoría General"].map((o) => <option key={o}>{o}</option>)}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-[#1A2332] mb-1.5">Mensaje</label>
                    <textarea rows={4} placeholder="Cuéntanos sobre tu proyecto, objetivos y presupuesto aproximado..."
                      className="w-full px-4 py-3 rounded-xl border border-black/10 text-sm focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all bg-white resize-none" />
                  </div>
                  <button onClick={() => setSent(true)} className="w-full py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:shadow-xl hover:scale-[1.02] transition-all duration-200 flex items-center justify-center gap-2">
                    <Send size={16} />Enviar mensaje
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      </section>

      {/* Map placeholder */}
      <section className="h-72 bg-[#F5F7FA] relative overflow-hidden border-t border-black/5">
        <img src="https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?w=1400&h=300&fit=crop&auto=format" alt="Ciudad de México" className="w-full h-full object-cover opacity-30" />
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="bg-white rounded-2xl p-5 shadow-xl shadow-black/10 flex items-center gap-4 border border-black/5">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center">
              <MapPin size={18} className="text-white" />
            </div>
            <div>
              <div className="font-bold text-[#1A2332]">RankPro Solutions</div>
              <div className="text-sm text-[#6B7280]">Av. Paseo de la Reforma 250, CDMX</div>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
