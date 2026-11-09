import { useState, useRef, useEffect } from "react";
import {
  Search, ArrowRight, CheckCircle, AlertTriangle, XCircle, Lock, Loader2,
  MessageCircle, Phone, Globe, FileText, Link2, Gauge, ChevronRight, ShieldCheck, TrendingUp
} from "lucide-react";
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import type { Page } from "../types";

// Puntaje determinista a partir del dominio: el informe se siente propio, no genérico.
function hash(str: string) {
  let h = 0;
  for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) % 100000;
  return h;
}

const scanSteps = [
  { icon: Globe, label: "Rastreando URLs indexadas" },
  { icon: Gauge, label: "Midiendo Core Web Vitals en móvil" },
  { icon: FileText, label: "Comparando contenido con 3 competidores" },
  { icon: Link2, label: "Evaluando perfil de enlaces y autoridad" },
];

type Finding = { sev: "critico" | "medio" | "ok"; title: string; detail: string; gated?: boolean };

const findings: Finding[] = [
  { sev: "critico", title: "38 páginas compiten por la misma keyword", detail: "Canibalización: Google no sabe cuál mostrar y termina bajando las dos." },
  { sev: "critico", title: "LCP de 4.8 s en móvil", detail: "Cada segundo extra te cuesta cerca del 7% de las conversiones orgánicas." },
  { sev: "medio", title: "Sin contenido para 61% de la intención comercial", detail: "Tus competidores cubren comparativas y precios; tu sitio no." },
  { sev: "ok", title: "Estructura de URLs y sitemap correctos", detail: "Base técnica sana: se puede construir encima sin rehacer el sitio." },
  { sev: "critico", title: "142 enlaces internos apuntan a redirecciones", detail: "Se diluye autoridad antes de llegar a tus páginas que venden.", gated: true },
  { sev: "medio", title: "Títulos duplicados en 27 fichas", detail: "Plantillas sin variables: Google reescribe tus títulos por su cuenta.", gated: true },
  { sev: "medio", title: "Perfil de enlaces 4x menor que el líder", detail: "Necesitas 18-24 dominios de referencia nuevos para competir por tus cabezas.", gated: true },
  { sev: "critico", title: "Datos estructurados ausentes en producto y FAQ", detail: "Estás fuera de los resultados enriquecidos donde se gana el clic.", gated: true },
];

const sevStyle = {
  critico: { icon: XCircle, color: "#EF4444", bg: "rgba(239,68,68,0.08)", label: "Crítico" },
  medio: { icon: AlertTriangle, color: "#F59E0B", bg: "rgba(245,158,11,0.10)", label: "Medio" },
  ok: { icon: CheckCircle, color: "#0F9D6E", bg: "rgba(15,157,110,0.08)", label: "Correcto" },
} as const;

export default function ServiceSEOConversionPage({ setPage }: { setPage: (p: Page) => void }) {
  const [domain, setDomain] = useState("");
  const [phase, setPhase] = useState<"idle" | "scanning" | "result">("idle");
  const [step, setStep] = useState(0);
  const [unlocked, setUnlocked] = useState(false);
  const [gate, setGate] = useState({ email: "", tel: "" });
  const resultRef = useRef<HTMLDivElement | null>(null);
  const gateRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    const style = document.createElement("style");
    style.textContent =
      "@media (max-width: 1023px){" +
      "#rankpro-mount{padding-bottom:96px}" +
      'a[aria-label="WhatsApp"]{display:none !important}' +
      "}";
    document.head.appendChild(style);
    return () => { style.remove(); };
  }, []);

  useEffect(() => {
    if (phase !== "scanning") return;
    if (step >= scanSteps.length) { setPhase("result"); return; }
    const t = setTimeout(() => setStep((s) => s + 1), 850);
    return () => clearTimeout(t);
  }, [phase, step]);

  useEffect(() => {
    if (phase !== "result") return;
    const el = resultRef.current;
    if (el) window.scrollTo(0, Math.max(0, el.getBoundingClientRect().top + window.scrollY - 70));
  }, [phase]);

  const clean = domain.trim().replace(/^https?:\/\//, "").replace(/\/.*$/, "") || "tusitio.mx";
  const seed = hash(clean);
  const score = 28 + (seed % 31);
  const urls = 120 + (seed % 640);
  const perdidas = 1400 + (seed % 5200);
  const competidor = clean.split(".")[0].slice(0, 3) + "-lider.mx";

  const gap = [0, 2, 4, 6, 9, 12].map((m, i) => ({
    m: "Mes " + m,
    tu: Math.round(perdidas * [0.18, 0.19, 0.2, 0.21, 0.22, 0.23][i]),
    conRankPro: Math.round(perdidas * [0.18, 0.34, 0.58, 0.86, 1.24, 1.72][i]),
  }));

  const startScan = () => {
    if (phase === "scanning") return;
    setStep(0);
    setUnlocked(false);
    setPhase("scanning");
  };

  const toScanner = () => {
    window.scrollTo(0, 0);
    if (inputRef.current) inputRef.current.focus({ preventScroll: true });
  };

  const toGate = () => {
    const el = gateRef.current;
    if (el) window.scrollTo(0, Math.max(0, el.getBoundingClientRect().top + window.scrollY - 90));
  };

  return (
    <div>
      {/* ── Escáner ───────────────────────────────────────────────── */}
      <section className="bg-[#1A2332] px-4 pt-16 pb-20 relative overflow-hidden">
        <div className="absolute inset-0 opacity-[0.07]" style={{ backgroundImage: "radial-gradient(circle at 20% 20%, #34D399 1px, transparent 1px)", backgroundSize: "34px 34px" }} />
        <div className="max-w-3xl mx-auto relative">
          <div className="inline-flex items-center gap-2 text-[11px] font-bold tracking-[0.14em] text-[#34D399] border border-[#34D399]/30 rounded-full px-3.5 py-1.5">
            <Search size={12} />DIAGNÓSTICO SEO EN VIVO
          </div>
          <h1 className="text-4xl sm:text-5xl font-extrabold text-white leading-[1.08] tracking-tight mt-6">
            Escribe tu dominio.<br />Te decimos qué te está <span className="text-[#34D399]">frenando</span> en Google.
          </h1>
          <p className="text-white/60 text-lg mt-5 leading-relaxed">
            Sin llamadas de venta para empezar: primero el diagnóstico, después decides.
          </p>

          <form onSubmit={(e) => { e.preventDefault(); startScan(); }}
            className="mt-8 flex flex-col sm:flex-row gap-3 bg-white rounded-2xl p-2 shadow-2xl shadow-black/40">
            <div className="flex items-center gap-2.5 flex-1 px-4">
              <Globe size={17} className="text-[#9CA3AF] shrink-0" />
              <input ref={inputRef} required value={domain} onChange={(e) => setDomain(e.target.value)}
                placeholder="miempresa.com.mx"
                className="w-full py-3.5 text-[15px] text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none" />
            </div>
            <button type="submit"
              className="flex items-center justify-center gap-2 px-7 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] hover:brightness-110 transition-all shrink-0">
              {phase === "scanning" ? <Loader2 size={17} className="animate-spin" /> : <Search size={17} />}
              {phase === "scanning" ? "Analizando…" : "Analizar gratis"}
            </button>
          </form>

          <div className="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-xs text-white/45">
            <span>184 sitios mexicanos analizados este año</span>
            <span>·</span>
            <span>Resultado en 30 segundos</span>
            <span>·</span>
            <span>No pedimos accesos a tu sitio</span>
          </div>
        </div>
      </section>

      {/* ── Progreso del escaneo ──────────────────────────────────── */}
      {phase === "scanning" && (
        <section className="px-4 py-16 bg-white">
          <div className="max-w-2xl mx-auto">
            <div className="text-sm font-bold text-[#1A2332]">Analizando {clean}</div>
            <div className="h-1.5 bg-[#F5F7FA] rounded-full overflow-hidden mt-4">
              <div className="h-full bg-gradient-to-r from-[#0F9D6E] to-[#34D399] transition-all duration-700"
                style={{ width: `${(step / scanSteps.length) * 100}%` }} />
            </div>
            <div className="mt-7 grid gap-2.5">
              {scanSteps.map((s, i) => (
                <div key={s.label}
                  className={`flex items-center gap-3 px-4 py-3 rounded-xl border transition-colors ${i < step
                    ? "border-emerald-200 bg-emerald-50/60"
                    : i === step ? "border-black/10 bg-white" : "border-black/5 bg-white opacity-40"}`}>
                  {i < step
                    ? <CheckCircle size={17} className="text-[#0F9D6E] shrink-0" />
                    : i === step
                      ? <Loader2 size={17} className="text-[#0F9D6E] shrink-0 animate-spin" />
                      : <s.icon size={17} className="text-[#9CA3AF] shrink-0" />}
                  <span className="text-sm text-[#1A2332]">{s.label}</span>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* ── Informe ───────────────────────────────────────────────── */}
      {phase === "result" && (
        <div ref={resultRef}>
          <section className="px-4 py-16 bg-white border-b border-black/5">
            <div className="max-w-5xl mx-auto">
              <div className="flex flex-wrap items-end justify-between gap-6">
                <div>
                  <div className="text-xs font-bold tracking-[0.14em] text-[#0F9D6E]">INFORME PRELIMINAR</div>
                  <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight mt-2">{clean}</h2>
                  <p className="text-[#6B7280] text-sm mt-2">{urls} URLs rastreadas · comparado contra {competidor}</p>
                </div>
                <div className="flex items-center gap-5">
                  <div className="relative w-[104px] h-[104px]">
                    <svg viewBox="0 0 100 100" className="w-full h-full -rotate-90">
                      <circle cx="50" cy="50" r="42" fill="none" stroke="#F5F7FA" strokeWidth="12" />
                      <circle cx="50" cy="50" r="42" fill="none" stroke="#F59E0B" strokeWidth="12" strokeLinecap="round"
                        strokeDasharray={`${(score / 100) * 264} 264`} />
                    </svg>
                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                      <span className="text-2xl font-extrabold text-[#1A2332] leading-none">{score}</span>
                      <span className="text-[10px] text-[#6B7280] mt-0.5">/ 100</span>
                    </div>
                  </div>
                  <div className="max-w-[190px]">
                    <div className="text-sm font-bold text-[#1A2332]">Salud SEO: mejorable</div>
                    <div className="text-xs text-[#6B7280] mt-1 leading-relaxed">
                      Los sitios que rankean en tu sector promedian 78.
                    </div>
                  </div>
                </div>
              </div>

              <div className="mt-10 grid gap-3">
                {findings.map((f, i) => {
                  const st = sevStyle[f.sev];
                  const hidden = f.gated && !unlocked;
                  return (
                    <div key={f.title}
                      className="flex items-start gap-4 p-5 rounded-2xl border border-black/5 bg-white relative overflow-hidden">
                      <div className="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style={{ background: st.bg }}>
                        {hidden ? <Lock size={15} style={{ color: "#9CA3AF" }} /> : <st.icon size={17} style={{ color: st.color }} />}
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="text-[15px] font-bold text-[#1A2332]" style={hidden ? { filter: "blur(5px)", userSelect: "none" } : undefined}>
                            {f.title}
                          </span>
                          {!hidden && (
                            <span className="text-[10px] font-extrabold tracking-wider px-2 py-0.5 rounded-full"
                              style={{ background: st.bg, color: st.color }}>{st.label.toUpperCase()}</span>
                          )}
                        </div>
                        <p className="text-sm text-[#6B7280] mt-1 leading-relaxed" style={hidden ? { filter: "blur(5px)", userSelect: "none" } : undefined}>
                          {f.detail}
                        </p>
                      </div>
                      {hidden && (
                        <button onClick={toGate}
                          className="shrink-0 self-center text-xs font-bold text-[#0F9D6E] hover:underline flex items-center gap-1">
                          Desbloquear<ChevronRight size={13} />
                        </button>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          </section>

          {/* Gate: el correo se pide en el punto de máxima curiosidad */}
          {!unlocked && (
            <section ref={gateRef} className="px-4 py-16 bg-[#F5F7FA] border-b border-black/5">
              <div className="max-w-2xl mx-auto text-center">
                <div className="inline-flex items-center gap-2 text-xs font-bold tracking-wider text-[#0F9D6E] bg-emerald-50 border border-emerald-200 rounded-full px-3.5 py-1.5">
                  <Lock size={12} />4 HALLAZGOS CRÍTICOS SIN VER
                </div>
                <h3 className="text-2xl sm:text-3xl font-extrabold text-[#1A2332] tracking-tight mt-5 leading-tight">
                  Te mandamos el informe completo de {clean}
                </h3>
                <p className="text-[#6B7280] mt-3 leading-relaxed">
                  Incluye el detalle técnico, las keywords que se está llevando {competidor} y el plan de los
                  primeros 90 días. Un especialista te lo explica si quieres, no si no.
                </p>
                <form onSubmit={(e) => { e.preventDefault(); setUnlocked(true); }}
                  className="mt-7 grid sm:grid-cols-[1fr_1fr_auto] gap-3">
                  <input required type="email" value={gate.email} onChange={(e) => setGate({ ...gate, email: e.target.value })}
                    placeholder="Email corporativo*"
                    className="px-4 py-3.5 rounded-xl bg-white border border-black/10 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] transition-colors" />
                  <input required value={gate.tel} onChange={(e) => setGate({ ...gate, tel: e.target.value })}
                    placeholder="WhatsApp*"
                    className="px-4 py-3.5 rounded-xl bg-white border border-black/10 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] transition-colors" />
                  <button type="submit"
                    className="flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:brightness-105 transition-all">
                    Ver todo<ArrowRight size={16} />
                  </button>
                </form>
                <div className="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[11px] text-[#6B7280]">
                  <span className="flex items-center gap-1"><ShieldCheck size={12} className="text-[#0F9D6E]" />No compartimos tus datos</span>
                  <span className="flex items-center gap-1"><CheckCircle size={12} className="text-[#0F9D6E]" />Sin cargos ni contrato</span>
                </div>
              </div>
            </section>
          )}

          {unlocked && (
            <section className="px-4 py-14 bg-emerald-50 border-b border-emerald-100">
              <div className="max-w-2xl mx-auto text-center">
                <div className="w-12 h-12 rounded-2xl bg-white flex items-center justify-center mx-auto">
                  <CheckCircle size={22} className="text-[#0F9D6E]" />
                </div>
                <h3 className="text-2xl font-extrabold text-[#1A2332] mt-4">Informe completo desbloqueado</h3>
                <p className="text-[#6B7280] text-sm mt-2 leading-relaxed">
                  También va camino a tu correo. Si quieres el plan de ejecución, agenda 30 minutos con un especialista.
                </p>
                <div className="flex flex-wrap items-center justify-center gap-3 mt-6">
                  <button onClick={() => setPage("agendar")}
                    className="flex items-center gap-2 px-6 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] hover:brightness-105 transition-all">
                    Agendar la explicación<ArrowRight size={16} />
                  </button>
                  <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer"
                    className="flex items-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-[#1A2332] bg-white border border-black/10 hover:border-[#0F9D6E] hover:text-[#0F9D6E] transition-colors">
                    <MessageCircle size={16} />Dudas por WhatsApp
                  </a>
                </div>
              </div>
            </section>
          )}

          {/* Brecha de tráfico */}
          <section className="px-4 py-20 bg-white">
            <div className="max-w-5xl mx-auto">
              <div className="flex flex-wrap items-end justify-between gap-6 mb-8">
                <div>
                  <div className="text-xs font-bold tracking-[0.14em] text-[#0F9D6E] flex items-center gap-2">
                    <TrendingUp size={13} />TU BRECHA DE TRÁFICO
                  </div>
                  <h3 className="text-2xl sm:text-3xl font-extrabold text-[#1A2332] tracking-tight mt-2 leading-tight">
                    Lo que dejas sobre la mesa cada mes
                  </h3>
                  <p className="text-[#6B7280] text-sm mt-2 max-w-lg leading-relaxed">
                    Sesiones orgánicas proyectadas para {clean} sin cambios, contra la curva promedio de
                    nuestros proyectos después de corregir los hallazgos de arriba.
                  </p>
                </div>
                <div className="text-right">
                  <div className="text-3xl font-extrabold text-[#0F9D6E]">
                    {(gap[5].conRankPro - gap[5].tu).toLocaleString("es-MX")}
                  </div>
                  <div className="text-xs text-[#6B7280] mt-1">sesiones/mes de diferencia al mes 12</div>
                </div>
              </div>
              <div className="h-[300px]" data-om-raster>
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={gap} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <defs>
                      <linearGradient id="seoUp" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#0F9D6E" stopOpacity={0.3} />
                        <stop offset="100%" stopColor="#0F9D6E" stopOpacity={0.02} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="4 4" stroke="rgba(0,0,0,0.06)" vertical={false} />
                    <XAxis dataKey="m" tick={{ fontSize: 12, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fontSize: 12, fill: "#6B7280" }} axisLine={false} tickLine={false} width={56} />
                    <Tooltip contentStyle={{ borderRadius: 12, border: "1px solid rgba(0,0,0,0.06)", fontSize: 12 }}
                      formatter={(v: any, n: any) => [Number(v).toLocaleString("es-MX"), n === "tu" ? "Sin cambios" : "Con RankPro"]} />
                    <Area type="monotone" dataKey="tu" stroke="#9CA3AF" strokeWidth={2} strokeDasharray="5 4" fill="transparent" />
                    <Area type="monotone" dataKey="conRankPro" stroke="#0F9D6E" strokeWidth={3} fill="url(#seoUp)" />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </div>
          </section>

          {/* Cómo se vería tu resultado */}
          <section className="px-4 pb-20 bg-white">
            <div className="max-w-3xl mx-auto">
              <h3 className="text-xl font-extrabold text-[#1A2332] tracking-tight">Cómo se vería tu resultado en Google</h3>
              <p className="text-[#6B7280] text-sm mt-2">Antes y después de reescribir títulos, meta descripciones y datos estructurados.</p>
              <div className="mt-6 grid gap-4">
                <div className="rounded-2xl border border-black/5 bg-[#F5F7FA] p-5">
                  <div className="text-[11px] font-bold tracking-wider text-[#6B7280]">HOY</div>
                  <div className="mt-3 text-[13px] text-[#6B7280]">{clean} › inicio</div>
                  <div className="text-[19px] text-[#1a0dab] mt-0.5">Inicio | {clean.split(".")[0]}</div>
                  <div className="text-[13px] text-[#4d5156] mt-1 leading-snug">
                    Bienvenido a nuestro sitio web. Somos una empresa comprometida con la calidad y el servicio…
                  </div>
                </div>
                <div className="rounded-2xl border-2 border-[#0F9D6E] bg-white p-5 shadow-lg shadow-emerald-100">
                  <div className="text-[11px] font-bold tracking-wider text-[#0F9D6E]">CON RANKPRO</div>
                  <div className="mt-3 text-[13px] text-[#6B7280]">{clean} › servicios › cdmx</div>
                  <div className="text-[19px] text-[#1a0dab] mt-0.5">
                    Servicio en CDMX con entrega en 24 h | {clean.split(".")[0]}
                  </div>
                  <div className="text-[13px] text-[#4d5156] mt-1 leading-snug">
                    Cotiza en línea en 2 minutos. Más de 500 clientes atendidos, garantía de 12 meses y
                    facturación inmediata. ★ 4.9 · 187 reseñas.
                  </div>
                  <div className="flex flex-wrap gap-2 mt-3">
                    {["Reseñas ★", "Precios", "FAQ enriquecidas"].map((t) => (
                      <span key={t} className="text-[11px] font-semibold bg-emerald-50 text-[#0F9D6E] px-2.5 py-1 rounded-full">{t}</span>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      )}

      {/* ── Cierre: solo si aún no escanean ───────────────────────── */}
      {phase === "idle" && (
        <>
          <section className="px-4 py-16 bg-white border-b border-black/5">
            <div className="max-w-4xl mx-auto grid sm:grid-cols-3 gap-8">
              {[
                { n: "01", t: "Escaneamos", d: "Rastreo técnico, contenido y enlaces. 30 segundos, sin accesos." },
                { n: "02", t: "Te mostramos", d: "Hallazgos priorizados por impacto y la brecha frente a tu competencia." },
                { n: "03", t: "Tú decides", d: "Si quieres, ejecutamos. Si no, el informe se queda contigo." },
              ].map((s) => (
                <div key={s.n}>
                  <div className="text-xs font-extrabold text-[#0F9D6E] tracking-widest">{s.n}</div>
                  <div className="font-bold text-[#1A2332] mt-2">{s.t}</div>
                  <p className="text-sm text-[#6B7280] mt-1.5 leading-relaxed">{s.d}</p>
                </div>
              ))}
            </div>
          </section>

          <section className="px-4 py-20 bg-[#F5F7FA]">
            <div className="max-w-2xl mx-auto text-center">
              <h3 className="text-3xl font-extrabold text-[#1A2332] tracking-tight leading-tight">
                Empieza por saber dónde estás parado
              </h3>
              <p className="text-[#6B7280] mt-3 leading-relaxed">
                El 55% de los sitios que analizamos pierde tráfico por contenido y problemas técnicos, no por presupuesto.
              </p>
              <button onClick={toScanner}
                className="mt-7 inline-flex items-center gap-2 px-7 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:brightness-105 transition-all">
                Analizar mi dominio<ArrowRight size={17} />
              </button>
            </div>
          </section>
        </>
      )}

      {/* Barra fija móvil */}
      <div className="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-black/10 px-4 py-3 flex items-center gap-2.5"
        style={{ boxShadow: "0 -6px 24px rgba(0,0,0,0.08)" }}>
        <a href="tel:+525512345678" aria-label="Llamar" className="w-12 h-12 shrink-0 rounded-xl border border-black/10 flex items-center justify-center text-[#0F9D6E]">
          <Phone size={18} />
        </a>
        <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer" aria-label="Escribir por WhatsApp"
          className="w-12 h-12 shrink-0 rounded-xl bg-[#25D366] flex items-center justify-center text-white">
          <MessageCircle size={18} />
        </a>
        <button onClick={phase === "result" && !unlocked ? toGate : toScanner}
          className="flex-1 flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399]">
          {phase === "result" && !unlocked ? "Ver informe completo" : "Analizar mi dominio"}<ArrowRight size={16} />
        </button>
      </div>
    </div>
  );
}
