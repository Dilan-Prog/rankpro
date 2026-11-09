import { useState, useRef, useEffect } from "react";
import {
  Target, ArrowRight, CheckCircle, Phone, MessageCircle, Star, ShieldCheck,
  Clock, TrendingUp, MousePointerClick, Wallet, ChevronDown, Award, Zap
} from "lucide-react";
import type { Page } from "../types";
import { testimonials } from "../data";
import {
  AreaChart, Area, BarChart, Bar, XAxis, YAxis, CartesianGrid,
  Tooltip, ResponsiveContainer, Cell
} from "recharts";
import { GreenBadge } from "../components/shared";

const proof = [
  { value: "4.8x", label: "ROAS promedio a 90 días" },
  { value: "-38%", label: "Costo por lead vs. cuenta previa" },
  { value: "312", label: "Cuentas de Google Ads activas" },
  { value: "48 h", label: "Auditoría entregada" },
];

const auditItems = [
  "Diagnóstico de desperdicio: términos, ubicaciones y horarios que te queman presupuesto.",
  "Revisión de estructura de campañas, pujas y calidad de anuncios.",
  "Análisis de landing pages y seguimiento de conversiones (GA4 / GTM).",
  "Proyección de leads y ROAS con tu presupuesto actual.",
];


const series = {
  leads: {
    label: "Leads calificados / mes",
    hint: "Volumen de solicitudes reales, no clics.",
    suffix: "",
    data: [
      { m: "Mes 0", v: 38 }, { m: "Mes 1", v: 71 }, { m: "Mes 2", v: 118 },
      { m: "Mes 3", v: 164 }, { m: "Mes 4", v: 203 }, { m: "Mes 5", v: 241 },
    ],
    up: true,
  },
  cpl: {
    label: "Costo por lead (MXN)",
    hint: "Lo que pagas por cada solicitud calificada.",
    suffix: "",
    data: [
      { m: "Mes 0", v: 940 }, { m: "Mes 1", v: 760 }, { m: "Mes 2", v: 612 },
      { m: "Mes 3", v: 505 }, { m: "Mes 4", v: 448 }, { m: "Mes 5", v: 402 },
    ],
    up: false,
  },
  roas: {
    label: "ROAS",
    hint: "Ingreso generado por cada peso invertido.",
    suffix: "x",
    data: [
      { m: "Mes 0", v: 1.4 }, { m: "Mes 1", v: 2.1 }, { m: "Mes 2", v: 3.0 },
      { m: "Mes 3", v: 3.9 }, { m: "Mes 4", v: 4.4 }, { m: "Mes 5", v: 4.8 },
    ],
    up: true,
  },
} as const;

const spendMix = [
  { name: "Búsquedas que sí convierten", v: 34 },
  { name: "Términos irrelevantes", v: 27 },
  { name: "Horarios sin demanda", v: 18 },
  { name: "Ubicaciones fuera de zona", v: 13 },
  { name: "Anuncios duplicados", v: 8 },
];

const guarantees = [
  { icon: ShieldCheck, title: "Sin contratos forzosos", desc: "Trabajamos mes a mes. Si no ves resultados, te vas sin penalización." },
  { icon: Wallet, title: "Tus cuentas son tuyas", desc: "Acceso total de administrador desde el día uno. Nada queda a nuestro nombre." },
  { icon: Clock, title: "Primeras campañas en 7 días", desc: "Del kickoff al lanzamiento en una semana, con seguimiento configurado." },
];

const objections = [
  { q: "¿Cuánto presupuesto necesito para empezar?", a: "Desde $30,000 MXN mensuales de inversión publicitaria obtenemos datos suficientes para optimizar. Con menos podemos trabajar, pero el aprendizaje toma más tiempo y te lo diremos antes de firmar." },
  { q: "Ya intenté Google Ads y no funcionó, ¿qué cambia?", a: "En el 80% de las cuentas que auditamos el problema no es el canal: es estructura, términos de búsqueda sin filtrar y conversiones mal medidas. La auditoría te muestra exactamente dónde se está yendo el dinero, con capturas de tu propia cuenta." },
  { q: "¿En cuánto tiempo veo resultados?", a: "Primeros leads en la semana 1-2. Rentabilidad estable entre el día 60 y 90, cuando el algoritmo ya tiene volumen de conversiones limpias." },
  { q: "¿Qué incluye la fee mensual?", a: "Estrategia, creación y optimización de campañas, anuncios, seguimiento, reportes y juntas. La inversión publicitaria se paga directo a Google desde tu tarjeta." },
  { q: "¿Firmo algo para la auditoría?", a: "Nada. Nos das acceso de solo lectura a tu cuenta, la revisamos y te entregamos el diagnóstico en 48 horas. Si decides no trabajar con nosotros, el documento es tuyo." },
];

function LeadForm({ compact = false }: { compact?: boolean }) {
  const [sent, setSent] = useState(false);
  const [form, setForm] = useState({ nombre: "", empresa: "", email: "", tel: "", presupuesto: "$30k - $80k MXN" });

  if (sent) {
    return (
      <div className="bg-white rounded-2xl border border-emerald-200 p-8 text-center shadow-xl shadow-emerald-100/60">
        <div className="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
          <CheckCircle size={26} className="text-[#0F9D6E]" />
        </div>
        <h3 className="text-xl font-extrabold text-[#1A2332]">Solicitud recibida</h3>
        <p className="text-[#6B7280] text-sm mt-2 leading-relaxed">
          Un estratega senior te contacta hoy mismo. Tu auditoría de cuenta estará lista en 48 horas hábiles.
        </p>
        <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer"
          className="mt-5 inline-flex items-center justify-center gap-2 w-full px-5 py-3 rounded-xl text-sm font-semibold bg-[#25D366] text-white hover:brightness-105 transition-all">
          <MessageCircle size={16} />Adelantar por WhatsApp
        </a>
      </div>
    );
  }

  return (
    <form
      onSubmit={(e) => { e.preventDefault(); setSent(true); }}
      className="bg-white rounded-2xl border border-black/5 shadow-2xl shadow-black/10 p-6 sm:p-7">
      <div className="flex items-center gap-2 text-[#0F9D6E] text-xs font-bold tracking-wider">
        <Zap size={13} />AUDITORÍA GRATUITA · 48 HORAS
      </div>
      <h3 className="text-xl font-extrabold text-[#1A2332] mt-2 leading-snug">
        Descubre cuánto presupuesto estás desperdiciando
      </h3>
      <p className="text-[#6B7280] text-sm mt-1.5 leading-relaxed">
        Sin compromiso, sin contratos. Solo el diagnóstico de tu cuenta y un plan claro.
      </p>

      <div className={`mt-5 grid gap-3 ${compact ? "sm:grid-cols-2" : "grid-cols-1 sm:grid-cols-2"}`}>
        <input required value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })}
          placeholder="Nombre*" className="px-4 py-3 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
        <input required value={form.empresa} onChange={(e) => setForm({ ...form, empresa: e.target.value })}
          placeholder="Empresa*" className="px-4 py-3 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
        <input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })}
          placeholder="Email corporativo*" className="px-4 py-3 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
        <input required value={form.tel} onChange={(e) => setForm({ ...form, tel: e.target.value })}
          placeholder="WhatsApp*" className="px-4 py-3 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
        <select value={form.presupuesto} onChange={(e) => setForm({ ...form, presupuesto: e.target.value })}
          className="sm:col-span-2 px-4 py-3 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors">
          <option>Menos de $30k MXN / mes</option>
          <option>$30k - $80k MXN</option>
          <option>$80k - $200k MXN</option>
          <option>Más de $200k MXN</option>
        </select>
      </div>

      <button type="submit"
        className="mt-4 w-full flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:shadow-xl hover:brightness-105 transition-all duration-200">
        Quiero mi auditoría gratis<ArrowRight size={17} />
      </button>

      <div className="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[11px] text-[#6B7280]">
        <span className="flex items-center gap-1"><ShieldCheck size={12} className="text-[#0F9D6E]" />Datos protegidos</span>
        <span className="flex items-center gap-1"><Clock size={12} className="text-[#0F9D6E]" />Respuesta el mismo día</span>
        <span className="flex items-center gap-1"><CheckCircle size={12} className="text-[#0F9D6E]" />Cero compromiso</span>
      </div>
    </form>
  );
}

function ROICalculator({ onCta }: { onCta: () => void }) {
  const [budget, setBudget] = useState(80);
  const [ticket, setTicket] = useState(8000);

  const cpc = 14;
  const clicks = Math.round((budget * 1000) / cpc);
  const leads = Math.round(clicks * 0.062);
  const ventas = Math.round(leads * 0.22);
  const ingresos = ventas * ticket;
  const fee = budget <= 50 ? 12900 : budget <= 200 ? 24900 : 39900;
  const roas = ingresos / (budget * 1000 + fee);

  const money = (n: number) => "$" + n.toLocaleString("es-MX");

  return (
    <div className="grid lg:grid-cols-2 gap-8 items-center bg-white rounded-3xl border border-black/5 shadow-xl shadow-black/5 p-7 sm:p-10">
      <div>
        <GreenBadge><TrendingUp size={12} />PROYECCIÓN CON TUS NÚMEROS</GreenBadge>
        <h3 className="text-2xl sm:text-3xl font-extrabold text-[#1A2332] leading-tight tracking-tight">
          ¿Cuánto puede generar tu inversión en Google Ads?
        </h3>
        <p className="text-[#6B7280] text-sm mt-3 leading-relaxed">
          Mueve los controles con tus cifras reales. Usamos promedios de nuestras 312 cuentas activas en México
          (CPC $14, 6.2% de conversión a lead, 22% de cierre).
        </p>

        <div className="mt-7 space-y-6">
          <div>
            <div className="flex items-baseline justify-between mb-2">
              <label className="text-sm font-semibold text-[#1A2332]">Inversión publicitaria mensual</label>
              <span className="text-sm font-extrabold text-[#0F9D6E]">${budget}k MXN</span>
            </div>
            <input type="range" min={20} max={400} step={10} value={budget}
              onChange={(e) => setBudget(Number(e.target.value))}
              className="w-full accent-[#0F9D6E]" />
          </div>
          <div>
            <div className="flex items-baseline justify-between mb-2">
              <label className="text-sm font-semibold text-[#1A2332]">Ticket promedio por venta</label>
              <span className="text-sm font-extrabold text-[#0F9D6E]">{money(ticket)}</span>
            </div>
            <input type="range" min={1000} max={80000} step={1000} value={ticket}
              onChange={(e) => setTicket(Number(e.target.value))}
              className="w-full accent-[#0F9D6E]" />
          </div>
        </div>
      </div>

      <div className="bg-gradient-to-br from-[#0F9D6E] to-[#34D399] rounded-2xl p-7 text-white">
        <div className="text-emerald-50 text-xs font-bold tracking-widest">PROYECCIÓN A 90 DÍAS</div>
        <div className="mt-5 grid grid-cols-2 gap-5">
          <div>
            <div className="text-3xl font-extrabold leading-none">{clicks.toLocaleString("es-MX")}</div>
            <div className="text-emerald-50 text-xs mt-1.5 flex items-center gap-1"><MousePointerClick size={12} />clics / mes</div>
          </div>
          <div>
            <div className="text-3xl font-extrabold leading-none">{leads.toLocaleString("es-MX")}</div>
            <div className="text-emerald-50 text-xs mt-1.5">leads calificados</div>
          </div>
          <div>
            <div className="text-3xl font-extrabold leading-none">{ventas.toLocaleString("es-MX")}</div>
            <div className="text-emerald-50 text-xs mt-1.5">ventas cerradas</div>
          </div>
          <div>
            <div className="text-3xl font-extrabold leading-none">{roas.toFixed(1)}x</div>
            <div className="text-emerald-50 text-xs mt-1.5">ROAS estimado</div>
          </div>
        </div>
        <div className="mt-6 pt-5 border-t border-white/20">
          <div className="text-emerald-50 text-xs">Ingreso proyectado mensual</div>
          <div className="text-4xl font-extrabold mt-1">{money(ingresos)}</div>
        </div>
        <button onClick={onCta}
          className="mt-6 w-full flex items-center justify-center gap-2 bg-white text-[#0F9D6E] font-bold text-sm px-5 py-3.5 rounded-xl hover:bg-emerald-50 transition-colors">
          Validar estos números con un experto<ArrowRight size={16} />
        </button>
        <p className="text-emerald-50/80 text-[11px] mt-3 leading-relaxed">
          Estimación referencial. En la auditoría ajustamos el modelo con el CPC real de tu industria.
        </p>
      </div>
    </div>
  );
}

function ResultsChart({ onCta }: { onCta: () => void }) {
  const [metric, setMetric] = useState<"leads" | "cpl" | "roas">("leads");
  const s = series[metric];
  const first = s.data[0].v;
  const last = s.data[s.data.length - 1].v;
  const delta = Math.round(((last - first) / first) * 100);

  return (
    <div className="grid lg:grid-cols-[1.35fr_1fr] gap-6">
      <div className="bg-white rounded-3xl border border-black/5 shadow-xl shadow-black/5 p-6 sm:p-8">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="text-sm font-bold text-[#1A2332]">{s.label}</div>
            <div className="text-xs text-[#6B7280] mt-1">{s.hint}</div>
          </div>
          <div className="flex gap-1 bg-[#F5F7FA] p-1 rounded-xl">
            {([["leads", "Leads"], ["cpl", "Costo/lead"], ["roas", "ROAS"]] as const).map(([k, label]) => (
              <button key={k} onClick={() => setMetric(k)}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors ${metric === k
                  ? "bg-white text-[#0F9D6E] shadow-sm"
                  : "text-[#6B7280] hover:text-[#1A2332]"}`}>
                {label}
              </button>
            ))}
          </div>
        </div>

        <div className="mt-5 flex items-baseline gap-3">
          <span className="text-4xl font-extrabold text-[#1A2332]">
            {last.toLocaleString("es-MX")}{s.suffix}
          </span>
          <span className={`text-sm font-bold px-2.5 py-1 rounded-full ${s.up ? "bg-emerald-50 text-[#0F9D6E]" : "bg-emerald-50 text-[#0F9D6E]"}`}>
            {delta > 0 ? "+" : ""}{delta}% vs. mes 0
          </span>
        </div>

        <div className="mt-6 h-[260px] -ml-2" data-om-raster>
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={s.data as any} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id="semGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#0F9D6E" stopOpacity={0.28} />
                  <stop offset="100%" stopColor="#0F9D6E" stopOpacity={0.02} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="4 4" stroke="rgba(0,0,0,0.06)" vertical={false} />
              <XAxis dataKey="m" tick={{ fontSize: 12, fill: "#6B7280" }} axisLine={false} tickLine={false} />
              <YAxis tick={{ fontSize: 12, fill: "#6B7280" }} axisLine={false} tickLine={false} width={48} />
              <Tooltip
                contentStyle={{ borderRadius: 12, border: "1px solid rgba(0,0,0,0.06)", fontSize: 12, boxShadow: "0 8px 24px rgba(0,0,0,0.08)" }}
                formatter={(v: any) => [`${Number(v).toLocaleString("es-MX")}${s.suffix}`, s.label]} />
              <Area type="monotone" dataKey="v" stroke="#0F9D6E" strokeWidth={3} fill="url(#semGrad)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="bg-[#F5F7FA] rounded-3xl border border-black/5 p-6 sm:p-8 flex flex-col">
        <div className="text-sm font-bold text-[#1A2332]">A dónde se va el presupuesto</div>
        <div className="text-xs text-[#6B7280] mt-1">Distribución promedio del gasto en las cuentas que auditamos, antes de optimizarlas.</div>
        <div className="mt-5 h-[240px]" data-om-raster>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={spendMix} layout="vertical" margin={{ top: 0, right: 28, left: 0, bottom: 0 }}>
              <XAxis type="number" hide domain={[0, 40]} />
              <YAxis type="category" dataKey="name" width={148} tick={{ fontSize: 11, fill: "#1A2332" }} axisLine={false} tickLine={false} />
              <Tooltip formatter={(v: any) => [`${v}% del gasto`, ""]}
                contentStyle={{ borderRadius: 12, border: "1px solid rgba(0,0,0,0.06)", fontSize: 12 }} />
              <Bar dataKey="v" radius={[0, 8, 8, 0]} barSize={16}>
                {spendMix.map((d, i) => (
                  <Cell key={d.name} fill={i === 0 ? "#0F9D6E" : "#F59E0B"} fillOpacity={i === 0 ? 1 : 0.75 - i * 0.08} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>
        <p className="text-xs text-[#6B7280] leading-relaxed mt-4">
          En promedio, <span className="font-bold text-[#1A2332]">66% del presupuesto</span> se va en clics que jamás iban a comprar.
          Ese es el primer dinero que recuperamos.
        </p>
        <button onClick={onCta}
          className="mt-5 w-full flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] hover:brightness-105 transition-all">
          Ver mi desperdicio real<ArrowRight size={15} />
        </button>
      </div>
    </div>
  );
}

export default function ServiceSEMConversionPage({ setPage }: { setPage: (p: Page) => void }) {
  const formRef = useRef<HTMLDivElement | null>(null);
  const [openQ, setOpenQ] = useState<number | null>(0);

  // La barra fija móvil vive fuera del Shell: reservamos el espacio en el documento
  // para que no tape el footer, y subimos el FAB de WhatsApp por encima de ella.
  useEffect(() => {
    // Reservamos espacio para la barra fija y ocultamos el FAB del Shell en móvil
    // (su acción vive dentro de la barra) mediante una hoja de estilos propia.
    const style = document.createElement("style");
    style.id = "rp-cta-bar-offsets";
    style.textContent =
      "@media (max-width: 1023px){" +
      "#rankpro-mount{padding-bottom:96px}" +
      'a[aria-label="WhatsApp"]{display:none !important}' +
      "}";
    document.head.appendChild(style);
    return () => { style.remove(); };
  }, []);

  const toForm = () => {
    const el = formRef.current;
    if (!el) return;
    const target = Math.max(0, el.getBoundingClientRect().top + window.scrollY - 90);
    window.scrollTo(0, target);
    const input = el.querySelector("input") as HTMLInputElement | null;
    if (input) input.focus({ preventScroll: true });
  };


  return (
    <div>
      {/* Hero de conversión */}
      <section className="bg-[#F5F7FA] px-4 pt-14 pb-16 border-b border-black/5">
        <div className="max-w-7xl mx-auto grid lg:grid-cols-[1.05fr_0.95fr] gap-12 items-start">
          <div>
            <GreenBadge><Target size={12} />SEM &amp; GOOGLE ADS</GreenBadge>
            <h1 className="text-4xl sm:text-5xl font-extrabold text-[#1A2332] leading-[1.08] tracking-tight">
              Deja de pagar clics.<br />Empieza a pagar <span className="text-[#0F9D6E]">ventas.</span>
            </h1>
            <p className="text-[#6B7280] text-lg mt-5 leading-relaxed max-w-xl">
              Auditamos tu cuenta de Google Ads gratis y te mostramos, con capturas, cuánto presupuesto
              se está yendo en búsquedas que nunca van a comprar. Después lo arreglamos.
            </p>

            <ul className="mt-7 space-y-3">
              {[
                "Diagnóstico en 48 horas, sin contrato ni tarjeta.",
                "Estructura, pujas y seguimiento reconstruidos desde cero.",
                "Reportes con costo por lead y ROAS, no con impresiones.",
              ].map((t) => (
                <li key={t} className="flex items-start gap-3 text-[#1A2332] text-[15px]">
                  <CheckCircle size={19} className="text-[#0F9D6E] shrink-0 mt-0.5" />{t}
                </li>
              ))}
            </ul>

            <div className="flex flex-wrap items-center gap-3 mt-8">
              <button onClick={toForm}
                className="flex items-center gap-2 px-7 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:shadow-xl hover:brightness-105 transition-all duration-200">
                Auditar mi cuenta gratis<ArrowRight size={17} />
              </button>
              <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer"
                className="flex items-center gap-2 px-6 py-4 rounded-xl font-semibold text-[#1A2332] bg-white border border-black/10 hover:border-[#0F9D6E] hover:text-[#0F9D6E] transition-colors">
                <MessageCircle size={16} />Preguntar por WhatsApp
              </a>
            </div>

            <div className="mt-8 pt-7 border-t border-black/5 grid grid-cols-2 sm:grid-cols-4 gap-5">
              {proof.map((p) => (
                <div key={p.label}>
                  <div className="text-2xl font-extrabold text-[#0F9D6E]">{p.value}</div>
                  <div className="text-xs text-[#6B7280] mt-1 leading-snug">{p.label}</div>
                </div>
              ))}
            </div>

            <div className="mt-7 flex flex-wrap items-center gap-5 text-xs font-semibold text-[#6B7280]">
              <span className="flex items-center gap-1.5"><Award size={14} className="text-[#0F9D6E]" />Google Premier Partner</span>
              <span className="flex items-center gap-1.5"><Award size={14} className="text-[#0F9D6E]" />Meta Business Partner</span>
              <span className="flex items-center gap-1.5"><Star size={14} className="text-[#F59E0B] fill-[#F59E0B]" />4.9 / 5 · 187 reseñas</span>
            </div>
          </div>

          <div ref={formRef} className="lg:sticky lg:top-32">
            <LeadForm />
          </div>
        </div>
      </section>

      {/* Calculadora */}
      <section className="px-4 py-20 bg-white">
        <div className="max-w-6xl mx-auto">
          <ROICalculator onCta={toForm} />
        </div>
      </section>

      {/* Qué incluye la auditoría */}
      <section className="px-4 py-20 bg-[#F5F7FA] border-y border-black/5">
        <div className="max-w-6xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
          <div>
            <GreenBadge><CheckCircle size={12} />LA OFERTA, SIN LETRA CHICA</GreenBadge>
            <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight leading-tight">
              Qué recibes en la auditoría gratuita
            </h2>
            <p className="text-[#6B7280] mt-4 leading-relaxed">
              Un documento de 12 a 18 páginas con hallazgos concretos de tu cuenta y el plan de los
              primeros 90 días. Te lo presentamos en una llamada de 30 minutos.
            </p>
            <button onClick={toForm}
              className="mt-7 flex items-center gap-2 px-6 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:brightness-105 transition-all">
              Reclamar mi auditoría<ArrowRight size={16} />
            </button>
          </div>
          <div className="grid gap-3">
            {auditItems.map((t, i) => (
              <div key={t} className="flex items-start gap-4 bg-white rounded-2xl border border-black/5 p-5">
                <div className="w-9 h-9 rounded-xl bg-emerald-50 text-[#0F9D6E] font-extrabold text-sm flex items-center justify-center shrink-0">
                  {String(i + 1).padStart(2, "0")}
                </div>
                <p className="text-sm text-[#1A2332] leading-relaxed">{t}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Gráficos de resultados */}
      <section className="px-4 py-20 bg-white">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-12">
            <GreenBadge><TrendingUp size={12} />CURVA REAL DE NUESTRAS CUENTAS</GreenBadge>
            <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight">Qué cambia en los primeros 5 meses</h2>
            <p className="text-[#6B7280] mt-3 text-sm max-w-xl mx-auto leading-relaxed">
              Promedio ponderado de 312 cuentas de Google Ads gestionadas en México. Mes 0 = estado en que las recibimos.
            </p>
          </div>
          <ResultsChart onCta={toForm} />
        </div>
      </section>

      {/* Garantías */}
      <section className="px-4 py-20 bg-white">
        <div className="max-w-6xl mx-auto grid md:grid-cols-3 gap-6">
          {guarantees.map((g) => (
            <div key={g.title} className="bg-[#F5F7FA] rounded-2xl p-7 border border-black/5">
              <div className="w-11 h-11 rounded-xl bg-white border border-black/5 flex items-center justify-center mb-4">
                <g.icon size={20} className="text-[#0F9D6E]" />
              </div>
              <h3 className="font-bold text-[#1A2332]">{g.title}</h3>
              <p className="text-sm text-[#6B7280] mt-2 leading-relaxed">{g.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Objeciones */}
      <section className="px-4 pb-20 bg-white">
        <div className="max-w-3xl mx-auto">
          <div className="text-center mb-10">
            <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight">Antes de que preguntes</h2>
          </div>
          <div className="space-y-3">
            {objections.map((o, i) => (
              <div key={o.q} className="bg-[#F5F7FA] rounded-2xl border border-black/5 overflow-hidden">
                <button onClick={() => setOpenQ(openQ === i ? null : i)}
                  className="w-full flex items-center justify-between gap-4 text-left px-6 py-5 hover:bg-emerald-50/60 transition-colors">
                  <span className="font-semibold text-[#1A2332] text-[15px]">{o.q}</span>
                  <ChevronDown size={18} className={`text-[#0F9D6E] shrink-0 transition-transform duration-200 ${openQ === i ? "rotate-180" : ""}`} />
                </button>
                {openQ === i && <p className="px-6 pb-6 text-sm text-[#6B7280] leading-relaxed">{o.a}</p>}
              </div>
            ))}
          </div>
          <div className="text-center mt-10">
            <button onClick={toForm}
              className="inline-flex items-center gap-2 px-7 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:brightness-105 transition-all">
              Resolver mi caso con un estratega<ArrowRight size={17} />
            </button>
          </div>
        </div>
      </section>

      {/* CTA final */}
      <section className="px-4 py-20 bg-gradient-to-br from-[#0F9D6E] to-[#34D399]">
        <div className="max-w-3xl mx-auto text-center">
          <h2 className="text-4xl font-extrabold text-white leading-tight tracking-tight">
            Tu competencia ya está pujando por tus clientes
          </h2>
          <p className="text-emerald-50 text-lg mt-4 leading-relaxed">
            Quedan 2 auditorías gratuitas este mes. Cada una toma 48 horas y no te compromete a nada.
          </p>
          <div className="flex flex-wrap items-center justify-center gap-3 mt-8">
            <button onClick={toForm}
              className="flex items-center gap-2 px-8 py-4 rounded-xl font-bold text-[#0F9D6E] bg-white hover:bg-emerald-50 transition-colors shadow-xl shadow-emerald-700/20">
              Reclamar mi auditoría gratis<ArrowRight size={17} />
            </button>
            <button onClick={() => setPage("agendar")}
              className="flex items-center gap-2 px-7 py-4 rounded-xl font-semibold text-white border-2 border-white/50 hover:bg-white/10 transition-colors">
              Prefiero agendar una llamada
            </button>
          </div>
        </div>
      </section>

      {/* CTA fija móvil */}
      <div className="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-black/10 px-4 py-3 flex items-center gap-2.5" style={{ boxShadow: "0 -6px 24px rgba(0,0,0,0.08)" }}>
        <a href="tel:+525512345678" aria-label="Llamar" className="w-12 h-12 shrink-0 rounded-xl border border-black/10 flex items-center justify-center text-[#0F9D6E]">
          <Phone size={18} />
        </a>
        <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer" aria-label="Escribir por WhatsApp"
          className="w-12 h-12 shrink-0 rounded-xl bg-[#25D366] flex items-center justify-center text-white">
          <MessageCircle size={18} />
        </a>
        <button onClick={toForm}
          className="flex-1 flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399]">
          Auditoría gratis<ArrowRight size={16} />
        </button>
      </div>
    </div>
  );
}
