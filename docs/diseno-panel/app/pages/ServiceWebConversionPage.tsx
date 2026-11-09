import { useState, useRef, useEffect } from "react";
import {
  Code2, ArrowRight, CheckCircle, Phone, MessageCircle, Gauge, Github,
  Layers, ShoppingCart, LayoutDashboard, FileCode2, Zap, ChevronDown, ShieldCheck, Rocket
} from "lucide-react";
import type { Page } from "../types";

type TipoKey = "landing" | "corporativo" | "ecommerce" | "plataforma";

const tipos: { key: TipoKey; icon: any; label: string; desc: string; base: number; semanas: number }[] = [
  { key: "landing", icon: Zap, label: "Landing de campaña", desc: "1 objetivo, máxima conversión", base: 38000, semanas: 3 },
  { key: "corporativo", icon: Layers, label: "Sitio corporativo", desc: "Servicios, contenido, blog", base: 78000, semanas: 6 },
  { key: "ecommerce", icon: ShoppingCart, label: "E-commerce", desc: "Catálogo, pagos, envíos", base: 145000, semanas: 10 },
  { key: "plataforma", icon: LayoutDashboard, label: "Plataforma / SaaS", desc: "Cuentas, panel, roles", base: 240000, semanas: 16 },
];

const features: { key: string; label: string; costo: number; semanas: number }[] = [
  { key: "cms", label: "CMS editable por tu equipo", costo: 18000, semanas: 1 },
  { key: "i18n", label: "Multi-idioma", costo: 22000, semanas: 1 },
  { key: "pagos", label: "Pagos en línea", costo: 32000, semanas: 2 },
  { key: "reservas", label: "Reservas / agenda", costo: 28000, semanas: 2 },
  { key: "crm", label: "Integración CRM", costo: 19000, semanas: 1 },
  { key: "panel", label: "Panel de administración", costo: 46000, semanas: 3 },
  { key: "api", label: "Integraciones por API", costo: 34000, semanas: 2 },
  { key: "seo", label: "SEO técnico y schema", costo: 14000, semanas: 1 },
];

const fases = [
  { nombre: "Descubrimiento", peso: 0.12, color: "#1A2332" },
  { nombre: "UI / Prototipo", peso: 0.24, color: "#0F9D6E" },
  { nombre: "Desarrollo", peso: 0.4, color: "#34D399" },
  { nombre: "QA y contenido", peso: 0.16, color: "#F59E0B" },
  { nombre: "Lanzamiento", peso: 0.08, color: "#6B7280" },
];

const stack = ["React / Next.js", "TypeScript", "Tailwind", "Node", "PostgreSQL", "Vercel / AWS", "Stripe / Conekta", "GA4 + GTM"];

const metas = [
  { valor: "< 1.8 s", label: "LCP en móvil", nota: "Google exige menos de 2.5 s" },
  { valor: "> 95", label: "PageSpeed móvil", nota: "Medido antes de entregar" },
  { valor: "100%", label: "Responsive real", nota: "Probado en 12 dispositivos" },
  { valor: "AA", label: "Accesibilidad WCAG", nota: "Contraste y navegación por teclado" },
];

const objections = [
  { q: "¿Por qué a medida y no WordPress con plantilla?", a: "Si tu sitio es informativo y no vas a crecer, una plantilla puede bastar y te lo diremos. A medida gana cuando necesitas velocidad real, integraciones, procesos propios o un diseño que no se parezca al de todos: se paga solo en conversión y en no rehacerlo en 18 meses." },
  { q: "¿De quién es el código?", a: "Tuyo. Trabajamos en tu repositorio de GitHub desde el primer commit, con acceso de propietario para ti. Sin licencias atadas a nosotros ni hosting cautivo." },
  { q: "¿Qué pasa si el alcance cambia a medio proyecto?", a: "Cotizamos por fases. Los cambios dentro del alcance entran sin costo; lo nuevo se cotiza aparte y tú apruebas antes de que se programe una línea." },
  { q: "¿Incluye contenido y fotos?", a: "Incluye estructura, redacción de la copy base y montaje. Fotografía y video se cotizan aparte o los aporta tu equipo." },
  { q: "¿Y después del lanzamiento?", a: "30 días de garantía sobre cualquier bug, más planes de mantenimiento mensuales opcionales con mejoras continuas y monitoreo de velocidad." },
];

const money = (n: number) => "$" + Math.round(n).toLocaleString("es-MX");

export default function ServiceWebConversionPage({ setPage }: { setPage: (p: Page) => void }) {
  const [tipo, setTipo] = useState<TipoKey>("corporativo");
  const [paginas, setPaginas] = useState(8);
  const [picked, setPicked] = useState<string[]>(["cms", "seo"]);
  const [rush, setRush] = useState(false);
  const [sent, setSent] = useState(false);
  const [form, setForm] = useState({ nombre: "", empresa: "", email: "", tel: "" });
  const configRef = useRef<HTMLDivElement | null>(null);
  const formRef = useRef<HTMLDivElement | null>(null);

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

  const t = tipos.find((x) => x.key === tipo)!;
  const extras = features.filter((f) => picked.includes(f.key));
  const costoExtras = extras.reduce((s, f) => s + f.costo, 0);
  const semanasExtras = extras.reduce((s, f) => s + f.semanas, 0);
  const paginasExtra = Math.max(0, paginas - 5) * 6200;
  const subtotal = t.base + paginasExtra + costoExtras;
  const total = rush ? subtotal * 1.28 : subtotal;
  const semanas = Math.max(2, Math.round((t.semanas + semanasExtras + Math.floor(Math.max(0, paginas - 5) / 4)) * (rush ? 0.7 : 1)));

  const toggle = (k: string) =>
    setPicked((p) => (p.includes(k) ? p.filter((x) => x !== k) : [...p, k]));

  const scrollTo = (el: HTMLElement | null, offset = 90) => {
    if (!el) return;
    window.scrollTo(0, Math.max(0, el.getBoundingClientRect().top + window.scrollY - offset));
  };

  return (
    <div>
      {/* ── Hero ─────────────────────────────────────────────────── */}
      <section className="px-4 pt-14 pb-16 bg-white border-b border-black/5">
        <div className="max-w-7xl mx-auto grid lg:grid-cols-[1fr_1fr] gap-14 items-center">
          <div>
            <div className="inline-flex items-center gap-2 text-[11px] font-bold tracking-[0.14em] text-[#0F9D6E] bg-emerald-50 border border-emerald-200 rounded-full px-3.5 py-1.5">
              <Code2 size={12} />DESARROLLO WEB A LA MEDIDA
            </div>
            <h1 className="text-4xl sm:text-5xl font-extrabold text-[#1A2332] leading-[1.08] tracking-tight mt-6">
              Tu sitio, armado a tu medida.<br />
              <span className="text-[#0F9D6E]">Con precio y fecha</span> antes de la junta.
            </h1>
            <p className="text-[#6B7280] text-lg mt-5 leading-relaxed max-w-xl">
              Elige lo que necesitas y verás el rango de inversión, las semanas de entrega y el
              cronograma por fases al instante. Sin correos de ida y vuelta para saber cuánto cuesta.
            </p>
            <div className="flex flex-wrap items-center gap-3 mt-8">
              <button onClick={() => scrollTo(configRef.current, 70)}
                className="flex items-center gap-2 px-7 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:brightness-105 transition-all">
                Armar mi cotización<ArrowRight size={17} />
              </button>
              <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer"
                className="flex items-center gap-2 px-6 py-4 rounded-xl font-semibold text-[#1A2332] border border-black/10 hover:border-[#0F9D6E] hover:text-[#0F9D6E] transition-colors">
                <MessageCircle size={16} />Tengo un caso raro
              </a>
            </div>
            <div className="mt-8 flex flex-wrap gap-x-7 gap-y-2 text-xs text-[#6B7280]">
              <span className="flex items-center gap-1.5"><Github size={13} className="text-[#0F9D6E]" />Código en tu repositorio</span>
              <span className="flex items-center gap-1.5"><Gauge size={13} className="text-[#0F9D6E]" />PageSpeed &gt; 95 garantizado</span>
              <span className="flex items-center gap-1.5"><ShieldCheck size={13} className="text-[#0F9D6E]" />30 días de garantía</span>
            </div>
          </div>

          {/* Mock de navegador */}
          <div className="rounded-2xl border border-black/10 bg-[#F5F7FA] overflow-hidden shadow-2xl shadow-black/10">
            <div className="flex items-center gap-2 px-4 py-3 bg-white border-b border-black/5">
              <span className="w-2.5 h-2.5 rounded-full bg-[#EF4444]" />
              <span className="w-2.5 h-2.5 rounded-full bg-[#F59E0B]" />
              <span className="w-2.5 h-2.5 rounded-full bg-[#0F9D6E]" />
              <div className="ml-3 flex-1 bg-[#F5F7FA] rounded-md px-3 py-1 text-[11px] text-[#6B7280] truncate">
                https://tumarca.mx
              </div>
            </div>
            <div className="p-5 space-y-4 bg-white">
              <div className="flex items-center justify-between">
                <div className="h-3.5 w-24 rounded bg-[#1A2332]" />
                <div className="flex gap-2">
                  {[0, 1, 2].map((i) => <div key={i} className="h-2.5 w-12 rounded bg-black/10" />)}
                  <div className="h-6 w-20 rounded-md bg-gradient-to-r from-[#0F9D6E] to-[#34D399]" />
                </div>
              </div>
              <div className="grid grid-cols-[1.2fr_1fr] gap-4 items-center pt-2">
                <div className="space-y-2.5">
                  <div className="h-6 w-full rounded bg-[#1A2332]" />
                  <div className="h-6 w-3/4 rounded bg-[#0F9D6E]" />
                  <div className="h-2 w-full rounded bg-black/10" />
                  <div className="h-2 w-5/6 rounded bg-black/10" />
                  <div className="h-8 w-32 rounded-lg bg-gradient-to-r from-[#0F9D6E] to-[#34D399] mt-3" />
                </div>
                <div className="grid grid-cols-2 gap-2.5">
                  {[0, 1, 2, 3].map((i) => (
                    <div key={i} className="rounded-xl border border-black/5 bg-[#F5F7FA] p-3">
                      <div className="w-6 h-6 rounded-lg bg-emerald-100 mb-2" />
                      <div className="h-1.5 w-full rounded bg-black/10" />
                      <div className="h-1.5 w-2/3 rounded bg-black/10 mt-1.5" />
                    </div>
                  ))}
                </div>
              </div>
              <div className="grid grid-cols-4 gap-3 pt-3 border-t border-black/5">
                {metas.map((m) => (
                  <div key={m.label}>
                    <div className="text-[13px] font-extrabold text-[#0F9D6E]">{m.valor}</div>
                    <div className="text-[9px] text-[#6B7280] leading-tight mt-0.5">{m.label}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── Configurador ─────────────────────────────────────────── */}
      <section ref={configRef} className="px-4 py-20 bg-[#F5F7FA] border-b border-black/5">
        <div className="max-w-7xl mx-auto">
          <div className="max-w-2xl">
            <div className="text-xs font-bold tracking-[0.14em] text-[#0F9D6E]">CONFIGURADOR</div>
            <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight mt-2 leading-tight">
              Arma tu proyecto y mira el precio moverse
            </h2>
            <p className="text-[#6B7280] mt-3 leading-relaxed">
              Rangos reales de proyectos entregados en México. La propuesta final se ajusta después de
              entender tu operación, pero el orden de magnitud es este.
            </p>
          </div>

          <div className="grid lg:grid-cols-[1fr_380px] gap-6 mt-10 items-start">
            <div className="space-y-4">
              {/* Tipo */}
              <div className="bg-white rounded-2xl border border-black/5 p-6">
                <div className="text-sm font-bold text-[#1A2332]">1 · ¿Qué vas a construir?</div>
                <div className="grid sm:grid-cols-2 gap-3 mt-4">
                  {tipos.map((op) => {
                    const on = op.key === tipo;
                    return (
                      <button key={op.key} onClick={() => setTipo(op.key)}
                        className={`flex items-start gap-3 p-4 rounded-xl border text-left transition-all ${on
                          ? "border-[#0F9D6E] bg-emerald-50 shadow-sm"
                          : "border-black/10 bg-white hover:border-[#0F9D6E]/40"}`}>
                        <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${on ? "bg-[#0F9D6E]" : "bg-[#F5F7FA]"}`}>
                          <op.icon size={17} className={on ? "text-white" : "text-[#0F9D6E]"} />
                        </div>
                        <div className="min-w-0">
                          <div className="text-sm font-bold text-[#1A2332]">{op.label}</div>
                          <div className="text-xs text-[#6B7280] mt-0.5">{op.desc}</div>
                          <div className="text-xs font-semibold text-[#0F9D6E] mt-1.5">desde {money(op.base)}</div>
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Páginas */}
              <div className="bg-white rounded-2xl border border-black/5 p-6">
                <div className="flex items-baseline justify-between">
                  <div className="text-sm font-bold text-[#1A2332]">2 · ¿Cuántas plantillas de página?</div>
                  <div className="text-sm font-extrabold text-[#0F9D6E]">{paginas}</div>
                </div>
                <input type="range" min={1} max={40} value={paginas}
                  onChange={(e) => setPaginas(Number(e.target.value))}
                  className="w-full mt-4 accent-[#0F9D6E]" />
                <div className="flex justify-between text-[11px] text-[#6B7280] mt-1">
                  <span>1 plantilla</span><span>5 incluidas</span><span>40+</span>
                </div>
              </div>

              {/* Funciones */}
              <div className="bg-white rounded-2xl border border-black/5 p-6">
                <div className="text-sm font-bold text-[#1A2332]">3 · ¿Qué debe hacer?</div>
                <div className="flex flex-wrap gap-2 mt-4">
                  {features.map((f) => {
                    const on = picked.includes(f.key);
                    return (
                      <button key={f.key} onClick={() => toggle(f.key)}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-semibold border transition-all ${on
                          ? "border-[#0F9D6E] bg-[#0F9D6E] text-white"
                          : "border-black/10 bg-white text-[#1A2332] hover:border-[#0F9D6E]/40"}`}>
                        {on && <CheckCircle size={14} />}{f.label}
                      </button>
                    );
                  })}
                </div>
                <div className="flex items-center justify-between gap-4 mt-6 pt-5 border-t border-black/5">
                  <div>
                    <div className="text-sm font-bold text-[#1A2332]">Entrega acelerada</div>
                    <div className="text-xs text-[#6B7280] mt-0.5">Equipo dedicado, ~30% menos tiempo</div>
                  </div>
                  <button onClick={() => setRush(!rush)} aria-pressed={rush}
                    className={`w-14 h-8 rounded-full p-1 transition-colors shrink-0 ${rush ? "bg-[#0F9D6E]" : "bg-black/15"}`}>
                    <div className={`w-6 h-6 rounded-full bg-white transition-transform ${rush ? "translate-x-6" : ""}`} />
                  </button>
                </div>
              </div>
            </div>

            {/* Resumen */}
            <div className="lg:sticky lg:top-24">
              <div className="bg-[#1A2332] rounded-2xl p-7 text-white shadow-2xl shadow-black/20">
                <div className="text-[11px] font-bold tracking-[0.14em] text-[#34D399]">TU PROYECTO</div>
                <div className="mt-4 text-sm text-white/60">Rango de inversión</div>
                <div className="text-3xl font-extrabold mt-1 leading-none">
                  {money(total * 0.9)} – {money(total * 1.15)}
                </div>
                <div className="text-xs text-white/45 mt-1.5">MXN + IVA · pago en 3 hitos</div>

                <div className="mt-6 grid grid-cols-2 gap-4 pt-5 border-t border-white/10">
                  <div>
                    <div className="text-2xl font-extrabold">{semanas}</div>
                    <div className="text-xs text-white/55 mt-1">semanas de entrega</div>
                  </div>
                  <div>
                    <div className="text-2xl font-extrabold">{2 + Math.min(4, extras.length)}</div>
                    <div className="text-xs text-white/55 mt-1">personas asignadas</div>
                  </div>
                </div>

                <div className="mt-6 pt-5 border-t border-white/10 space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-white/60">{t.label}</span>
                    <span className="font-semibold">{money(t.base)}</span>
                  </div>
                  {paginasExtra > 0 && (
                    <div className="flex justify-between text-sm">
                      <span className="text-white/60">{paginas - 5} plantillas extra</span>
                      <span className="font-semibold">{money(paginasExtra)}</span>
                    </div>
                  )}
                  {extras.map((f) => (
                    <div key={f.key} className="flex justify-between text-sm">
                      <span className="text-white/60">{f.label}</span>
                      <span className="font-semibold">{money(f.costo)}</span>
                    </div>
                  ))}
                  {rush && (
                    <div className="flex justify-between text-sm">
                      <span className="text-[#34D399]">Entrega acelerada</span>
                      <span className="font-semibold text-[#34D399]">+28%</span>
                    </div>
                  )}
                </div>

                <button onClick={() => scrollTo(formRef.current)}
                  className="mt-7 w-full flex items-center justify-center gap-2 px-5 py-4 rounded-xl font-bold bg-gradient-to-r from-[#0F9D6E] to-[#34D399] hover:brightness-110 transition-all">
                  Recibir propuesta detallada<ArrowRight size={16} />
                </button>
                <p className="text-[11px] text-white/40 mt-3 leading-relaxed">
                  Te llega en PDF con alcance, cronograma y supuestos. Sin compromiso.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── Cronograma ───────────────────────────────────────────── */}
      <section className="px-4 py-20 bg-white">
        <div className="max-w-5xl mx-auto">
          <div className="text-xs font-bold tracking-[0.14em] text-[#0F9D6E]">CRONOGRAMA ESTIMADO</div>
          <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight mt-2">
            Cómo se reparten esas {semanas} semanas
          </h2>
          <div className="mt-9 space-y-4">
            {fases.map((f, i) => {
              const sem = Math.max(1, Math.round(semanas * f.peso));
              const inicio = fases.slice(0, i).reduce((s, x) => s + x.peso, 0) * 100;
              return (
                <div key={f.nombre} className="grid sm:grid-cols-[168px_1fr_92px] items-center gap-4">
                  <div className="text-sm font-bold text-[#1A2332]">{f.nombre}</div>
                  <div className="h-9 rounded-lg bg-[#F5F7FA] relative overflow-hidden">
                    <div className="absolute top-0 bottom-0 rounded-lg flex items-center px-3"
                      style={{ left: `${inicio}%`, width: `${f.peso * 100}%`, background: f.color }}>
                      <span className="text-[11px] font-bold text-white whitespace-nowrap">{sem} sem</span>
                    </div>
                  </div>
                  <div className="text-xs text-[#6B7280]">{Math.round(f.peso * 100)}% del esfuerzo</div>
                </div>
              );
            })}
          </div>
          <div className="mt-8 flex flex-wrap items-center gap-3 text-xs text-[#6B7280]">
            <span className="flex items-center gap-1.5"><CheckCircle size={13} className="text-[#0F9D6E]" />Demo navegable desde la semana 2</span>
            <span className="flex items-center gap-1.5"><CheckCircle size={13} className="text-[#0F9D6E]" />Entregas semanales en ambiente de pruebas</span>
          </div>
        </div>
      </section>

      {/* ── Stack y estándares ──────────────────────────────────── */}
      <section className="px-4 py-20 bg-[#1A2332]">
        <div className="max-w-6xl mx-auto grid lg:grid-cols-2 gap-14 items-center">
          <div>
            <div className="text-xs font-bold tracking-[0.14em] text-[#34D399]">CON QUÉ LO CONSTRUIMOS</div>
            <h2 className="text-3xl font-extrabold text-white tracking-tight mt-2 leading-tight">
              Tecnología que tu equipo puede mantener
            </h2>
            <p className="text-white/60 mt-4 leading-relaxed">
              Nada de frameworks exóticos ni código que solo nosotros entendemos. Stack estándar de mercado,
              documentado, en tu repositorio, con capacitación al cierre.
            </p>
            <div className="flex flex-wrap gap-2 mt-7">
              {stack.map((s) => (
                <span key={s} className="text-xs font-semibold text-white/80 bg-white/10 border border-white/10 px-3.5 py-2 rounded-full">{s}</span>
              ))}
            </div>
            <div className="flex flex-wrap gap-6 mt-8 text-sm">
              <span className="flex items-center gap-2 text-white/70"><Github size={16} className="text-[#34D399]" />Repositorio a tu nombre</span>
              <span className="flex items-center gap-2 text-white/70"><FileCode2 size={16} className="text-[#34D399]" />Documentación técnica</span>
            </div>
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            {metas.map((m) => (
              <div key={m.label} className="rounded-2xl bg-white/5 border border-white/10 p-6">
                <div className="text-3xl font-extrabold text-[#34D399]">{m.valor}</div>
                <div className="text-sm font-bold text-white mt-2">{m.label}</div>
                <div className="text-xs text-white/50 mt-1.5 leading-relaxed">{m.nota}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Formulario ───────────────────────────────────────────── */}
      <section ref={formRef} className="px-4 py-20 bg-white border-b border-black/5">
        <div className="max-w-2xl mx-auto">
          {sent ? (
            <div className="text-center">
              <div className="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto">
                <CheckCircle size={26} className="text-[#0F9D6E]" />
              </div>
              <h2 className="text-2xl font-extrabold text-[#1A2332] mt-4">Propuesta en camino</h2>
              <p className="text-[#6B7280] text-sm mt-2 leading-relaxed">
                Recibimos tu configuración: {t.label.toLowerCase()}, {paginas} plantillas
                {extras.length > 0 ? ` y ${extras.length} funciones adicionales` : ""}.
                Te enviamos el PDF con alcance y cronograma en menos de 24 horas hábiles.
              </p>
              <div className="flex flex-wrap items-center justify-center gap-3 mt-7">
                <button onClick={() => setPage("agendar")}
                  className="flex items-center gap-2 px-6 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] hover:brightness-105 transition-all">
                  Agendar la revisión<ArrowRight size={16} />
                </button>
                <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer"
                  className="flex items-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-[#1A2332] border border-black/10 hover:border-[#0F9D6E] hover:text-[#0F9D6E] transition-colors">
                  <MessageCircle size={16} />Dudas por WhatsApp
                </a>
              </div>
            </div>
          ) : (
            <>
              <div className="text-center">
                <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight leading-tight">
                  Recibe la propuesta de tu configuración
                </h2>
                <p className="text-[#6B7280] mt-3 leading-relaxed">
                  {t.label} · {paginas} plantillas · {extras.length} funciones · {semanas} semanas ·
                  <span className="font-bold text-[#1A2332]"> {money(total * 0.9)} – {money(total * 1.15)}</span>
                </p>
              </div>
              <form onSubmit={(e) => { e.preventDefault(); setSent(true); }} className="mt-8 grid sm:grid-cols-2 gap-3">
                <input required value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })}
                  placeholder="Nombre*" className="px-4 py-3.5 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
                <input required value={form.empresa} onChange={(e) => setForm({ ...form, empresa: e.target.value })}
                  placeholder="Empresa*" className="px-4 py-3.5 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
                <input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })}
                  placeholder="Email corporativo*" className="px-4 py-3.5 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
                <input required value={form.tel} onChange={(e) => setForm({ ...form, tel: e.target.value })}
                  placeholder="WhatsApp*" className="px-4 py-3.5 rounded-xl bg-[#F5F7FA] border border-black/5 text-sm text-[#1A2332] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0F9D6E] focus:bg-white transition-colors" />
                <button type="submit"
                  className="sm:col-span-2 flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:brightness-105 transition-all">
                  Enviarme la propuesta<ArrowRight size={17} />
                </button>
              </form>
              <div className="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[11px] text-[#6B7280]">
                <span className="flex items-center gap-1"><ShieldCheck size={12} className="text-[#0F9D6E]" />No compartimos tus datos</span>
                <span className="flex items-center gap-1"><Rocket size={12} className="text-[#0F9D6E]" />Respuesta en menos de 24 h</span>
              </div>
            </>
          )}
        </div>
      </section>

      {/* ── Objeciones ───────────────────────────────────────────── */}
      <section className="px-4 py-20 bg-[#F5F7FA]">
        <div className="max-w-3xl mx-auto">
          <h2 className="text-3xl font-extrabold text-[#1A2332] tracking-tight text-center">Lo que siempre nos preguntan</h2>
          <div className="space-y-3 mt-10">
            {objections.map((o, i) => (
              <FaqItem key={o.q} q={o.q} a={o.a} defaultOpen={i === 0} />
            ))}
          </div>
          <div className="text-center mt-10">
            <button onClick={() => scrollTo(configRef.current, 70)}
              className="inline-flex items-center gap-2 px-7 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399] shadow-lg shadow-emerald-200 hover:brightness-105 transition-all">
              Cotizar mi proyecto<ArrowRight size={17} />
            </button>
          </div>
        </div>
      </section>

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
        <button onClick={() => scrollTo(configRef.current, 70)}
          className="flex-1 flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-[#0F9D6E] to-[#34D399]">
          Cotizar · {money(total * 0.9)}+<ArrowRight size={16} />
        </button>
      </div>
    </div>
  );
}

function FaqItem({ q, a, defaultOpen }: { q: string; a: string; defaultOpen?: boolean }) {
  const [open, setOpen] = useState(!!defaultOpen);
  return (
    <div className="bg-white rounded-2xl border border-black/5 overflow-hidden">
      <button onClick={() => setOpen(!open)}
        className="w-full flex items-center justify-between gap-4 text-left px-6 py-5 hover:bg-emerald-50/60 transition-colors">
        <span className="font-semibold text-[#1A2332] text-[15px]">{q}</span>
        <ChevronDown size={18} className={`text-[#0F9D6E] shrink-0 transition-transform duration-200 ${open ? "rotate-180" : ""}`} />
      </button>
      {open && <p className="px-6 pb-6 text-sm text-[#6B7280] leading-relaxed">{a}</p>}
    </div>
  );
}
