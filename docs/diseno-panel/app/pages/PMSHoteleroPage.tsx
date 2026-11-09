import { useState } from "react";
import {
  BedDouble, CalendarCheck, Users, CreditCard, BarChart3, ClipboardList,
  Settings, LogOut, Bell, Clock, Filter, Download, ChevronUp, TrendingUp,
  DollarSign, X, User, UserPlus, LayoutDashboard
} from "lucide-react";
import {
  BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid,
  Tooltip, ResponsiveContainer, Cell
} from "recharts";
import type { Page } from "../types";

const pmsRooms = [
  // Piso 1
  { num: "101", type: "Estándar",  status: "occupied",   guest: "Carlos Vega",       nights: 2, checkout: "17 Jun" },
  { num: "102", type: "Estándar",  status: "available",  guest: "",                  nights: 0, checkout: "" },
  { num: "103", type: "Estándar",  status: "cleaning",   guest: "",                  nights: 0, checkout: "" },
  { num: "104", type: "Doble",     status: "occupied",   guest: "Ana Flores",        nights: 4, checkout: "19 Jun" },
  { num: "105", type: "Doble",     status: "available",  guest: "",                  nights: 0, checkout: "" },
  { num: "106", type: "Doble",     status: "reserved",   guest: "Pedro Salinas",     nights: 3, checkout: "20 Jun" },
  // Piso 2
  { num: "201", type: "Suite",     status: "occupied",   guest: "María Rodríguez",   nights: 1, checkout: "16 Jun" },
  { num: "202", type: "Suite",     status: "available",  guest: "",                  nights: 0, checkout: "" },
  { num: "203", type: "Estándar",  status: "maintenance",guest: "",                  nights: 0, checkout: "" },
  { num: "204", type: "Estándar",  status: "occupied",   guest: "Luis Torres",       nights: 6, checkout: "22 Jun" },
  { num: "205", type: "Doble",     status: "cleaning",   guest: "",                  nights: 0, checkout: "" },
  { num: "206", type: "Doble",     status: "reserved",   guest: "Sofía Mendez",      nights: 2, checkout: "18 Jun" },
  // Piso 3
  { num: "301", type: "Junior Suite", status: "occupied",  guest: "Roberto Díaz",    nights: 3, checkout: "19 Jun" },
  { num: "302", type: "Junior Suite", status: "available", guest: "",                nights: 0, checkout: "" },
  { num: "303", type: "Estándar",     status: "occupied",  guest: "Lucía Herrera",   nights: 2, checkout: "17 Jun" },
  { num: "304", type: "Estándar",     status: "reserved",  guest: "Emilio Garza",    nights: 5, checkout: "21 Jun" },
  { num: "305", type: "Suite",        status: "available", guest: "",                nights: 0, checkout: "" },
  { num: "306", type: "Suite",        status: "occupied",  guest: "Valentina Cruz",  nights: 7, checkout: "23 Jun" },
];

const pmsArrivals = [
  { guest: "Pedro Salinas",  room: "106", type: "Doble",    time: "14:00", nights: 3, pax: 2, status: "confirmed" },
  { guest: "Sofía Mendez",   room: "206", type: "Doble",    time: "15:30", nights: 2, pax: 1, status: "confirmed" },
  { guest: "Emilio Garza",   room: "304", type: "Estándar", time: "16:00", nights: 5, pax: 2, status: "pending"   },
  { guest: "Karla Ibáñez",   room: "102", type: "Estándar", time: "18:00", nights: 1, pax: 1, status: "confirmed" },
];

const pmsDepartures = [
  { guest: "María Rodríguez", room: "201", type: "Suite",    time: "11:00", paid: true  },
  { guest: "Carlos Vega",     room: "101", type: "Estándar", time: "12:00", paid: false },
  { guest: "Lucía Herrera",   room: "303", type: "Estándar", time: "12:00", paid: true  },
];

const pmsRevenueData = [
  { dia: "Lun", hab: 18400, alimentos: 4200, spa: 1800 },
  { dia: "Mar", hab: 21000, alimentos: 5100, spa: 2200 },
  { dia: "Mié", hab: 16800, alimentos: 3900, spa: 1500 },
  { dia: "Jue", hab: 24500, alimentos: 6300, spa: 3100 },
  { dia: "Vie", hab: 31200, alimentos: 8700, spa: 4200 },
  { dia: "Sáb", hab: 38600, alimentos: 9400, spa: 5100 },
  { dia: "Hoy", hab: 29400, alimentos: 7200, spa: 3800 },
];

const pmsOccupancyData = [
  { mes: "Ene", occ: 62 }, { mes: "Feb", occ: 71 }, { mes: "Mar", occ: 78 },
  { mes: "Abr", occ: 74 }, { mes: "May", occ: 83 }, { mes: "Jun", occ: 88 },
];

const statusConfig: Record<string, { label: string; bg: string; dot: string; text: string }> = {
  occupied:    { label: "Ocupada",     bg: "bg-blue-50",   dot: "bg-blue-500",   text: "text-blue-700"   },
  available:   { label: "Disponible",  bg: "bg-emerald-50",dot: "bg-emerald-500",text: "text-emerald-700" },
  cleaning:    { label: "Limpieza",    bg: "bg-amber-50",  dot: "bg-amber-400",  text: "text-amber-700"  },
  maintenance: { label: "Mantenim.",   bg: "bg-red-50",    dot: "bg-red-400",    text: "text-red-700"    },
  reserved:    { label: "Reservada",   bg: "bg-purple-50", dot: "bg-purple-500", text: "text-purple-700" },
};

export default function PMSHoteleroPage({ setPage }: { setPage: (p: Page) => void }) {
  const [activeSection, setActiveSection] = useState("dashboard");
  const [filterStatus, setFilterStatus] = useState("all");
  const [selectedRoom, setSelectedRoom] = useState<typeof pmsRooms[0] | null>(null);

  const today = "Domingo, 15 de junio de 2025";
  const totalRooms = pmsRooms.length;
  const occupied  = pmsRooms.filter(r => r.status === "occupied").length;
  const available = pmsRooms.filter(r => r.status === "available").length;
  const cleaning  = pmsRooms.filter(r => r.status === "cleaning").length;
  const reserved  = pmsRooms.filter(r => r.status === "reserved").length;
  const occRate   = Math.round((occupied / totalRooms) * 100);

  const kpis = [
    { label: "Ocupación",        value: `${occRate}%`,     sub: `${occupied} de ${totalRooms} hab.`,  icon: BedDouble,     grad: "from-blue-500 to-blue-600",     change: "+5% vs ayer" },
    { label: "Ingresos del día", value: "$40,400",         sub: "MXN (hab. + servicios)",             icon: DollarSign,    grad: "from-emerald-500 to-emerald-600",change: "+12% vs ayer" },
    { label: "RevPAR",           value: "$2,244",          sub: "Revenue per available room",         icon: TrendingUp,    grad: "from-teal-500 to-teal-600",      change: "+8% vs semana" },
    { label: "Llegadas hoy",     value: `${pmsArrivals.length}`,sub: "huéspedes por llegar",          icon: CalendarCheck, grad: "from-purple-500 to-purple-600",  change: `${pmsDepartures.length} salidas` },
  ];

  const sideNav = [
    { id: "dashboard",   icon: LayoutDashboard, label: "Dashboard"       },
    { id: "habitaciones",icon: BedDouble,        label: "Habitaciones"    },
    { id: "reservas",    icon: CalendarCheck,    label: "Reservas"        },
    { id: "huespedes",   icon: Users,            label: "Huéspedes"       },
    { id: "facturacion", icon: CreditCard,       label: "Facturación"     },
    { id: "reportes",    icon: BarChart3,        label: "Reportes"        },
    { id: "inventario",  icon: ClipboardList,    label: "Inventario"      },
    { id: "settings",    icon: Settings,         label: "Configuración"   },
  ];

  const filteredRooms = filterStatus === "all"
    ? pmsRooms
    : pmsRooms.filter(r => r.status === filterStatus);

  return (
    <div className="min-h-screen bg-[#F0F4F8] flex font-[Manrope,sans-serif]">

      {/* ── Sidebar ── */}
      <aside className="w-64 bg-[#0F172A] shrink-0 flex flex-col hidden lg:flex">
        {/* Hotel brand */}
        <div className="px-5 py-6 border-b border-white/10">
          <div className="flex items-center gap-3 mb-1">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center shadow-lg shadow-emerald-900/40">
              <BedDouble size={20} className="text-white" />
            </div>
            <div>
              <div className="font-extrabold text-white text-base leading-tight">PMS Hotelero</div>
              <div className="text-[10px] text-white/40 leading-tight">by RankPro Solutions</div>
            </div>
          </div>
          <div className="mt-3 px-3 py-2 bg-white/5 rounded-xl border border-white/10">
            <div className="text-xs font-semibold text-white/80">Hotel Boutique Arrecife</div>
            <div className="text-[10px] text-white/40">Cancún, Q. Roo · 18 habitaciones</div>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 p-3 space-y-0.5">
          {sideNav.map((item) => (
            <button key={item.id} onClick={() => setActiveSection(item.id)}
              className={`w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ${
                activeSection === item.id
                  ? "bg-[#0F9D6E] text-white shadow-md shadow-emerald-900/30"
                  : "text-white/50 hover:text-white hover:bg-white/8"
              }`}>
              <item.icon size={17} />
              {item.label}
              {item.id === "reservas" && (
                <span className="ml-auto bg-purple-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">4</span>
              )}
            </button>
          ))}
        </nav>

        {/* User */}
        <div className="p-4 border-t border-white/10">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-teal-500 flex items-center justify-center shrink-0">
              <span className="text-white font-bold text-sm">RC</span>
            </div>
            <div className="min-w-0">
              <div className="text-white text-sm font-semibold truncate">Recepción Central</div>
              <div className="text-white/40 text-xs">Turno matutino · 06:00–14:00</div>
            </div>
          </div>
          <button onClick={() => setPage("productos")}
            className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-white/40 hover:text-white hover:bg-white/8 transition-colors text-xs">
            <LogOut size={14} />Salir del sistema
          </button>
        </div>
      </aside>

      {/* ── Main content ── */}
      <div className="flex-1 flex flex-col overflow-hidden">

        {/* Top bar */}
        <header className="bg-white border-b border-black/5 px-6 py-3.5 flex items-center justify-between shrink-0">
          <div>
            <h1 className="text-lg font-extrabold text-[#0F172A]">
              {sideNav.find(n => n.id === activeSection)?.label ?? "Dashboard"}
            </h1>
            <p className="text-xs text-[#6B7280]">{today}</p>
          </div>
          <div className="flex items-center gap-3">
            {/* Quick actions */}
            <button className="flex items-center gap-2 px-4 py-2 rounded-xl bg-[#F0F4F8] border border-black/8 text-sm font-medium text-[#0F172A] hover:bg-[#E8EDF2] transition-colors">
              <CalendarCheck size={15} className="text-[#0F9D6E]" />Nueva reserva
            </button>
            <button className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-sm font-semibold shadow-md shadow-emerald-200 hover:shadow-lg transition-all">
              <UserPlus size={15} />Check-in
            </button>
            <div className="relative">
              <button className="w-9 h-9 rounded-xl bg-[#F0F4F8] border border-black/8 flex items-center justify-center hover:bg-[#E8EDF2] transition-colors">
                <Bell size={16} className="text-[#0F172A]" />
              </button>
              <span className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-white text-[9px] flex items-center justify-center font-bold">2</span>
            </div>
          </div>
        </header>

        {/* Scrollable body */}
        <div className="flex-1 overflow-auto p-6 space-y-6">

          {/* ── KPI Row ── */}
          <div className="grid grid-cols-2 xl:grid-cols-4 gap-4">
            {kpis.map((k) => (
              <div key={k.label} className="bg-white rounded-2xl p-5 border border-black/5 shadow-sm hover:shadow-md transition-shadow">
                <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ${k.grad} flex items-center justify-center mb-4 shadow-md`}>
                  <k.icon size={20} className="text-white" />
                </div>
                <div className="text-2xl font-extrabold text-[#0F172A]">{k.value}</div>
                <div className="text-xs text-[#6B7280] mt-0.5">{k.sub}</div>
                <div className="flex items-center gap-1 mt-2 text-xs font-semibold text-emerald-600">
                  <ChevronUp size={12} />{k.change}
                </div>
              </div>
            ))}
          </div>

          {/* ── Occupancy legend pills ── */}
          <div className="flex flex-wrap gap-2">
            {[
              { status: "all",         label: `Todas (${totalRooms})`,    color: "bg-[#0F172A] text-white" },
              { status: "occupied",    label: `Ocupadas (${occupied})`,   color: "bg-blue-100 text-blue-700" },
              { status: "available",   label: `Disponibles (${available})`,color: "bg-emerald-100 text-emerald-700" },
              { status: "cleaning",    label: `Limpieza (${cleaning})`,   color: "bg-amber-100 text-amber-700" },
              { status: "reserved",    label: `Reservadas (${reserved})`, color: "bg-purple-100 text-purple-700" },
              { status: "maintenance", label: "Mantenimiento (1)",        color: "bg-red-100 text-red-700" },
            ].map((f) => (
              <button key={f.status} onClick={() => setFilterStatus(f.status)}
                className={`px-4 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 ${
                  filterStatus === f.status ? f.color + " shadow-md" : "bg-white text-[#6B7280] border border-black/8 hover:border-[#0F9D6E]"
                }`}>
                {f.label}
              </button>
            ))}
          </div>

          {/* ── Room Grid + Side panel ── */}
          <div className="grid xl:grid-cols-3 gap-6">

            {/* Room grid */}
            <div className="xl:col-span-2 bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-black/5 flex items-center justify-between">
                <h2 className="font-bold text-[#0F172A]">Mapa de Habitaciones</h2>
                <span className="text-xs text-[#6B7280]">{filteredRooms.length} habitaciones</span>
              </div>

              {/* Floor groups */}
              {["1", "2", "3"].map((floor) => {
                const floorRooms = filteredRooms.filter(r => r.num.startsWith(floor));
                if (floorRooms.length === 0) return null;
                return (
                  <div key={floor} className="p-5 border-b border-black/5 last:border-b-0">
                    <div className="text-xs font-extrabold text-[#6B7280] uppercase tracking-widest mb-3">
                      Piso {floor}
                    </div>
                    <div className="grid grid-cols-3 sm:grid-cols-6 gap-3">
                      {floorRooms.map((room) => {
                        const cfg = statusConfig[room.status];
                        const isSelected = selectedRoom?.num === room.num;
                        return (
                          <button key={room.num} onClick={() => setSelectedRoom(isSelected ? null : room)}
                            className={`group rounded-xl p-3 border-2 transition-all duration-200 text-left ${
                              isSelected
                                ? "border-[#0F9D6E] bg-emerald-50 shadow-md shadow-emerald-100"
                                : `${cfg.bg} border-transparent hover:border-[#0F9D6E]/40 hover:shadow-sm`
                            }`}>
                            <div className="flex items-center justify-between mb-2">
                              <span className="font-extrabold text-[#0F172A] text-sm">{room.num}</span>
                              <span className={`w-2.5 h-2.5 rounded-full ${cfg.dot}`} />
                            </div>
                            <div className={`text-[10px] font-semibold ${cfg.text}`}>{cfg.label}</div>
                            <div className="text-[10px] text-[#6B7280] mt-0.5 truncate">{room.type}</div>
                            {room.guest && (
                              <div className="text-[9px] text-[#0F172A] font-medium mt-1 truncate">{room.guest}</div>
                            )}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Side panel: selected room OR arrivals/departures */}
            <div className="space-y-4">
              {selectedRoom ? (
                /* Room detail card */
                <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
                  <div className={`h-2 ${
                    selectedRoom.status === "occupied"    ? "bg-blue-500" :
                    selectedRoom.status === "available"   ? "bg-emerald-500" :
                    selectedRoom.status === "cleaning"    ? "bg-amber-400" :
                    selectedRoom.status === "maintenance" ? "bg-red-400" : "bg-purple-500"
                  }`} />
                  <div className="p-5">
                    <div className="flex items-start justify-between mb-4">
                      <div>
                        <div className="text-2xl font-extrabold text-[#0F172A]">Hab. {selectedRoom.num}</div>
                        <div className="text-sm text-[#6B7280]">{selectedRoom.type}</div>
                      </div>
                      <button onClick={() => setSelectedRoom(null)} className="w-8 h-8 rounded-lg bg-[#F0F4F8] flex items-center justify-center hover:bg-[#E8EDF2] transition-colors">
                        <X size={15} className="text-[#6B7280]" />
                      </button>
                    </div>

                    <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold mb-5 ${statusConfig[selectedRoom.status].bg} ${statusConfig[selectedRoom.status].text}`}>
                      <span className={`w-2 h-2 rounded-full ${statusConfig[selectedRoom.status].dot}`} />
                      {statusConfig[selectedRoom.status].label}
                    </div>

                    {selectedRoom.guest && (
                      <div className="space-y-3 mb-5">
                        <div className="flex items-center gap-3 p-3 bg-[#F0F4F8] rounded-xl">
                          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-teal-500 flex items-center justify-center shrink-0">
                            <User size={16} className="text-white" />
                          </div>
                          <div>
                            <div className="font-semibold text-[#0F172A] text-sm">{selectedRoom.guest}</div>
                            <div className="text-xs text-[#6B7280]">Huésped principal</div>
                          </div>
                        </div>
                        <div className="grid grid-cols-2 gap-2 text-xs">
                          <div className="bg-[#F0F4F8] rounded-xl p-3">
                            <div className="text-[#6B7280]">Noches</div>
                            <div className="font-bold text-[#0F172A] text-base mt-0.5">{selectedRoom.nights}</div>
                          </div>
                          <div className="bg-[#F0F4F8] rounded-xl p-3">
                            <div className="text-[#6B7280]">Check-out</div>
                            <div className="font-bold text-[#0F172A] text-sm mt-0.5">{selectedRoom.checkout}</div>
                          </div>
                        </div>
                      </div>
                    )}

                    <div className="space-y-2">
                      {selectedRoom.status === "occupied" && (
                        <>
                          <button className="w-full py-2.5 rounded-xl bg-red-50 text-red-600 text-xs font-semibold hover:bg-red-100 transition-colors border border-red-200">
                            Registrar Check-out
                          </button>
                          <button className="w-full py-2.5 rounded-xl bg-[#F0F4F8] text-[#0F172A] text-xs font-semibold hover:bg-[#E8EDF2] transition-colors">
                            Ver consumos del huésped
                          </button>
                        </>
                      )}
                      {selectedRoom.status === "available" && (
                        <button className="w-full py-2.5 rounded-xl bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-xs font-semibold shadow-md shadow-emerald-200 hover:shadow-lg transition-all">
                          Asignar / Check-in
                        </button>
                      )}
                      {selectedRoom.status === "cleaning" && (
                        <button className="w-full py-2.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-semibold hover:bg-emerald-100 transition-colors border border-emerald-200">
                          Marcar como lista ✓
                        </button>
                      )}
                      {selectedRoom.status === "reserved" && (
                        <button className="w-full py-2.5 rounded-xl bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-xs font-semibold shadow-md shadow-emerald-200 hover:shadow-lg transition-all">
                          Registrar Check-in
                        </button>
                      )}
                      <button className="w-full py-2.5 rounded-xl bg-[#F0F4F8] text-[#6B7280] text-xs font-semibold hover:bg-[#E8EDF2] transition-colors">
                        Reportar incidencia
                      </button>
                    </div>
                  </div>
                </div>
              ) : (
                /* Default: arrivals */
                <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
                  <div className="px-5 py-4 border-b border-black/5">
                    <h3 className="font-bold text-[#0F172A] text-sm">Llegadas de hoy</h3>
                    <p className="text-xs text-[#6B7280]">{pmsArrivals.length} huéspedes por llegar</p>
                  </div>
                  <div className="divide-y divide-black/5">
                    {pmsArrivals.map((a) => (
                      <div key={a.guest} className="px-5 py-4 hover:bg-[#F8FAFC] transition-colors">
                        <div className="flex items-start justify-between gap-2">
                          <div className="flex items-center gap-3 min-w-0">
                            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shrink-0">
                              <User size={15} className="text-white" />
                            </div>
                            <div className="min-w-0">
                              <div className="font-semibold text-[#0F172A] text-sm truncate">{a.guest}</div>
                              <div className="text-xs text-[#6B7280]">Hab. {a.room} · {a.type} · {a.pax} pax</div>
                            </div>
                          </div>
                          <span className={`text-[10px] font-bold px-2 py-1 rounded-full shrink-0 ${
                            a.status === "confirmed" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"
                          }`}>
                            {a.status === "confirmed" ? "Confirmado" : "Pendiente"}
                          </span>
                        </div>
                        <div className="flex items-center gap-3 mt-2 ml-12 text-xs text-[#6B7280]">
                          <span className="flex items-center gap-1"><Clock size={11} />{a.time}</span>
                          <span>·</span>
                          <span>{a.nights} noche{a.nights > 1 ? "s" : ""}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Departures */}
              <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
                <div className="px-5 py-4 border-b border-black/5">
                  <h3 className="font-bold text-[#0F172A] text-sm">Salidas de hoy</h3>
                  <p className="text-xs text-[#6B7280]">{pmsDepartures.length} check-outs pendientes</p>
                </div>
                <div className="divide-y divide-black/5">
                  {pmsDepartures.map((d) => (
                    <div key={d.guest} className="px-5 py-3.5 flex items-center justify-between hover:bg-[#F8FAFC] transition-colors">
                      <div>
                        <div className="font-semibold text-[#0F172A] text-sm">{d.guest}</div>
                        <div className="text-xs text-[#6B7280]">Hab. {d.room} · {d.time}</div>
                      </div>
                      <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full ${
                        d.paid ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-600"
                      }`}>
                        {d.paid ? "Saldado" : "Pendiente pago"}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* ── Charts row ── */}
          <div className="grid xl:grid-cols-3 gap-6">

            {/* Revenue stacked bar */}
            <div className="xl:col-span-2 bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
              <div className="flex items-center justify-between mb-5">
                <div>
                  <h3 className="font-bold text-[#0F172A]">Ingresos por área</h3>
                  <p className="text-xs text-[#6B7280]">Últimos 7 días · MXN</p>
                </div>
                <div className="flex gap-4 text-xs">
                  {[["Habitaciones","#0F9D6E"],["A&B","#34D399"],["Spa","#6EE7B7"]].map(([l,c]) => (
                    <div key={l as string} className="flex items-center gap-1.5">
                      <div className="w-2.5 h-2.5 rounded-full" style={{ background: c as string }} />
                      <span className="text-[#6B7280]">{l}</span>
                    </div>
                  ))}
                </div>
              </div>
              <ResponsiveContainer width="100%" height={200}>
                <BarChart data={pmsRevenueData.map(d => ({ dia: d.dia, total: d.hab + d.alimentos + d.spa }))} barSize={28}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
                  <XAxis dataKey="dia" tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} tickFormatter={v => `$${(v/1000).toFixed(0)}k`} />
                  <Tooltip formatter={(v: number) => [`$${v.toLocaleString()}`, "Total"]} contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 12 }} />
                  <Bar dataKey="total" name="Total" radius={[6,6,0,0]}>
                    {pmsRevenueData.map((_, i) => <Cell key={`pms-bar-${i}`} fill={i === pmsRevenueData.length - 1 ? "#0F9D6E" : "#d1fae5"} />)}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>

            {/* Occupancy trend */}
            <div className="bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
              <h3 className="font-bold text-[#0F172A] mb-1">Ocupación mensual</h3>
              <p className="text-xs text-[#6B7280] mb-1">Últimos 6 meses (%)</p>
              <div className="text-3xl font-extrabold text-[#0F9D6E] mb-4">{occRate}%<span className="text-sm font-medium text-[#6B7280] ml-1">este mes</span></div>
              <ResponsiveContainer width="100%" height={130}>
                <LineChart data={pmsOccupancyData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="mes" tick={{ fontSize: 10, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                  <YAxis domain={[50, 100]} tick={{ fontSize: 10, fill: "#6B7280" }} axisLine={false} tickLine={false} unit="%" />
                  <Tooltip formatter={(v: number) => `${v}%`} contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 11 }} />
                  <Line type="monotone" dataKey="occ" stroke="#0F9D6E" strokeWidth={2.5} dot={{ fill: "#0F9D6E", r: 4 }} name="Ocupación" />
                </LineChart>
              </ResponsiveContainer>

              {/* Stat pills */}
              <div className="grid grid-cols-2 gap-2 mt-4">
                {[["ADR","$1,800 MXN"],["RevPAR","$1,584"],["GOPPAR","$1,240"],["Estancia prom.","2.8 noches"]].map(([l,v]) => (
                  <div key={l as string} className="bg-[#F0F4F8] rounded-xl p-3">
                    <div className="text-[10px] text-[#6B7280]">{l}</div>
                    <div className="font-bold text-[#0F172A] text-xs mt-0.5">{v}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* ── Housekeeping status ── */}
          <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-black/5 flex items-center justify-between">
              <div>
                <h3 className="font-bold text-[#0F172A]">Housekeeping</h3>
                <p className="text-xs text-[#6B7280]">Estado de limpieza en tiempo real</p>
              </div>
              <button className="text-xs font-semibold text-[#0F9D6E] hover:underline">Ver todas →</button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[600px] text-sm">
                <thead>
                  <tr className="border-b border-black/5 text-[#6B7280] text-xs bg-[#F8FAFC]">
                    <th className="text-left p-4 font-semibold">Habitación</th>
                    <th className="text-left p-4 font-semibold">Tipo</th>
                    <th className="text-left p-4 font-semibold">Estado</th>
                    <th className="text-left p-4 font-semibold">Asignada a</th>
                    <th className="text-left p-4 font-semibold">Prioridad</th>
                    <th className="text-center p-4 font-semibold">Acción</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-black/5">
                  {[
                    { room: "103", type: "Estándar",  status: "cleaning",  staff: "María López",    priority: "Alta",  },
                    { room: "205", type: "Doble",      status: "cleaning",  staff: "Jorge Ruiz",     priority: "Alta",  },
                    { room: "101", type: "Estándar",  status: "reserved",  staff: "Sin asignar",    priority: "Media", },
                    { room: "201", type: "Suite",      status: "occupied",  staff: "Sin asignar",    priority: "Baja",  },
                    { room: "203", type: "Estándar",  status: "maintenance",staff: "Técnico Gómez",  priority: "Urgente"},
                  ].map((row) => (
                    <tr key={row.room} className="hover:bg-[#F8FAFC] transition-colors">
                      <td className="p-4 font-bold text-[#0F172A]">Hab. {row.room}</td>
                      <td className="p-4 text-[#6B7280]">{row.type}</td>
                      <td className="p-4">
                        <span className={`inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full ${statusConfig[row.status].bg} ${statusConfig[row.status].text}`}>
                          <span className={`w-1.5 h-1.5 rounded-full ${statusConfig[row.status].dot}`} />
                          {statusConfig[row.status].label}
                        </span>
                      </td>
                      <td className="p-4 text-[#0F172A] text-sm">{row.staff}</td>
                      <td className="p-4">
                        <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${
                          row.priority === "Urgente" ? "bg-red-50 text-red-600" :
                          row.priority === "Alta"    ? "bg-amber-50 text-amber-600" :
                          row.priority === "Media"   ? "bg-blue-50 text-blue-600" :
                                                       "bg-[#F0F4F8] text-[#6B7280]"
                        }`}>{row.priority}</span>
                      </td>
                      <td className="p-4 text-center">
                        <button className="text-xs font-semibold text-[#0F9D6E] hover:underline">
                          {row.status === "cleaning" ? "Marcar lista" : "Asignar"}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* ── Recent reservations ── */}
          <div className="bg-white rounded-2xl border border-black/5 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-black/5 flex items-center justify-between">
              <div>
                <h3 className="font-bold text-[#0F172A]">Reservas recientes</h3>
                <p className="text-xs text-[#6B7280]">Últimas 24 horas</p>
              </div>
              <div className="flex gap-2">
                <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#F0F4F8] text-xs font-medium text-[#0F172A] hover:bg-[#E8EDF2] transition-colors">
                  <Filter size={12} />Filtrar
                </button>
                <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-xs font-semibold shadow-sm">
                  <Download size={12} />Exportar
                </button>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[700px] text-sm">
                <thead>
                  <tr className="border-b border-black/5 text-[#6B7280] text-xs bg-[#F8FAFC]">
                    <th className="text-left p-4 font-semibold">ID Reserva</th>
                    <th className="text-left p-4 font-semibold">Huésped</th>
                    <th className="text-left p-4 font-semibold">Habitación</th>
                    <th className="text-left p-4 font-semibold">Check-in</th>
                    <th className="text-left p-4 font-semibold">Check-out</th>
                    <th className="text-left p-4 font-semibold">Canal</th>
                    <th className="text-right p-4 font-semibold">Total</th>
                    <th className="text-center p-4 font-semibold">Estado</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-black/5">
                  {[
                    { id: "#R-2891", guest: "Karla Ibáñez",    room: "102 · Estándar",   cin: "15 Jun", cout: "16 Jun", canal: "Directo",  total: "$1,800", status: "confirmed" },
                    { id: "#R-2890", guest: "Emilio Garza",    room: "304 · Estándar",   cin: "15 Jun", cout: "20 Jun", canal: "Booking",  total: "$9,200", status: "confirmed" },
                    { id: "#R-2889", guest: "Daniela Valls",   room: "302 · Jr. Suite",  cin: "17 Jun", cout: "19 Jun", canal: "Expedia",  total: "$6,400", status: "pending"   },
                    { id: "#R-2888", guest: "Marco Villanueva",room: "105 · Doble",      cin: "18 Jun", cout: "21 Jun", canal: "Airbnb",   total: "$5,400", status: "confirmed" },
                    { id: "#R-2887", guest: "Isabel Moreno",   room: "202 · Suite",      cin: "20 Jun", cout: "24 Jun", canal: "Directo",  total: "$14,800",status: "pending"   },
                  ].map((r) => (
                    <tr key={r.id} className="hover:bg-[#F8FAFC] transition-colors">
                      <td className="p-4 font-mono text-xs font-bold text-[#0F9D6E]">{r.id}</td>
                      <td className="p-4 font-medium text-[#0F172A]">{r.guest}</td>
                      <td className="p-4 text-[#6B7280] text-xs">{r.room}</td>
                      <td className="p-4 text-[#6B7280] text-xs">{r.cin}</td>
                      <td className="p-4 text-[#6B7280] text-xs">{r.cout}</td>
                      <td className="p-4">
                        <span className="text-xs font-semibold bg-[#F0F4F8] text-[#0F172A] px-2.5 py-1 rounded-full">{r.canal}</span>
                      </td>
                      <td className="p-4 text-right font-bold text-[#0F172A]">{r.total}</td>
                      <td className="p-4 text-center">
                        <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full ${
                          r.status === "confirmed" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"
                        }`}>
                          {r.status === "confirmed" ? "Confirmada" : "Pendiente"}
                        </span>
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
