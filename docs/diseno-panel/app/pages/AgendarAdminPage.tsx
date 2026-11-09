import { useMemo, useState } from "react";
import { Calendar, List, Settings2, ChevronLeft, ChevronRight, Plus, Search, X, Mail, MessageCircle, ExternalLink, Clock, CheckCircle, AlertCircle, Users, TrendingUp, Trash2 } from "lucide-react";
import { TOPICS, DOW, MONTHS, loadAvail, saveAvail, loadBookings, saveBookings, slotsFor, ymd, parseYmd, addDays, fmtTime, fmtDateLong, fmtDateShort, toMin, fromMin, type Booking, type BookingStatus, type Availability } from "./agendaStore";

const STATUS: Record<BookingStatus, { label: string; cls: string; block: string }> = {
  confirmada: { label: "Confirmada", cls: "bg-emerald-50 text-[#0F9D6E] border-emerald-200", block: "bg-emerald-50 border-l-[#0F9D6E] text-[#0B7C57]" },
  pendiente:  { label: "Pendiente",  cls: "bg-amber-50 text-amber-700 border-amber-200",    block: "bg-amber-50 border-l-amber-500 text-amber-800" },
  completada: { label: "Completada", cls: "bg-slate-100 text-slate-600 border-slate-200",   block: "bg-slate-100 border-l-slate-400 text-slate-600" },
  "no-show":  { label: "No asistió", cls: "bg-red-50 text-red-600 border-red-200",          block: "bg-red-50 border-l-red-400 text-red-700" },
  cancelada:  { label: "Cancelada",  cls: "bg-white text-slate-400 border-slate-200 line-through", block: "bg-white border-l-slate-300 text-slate-400 line-through" },
};
const HOURS = Array.from({ length: 12 }, (_, i) => 8 + i); // 8:00 – 19:00
const ROW = 80;
const inputCls = "w-full px-3 py-2.5 rounded-lg border border-black/10 text-sm bg-white focus:outline-none focus:border-[#0F9D6E] focus:ring-2 focus:ring-[#0F9D6E]/20";
const timeOpts = Array.from({ length: 29 }, (_, i) => fromMin(6 * 60 + i * 30)); // 06:00 – 20:00

function Pill({ s }: { s: BookingStatus }) {
  return <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-semibold border ${STATUS[s].cls}`}>{STATUS[s].label}</span>;
}

export default function AgendarAdminPage() {
  const [bookings, setBookingsRaw] = useState<Booking[]>(loadBookings);
  const [avail, setAvailRaw] = useState<Availability>(loadAvail);
  const setBookings = (b: Booking[]) => { setBookingsRaw(b); saveBookings(b); };
  const setAvail = (a: Availability) => { setAvailRaw(a); saveAvail(a); };

  const [tab, setTab] = useState<"agenda" | "citas" | "disponibilidad">("agenda");
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const todayS = ymd(today);
  const [weekStart, setWeekStart] = useState(() => addDays(today, -((today.getDay() + 6) % 7)));
  const [openId, setOpenId] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [q, setQ] = useState("");
  const [filter, setFilter] = useState<"todas" | "proximas" | BookingStatus>("proximas");
  const [newBlock, setNewBlock] = useState("");

  const open = bookings.find((b) => b.id === openId) || null;
  const update = (id: string, patch: Partial<Booking>) => setBookings(bookings.map((b) => (b.id === id ? { ...b, ...patch } : b)));
  const sorted = useMemo(() => [...bookings].sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time)), [bookings]);

  const stats = useMemo(() => {
    const in7 = ymd(addDays(today, 7));
    const active = (b: Booking) => b.status === "confirmada" || b.status === "pendiente";
    const done = bookings.filter((b) => b.status === "completada").length;
    const ns = bookings.filter((b) => b.status === "no-show").length;
    return [
      { icon: Calendar, label: "Hoy", value: bookings.filter((b) => b.date === todayS && active(b)).length },
      { icon: Clock, label: "Próximos 7 días", value: bookings.filter((b) => b.date >= todayS && b.date <= in7 && active(b)).length },
      { icon: AlertCircle, label: "Por confirmar", value: bookings.filter((b) => b.status === "pendiente").length },
      { icon: TrendingUp, label: "Asistencia", value: done + ns ? Math.round((done / (done + ns)) * 100) + "%" : "—" },
    ];
  }, [bookings]);

  const listed = sorted.filter((b) => {
    if (filter === "proximas" && !(b.date >= todayS && (b.status === "confirmada" || b.status === "pendiente"))) return false;
    if (filter !== "todas" && filter !== "proximas" && b.status !== filter) return false;
    const s = q.toLowerCase();
    return !s || [b.name, b.email, b.company, b.topic, b.phone].some((x) => x.toLowerCase().includes(s));
  });

  const week = Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)).filter((d) => avail.days[d.getDay()].on || bookings.some((b) => b.date === ymd(d)));
  const weekLabel = (() => { const e = addDays(weekStart, 6); return weekStart.getMonth() === e.getMonth() ? `${weekStart.getDate()} – ${e.getDate()} de ${MONTHS[e.getMonth()]}` : `${weekStart.getDate()} ${MONTHS[weekStart.getMonth()].slice(0, 3)} – ${e.getDate()} ${MONTHS[e.getMonth()].slice(0, 3)}`; })();

  return (
    <div className="min-h-screen bg-[#F5F7FA] text-[#1A2332]">
      {/* Top bar */}
      <header className="bg-white border-b border-black/5 sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-6 h-16 flex items-center gap-4">
          <img src="assets/rankpro-logo-horizontal-black.png" alt="RankPro" className="h-8 w-auto" />
          <div className="h-6 w-px bg-black/10" />
          <div className="font-bold text-sm">Agenda <span className="text-[#6B7280] font-medium">· Panel administrativo</span></div>
          <div className="flex-1" />
          <a href="./RankPro - Agendar.dc.html" target="_blank" className="hidden sm:inline-flex items-center gap-1.5 text-sm font-semibold text-[#6B7280] hover:text-[#0F9D6E]"><ExternalLink size={14} />Página pública</a>
          <button onClick={() => setCreating(true)} className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#0F9D6E] hover:bg-[#0B7C57] text-white text-sm font-bold transition-colors"><Plus size={16} />Nueva cita</button>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-6 py-8">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          {stats.map((s) => (
            <div key={s.label} className="bg-white rounded-2xl border border-black/5 p-5 flex items-center gap-4">
              <div className="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center"><s.icon size={18} className="text-[#0F9D6E]" /></div>
              <div><div className="text-2xl font-extrabold leading-none">{s.value}</div><div className="text-xs text-[#6B7280] font-semibold mt-1">{s.label}</div></div>
            </div>
          ))}
        </div>

        <div className="flex flex-wrap items-center gap-3 mb-5">
          <div className="inline-flex bg-white rounded-xl border border-black/5 p-1">
            {([["agenda", "Agenda", Calendar], ["citas", "Citas", List], ["disponibilidad", "Disponibilidad", Settings2]] as const).map(([id, label, Icon]) => (
              <button key={id} onClick={() => setTab(id)} className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors ${tab === id ? "bg-[#1A2332] text-white" : "text-[#6B7280] hover:text-[#1A2332]"}`}><Icon size={15} />{label}</button>
            ))}
          </div>
          {tab === "agenda" && (
            <div className="flex items-center gap-2 ml-auto">
              <button onClick={() => setWeekStart(addDays(today, -((today.getDay() + 6) % 7)))} className="px-3 py-2 rounded-lg bg-white border border-black/5 text-sm font-semibold hover:border-[#0F9D6E]">Hoy</button>
              <button onClick={() => setWeekStart(addDays(weekStart, -7))} className="w-9 h-9 rounded-lg bg-white border border-black/5 flex items-center justify-center hover:border-[#0F9D6E]"><ChevronLeft size={16} /></button>
              <button onClick={() => setWeekStart(addDays(weekStart, 7))} className="w-9 h-9 rounded-lg bg-white border border-black/5 flex items-center justify-center hover:border-[#0F9D6E]"><ChevronRight size={16} /></button>
              <div className="font-bold text-sm min-w-[150px] text-right">{weekLabel}</div>
            </div>
          )}
          {tab === "citas" && (
            <div className="flex flex-wrap items-center gap-2 ml-auto">
              <div className="relative"><Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#6B7280]" /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar cliente, correo, tema…" className="pl-9 pr-3 py-2 rounded-lg bg-white border border-black/5 text-sm w-64 focus:outline-none focus:border-[#0F9D6E]" /></div>
              <select value={filter} onChange={(e) => setFilter(e.target.value as any)} className="px-3 py-2 rounded-lg bg-white border border-black/5 text-sm font-semibold focus:outline-none focus:border-[#0F9D6E]">
                <option value="proximas">Próximas</option><option value="todas">Todas</option>
                {(Object.keys(STATUS) as BookingStatus[]).map((s) => <option key={s} value={s}>{STATUS[s].label}</option>)}
              </select>
            </div>
          )}
        </div>

        {tab === "agenda" && (
          <div className="bg-white rounded-2xl border border-black/5 overflow-x-auto">
            <div className="min-w-[720px]">
              <div className="grid border-b border-black/5" style={{ gridTemplateColumns: `64px repeat(${week.length}, minmax(0,1fr))` }}>
                <div />
                {week.map((d) => { const s = ymd(d); const isT = s === todayS; return (
                  <div key={s} className="py-3 text-center border-l border-black/5">
                    <div className="text-[11px] font-bold tracking-wider text-[#6B7280]">{DOW[d.getDay()].toUpperCase()}</div>
                    <div className={`mx-auto mt-1 w-8 h-8 rounded-full flex items-center justify-center font-extrabold ${isT ? "bg-[#0F9D6E] text-white" : ""}`}>{d.getDate()}</div>
                  </div>); })}
              </div>
              <div className="grid relative" style={{ gridTemplateColumns: `64px repeat(${week.length}, minmax(0,1fr))` }}>
                <div>{HOURS.map((h) => <div key={h} style={{ height: ROW }} className="text-[11px] text-[#6B7280] text-right pr-2 -mt-2">{fmtTime(fromMin(h * 60)).replace(":00", "")}</div>)}</div>
                {week.map((d) => { const s = ymd(d); const rule = avail.days[d.getDay()]; const blocked = avail.blocked.includes(s); return (
                  <div key={s} className="relative border-l border-black/5" style={{ height: HOURS.length * ROW }}>
                    {HOURS.map((h) => <div key={h} style={{ height: ROW }} className="border-b border-black/[0.04]" />)}
                    {rule.on && !blocked && <div className="absolute inset-x-0 bg-emerald-50/40 pointer-events-none" style={{ top: ((toMin(rule.from) - 480) / 60) * ROW, height: ((toMin(rule.to) - toMin(rule.from)) / 60) * ROW }} />}
                    {blocked && <div className="absolute inset-0 flex items-start justify-center pt-3" style={{ background: "repeating-linear-gradient(135deg, transparent 0 6px, rgba(0,0,0,0.04) 6px 12px)" }}><span className="text-[11px] font-bold text-[#6B7280] bg-white px-2 py-0.5 rounded">Bloqueado</span></div>}
                    {bookings.filter((b) => b.date === s).map((b) => (
                      <button key={b.id} onClick={() => setOpenId(b.id)}
                        className={`absolute left-1 right-1 rounded-md border-l-4 px-2 py-0.5 leading-tight text-left overflow-hidden hover:shadow-md transition-shadow ${STATUS[b.status].block}`}
                        style={{ top: ((toMin(b.time) - 480) / 60) * ROW + 1, height: (avail.duration / 60) * ROW - 2 }}>
                        <div className="text-[11px] font-bold truncate">{fmtTime(b.time)} · {b.name}</div>
                        {(avail.duration / 60) * ROW >= 36 && <div className="text-[10px] truncate opacity-80">{b.topic}</div>}
                      </button>
                    ))}
                  </div>); })}
              </div>
            </div>
          </div>
        )}

        {tab === "citas" && (
          <div className="bg-white rounded-2xl border border-black/5 overflow-x-auto">
            <table className="w-full text-sm min-w-[760px]">
              <thead><tr className="text-left text-[11px] tracking-wider text-[#6B7280] border-b border-black/5">
                {["FECHA", "CLIENTE", "TEMA", "ORIGEN", "ESTADO", ""].map((h) => <th key={h} className="px-5 py-3 font-bold">{h}</th>)}
              </tr></thead>
              <tbody>
                {listed.map((b) => (
                  <tr key={b.id} onClick={() => setOpenId(b.id)} className="border-b border-black/5 last:border-0 hover:bg-[#F5F7FA] cursor-pointer">
                    <td className="px-5 py-3.5"><div className="font-semibold">{fmtDateShort(b.date)}</div><div className="text-xs text-[#6B7280]">{fmtTime(b.time)}</div></td>
                    <td className="px-5 py-3.5"><div className="font-semibold">{b.name}</div><div className="text-xs text-[#6B7280]">{b.company || b.email}</div></td>
                    <td className="px-5 py-3.5 text-[#6B7280]">{b.topic}</td>
                    <td className="px-5 py-3.5 text-[#6B7280]">{b.source}</td>
                    <td className="px-5 py-3.5"><Pill s={b.status} /></td>
                    <td className="px-5 py-3.5 text-right">
                      {b.status === "pendiente" && <button onClick={(e) => { e.stopPropagation(); update(b.id, { status: "confirmada" }); }} className="px-3 py-1.5 rounded-lg bg-[#0F9D6E] text-white text-xs font-bold hover:bg-[#0B7C57]">Confirmar</button>}
                    </td>
                  </tr>
                ))}
                {listed.length === 0 && <tr><td colSpan={6} className="px-5 py-12 text-center text-[#6B7280]">No hay citas con estos filtros.</td></tr>}
              </tbody>
            </table>
          </div>
        )}

        {tab === "disponibilidad" && (
          <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5">
            <div className="bg-white rounded-2xl border border-black/5 p-6">
              <h3 className="font-extrabold text-lg mb-1">Horario semanal</h3>
              <p className="text-sm text-[#6B7280] mb-5">Los clientes solo verán horarios dentro de estos rangos. Se guarda automáticamente.</p>
              <div className="divide-y divide-black/5">
                {[1, 2, 3, 4, 5, 6, 0].map((i) => { const r = avail.days[i]; const set = (p: any) => setAvail({ ...avail, days: { ...avail.days, [i]: { ...r, ...p } } }); return (
                  <div key={i} className="flex flex-wrap items-center gap-4 py-3.5">
                    <button onClick={() => set({ on: !r.on })} className={`relative w-11 h-6 rounded-full transition-colors ${r.on ? "bg-[#0F9D6E]" : "bg-slate-200"}`} aria-label={"Activar " + DOW[i]}>
                      <span className={`absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all ${r.on ? "left-[22px]" : "left-0.5"}`} />
                    </button>
                    <div className="w-24 font-semibold">{["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"][i]}</div>
                    {r.on ? (
                      <div className="flex items-center gap-2">
                        <select value={r.from} onChange={(e) => set({ from: e.target.value })} className={inputCls + " w-32"}>{timeOpts.map((t) => <option key={t} value={t}>{fmtTime(t)}</option>)}</select>
                        <span className="text-[#6B7280]">–</span>
                        <select value={r.to} onChange={(e) => set({ to: e.target.value })} className={inputCls + " w-32"}>{timeOpts.filter((t) => t > r.from).map((t) => <option key={t} value={t}>{fmtTime(t)}</option>)}</select>
                      </div>
                    ) : <span className="text-sm text-[#6B7280]">No disponible</span>}
                  </div>); })}
              </div>
            </div>
            <div className="flex flex-col gap-5">
              <div className="bg-white rounded-2xl border border-black/5 p-6 space-y-4">
                <h3 className="font-extrabold text-lg">Reglas de reserva</h3>
                {([["duration", "Duración de la cita", [15, 30, 45, 60], "min"], ["buffer", "Descanso entre citas", [0, 5, 10, 15, 30], "min"], ["leadHours", "Aviso mínimo", [0, 1, 2, 4, 12, 24, 48], "h"], ["horizonDays", "Reservar con hasta", [7, 14, 30, 45, 60, 90], "días"]] as const).map(([k, label, opts, u]) => (
                  <label key={k} className="block">
                    <span className="block text-sm font-semibold mb-1.5">{label}</span>
                    <select value={(avail as any)[k]} onChange={(e) => setAvail({ ...avail, [k]: Number(e.target.value) })} className={inputCls}>{opts.map((o) => <option key={o} value={o}>{o} {u}</option>)}</select>
                  </label>
                ))}
              </div>
              <div className="bg-white rounded-2xl border border-black/5 p-6">
                <h3 className="font-extrabold text-lg mb-1">Días bloqueados</h3>
                <p className="text-sm text-[#6B7280] mb-4">Vacaciones, festivos o eventos.</p>
                <div className="flex gap-2 mb-3">
                  <input type="date" min={todayS} value={newBlock} onChange={(e) => setNewBlock(e.target.value)} className={inputCls} />
                  <button disabled={!newBlock} onClick={() => { if (!avail.blocked.includes(newBlock)) setAvail({ ...avail, blocked: [...avail.blocked, newBlock].sort() }); setNewBlock(""); }} className="px-4 rounded-lg bg-[#1A2332] text-white text-sm font-bold disabled:opacity-30">Añadir</button>
                </div>
                <div className="flex flex-wrap gap-2">
                  {avail.blocked.map((d) => (
                    <span key={d} className="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-[#F5F7FA] border border-black/5 text-xs font-semibold">{fmtDateShort(d)}
                      <button onClick={() => setAvail({ ...avail, blocked: avail.blocked.filter((x) => x !== d) })} className="w-5 h-5 rounded-full hover:bg-black/5 flex items-center justify-center"><X size={12} /></button>
                    </span>
                  ))}
                  {avail.blocked.length === 0 && <span className="text-sm text-[#6B7280]">Ninguno.</span>}
                </div>
              </div>
            </div>
          </div>
        )}
      </main>

      {open && <Drawer b={open} avail={avail} bookings={bookings} onClose={() => setOpenId(null)} onUpdate={(p) => update(open.id, p)} onDelete={() => { setBookings(bookings.filter((x) => x.id !== open.id)); setOpenId(null); }} />}
      {creating && <CreateModal avail={avail} bookings={bookings} onClose={() => setCreating(false)} onCreate={(b) => { setBookings([...bookings, b]); setCreating(false); setOpenId(b.id); }} />}
    </div>
  );
}

function SlotPicker({ date, time, setDate, setTime, avail, bookings, ignoreId }: { date: string; time: string; setDate: (s: string) => void; setTime: (s: string) => void; avail: Availability; bookings: Booking[]; ignoreId?: string }) {
  const slots = date ? slotsFor(date, avail, bookings, ignoreId) : [];
  return (
    <div className="grid grid-cols-2 gap-2">
      <input type="date" min={ymd(new Date())} value={date} onChange={(e) => { setDate(e.target.value); setTime(""); }} className={inputCls} />
      <select value={time} onChange={(e) => setTime(e.target.value)} className={inputCls} disabled={!slots.length}>
        <option value="">{date ? (slots.length ? "Hora…" : "Sin horarios") : "Hora…"}</option>
        {slots.map((t) => <option key={t} value={t}>{fmtTime(t)}</option>)}
      </select>
    </div>
  );
}

function Drawer({ b, avail, bookings, onClose, onUpdate, onDelete }: { b: Booking; avail: Availability; bookings: Booking[]; onClose: () => void; onUpdate: (p: Partial<Booking>) => void; onDelete: () => void }) {
  const [resched, setResched] = useState(false);
  const [d, setD] = useState(b.date); const [t, setT] = useState("");
  const wa = "https://wa.me/" + b.phone.replace(/\D/g, "");
  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-[#0E1B2A]/30" onClick={onClose} />
      <aside className="relative w-full max-w-md bg-white h-full overflow-y-auto shadow-2xl">
        <div className="p-6 border-b border-black/5 flex items-start justify-between gap-4">
          <div>
            <div className="mb-2"><Pill s={b.status} /></div>
            <h3 className="text-xl font-extrabold">{b.name}</h3>
            <div className="text-sm text-[#6B7280]">{b.company || "—"}</div>
          </div>
          <button onClick={onClose} className="w-9 h-9 rounded-full hover:bg-[#F5F7FA] flex items-center justify-center" aria-label="Cerrar"><X size={18} /></button>
        </div>
        <div className="p-6 space-y-6">
          <div className="rounded-xl bg-[#F5F7FA] p-4">
            <div className="font-extrabold">{fmtDateLong(b.date)}</div>
            <div className="text-sm text-[#6B7280]">{fmtTime(b.time)} – {fmtTime(fromMin(toMin(b.time) + avail.duration))} · Videollamada</div>
          </div>
          <dl className="grid grid-cols-[90px_minmax(0,1fr)] gap-y-2.5 text-sm">
            <dt className="text-[#6B7280]">Correo</dt><dd className="font-semibold break-all">{b.email}</dd>
            <dt className="text-[#6B7280]">WhatsApp</dt><dd className="font-semibold">{b.phone}</dd>
            <dt className="text-[#6B7280]">Tema</dt><dd className="font-semibold">{b.topic}</dd>
            <dt className="text-[#6B7280]">Origen</dt><dd className="font-semibold">{b.source}</dd>
            {b.note && <><dt className="text-[#6B7280]">Nota</dt><dd>{b.note}</dd></>}
          </dl>
          <div className="grid grid-cols-2 gap-2">
            <a href={wa} target="_blank" rel="noopener noreferrer" className="flex items-center justify-center gap-2 py-2.5 rounded-lg border border-black/10 text-sm font-semibold hover:border-[#0F9D6E]"><MessageCircle size={15} />WhatsApp</a>
            <a href={"mailto:" + b.email} className="flex items-center justify-center gap-2 py-2.5 rounded-lg border border-black/10 text-sm font-semibold text-[#1A2332] hover:border-[#0F9D6E]"><Mail size={15} />Correo</a>
          </div>
          <div>
            <div className="text-[11px] font-bold tracking-wider text-[#6B7280] mb-2">CAMBIAR ESTADO</div>
            <div className="flex flex-wrap gap-2">
              {(Object.keys(STATUS) as BookingStatus[]).map((s) => (
                <button key={s} onClick={() => onUpdate({ status: s })} className={`px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ${b.status === s ? "bg-[#1A2332] border-[#1A2332] text-white" : "border-black/10 hover:border-[#0F9D6E]"}`}>{STATUS[s].label}</button>
              ))}
            </div>
          </div>
          <div>
            {!resched ? (
              <button onClick={() => setResched(true)} className="w-full py-2.5 rounded-lg border border-black/10 text-sm font-semibold hover:border-[#0F9D6E]">Reprogramar</button>
            ) : (
              <div className="space-y-2">
                <div className="text-[11px] font-bold tracking-wider text-[#6B7280]">NUEVO HORARIO</div>
                <SlotPicker date={d} time={t} setDate={setD} setTime={setT} avail={avail} bookings={bookings} ignoreId={b.id} />
                <div className="grid grid-cols-2 gap-2">
                  <button onClick={() => setResched(false)} className="py-2.5 rounded-lg border border-black/10 text-sm font-semibold">Cancelar</button>
                  <button disabled={!t} onClick={() => { onUpdate({ date: d, time: t }); setResched(false); }} className="py-2.5 rounded-lg bg-[#0F9D6E] text-white text-sm font-bold disabled:opacity-30">Guardar</button>
                </div>
              </div>
            )}
          </div>
          <button onClick={onDelete} className="inline-flex items-center gap-1.5 text-sm font-semibold text-red-500 hover:text-red-600"><Trash2 size={14} />Eliminar registro</button>
        </div>
      </aside>
    </div>
  );
}

function CreateModal({ avail, bookings, onClose, onCreate }: { avail: Availability; bookings: Booking[]; onClose: () => void; onCreate: (b: Booking) => void }) {
  const [f, setF] = useState({ name: "", email: "", phone: "", company: "", topic: TOPICS[0], note: "", source: "Teléfono" });
  const [d, setD] = useState(""); const [t, setT] = useState("");
  const ok = f.name.trim() && f.email.includes("@") && d && t;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-[#0E1B2A]/30" onClick={onClose} />
      <div className="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-5">
          <h3 className="text-xl font-extrabold flex items-center gap-2"><Users size={18} className="text-[#0F9D6E]" />Nueva cita</h3>
          <button onClick={onClose} className="w-9 h-9 rounded-full hover:bg-[#F5F7FA] flex items-center justify-center" aria-label="Cerrar"><X size={18} /></button>
        </div>
        <div className="space-y-3">
          <input value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} placeholder="Nombre *" className={inputCls} />
          <div className="grid grid-cols-2 gap-2">
            <input value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} placeholder="Correo *" className={inputCls} />
            <input value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} placeholder="WhatsApp" className={inputCls} />
          </div>
          <input value={f.company} onChange={(e) => setF({ ...f, company: e.target.value })} placeholder="Empresa" className={inputCls} />
          <div className="grid grid-cols-2 gap-2">
            <select value={f.topic} onChange={(e) => setF({ ...f, topic: e.target.value })} className={inputCls}>{TOPICS.map((x) => <option key={x}>{x}</option>)}</select>
            <select value={f.source} onChange={(e) => setF({ ...f, source: e.target.value })} className={inputCls}>{["Teléfono", "WhatsApp", "Referido", "Evento", "Sitio web"].map((x) => <option key={x}>{x}</option>)}</select>
          </div>
          <SlotPicker date={d} time={t} setDate={setD} setTime={setT} avail={avail} bookings={bookings} />
          <textarea rows={2} value={f.note} onChange={(e) => setF({ ...f, note: e.target.value })} placeholder="Nota interna" className={inputCls + " resize-none"} />
        </div>
        <button disabled={!ok} onClick={() => onCreate({ id: "b" + Date.now(), date: d, time: t, ...f, status: "confirmada", createdAt: new Date().toISOString() })}
          className="mt-5 w-full py-3 rounded-xl bg-[#0F9D6E] hover:bg-[#0B7C57] text-white font-bold disabled:opacity-30 inline-flex items-center justify-center gap-2"><CheckCircle size={16} />Crear cita</button>
      </div>
    </div>
  );
}
