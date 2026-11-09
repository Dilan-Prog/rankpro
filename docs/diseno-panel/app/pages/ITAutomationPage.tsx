import { useState, useRef } from "react";
import {
  Workflow, ArrowRight, Zap, Clock, UserX, Repeat, CheckCircle, ChevronDown,
  FormInput, Database, BellRing, BarChart3, Rocket, ShieldCheck, MessageCircle
} from "lucide-react";
import type { Page } from "../types";

const dolores = [
  { icon: UserX, title: "Leads que se enfrían", desc: "Un formulario llega al correo de alguien que está en junta. Cuando alguien responde, el prospecto ya cotizó con otro." },
  { icon: Clock, title: "Respuesta lenta", desc: "El primero en contestar gana. Si tu equipo tarda horas, el 78% de esos leads ya no van a contestar." },
  { icon: Repeat, title: "Trabajo manual repetitivo", desc: "Copiar datos entre hojas, CRM y facturación. Horas de gente capaz haciendo lo que un flujo hace solo." },
];

const soluciones = [
  { icon: FormInput, title: "Captación automática de leads", desc: "Formularios, WhatsApp, Meta y Google Ads entran a un solo flujo, sin capturas manuales." },
  { icon: Database, title: "Integración con tu CRM", desc: "Cada lead se crea, se asigna y se etiqueta solo, con su origen y campaña intactos." },
  { icon: BellRing, title: "Notificaciones en tiempo real", desc: "Aviso al vendedor correcto por WhatsApp o Slack en segundos, con contexto y siguiente paso." },
  { icon: BarChart3, title: "Reportes automáticos", desc: "Tableros y resúmenes que llegan solos cada lunes. Nadie vuelve a armar el reporte a mano." },
];

const pasos = [
  { n: "01", title: "Diagnóstico gratuito", desc: "Mapeamos tus procesos actuales y encontramos dónde se pierde tiempo y dinero. 45 minutos, sin costo." },
  { n: "02", title: "Diseño del flujo", desc: "Te entregamos el diagrama del flujo propuesto, los sistemas que conecta y el ahorro estimado." },
  { n: "03", title: "Implementación con n8n", desc: "Construimos, probamos y ponemos en marcha en tu propia instancia. Tú eres dueño del flujo." },
  { n: "04", title: "Monitoreo y optimización", desc: "Alertas si algo falla, ajustes continuos y nuevos flujos conforme crece la operación." },
];

const faqs = [
  { q: "¿Qué es n8n y por qué lo usan?", a: "Es una plataforma de automatización que conecta tus aplicaciones sin necesidad de desarrollar todo desde cero. La diferencia con otras: puede vivir en tu propio servidor, así que tus datos no salen de tu infraestructura y no pagas por cada ejecución." },
  { q: "¿Necesito personal técnico para operarlo?", a: "No. Entregamos los flujos funcionando, documentados y con un tablero simple para monitorearlos. Capacitamos a tu equipo en una sesión y quedamos como soporte." },
  { q: "¿Se conecta con el sistema que ya uso?", a: "Casi siempre sí: HubSpot, Salesforce, Pipedrive, Zoho, Google Workspace, Meta, WhatsApp Business, Shopify, facturación mexicana y cualquier sistema con API. Si no tiene API, lo revisamos en el diagnóstico." },
  { q: "¿Cuánto tarda la implementación?", a: "Un flujo de captación y asignación de leads suele estar operando en 2 a 3 semanas. Proyectos con varios sistemas y reglas de negocio, entre 4 y 8 semanas." },
  { q: "¿Qué pasa si un flujo falla?", a: "Cada flujo lleva reintentos, registro de errores y alertas. Si algo se rompe, lo sabemos antes que tú y queda respaldo de cada ejecución para no perder ningún lead." },
];

function FlowNode({ icon: Icon, label, sub, accent }: { icon: any; label: string; sub?: string; accent?: boolean }) {
  return (
    <div className={`rounded-2xl border px-5 py-4 min-w-[150px] ${accent
      ? "border-[#34D399]/40 bg-[#34D399]/10"
      : "border-white/10 bg-white/[0.04]"}`}>
      <div className={`w-9 h-9 rounded-xl flex items-center justify-center mb-3 ${accent ? "bg-[#34D399]" : "bg-white/10"}`}>
        <Icon size={17} className={accent ? "text-[#08131F]" : "text-[#34D399]"} />
      </div>
      <div className="text-sm font-semibold text-white leading-snug">{label}</div>
      {sub && <div className="text-[11px] text-white/45 mt-1">{sub}</div>}
    </div>
  );
}

function Connector({ vertical = false }: { vertical?: boolean }) {
  return vertical ? (
    <div className="flex sm:hidden justify-center py-1">
      <div className="w-px h-6 bg-gradient-to-b from-[#34D399]/60 to-transparent" />
    </div>
  ) : (
    <div className="hidden sm:flex items-center flex-1 min-w-[24px]">
      <div className="h-px w-full bg-gradient-to-r from-[#34D399]/50 to-[#34D399]/20" />
      <ArrowRight size={13} className="text-[#34D399] shrink-0 -ml-1" />
    </div>
  );
}

export default function ITAutomationPage({ setPage }: { setPage: (p: Page) => void }) {
  void setPage;
  const formRef = useRef<HTMLDivElement | null>(null);
  const [open, setOpen] = useState<number | null>(0);
  const [sent, setSent] = useState(false);
  const [form, setForm] = useState({ nombre: "", email: "", empresa: "", mensaje: "" });

  const toForm = () => {
    const el = formRef.current;
    if (!el) return;
    window.scrollTo(0, Math.max(0, el.getBoundingClientRect().top + window.scrollY - 80));
    const input = el.querySelector("input") as HTMLInputElement | null;
    if (input) input.focus({ preventScroll: true });
  };

  return (
    <div className="min-h-screen bg-[#08131F]" style={{ fontFamily: '"DM Sans", system-ui, sans-serif' }}>
      {/* ── Hero ─────────────────────────────────────────────────── */}
      <section className="relative overflow-hidden px-5 pt-20 pb-24">
        <div className="absolute inset-0 opacity-[0.06]"
          style={{ backgroundImage: "linear-gradient(#34D399 1px, transparent 1px), linear-gradient(90deg, #34D399 1px, transparent 1px)", backgroundSize: "56px 56px" }} />
        <div className="absolute -top-40 -right-32 w-[520px] h-[520px] rounded-full"
          style={{ background: "radial-gradient(circle, rgba(52,211,153,0.16), transparent 65%)" }} />
        <div className="max-w-6xl mx-auto relative grid lg:grid-cols-[1.05fr_0.95fr] gap-16 items-center">
          <div>
            <div className="inline-flex items-center gap-2 text-[11px] font-bold tracking-[0.16em] text-[#34D399] border border-[#34D399]/25 bg-[#34D399]/[0.07] rounded-full px-3.5 py-1.5">
              <Zap size={12} />ÁREA DE IT · AUTOMATIZACIÓN CON n8n
            </div>
            <h1 className="text-[40px] sm:text-[56px] font-extrabold text-white leading-[1.05] tracking-tight mt-7">
              Automatiza tus procesos,<br />
              <span className="text-[#34D399]">recupera tu tiempo.</span>
            </h1>
            <p className="text-white/60 text-lg mt-6 leading-relaxed max-w-xl">
              Conectamos marketing, ventas y operaciones con automatización inteligente: los leads
              llegan solos al CRM, tu equipo se entera al instante y los reportes se arman sin nadie.
            </p>
            <div className="flex flex-wrap items-center gap-3 mt-9">
              <button onClick={toForm}
                className="flex items-center gap-2 px-7 py-4 rounded-xl font-bold text-[#08131F] bg-[#34D399] hover:bg-[#6EE7B7] transition-colors shadow-[0_12px_40px_-12px_rgba(52,211,153,0.8)]">
                Agenda tu diagnóstico gratis<ArrowRight size={17} />
              </button>
              <a href="#solucion"
                className="flex items-center gap-2 px-6 py-4 rounded-xl font-semibold text-white/80 border border-white/15 hover:border-white/40 hover:text-white transition-colors">
                Ver qué automatizamos
              </a>
            </div>
            <div className="flex flex-wrap gap-x-7 gap-y-2 mt-8 text-xs text-white/40">
              <span className="flex items-center gap-1.5"><CheckCircle size={13} className="text-[#34D399]" />45 minutos, sin costo</span>
              <span className="flex items-center gap-1.5"><CheckCircle size={13} className="text-[#34D399]" />En tu propia instancia</span>
              <span className="flex items-center gap-1.5"><CheckCircle size={13} className="text-[#34D399]" />Sin contratos anuales</span>
            </div>
          </div>

          {/* Diagrama de nodos */}
          <div className="rounded-3xl border border-white/10 bg-white/[0.03] p-7">
            <div className="flex items-center justify-between mb-6">
              <div className="text-xs font-bold tracking-widest text-white/35">FLUJO ACTIVO</div>
              <div className="flex items-center gap-2 text-[11px] text-[#34D399]">
                <span className="w-1.5 h-1.5 rounded-full bg-[#34D399]" />ejecutándose
              </div>
            </div>
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <FlowNode icon={FormInput} label="Nuevo lead" sub="Web · Ads · WhatsApp" accent />
                <Connector />
                <FlowNode icon={Workflow} label="n8n" sub="valida y enriquece" />
              </div>
              <div className="grid sm:grid-cols-3 gap-3">
                <FlowNode icon={Database} label="CRM" sub="asignado" />
                <FlowNode icon={BellRing} label="Aviso" sub="&lt; 60 seg" />
                <FlowNode icon={BarChart3} label="Tablero" sub="tiempo real" />
              </div>
            </div>
            <div className="mt-6 pt-5 border-t border-white/10 grid grid-cols-3 gap-4">
              {[["428", "ejecuciones/mes"], ["0", "capturas manuales"], ["52 s", "lead a contacto"]].map(([v, l]) => (
                <div key={l}>
                  <div className="text-xl font-extrabold text-white">{v}</div>
                  <div className="text-[10px] text-white/40 mt-0.5 leading-tight">{l}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ── Dolor ────────────────────────────────────────────────── */}
      <section id="problema" className="px-5 py-24 border-t border-white/[0.07]">
        <div className="max-w-6xl mx-auto">
          <div className="max-w-2xl">
            <div className="text-xs font-bold tracking-[0.16em] text-[#34D399]">EL PROBLEMA</div>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mt-3 leading-tight">
              No es falta de leads. Es lo que pasa después.
            </h2>
          </div>
          <div className="grid md:grid-cols-3 gap-5 mt-12">
            {dolores.map((d) => (
              <div key={d.title} className="rounded-2xl border border-white/10 bg-white/[0.03] p-7">
                <div className="w-11 h-11 rounded-xl bg-white/[0.06] flex items-center justify-center">
                  <d.icon size={20} className="text-[#34D399]" />
                </div>
                <h3 className="text-lg font-bold text-white mt-5">{d.title}</h3>
                <p className="text-sm text-white/55 mt-2.5 leading-relaxed">{d.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Solución ─────────────────────────────────────────────── */}
      <section id="solucion" className="px-5 py-24 bg-white/[0.02] border-y border-white/[0.07]">
        <div className="max-w-6xl mx-auto">
          <div className="max-w-2xl">
            <div className="text-xs font-bold tracking-[0.16em] text-[#34D399]">QUÉ HACEMOS</div>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mt-3 leading-tight">
              Un flujo que trabaja mientras tu equipo vende
            </h2>
            <p className="text-white/55 mt-4 leading-relaxed">
              Cuatro piezas que resuelven el 80% del trabajo manual de una agencia o un área comercial.
            </p>
          </div>

          <div className="grid sm:grid-cols-2 gap-5 mt-12">
            {soluciones.map((s) => (
              <div key={s.title} className="flex items-start gap-4 rounded-2xl border border-white/10 bg-[#08131F] p-6">
                <div className="w-10 h-10 rounded-xl bg-[#34D399]/12 flex items-center justify-center shrink-0">
                  <s.icon size={18} className="text-[#34D399]" />
                </div>
                <div>
                  <h3 className="font-bold text-white">{s.title}</h3>
                  <p className="text-sm text-white/55 mt-1.5 leading-relaxed">{s.desc}</p>
                </div>
              </div>
            ))}
          </div>

          {/* Diagrama del flujo */}
          <div className="mt-14 rounded-3xl border border-white/10 bg-[#08131F] p-7 sm:p-10">
            <div className="text-xs font-bold tracking-[0.16em] text-white/35">EL FLUJO, DE PRINCIPIO A FIN</div>
            <div className="mt-8 flex flex-col sm:flex-row sm:items-stretch gap-2 sm:gap-3">
              <FlowNode icon={FormInput} label="Formulario" sub="o anuncio, o WhatsApp" />
              <Connector /><Connector vertical />
              <FlowNode icon={Workflow} label="Automatización" sub="reglas y validación" accent />
              <Connector /><Connector vertical />
              <FlowNode icon={Database} label="CRM" sub="lead asignado" />
              <Connector /><Connector vertical />
              <FlowNode icon={BellRing} label="Notificación" sub="al vendedor correcto" />
            </div>
            <p className="text-sm text-white/45 mt-8 leading-relaxed max-w-2xl">
              Cada paso queda registrado: si algo falla, se reintenta y se avisa. Ningún lead se queda
              en un correo sin abrir.
            </p>
          </div>
        </div>
      </section>

      {/* ── Prueba social ───────────────────────────────────────── */}
      <section className="px-5 py-24">
        <div className="max-w-6xl mx-auto grid lg:grid-cols-[0.9fr_1.1fr] gap-14 items-center">
          <div>
            <div className="text-[64px] sm:text-[80px] font-extrabold text-[#34D399] leading-none tracking-tight">-70%</div>
            <div className="text-xl font-bold text-white mt-2">en tiempo de respuesta a leads</div>
            <p className="text-white/55 text-sm mt-4 leading-relaxed max-w-md">
              Promedio de las operaciones que automatizamos en su primer trimestre, medido desde que
              entra el lead hasta el primer contacto humano.
            </p>
          </div>
          <div className="grid sm:grid-cols-3 gap-4">
            {[
              { quote: "Pasamos de contestar en 4 horas a 3 minutos. Se nota en el cierre.", who: "Dirección Comercial", where: "Grupo inmobiliario" },
              { quote: "Dejamos de capturar leads a mano. Nadie extraña ese Excel.", who: "Gerencia de Marketing", where: "Retail nacional" },
              { quote: "El reporte de los lunes ya llega hecho. Recuperamos dos días al mes.", who: "Operaciones", where: "Agencia B2B" },
            ].map((t) => (
              <div key={t.who} className="rounded-2xl border border-white/10 bg-white/[0.03] p-6 flex flex-col">
                <p className="text-sm text-white/80 leading-relaxed flex-1">“{t.quote}”</p>
                <div className="mt-5 pt-4 border-t border-white/10">
                  <div className="text-xs font-bold text-white">{t.who}</div>
                  <div className="text-[11px] text-white/40 mt-0.5">{t.where}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Proceso ─────────────────────────────────────────────── */}
      <section id="proceso" className="px-5 py-24 bg-white/[0.02] border-y border-white/[0.07]">
        <div className="max-w-6xl mx-auto">
          <div className="max-w-2xl">
            <div className="text-xs font-bold tracking-[0.16em] text-[#34D399]">CÓMO FUNCIONA</div>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mt-3 leading-tight">
              Cuatro pasos, sin sorpresas
            </h2>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-12">
            {pasos.map((p) => (
              <div key={p.n} className="rounded-2xl border border-white/10 bg-[#08131F] p-6">
                <div className="text-xs font-extrabold tracking-widest text-[#34D399]">{p.n}</div>
                <h3 className="font-bold text-white mt-3">{p.title}</h3>
                <p className="text-sm text-white/55 mt-2 leading-relaxed">{p.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA intermedio ──────────────────────────────────────── */}
      <section className="px-5 py-16">
        <div className="max-w-6xl mx-auto rounded-3xl bg-[#34D399] px-8 sm:px-12 py-12 flex flex-wrap items-center justify-between gap-8">
          <div>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-[#08131F] tracking-tight leading-tight">
              ¿Listo para automatizar? Hablemos.
            </h2>
            <p className="text-[#08131F]/70 mt-3 max-w-lg leading-relaxed">
              En 45 minutos te decimos qué se puede automatizar hoy, qué ahorro representa y qué no
              vale la pena tocar todavía.
            </p>
          </div>
          <button onClick={toForm}
            className="flex items-center gap-2 px-8 py-4 rounded-xl font-bold text-white bg-[#08131F] hover:bg-[#0F2438] transition-colors shrink-0">
            Agendar diagnóstico<ArrowRight size={17} />
          </button>
        </div>
      </section>

      {/* ── FAQ ─────────────────────────────────────────────────── */}
      <section id="faq" className="px-5 py-24">
        <div className="max-w-3xl mx-auto">
          <div className="text-xs font-bold tracking-[0.16em] text-[#34D399]">PREGUNTAS FRECUENTES</div>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mt-3">
            Lo que suelen preguntarnos
          </h2>
          <div className="space-y-3 mt-10">
            {faqs.map((f, i) => (
              <div key={f.q} className="rounded-2xl border border-white/10 bg-white/[0.03] overflow-hidden">
                <button onClick={() => setOpen(open === i ? null : i)}
                  className="w-full flex items-center justify-between gap-4 text-left px-6 py-5 hover:bg-white/[0.04] transition-colors">
                  <span className="font-semibold text-white text-[15px]">{f.q}</span>
                  <ChevronDown size={18} className={`text-[#34D399] shrink-0 transition-transform duration-200 ${open === i ? "rotate-180" : ""}`} />
                </button>
                {open === i && <p className="px-6 pb-6 text-sm text-white/55 leading-relaxed">{f.a}</p>}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Formulario final ────────────────────────────────────── */}
      <section ref={formRef} className="px-5 pb-28">
        <div className="max-w-5xl mx-auto rounded-3xl border border-white/10 bg-white/[0.03] p-8 sm:p-12">
          {sent ? (
            <div className="text-center max-w-lg mx-auto py-6">
              <div className="w-14 h-14 rounded-2xl bg-[#34D399] flex items-center justify-center mx-auto">
                <CheckCircle size={26} className="text-[#08131F]" />
              </div>
              <h2 className="text-2xl font-extrabold text-white mt-5">Solicitud recibida</h2>
              <p className="text-white/55 mt-3 leading-relaxed">
                Te contactamos hoy mismo para agendar el diagnóstico. Si prefieres adelantarlo, escríbenos
                por WhatsApp y lo cerramos en el chat.
              </p>
              <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer"
                className="mt-6 inline-flex items-center gap-2 px-6 py-3.5 rounded-xl font-bold text-[#08131F] bg-[#34D399] hover:bg-[#6EE7B7] transition-colors">
                <MessageCircle size={17} />Abrir WhatsApp
              </a>
            </div>
          ) : (
            <div className="grid lg:grid-cols-[0.9fr_1.1fr] gap-12">
              <div>
                <h2 className="text-3xl font-extrabold text-white tracking-tight leading-tight">
                  Cuéntanos qué te está quitando tiempo
                </h2>
                <p className="text-white/55 mt-4 leading-relaxed">
                  Un especialista de IT revisa tu caso y te propone el primer flujo a automatizar,
                  con el ahorro estimado en horas.
                </p>
                <div className="mt-8 space-y-3">
                  {[
                    { icon: Rocket, t: "Respuesta en menos de 24 h hábiles" },
                    { icon: ShieldCheck, t: "Tus datos no salen de tu infraestructura" },
                    { icon: Zap, t: "Primer flujo operando en 2-3 semanas" },
                  ].map((x) => (
                    <div key={x.t} className="flex items-center gap-3 text-sm text-white/70">
                      <x.icon size={16} className="text-[#34D399] shrink-0" />{x.t}
                    </div>
                  ))}
                </div>
              </div>

              <form onSubmit={(e) => { e.preventDefault(); setSent(true); }} className="grid gap-3">
                <div className="grid sm:grid-cols-2 gap-3">
                  <input required value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })}
                    placeholder="Nombre*"
                    className="px-4 py-3.5 rounded-xl bg-[#08131F] border border-white/10 text-sm text-white placeholder:text-white/35 focus:outline-none focus:border-[#34D399] transition-colors" />
                  <input required value={form.empresa} onChange={(e) => setForm({ ...form, empresa: e.target.value })}
                    placeholder="Empresa*"
                    className="px-4 py-3.5 rounded-xl bg-[#08131F] border border-white/10 text-sm text-white placeholder:text-white/35 focus:outline-none focus:border-[#34D399] transition-colors" />
                </div>
                <input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })}
                  placeholder="Email corporativo*"
                  className="px-4 py-3.5 rounded-xl bg-[#08131F] border border-white/10 text-sm text-white placeholder:text-white/35 focus:outline-none focus:border-[#34D399] transition-colors" />
                <textarea value={form.mensaje} onChange={(e) => setForm({ ...form, mensaje: e.target.value })}
                  placeholder="¿Qué proceso te gustaría automatizar?" rows={4}
                  className="px-4 py-3.5 rounded-xl bg-[#08131F] border border-white/10 text-sm text-white placeholder:text-white/35 focus:outline-none focus:border-[#34D399] transition-colors resize-none" />
                <button type="submit"
                  className="flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-bold text-[#08131F] bg-[#34D399] hover:bg-[#6EE7B7] transition-colors">
                  Solicitar automatización<ArrowRight size={17} />
                </button>
                <p className="text-[11px] text-white/35 text-center leading-relaxed">
                  Sin costo y sin compromiso. Usamos tus datos solo para contactarte sobre este servicio.
                </p>
              </form>
            </div>
          )}
        </div>
      </section>

    </div>
  );
}
