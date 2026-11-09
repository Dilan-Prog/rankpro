import { useState } from "react";
import {
  Calendar, Users, Settings, LogOut, Search, Bell, UserPlus, X,
  Filter, Download, DollarSign, ChevronUp, Smile, Stethoscope,
  ScanLine, CreditCard, ClipboardList, BarChart3, HeartPulse
} from "lucide-react";
import {
  BarChart, Bar, AreaChart, Area, XAxis, YAxis, CartesianGrid,
  Tooltip, ResponsiveContainer, PieChart as RPieChart, Pie, Cell
} from "recharts";
import type { Page } from "../types";

const dpmsAppointments = [
  { time: "08:00", patient: "Laura Sánchez",    age: 34, treatment: "Limpieza dental",          chair: 1, duration: 45, status: "done",    avatar: "LS", color: "from-emerald-400 to-teal-500" },
  { time: "08:45", patient: "Miguel Ángel Ruiz",age: 28, treatment: "Blanqueamiento dental",     chair: 2, duration: 60, status: "done",    avatar: "MR", color: "from-blue-400 to-blue-600" },
  { time: "09:45", patient: "Diana Torres",      age: 41, treatment: "Extracción molar",          chair: 1, duration: 30, status: "in-chair",avatar: "DT", color: "from-purple-400 to-purple-600" },
  { time: "10:15", patient: "Roberto Campos",    age: 55, treatment: "Corona de porcelana",       chair: 3, duration: 90, status: "waiting", avatar: "RC", color: "from-amber-400 to-orange-500" },
  { time: "11:00", patient: "Sofía Medina",      age: 22, treatment: "Ortodoncia — revisión",     chair: 2, duration: 30, status: "waiting", avatar: "SM", color: "from-pink-400 to-rose-500" },
  { time: "11:30", patient: "Carlos Ibáñez",     age: 38, treatment: "Resina compuesta",          chair: 1, duration: 45, status: "scheduled",avatar:"CI", color: "from-cyan-400 to-teal-500" },
  { time: "12:15", patient: "Valeria Ortiz",     age: 29, treatment: "Endodoncia",                chair: 3, duration: 120,status: "scheduled",avatar:"VO", color: "from-indigo-400 to-purple-500" },
  { time: "14:00", patient: "Ernesto Villanueva",age: 62, treatment: "Implante dental — fase 2",  chair: 2, duration: 60, status: "scheduled",avatar:"EV", color: "from-green-400 to-emerald-600" },
  { time: "15:00", patient: "Andrea Castillo",   age: 18, treatment: "Brackets metálicos",        chair: 1, duration: 45, status: "scheduled",avatar:"AC", color: "from-rose-400 to-pink-600" },
  { time: "16:00", patient: "Hugo Delgado",      age: 45, treatment: "Puente de 3 piezas",        chair: 3, duration: 90, status: "scheduled",avatar:"HD", color: "from-violet-400 to-purple-600" },
];

const dpmsPatients = [
  { name: "Laura Sánchez",     lastVisit: "15 Jun 2025", nextVisit: "15 Sep 2025", treatment: "Control semestral",      balance: "$0",      status: "al corriente" },
  { name: "Miguel Ángel Ruiz", lastVisit: "15 Jun 2025", nextVisit: "22 Jun 2025", treatment: "Blanqueamiento",          balance: "$2,400",  status: "pendiente"    },
  { name: "Roberto Campos",    lastVisit: "10 May 2025", nextVisit: "15 Jun 2025", treatment: "Corona de porcelana",     balance: "$8,500",  status: "pendiente"    },
  { name: "Sofía Medina",      lastVisit: "01 Jun 2025", nextVisit: "11 Jun 2025", treatment: "Ortodoncia mensual",      balance: "$0",      status: "al corriente" },
  { name: "Valeria Ortiz",     lastVisit: "20 May 2025", nextVisit: "15 Jun 2025", treatment: "Endodoncia",              balance: "$5,200",  status: "pendiente"    },
];

const dpmsRevenueData = [
  { dia: "Lun", implantos: 12000, ortodoncia: 8500, restauracion: 5200, limpieza: 2800 },
  { dia: "Mar", implantos: 0,     ortodoncia: 9200, restauracion: 6100, limpieza: 3400 },
  { dia: "Mié", implantos: 18000, ortodoncia: 7800, restauracion: 4900, limpieza: 2100 },
  { dia: "Jue", implantos: 0,     ortodoncia: 11200,restauracion: 7300, limpieza: 3900 },
  { dia: "Vie", implantos: 24000, ortodoncia: 9600, restauracion: 5800, limpieza: 4200 },
  { dia: "Sáb", implantos: 8000,  ortodoncia: 6400, restauracion: 3200, limpieza: 5100 },
  { dia: "Hoy", implantos: 14000, ortodoncia: 8800, restauracion: 6600, limpieza: 3100 },
];

const dpmsTreatmentMix = [
  { name: "Implantes",    value: 34, color: "#0F9D6E" },
  { name: "Ortodoncia",   value: 28, color: "#34D399" },
  { name: "Restauración", value: 22, color: "#10B981" },
  { name: "Limpieza",     value: 16, color: "#6EE7B7" },
];

const dpmsPatientTrend = [
  { mes: "Ene", nuevos: 18, recurrentes: 52 },
  { mes: "Feb", nuevos: 22, recurrentes: 58 },
  { mes: "Mar", nuevos: 19, recurrentes: 61 },
  { mes: "Abr", nuevos: 27, recurrentes: 64 },
  { mes: "May", nuevos: 31, recurrentes: 70 },
  { mes: "Jun", nuevos: 24, recurrentes: 68 },
];

const upperTeeth  = [18,17,16,15,14,13,12,11, 21,22,23,24,25,26,27,28];
const lowerTeeth  = [48,47,46,45,44,43,42,41, 31,32,33,34,35,36,37,38];
const toothStatus: Record<number, { color: string; label: string }> = {
  16: { color: "#ef4444", label: "Extracción" },
  26: { color: "#f59e0b", label: "Corona" },
  36: { color: "#f59e0b", label: "Corona" },
  46: { color: "#3b82f6", label: "Resina" },
  11: { color: "#8b5cf6", label: "Tratamiento de conducto" },
  21: { color: "#8b5cf6", label: "Tratamiento de conducto" },
  15: { color: "#10b981", label: "Sellante" },
  25: { color: "#10b981", label: "Sellante" },
};

const apptStatusCfg: Record<string, { label: string; bg: string; text: string; dot: string }> = {
  done:      { label: "Atendido",    bg: "bg-emerald-50",text: "text-emerald-700",dot: "bg-emerald-500" },
  "in-chair":{ label: "En sillón",  bg: "bg-blue-50",   text: "text-blue-700",   dot: "bg-blue-500 animate-pulse" },
  waiting:   { label: "Sala espera",bg: "bg-amber-50",  text: "text-amber-700",  dot: "bg-amber-400" },
  scheduled: { label: "Programado", bg: "bg-[#F0F4F8]", text: "text-[#6B7280]",  dot: "bg-[#6B7280]" },
};

export default function DPMSOdontologicoPage({ setPage }: { setPage: (p: Page) => void }) {
  const [activeSection, setActiveSection] = useState("agenda");
  const [selectedPatient, setSelectedPatient] = useState<typeof dpmsAppointments[0] | null>(null);
  const [activeTab, setActiveTab] = useState<"odontograma"|"historia"|"plan">("odontograma");

  const today = "Domingo, 15 de junio de 2025";
  const doneCount      = dpmsAppointments.filter(a => a.status === "done").length;
  const waitingCount   = dpmsAppointments.filter(a => a.status === "waiting").length;
  const scheduledCount = dpmsAppointments.filter(a => a.status === "scheduled").length;

  const sideNav = [
    { id: "agenda",    icon: Calendar,       label: "Agenda de hoy"   },
    { id: "pacientes", icon: Users,          label: "Pacientes"       },
    { id: "tratamientos",icon: Stethoscope,  label: "Tratamientos"   },
    { id: "radiografias",icon: ScanLine,     label: "Radiografías"   },
    { id: "facturacion",icon: CreditCard,    label: "Facturación CFDI"},
    { id: "inventario", icon: ClipboardList, label: "Inventario"      },
    { id: "reportes",   icon: BarChart3,     label: "Reportes"       },
    { id: "configuracion",icon: Settings,    label: "Configuración"  },
  ];

  const kpis = [
    { label: "Citas hoy",           value: `${dpmsAppointments.length}`, sub: `${doneCount} atendidas · ${waitingCount} en espera`, icon: Calendar,    grad: "from-cyan-500 to-teal-600",    change: "+3 vs ayer" },
    { label: "Ingresos del día",    value: "$32,500",                    sub: "MXN acumulado hoy",                                  icon: DollarSign,   grad: "from-emerald-500 to-emerald-600",change: "+18% vs promedio" },
    { label: "Pacientes nuevos",    value: "4",                          sub: "de 24 citas este mes",                               icon: UserPlus,     grad: "from-purple-500 to-purple-600", change: "+2 vs semana ant." },
    { label: "Tratamientos activos",value: "38",                         sub: "planes en curso",                                    icon: HeartPulse,   grad: "from-rose-500 to-pink-600",    change: "12 urgentes" },
  ];

  return (
    <div className="min-h-screen bg-[#F0F6FF] flex font-[Manrope,sans-serif]">

      {/* ── Sidebar ── */}
      <aside className="w-64 bg-[#0A1628] shrink-0 flex-col hidden lg:flex">
        {/* Brand */}
        <div className="px-5 py-6 border-b border-white/10">
          <div className="flex items-center gap-3 mb-1">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center shadow-lg shadow-cyan-900/40">
              <Smile size={20} className="text-white" />
            </div>
            <div>
              <div className="font-extrabold text-white text-base leading-tight">DPMS Dental</div>
              <div className="text-[10px] text-white/40 leading-tight">by RankPro Solutions</div>
            </div>
          </div>
          <div className="mt-3 px-3 py-2 bg-white/5 rounded-xl border border-white/10">
            <div className="text-xs font-semibold text-white/80">Clínica Dental Sonrisa+</div>
            <div className="text-[10px] text-white/40">Polanco, CDMX · 3 consultorios</div>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 p-3 space-y-0.5">
          {sideNav.map((item) => (
            <button key={item.id} onClick={() => setActiveSection(item.id)}
              className={`w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ${
                activeSection === item.id
                  ? "bg-cyan-500 text-white shadow-md shadow-cyan-900/30"
                  : "text-white/50 hover:text-white hover:bg-white/8"
              }`}>
              <item.icon size={17} />
              {item.label}
              {item.id === "agenda" && waitingCount > 0 && (
                <span className="ml-auto bg-amber-400 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{waitingCount}</span>
              )}
            </button>
          ))}
        </nav>

        {/* Doctor info */}
        <div className="p-4 border-t border-white/10">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center shrink-0">
              <span className="text-white font-bold text-sm">DR</span>
            </div>
            <div className="min-w-0">
              <div className="text-white text-sm font-semibold truncate">Dr. Ramírez Ortega</div>
              <div className="text-white/40 text-xs">Odontólogo General · Turno A</div>
            </div>
          </div>
          <button onClick={() => setPage("productos")}
            className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-white/40 hover:text-white hover:bg-white/8 transition-colors text-xs">
            <LogOut size={14} />Cerrar sesión
          </button>
        </div>
      </aside>

      {/* ── Main ── */}
      <div className="flex-1 flex flex-col overflow-hidden">

        {/* Header */}
        <header className="bg-white border-b border-black/5 px-6 py-3.5 flex items-center justify-between shrink-0">
          <div>
            <h1 className="text-lg font-extrabold text-[#0A1628]">
              {sideNav.find(n => n.id === activeSection)?.label ?? "Agenda"}
            </h1>
            <p className="text-xs text-[#6B7280]">{today} · Consultorios 1, 2 y 3 activos</p>
          </div>
          <div className="flex items-center gap-3">
            <button className="flex items-center gap-2 px-4 py-2 rounded-xl bg-[#F0F6FF] border border-black/8 text-sm font-medium text-[#0A1628] hover:bg-[#E4EEFF] transition-colors">
              <Search size={15} className="text-cyan-600" />Buscar paciente
            </button>
            <button className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 text-white text-sm font-semibold shadow-md shadow-cyan-200 hover:shadow-lg transition-all">
              <UserPlus size={15} />Nueva cita
            </button>
            <div className="relative">
              <button className="w-9 h-9 rounded-xl bg-[#F0F6FF] border border-black/8 flex items-center justify-center hover:bg-[#E4EEFF] transition-colors">
                <Bell size={16} className="text-[#0A1628]" />
              </button>
              <span className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-white text-[9px] flex items-center justify-center font-bold">3</span>
            </div>
          </div>
        </header>

        {/* Scrollable body */}
        <div className="flex-1 overflow-auto p-6 space-y-6">

          {/* KPIs */}
          <div className="grid grid-cols-2 xl:grid-cols-4 gap-4">
            {kpis.map((k) => (
              <div key={k.label} className="bg-white rounded-2xl p-5 border border-black/5 shadow-sm hover:shadow-md transition-shadow">
                <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ${k.grad} flex items-center justify-center mb-4 shadow-md`}>
                  <k.icon size={20} className="text-white" />
                </div>
                <div className="text-2xl font-extrabold text-[#0A1628]">{k.value}</div>
                <div className="text-xs text-[#6B7280] mt-0.5">{k.sub}</div>
                <div className="flex items-center gap-1 mt-2 text-xs font-semibold text-cyan-600">
                  <ChevronUp size={12} />{k.change}
                </div>
              </div>
            ))}
          </div>

          {/* Agenda + Patient panel */}
          <div className="grid xl:grid-cols-5 gap-6">

            {/* Appointment list */}
            <div className="xl:col-span-3 bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-black/5 flex items-center justify-between">
                <div>
                  <h2 className="font-bold text-[#0A1628]">Agenda del día</h2>
                  <p className="text-xs text-[#6B7280]">{dpmsAppointments.length} citas · 3 consultorios</p>
                </div>
                {/* Status legend */}
                <div className="flex gap-3">
                  {[
                    { label: `${doneCount} atendidos`,   dot: "bg-emerald-500" },
                    { label: `${waitingCount} en espera`,dot: "bg-amber-400" },
                    { label: `${scheduledCount} prog.`,  dot: "bg-[#cbd5e1]" },
                  ].map(l => (
                    <div key={l.label} className="flex items-center gap-1.5 text-xs text-[#6B7280]">
                      <span className={`w-2 h-2 rounded-full ${l.dot}`} />
                      {l.label}
                    </div>
                  ))}
                </div>
              </div>

              {/* Chair tabs */}
              <div className="flex border-b border-black/5">
                {[["Todos","all"],["Consultorio 1","1"],["Consultorio 2","2"],["Consultorio 3","3"]].map(([label, val]) => (
                  <button key={val as string}
                    className="flex-1 py-2.5 text-xs font-semibold text-[#6B7280] hover:text-cyan-600 hover:bg-cyan-50 transition-colors border-r border-black/5 last:border-r-0">
                    {label}
                  </button>
                ))}
              </div>

              <div className="divide-y divide-black/5 max-h-[520px] overflow-y-auto">
                {dpmsAppointments.map((appt, idx) => {
                  const cfg = apptStatusCfg[appt.status];
                  const isSelected = selectedPatient?.patient === appt.patient;
                  return (
                    <button key={idx} onClick={() => setSelectedPatient(isSelected ? null : appt)}
                      className={`w-full flex items-start gap-4 px-5 py-4 text-left transition-all duration-200 ${
                        isSelected ? "bg-cyan-50 border-l-4 border-cyan-500" : "hover:bg-[#F8FAFC] border-l-4 border-transparent"
                      }`}>
                      {/* Time */}
                      <div className="w-14 shrink-0 text-center">
                        <div className="font-extrabold text-[#0A1628] text-sm">{appt.time}</div>
                        <div className="text-[10px] text-[#6B7280]">{appt.duration}min</div>
                      </div>

                      {/* Avatar */}
                      <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${appt.color} flex items-center justify-center shrink-0 shadow-sm`}>
                        <span className="text-white font-bold text-xs">{appt.avatar}</span>
                      </div>

                      {/* Info */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-2">
                          <span className="font-semibold text-[#0A1628] text-sm truncate">{appt.patient}</span>
                          <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0 inline-flex items-center gap-1 ${cfg.bg} ${cfg.text}`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${cfg.dot}`} />{cfg.label}
                          </span>
                        </div>
                        <div className="text-xs text-[#6B7280] mt-0.5">{appt.treatment}</div>
                        <div className="flex items-center gap-2 mt-1 text-[10px] text-[#6B7280]">
                          <span className="bg-[#F0F6FF] px-2 py-0.5 rounded-full font-medium">Consultorio {appt.chair}</span>
                          <span>·</span>
                          <span>{appt.age} años</span>
                        </div>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Patient detail panel */}
            <div className="xl:col-span-2 space-y-4">
              {selectedPatient ? (
                <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
                  {/* Patient header */}
                  <div className="bg-gradient-to-r from-cyan-500 to-teal-500 px-5 py-5">
                    <div className="flex items-start justify-between">
                      <div className="flex items-center gap-3">
                        <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${selectedPatient.color} flex items-center justify-center shadow-lg`}>
                          <span className="text-white font-extrabold">{selectedPatient.avatar}</span>
                        </div>
                        <div>
                          <div className="font-extrabold text-white text-lg leading-tight">{selectedPatient.patient}</div>
                          <div className="text-cyan-100 text-xs">{selectedPatient.age} años · Consultorio {selectedPatient.chair}</div>
                        </div>
                      </div>
                      <button onClick={() => setSelectedPatient(null)}
                        className="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center hover:bg-white/30 transition-colors">
                        <X size={14} className="text-white" />
                      </button>
                    </div>
                    <div className="mt-4 grid grid-cols-3 gap-2">
                      {[
                        { label: "Cita",      value: selectedPatient.time },
                        { label: "Duración",  value: `${selectedPatient.duration}min` },
                        { label: "Estado",    value: apptStatusCfg[selectedPatient.status].label },
                      ].map(s => (
                        <div key={s.label} className="bg-white/15 rounded-xl p-2.5 text-center">
                          <div className="text-cyan-100 text-[10px]">{s.label}</div>
                          <div className="text-white font-bold text-xs mt-0.5">{s.value}</div>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Treatment badge */}
                  <div className="px-5 py-3 bg-cyan-50 border-b border-cyan-100 flex items-center gap-2">
                    <Stethoscope size={14} className="text-cyan-600" />
                    <span className="text-sm font-semibold text-cyan-800">{selectedPatient.treatment}</span>
                  </div>

                  {/* Tabs */}
                  <div className="flex border-b border-black/5">
                    {(["odontograma","historia","plan"] as const).map(tab => (
                      <button key={tab} onClick={() => setActiveTab(tab)}
                        className={`flex-1 py-3 text-xs font-semibold capitalize transition-colors ${
                          activeTab === tab ? "text-cyan-600 border-b-2 border-cyan-500 bg-cyan-50" : "text-[#6B7280] hover:text-[#0A1628]"
                        }`}>
                        {tab === "odontograma" ? "Odontograma" : tab === "historia" ? "Historia" : "Plan trat."}
                      </button>
                    ))}
                  </div>

                  <div className="p-5">
                    {activeTab === "odontograma" && (
                      <div>
                        <p className="text-xs text-[#6B7280] mb-4 text-center">Haz clic en un diente para ver detalles</p>
                        {/* Upper arch */}
                        <div className="mb-1 text-[10px] text-[#6B7280] text-center font-semibold">SUPERIOR</div>
                        <div className="flex justify-center gap-1 mb-3">
                          {upperTeeth.map(n => {
                            const st = toothStatus[n];
                            return (
                              <div key={n} title={st ? `${n}: ${st.label}` : `Diente ${n}`}
                                className="flex flex-col items-center gap-0.5 cursor-pointer group">
                                <div className={`w-6 h-7 rounded-t-full border-2 transition-all duration-150 group-hover:scale-110 ${
                                  st ? "border-transparent" : "border-[#cbd5e1]"
                                }`}
                                  style={{ background: st ? st.color : "#f1f5f9" }} />
                                <span className="text-[8px] text-[#94a3b8] group-hover:text-[#0A1628]">{n}</span>
                              </div>
                            );
                          })}
                        </div>
                        {/* Divider */}
                        <div className="border-t-2 border-dashed border-[#e2e8f0] my-2 mx-4" />
                        {/* Lower arch */}
                        <div className="flex justify-center gap-1 mt-3 mb-1">
                          {lowerTeeth.map(n => {
                            const st = toothStatus[n];
                            return (
                              <div key={n} title={st ? `${n}: ${st.label}` : `Diente ${n}`}
                                className="flex flex-col items-center gap-0.5 cursor-pointer group">
                                <span className="text-[8px] text-[#94a3b8] group-hover:text-[#0A1628]">{n}</span>
                                <div className={`w-6 h-7 rounded-b-full border-2 transition-all duration-150 group-hover:scale-110 ${
                                  st ? "border-transparent" : "border-[#cbd5e1]"
                                }`}
                                  style={{ background: st ? st.color : "#f1f5f9" }} />
                              </div>
                            );
                          })}
                        </div>
                        <div className="mb-1 text-[10px] text-[#6B7280] text-center font-semibold">INFERIOR</div>
                        {/* Legend */}
                        <div className="flex flex-wrap gap-2 mt-4 justify-center">
                          {[
                            { color:"#ef4444", label:"Extracción" },
                            { color:"#f59e0b", label:"Corona" },
                            { color:"#8b5cf6", label:"Endodoncia" },
                            { color:"#3b82f6", label:"Resina" },
                            { color:"#10b981", label:"Sellante" },
                          ].map(l => (
                            <div key={l.label} className="flex items-center gap-1.5 text-[10px] text-[#6B7280]">
                              <div className="w-3 h-3 rounded-sm" style={{ background: l.color }} />
                              {l.label}
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {activeTab === "historia" && (
                      <div className="space-y-3">
                        {[
                          { date: "15 Jun 2025", note: "Extracción molar #16 bajo anestesia local. Sin complicaciones. Se prescribe amoxicilina 500mg.", doctor: "Dr. Ramírez" },
                          { date: "01 Jun 2025", note: "Radiografía panorámica. Se detecta caries en #16. Se agenda extracción.", doctor: "Dr. Ramírez" },
                          { date: "10 May 2025", note: "Limpieza dental de rutina. Instrucción de higiene oral. Próxima revisión en 6 meses.", doctor: "Dra. López" },
                        ].map((e, i) => (
                          <div key={i} className="border-l-2 border-cyan-200 pl-4">
                            <div className="text-[10px] text-cyan-600 font-semibold">{e.date} · {e.doctor}</div>
                            <div className="text-xs text-[#0A1628] mt-0.5 leading-relaxed">{e.note}</div>
                          </div>
                        ))}
                      </div>
                    )}

                    {activeTab === "plan" && (
                      <div className="space-y-2">
                        {[
                          { step: 1, tx: "Extracción molar #16",          cost: "$2,500", status: "done"    },
                          { step: 2, tx: "Implante zona #16 (fase 1)",     cost: "$14,000",status: "pending" },
                          { step: 3, tx: "Implante zona #16 (fase 2)",     cost: "$6,000", status: "pending" },
                          { step: 4, tx: "Corona sobre implante",          cost: "$8,500", status: "pending" },
                          { step: 5, tx: "Revisión final y fotografías",   cost: "$800",   status: "pending" },
                        ].map(item => (
                          <div key={item.step} className="flex items-center gap-3 p-3 rounded-xl bg-[#F8FAFC] border border-black/5">
                            <div className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 ${
                              item.status === "done" ? "bg-emerald-500 text-white" : "bg-[#e2e8f0] text-[#94a3b8]"
                            }`}>{item.status === "done" ? "✓" : item.step}</div>
                            <div className="flex-1 min-w-0">
                              <div className="text-xs font-semibold text-[#0A1628] truncate">{item.tx}</div>
                            </div>
                            <div className="text-xs font-bold text-cyan-600 shrink-0">{item.cost}</div>
                          </div>
                        ))}
                        <div className="flex justify-between pt-2 border-t border-black/5">
                          <span className="text-xs font-semibold text-[#6B7280]">Total del plan</span>
                          <span className="text-sm font-extrabold text-[#0A1628]">$31,800 MXN</span>
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="px-5 pb-5 grid grid-cols-2 gap-2">
                    <button className="py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 text-white text-xs font-semibold shadow-md shadow-cyan-200 hover:shadow-lg transition-all">
                      Iniciar consulta
                    </button>
                    <button className="py-2.5 rounded-xl bg-[#F0F6FF] text-cyan-700 text-xs font-semibold hover:bg-[#E4EEFF] transition-colors border border-cyan-200">
                      Ver expediente completo
                    </button>
                  </div>
                </div>
              ) : (
                /* Waiting room */
                <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
                  <div className="px-5 py-4 border-b border-black/5">
                    <h3 className="font-bold text-[#0A1628] text-sm">Sala de espera</h3>
                    <p className="text-xs text-[#6B7280]">{waitingCount} pacientes esperando · haz clic en una cita para ver detalles</p>
                  </div>
                  <div className="divide-y divide-black/5">
                    {dpmsAppointments.filter(a => a.status === "waiting" || a.status === "in-chair").map((appt, i) => (
                      <button key={i} onClick={() => setSelectedPatient(appt)}
                        className="w-full flex items-center gap-4 px-5 py-4 hover:bg-[#F8FAFC] transition-colors text-left">
                        <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${appt.color} flex items-center justify-center shrink-0 shadow-sm`}>
                          <span className="text-white font-bold text-xs">{appt.avatar}</span>
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="font-semibold text-[#0A1628] text-sm">{appt.patient}</div>
                          <div className="text-xs text-[#6B7280] truncate">{appt.treatment}</div>
                        </div>
                        <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full ${apptStatusCfg[appt.status].bg} ${apptStatusCfg[appt.status].text}`}>
                          {apptStatusCfg[appt.status].label}
                        </span>
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Quick stats */}
              <div className="bg-white rounded-2xl border border-black/5 shadow-sm p-5">
                <h4 className="font-bold text-[#0A1628] text-sm mb-4">Consultorios activos</h4>
                <div className="space-y-3">
                  {[
                    { chair: "Consultorio 1", doctor: "Dr. Ramírez",  appts: 4, current: "Diana Torres — Extracción" },
                    { chair: "Consultorio 2", doctor: "Dra. López",   appts: 3, current: "Disponible" },
                    { chair: "Consultorio 3", doctor: "Dr. Gutiérrez",appts: 3, current: "Disponible" },
                  ].map(c => (
                    <div key={c.chair} className="flex items-center gap-3 p-3 bg-[#F0F6FF] rounded-xl">
                      <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center shrink-0">
                        <Smile size={14} className="text-white" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="text-xs font-bold text-[#0A1628]">{c.chair} · {c.doctor}</div>
                        <div className="text-[10px] text-[#6B7280] truncate">{c.current}</div>
                      </div>
                      <span className="text-[10px] font-bold bg-white text-cyan-600 px-2 py-1 rounded-full border border-cyan-200">{c.appts} citas</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Charts row */}
          <div className="grid xl:grid-cols-3 gap-6">
            {/* Revenue stacked */}
            <div className="xl:col-span-2 bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
              <div className="flex items-center justify-between mb-5">
                <div>
                  <h3 className="font-bold text-[#0A1628]">Ingresos por tipo de tratamiento</h3>
                  <p className="text-xs text-[#6B7280]">Últimos 7 días · MXN</p>
                </div>
                <div className="flex flex-wrap gap-3 text-xs">
                  {[["Implantes","#0F9D6E"],["Ortodoncia","#34D399"],["Restauración","#10B981"],["Limpieza","#6EE7B7"]].map(([l,c]) => (
                    <div key={l as string} className="flex items-center gap-1.5">
                      <div className="w-2.5 h-2.5 rounded-full" style={{ background: c as string }} />
                      <span className="text-[#6B7280]">{l}</span>
                    </div>
                  ))}
                </div>
              </div>
              <ResponsiveContainer width="100%" height={200}>
                <BarChart data={dpmsRevenueData.map(d => ({ dia: d.dia, total: d.implantos + d.ortodoncia + d.restauracion + d.limpieza }))} barSize={28}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
                  <XAxis dataKey="dia" tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} tickFormatter={v => `$${(v/1000).toFixed(0)}k`} />
                  <Tooltip formatter={(v: number) => [`$${v.toLocaleString()}`, "Total"]} contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 12 }} />
                  <Bar dataKey="total" name="Total" radius={[6,6,0,0]}>
                    {dpmsRevenueData.map((_, i) => <Cell key={`bar-${i}`} fill={i === dpmsRevenueData.length - 1 ? "#0F9D6E" : "#d1fae5"} />)}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>

            {/* Pie + trend */}
            <div className="bg-white rounded-2xl p-6 border border-black/5 shadow-sm flex flex-col">
              <h3 className="font-bold text-[#0A1628] mb-1">Mix de tratamientos</h3>
              <p className="text-xs text-[#6B7280] mb-3">Participación en ingresos</p>
              <ResponsiveContainer width="100%" height={160}>
                <RPieChart>
                  <Pie data={dpmsTreatmentMix} cx="50%" cy="50%" innerRadius={45} outerRadius={72} dataKey="value" paddingAngle={3}>
                    {dpmsTreatmentMix.map((entry) => <Cell key={entry.name} fill={entry.color} />)}
                  </Pie>
                  <Tooltip formatter={(v: number) => `${v}%`} contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 12 }} />
                </RPieChart>
              </ResponsiveContainer>
              <div className="space-y-2 mt-2">
                {dpmsTreatmentMix.map(d => (
                  <div key={d.name} className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-2">
                      <div className="w-2.5 h-2.5 rounded-full" style={{ background: d.color }} />
                      <span className="text-[#6B7280]">{d.name}</span>
                    </div>
                    <span className="font-bold text-[#0A1628]">{d.value}%</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Patient trend + pending balance */}
          <div className="grid xl:grid-cols-3 gap-6">
            {/* Patient trend */}
            <div className="xl:col-span-2 bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
              <div className="flex items-center justify-between mb-5">
                <div>
                  <h3 className="font-bold text-[#0A1628]">Flujo de pacientes</h3>
                  <p className="text-xs text-[#6B7280]">Nuevos vs recurrentes · últimos 6 meses</p>
                </div>
                <div className="flex gap-4 text-xs">
                  {[["Nuevos","#06b6d4"],["Recurrentes","#0F9D6E"]].map(([l,c]) => (
                    <div key={l as string} className="flex items-center gap-1.5">
                      <div className="w-2.5 h-2.5 rounded-full" style={{ background: c as string }} />
                      <span className="text-[#6B7280]">{l}</span>
                    </div>
                  ))}
                </div>
              </div>
              <ResponsiveContainer width="100%" height={180}>
                <AreaChart data={dpmsPatientTrend}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="mes" tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                  <Tooltip contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 12 }} />
                  <Area type="monotone" dataKey="nuevos"      stroke="#06b6d4" strokeWidth={2} fill="#06b6d4" fillOpacity={0.12} name="Nuevos" />
                  <Area type="monotone" dataKey="recurrentes" stroke="#0F9D6E" strokeWidth={2} fill="#0F9D6E" fillOpacity={0.12} name="Recurrentes" />
                </AreaChart>
              </ResponsiveContainer>
            </div>

            {/* Pending balances */}
            <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-black/5 flex items-center justify-between">
                <div>
                  <h3 className="font-bold text-[#0A1628] text-sm">Saldos pendientes</h3>
                  <p className="text-xs text-[#6B7280]">Cobros por realizar</p>
                </div>
                <span className="text-xs font-extrabold text-red-600 bg-red-50 px-3 py-1 rounded-full">$16,100 MXN</span>
              </div>
              <div className="divide-y divide-black/5">
                {dpmsPatients.filter(p => p.status === "pendiente").map((p, i) => (
                  <div key={i} className="px-5 py-3.5 flex items-center justify-between hover:bg-[#F8FAFC] transition-colors">
                    <div>
                      <div className="font-semibold text-[#0A1628] text-sm">{p.name}</div>
                      <div className="text-[10px] text-[#6B7280]">{p.treatment}</div>
                    </div>
                    <div className="text-right">
                      <div className="font-extrabold text-red-600 text-sm">{p.balance}</div>
                      <button className="text-[10px] text-cyan-600 font-semibold hover:underline">Cobrar</button>
                    </div>
                  </div>
                ))}
              </div>
              <div className="px-5 py-3 bg-[#F0F6FF] border-t border-black/5">
                <button className="w-full py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 text-white text-xs font-semibold shadow-sm hover:shadow-md transition-all">
                  Generar CFDI 4.0
                </button>
              </div>
            </div>
          </div>

          {/* Recent patients table */}
          <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-black/5 flex items-center justify-between">
              <div>
                <h3 className="font-bold text-[#0A1628]">Historial de pacientes recientes</h3>
                <p className="text-xs text-[#6B7280]">Últimas visitas y próximas citas</p>
              </div>
              <div className="flex gap-2">
                <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#F0F6FF] text-xs font-medium text-[#0A1628] hover:bg-[#E4EEFF] transition-colors">
                  <Filter size={12} />Filtrar
                </button>
                <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gradient-to-r from-cyan-500 to-teal-500 text-white text-xs font-semibold shadow-sm">
                  <Download size={12} />Exportar
                </button>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[700px] text-sm">
                <thead>
                  <tr className="border-b border-black/5 text-[#6B7280] text-xs bg-[#F8FAFC]">
                    <th className="text-left p-4 font-semibold">Paciente</th>
                    <th className="text-left p-4 font-semibold">Última visita</th>
                    <th className="text-left p-4 font-semibold">Próxima cita</th>
                    <th className="text-left p-4 font-semibold">Tratamiento</th>
                    <th className="text-right p-4 font-semibold">Saldo</th>
                    <th className="text-center p-4 font-semibold">Estado</th>
                    <th className="text-center p-4 font-semibold">Acción</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-black/5">
                  {dpmsPatients.map((p, i) => (
                    <tr key={i} className="hover:bg-[#F8FAFC] transition-colors">
                      <td className="p-4">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-cyan-400 to-teal-500 flex items-center justify-center shrink-0">
                            <span className="text-white font-bold text-[10px]">{p.name.split(" ").map(n=>n[0]).join("").slice(0,2)}</span>
                          </div>
                          <span className="font-semibold text-[#0A1628]">{p.name}</span>
                        </div>
                      </td>
                      <td className="p-4 text-xs text-[#6B7280]">{p.lastVisit}</td>
                      <td className="p-4 text-xs text-[#0A1628] font-medium">{p.nextVisit}</td>
                      <td className="p-4 text-xs text-[#6B7280]">{p.treatment}</td>
                      <td className="p-4 text-right font-bold text-sm">
                        <span className={p.balance === "$0" ? "text-emerald-600" : "text-red-600"}>{p.balance}</span>
                      </td>
                      <td className="p-4 text-center">
                        <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full ${
                          p.status === "al corriente" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"
                        }`}>
                          {p.status === "al corriente" ? "Al corriente" : "Saldo pendiente"}
                        </span>
                      </td>
                      <td className="p-4 text-center">
                        <button className="text-xs font-semibold text-cyan-600 hover:underline">Ver expediente</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
