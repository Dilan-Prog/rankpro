import { useMemo, useState } from "react";
import { Calendar, CheckCircle, Clock, Video, Globe, ChevronLeft, ChevronRight, ArrowLeft, Shield, Headphones, Download, CalendarPlus, RotateCcw } from "lucide-react";
import { GreenBadge } from "../components/shared";
import { TOPICS, MONTHS, DOW, loadAvail, loadBookings, saveBookings, slotsFor, ymd, parseYmd, addDays, fmtTime, fmtDateLong, pad, toMin, type Booking } from "./agendaStore";

const inputCls = "w-full px-4 py-3 rounded-xl border border-black/10 text-sm bg-white focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20 transition-all";

export default function AgendarPage() {
  const avail = useMemo(loadAvail, []);
  const [bookings, setBookings] = useState<Booking[]>(loadBookings);
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const horizon = addDays(today, avail.horizonDays);

  const firstOpen = useMemo(() => {
    for (let i = 0; i < avail.horizonDays; i++) { const d = ymd(addDays(today, i)); if (slotsFor(d, avail, bookings).length) return d; }
    return "";
  }, []);

  const [month, setMonth] = useState(() => { const d = firstOpen ? parseYmd(firstOpen) : today; return new Date(d.getFullYear(), d.getMonth(), 1); });
  const [date, setDate] = useState(firstOpen);
  const [time, setTime] = useState("");
  const [topic, setTopic] = useState("");
  const [step, setStep] = useState<"pick" | "form" | "done">("pick");
  const [form, setForm] = useState({ name: "", email: "", phone: "", company: "", note: "" });
  const [touched, setTouched] = useState(false);
  const [done, setDone] = useState<Booking | null>(null);

  const slots = date ? slotsFor(date, avail, bookings) : [];
  const cells = useMemo(() => {
    const start = (month.getDay() + 6) % 7; // semana inicia en lunes
    const days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
    const arr: (null | { d: string; n: number; open: boolean; past: boolean })[] = Array(start).fill(null);
    for (let n = 1; n <= days; n++) {
      const dt = new Date(month.getFullYear(), month.getMonth(), n);
      const d = ymd(dt);
      const past = dt < today || dt > horizon;
      arr.push({ d, n, past, open: !past && slotsFor(d, avail, bookings).length > 0 });
    }
    return arr;
  }, [month, bookings]);

  const canPrev = month > new Date(today.getFullYear(), today.getMonth(), 1);
  const canNext = new Date(month.getFullYear(), month.getMonth() + 1, 1) <= horizon;
  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email);
  const valid = form.name.trim().length > 1 && emailOk && form.phone.replace(/\D/g, "").length >= 8;

  const confirm = () => {
    setTouched(true);
    if (!valid) return;
    const b: Booking = { id: "b" + Date.now(), date, time, ...form, topic: topic || "Sin especificar", status: "confirmada", createdAt: new Date().toISOString(), source: "Sitio web" };
    const next = [...bookings, b];
    saveBookings(next); setBookings(next); setDone(b); setStep("done");
  };

  const reset = () => { setStep("pick"); setTime(""); setDone(null); setTouched(false); setForm({ name: "", email: "", phone: "", company: "", note: "" }); };

  const gcalUrl = (b: Booking) => {
    const s = parseYmd(b.date); const m = toMin(b.time); s.setHours(Math.floor(m / 60), m % 60);
    const e = new Date(s.getTime() + avail.duration * 60e3);
    const f = (x: Date) => `${x.getFullYear()}${pad(x.getMonth() + 1)}${pad(x.getDate())}T${pad(x.getHours())}${pad(x.getMinutes())}00`;
    return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent("Consultoría RankPro")}&dates=${f(s)}/${f(e)}&details=${encodeURIComponent("Tema: " + b.topic)}`;
  };
  const downloadIcs = (b: Booking) => {
    const s = parseYmd(b.date); const m = toMin(b.time); s.setHours(Math.floor(m / 60), m % 60);
    const e = new Date(s.getTime() + avail.duration * 60e3);
    const f = (x: Date) => `${x.getFullYear()}${pad(x.getMonth() + 1)}${pad(x.getDate())}T${pad(x.getHours())}${pad(x.getMinutes())}00`;
    const ics = ["BEGIN:VCALENDAR", "VERSION:2.0", "BEGIN:VEVENT", `UID:${b.id}@rankpro`, `DTSTART:${f(s)}`, `DTEND:${f(e)}`, "SUMMARY:Consultoría RankPro", `DESCRIPTION:Tema: ${b.topic}`, "END:VEVENT", "END:VCALENDAR"].join("\r\n");
    const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([ics], { type: "text/calendar" })); a.download = "consultoria-rankpro.ics"; a.click();
  };

  const Summary = () => (
    <div className="space-y-3 text-sm">
      <div className="flex items-center gap-2.5 text-[#1A2332]"><Clock size={16} className="text-[#0F9D6E]" />{avail.duration} minutos</div>
      <div className="flex items-center gap-2.5 text-[#1A2332]"><Video size={16} className="text-[#0F9D6E]" />Videollamada (enlace por correo)</div>
      <div className="flex items-center gap-2.5 text-[#1A2332]"><Globe size={16} className="text-[#0F9D6E]" />Hora de Ciudad de México</div>
      {date && time && (
        <div className="flex items-start gap-2.5 font-semibold text-[#0F9D6E]"><Calendar size={16} className="mt-0.5" /><span>{fmtDateLong(date)}<br />{fmtTime(time)}</span></div>
      )}
    </div>
  );

  if (step === "done" && done) {
    return (
      <section className="min-h-[70vh] flex items-center justify-center px-4 py-16 bg-[#F5F7FA]">
        <div className="max-w-lg w-full bg-white rounded-3xl border border-black/5 shadow-xl shadow-black/5 p-8 md:p-10 text-center">
          <div className="w-16 h-16 rounded-full bg-[#0F9D6E] flex items-center justify-center mx-auto mb-5"><CheckCircle size={32} className="text-white" /></div>
          <h2 className="text-3xl font-extrabold text-[#1A2332] mb-2">¡Listo, {done.name.split(" ")[0]}!</h2>
          <p className="text-[#6B7280] mb-6">Tu consultoría quedó agendada. Te enviamos la confirmación a <span className="font-semibold text-[#1A2332]">{done.email}</span>.</p>
          <div className="rounded-2xl bg-emerald-50 border border-emerald-200 p-5 text-left mb-6">
            <div className="text-xs font-bold tracking-wider text-[#0F9D6E] mb-1">TU CITA</div>
            <div className="text-lg font-extrabold text-[#1A2332]">{fmtDateLong(done.date)}</div>
            <div className="text-[#1A2332]">{fmtTime(done.time)} · {avail.duration} min · Videollamada</div>
            <div className="text-sm text-[#6B7280] mt-1">Tema: {done.topic}</div>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <a href={gcalUrl(done)} target="_blank" rel="noopener noreferrer" className="flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-black/10 font-semibold text-sm text-[#1A2332] hover:border-[#0F9D6E] transition-colors"><CalendarPlus size={16} />Google Calendar</a>
            <button onClick={() => downloadIcs(done)} className="flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-black/10 font-semibold text-sm text-[#1A2332] hover:border-[#0F9D6E] transition-colors"><Download size={16} />Outlook / Apple (.ics)</button>
          </div>
          <button onClick={reset} className="inline-flex items-center gap-2 text-sm font-semibold text-[#6B7280] hover:text-[#0F9D6E]"><RotateCcw size={14} />Agendar otra cita</button>
        </div>
      </section>
    );
  }

  return (
    <>
      <section className="bg-[#F5F7FA] pt-14 pb-10 px-4">
        <div className="max-w-3xl mx-auto text-center">
          <GreenBadge><Calendar size={12} />CONSULTORÍA GRATUITA</GreenBadge>
          <h1 className="text-4xl md:text-5xl font-extrabold text-[#1A2332] mb-3 tracking-tight">Agenda en <span className="text-[#0F9D6E]">menos de 1 minuto</span></h1>
          <p className="text-[#6B7280]">Elige día y hora, deja tus datos y listo. Sin llamadas de venta ni compromiso.</p>
        </div>
      </section>

      <section className="bg-[#F5F7FA] pb-16 px-4">
        <div className="max-w-5xl mx-auto bg-white rounded-3xl border border-black/5 shadow-xl shadow-black/5 overflow-hidden grid grid-cols-1 lg:grid-cols-[300px_minmax(0,1fr)]">
          {/* Info */}
          <aside className="p-7 border-b lg:border-b-0 lg:border-r border-black/5">
            <div className="text-xs font-bold tracking-wider text-[#6B7280] mb-1">RANKPRO</div>
            <h2 className="text-xl font-extrabold text-[#1A2332] mb-5">Consultoría estratégica</h2>
            <Summary />
            <div className="mt-7">
              <div className="text-sm font-semibold text-[#1A2332] mb-2.5">¿Sobre qué quieres hablar? <span className="font-normal text-[#6B7280]">(opcional)</span></div>
              <div className="flex flex-wrap gap-2">
                {TOPICS.map((t) => (
                  <button key={t} onClick={() => setTopic(topic === t ? "" : t)}
                    className={`px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors ${topic === t ? "bg-[#0F9D6E] border-[#0F9D6E] text-white" : "border-black/10 text-[#1A2332] hover:border-[#0F9D6E]"}`}>{t}</button>
                ))}
              </div>
            </div>
          </aside>

          {step === "pick" ? (
            <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_220px]">
              {/* Calendario */}
              <div className="p-7">
                <div className="flex items-center justify-between mb-5">
                  <h3 className="font-extrabold text-[#1A2332] text-lg">{MONTHS[month.getMonth()][0].toUpperCase() + MONTHS[month.getMonth()].slice(1)} {month.getFullYear()}</h3>
                  <div className="flex gap-1.5">
                    <button disabled={!canPrev} onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1))} className="w-9 h-9 rounded-full flex items-center justify-center hover:bg-emerald-50 text-[#0F9D6E] disabled:opacity-25 disabled:hover:bg-transparent" aria-label="Mes anterior"><ChevronLeft size={18} /></button>
                    <button disabled={!canNext} onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1))} className="w-9 h-9 rounded-full flex items-center justify-center hover:bg-emerald-50 text-[#0F9D6E] disabled:opacity-25 disabled:hover:bg-transparent" aria-label="Mes siguiente"><ChevronRight size={18} /></button>
                  </div>
                </div>
                <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-[#6B7280] tracking-wider mb-2">
                  {[1, 2, 3, 4, 5, 6, 0].map((i) => <div key={i}>{DOW[i].toUpperCase()}</div>)}
                </div>
                <div className="grid grid-cols-7 gap-1">
                  {cells.map((c, i) => c === null ? <div key={i} /> : (
                    <button key={c.d} disabled={!c.open} onClick={() => { setDate(c.d); setTime(""); }}
                      className={`relative aspect-square rounded-full text-sm font-semibold flex items-center justify-center transition-colors ${
                        date === c.d ? "bg-[#0F9D6E] text-white" : c.open ? "bg-emerald-50 text-[#0F9D6E] hover:bg-emerald-100" : "text-[#1A2332]/25 cursor-default"}`}>
                      {c.n}
                      {c.d === ymd(today) && <span className={`absolute bottom-1.5 w-1 h-1 rounded-full ${date === c.d ? "bg-white" : "bg-[#0F9D6E]"}`} />}
                    </button>
                  ))}
                </div>
              </div>

              {/* Horarios */}
              <div className="p-7 md:pl-0 border-t md:border-t-0 border-black/5">
                {date ? (
                  <>
                    <div className="font-bold text-[#1A2332] mb-4">{fmtDateLong(date)}</div>
                    {slots.length === 0 && <p className="text-sm text-[#6B7280]">Sin horarios este día.</p>}
                    <div className="flex flex-col gap-2 max-h-[380px] overflow-y-auto pr-1">
                      {slots.map((t) => time === t ? (
                        <div key={t} className="grid grid-cols-2 gap-2">
                          <div className="py-3 rounded-xl bg-[#1A2332] text-white text-sm font-semibold text-center">{fmtTime(t)}</div>
                          <button onClick={() => setStep("form")} className="py-3 rounded-xl bg-[#0F9D6E] hover:bg-[#0B7C57] text-white text-sm font-bold transition-colors">Siguiente</button>
                        </div>
                      ) : (
                        <button key={t} onClick={() => setTime(t)} className="py-3 rounded-xl border-2 border-emerald-200 text-[#0F9D6E] text-sm font-bold hover:border-[#0F9D6E] transition-colors">{fmtTime(t)}</button>
                      ))}
                    </div>
                  </>
                ) : <p className="text-sm text-[#6B7280]">Elige un día disponible.</p>}
              </div>
            </div>
          ) : (
            <div className="p-7 md:p-9">
              <button onClick={() => setStep("pick")} className="inline-flex items-center gap-1.5 text-sm font-semibold text-[#6B7280] hover:text-[#0F9D6E] mb-5"><ArrowLeft size={15} />Cambiar horario</button>
              <h3 className="text-2xl font-extrabold text-[#1A2332] mb-1">Tus datos</h3>
              <p className="text-sm text-[#6B7280] mb-6">Solo lo necesario para enviarte el enlace de la videollamada.</p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label className="sm:col-span-2 block">
                  <span className="block text-sm font-semibold text-[#1A2332] mb-1.5">Nombre *</span>
                  <input autoFocus value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Juan García" className={inputCls + (touched && form.name.trim().length < 2 ? " border-red-400" : "")} />
                </label>
                <label className="block">
                  <span className="block text-sm font-semibold text-[#1A2332] mb-1.5">Correo *</span>
                  <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="juan@empresa.com" className={inputCls + (touched && !emailOk ? " border-red-400" : "")} />
                </label>
                <label className="block">
                  <span className="block text-sm font-semibold text-[#1A2332] mb-1.5">WhatsApp *</span>
                  <input type="tel" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="+52 55 1234 5678" className={inputCls + (touched && form.phone.replace(/\D/g, "").length < 8 ? " border-red-400" : "")} />
                </label>
                <label className="sm:col-span-2 block">
                  <span className="block text-sm font-semibold text-[#1A2332] mb-1.5">Empresa o sitio web <span className="font-normal text-[#6B7280]">(opcional)</span></span>
                  <input value={form.company} onChange={(e) => setForm({ ...form, company: e.target.value })} placeholder="miempresa.com" className={inputCls} />
                </label>
                <label className="sm:col-span-2 block">
                  <span className="block text-sm font-semibold text-[#1A2332] mb-1.5">¿Algo que debamos saber? <span className="font-normal text-[#6B7280]">(opcional)</span></span>
                  <textarea rows={2} value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} placeholder="Tu objetivo principal…" className={inputCls + " resize-none"} />
                </label>
              </div>
              {touched && !valid && <p className="text-sm text-red-500 mt-3">Revisa los campos marcados.</p>}
              <button onClick={confirm} className="mt-6 w-full py-4 rounded-xl bg-[#0F9D6E] hover:bg-[#0B7C57] text-white font-bold transition-colors">
                Confirmar · <span>{fmtDateLong(date)}</span>, {fmtTime(time)}
              </button>
              <p className="text-xs text-[#6B7280] text-center mt-3">Puedes reprogramar o cancelar desde el correo de confirmación.</p>
            </div>
          )}
        </div>

        <div className="max-w-5xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
          {[
            { icon: Shield, label: "100% gratuita", sub: "Sin costo ni compromiso" },
            { icon: Clock, label: `${avail.duration} minutos`, sub: "Sesión puntual y enfocada" },
            { icon: Headphones, label: "Experto real", sub: "No robots, no vendedores" },
          ].map((b) => (
            <div key={b.label} className="flex items-center gap-3 justify-center">
              <b.icon size={20} className="text-[#0F9D6E]" />
              <div><div className="font-bold text-[#1A2332] text-sm">{b.label}</div><div className="text-xs text-[#6B7280]">{b.sub}</div></div>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}
