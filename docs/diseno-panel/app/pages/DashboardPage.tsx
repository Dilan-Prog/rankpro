import { useState } from "react";
import {
  Search, TrendingUp, Target, Users, FileText, Settings, LogOut,
  Rocket, DollarSign, ChevronUp, ChevronDown, RefreshCw, Download,
  Bell, Filter, ExternalLink, CheckCircle, AlertCircle, Info, LayoutDashboard
} from "lucide-react";
import {
  AreaChart, Area, BarChart, Bar,
  XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart as RPieChart, Pie, Cell
} from "recharts";
import type { Page } from "../types";
import { trafficData, roiData, channelData } from "../data";

export default function DashboardPage({ setPage }: { setPage: (p: Page) => void }) {
  const [activeNav, setActiveNav] = useState("overview");

  const kpis = [
    { label: "Tráfico total / mes",   value: "64,191",  change: "+18.4%", up: true,  icon: TrendingUp, color: "from-emerald-400 to-emerald-600" },
    { label: "ROI Google Ads",        value: "420%",    change: "+35%",   up: true,  icon: DollarSign, color: "from-teal-400 to-emerald-500" },
    { label: "Posición SEO promedio", value: "#2.4",    change: "-0.8",   up: true,  icon: Search,     color: "from-green-400 to-emerald-500" },
    { label: "Costo por lead",        value: "$85 MXN", change: "-22%",   up: true,  icon: Target,     color: "from-emerald-500 to-teal-500" },
  ];

  const campaigns = [
    { name: "Google Search – Marca",    status: "Activa",    spend: "$12,400", conv: 142, cpl: "$87",  roas: "5.2x" },
    { name: "Google Display – Retarg.", status: "Activa",    spend: "$4,800",  conv: 38,  cpl: "$126", roas: "3.8x" },
    { name: "Meta Ads – Prospección",   status: "Activa",    spend: "$6,200",  conv: 67,  cpl: "$93",  roas: "4.1x" },
    { name: "TikTok Ads – Brand Aware.",status: "Pausada",   spend: "$1,900",  conv: 12,  cpl: "$158", roas: "2.4x" },
  ];

  const alerts = [
    { type: "success", msg: "Campaña Google Search superó objetivo de conversiones esta semana" },
    { type: "warning", msg: "El CPC de la campaña de Display aumentó un 12% en los últimos 3 días" },
    { type: "info",    msg: "Nuevo reporte mensual disponible para descarga en PDF" },
  ];

  const sideNav = [
    { id: "overview", icon: LayoutDashboard, label: "Dashboard" },
    { id: "seo",      icon: Search,           label: "SEO" },
    { id: "ads",      icon: Target,           label: "Ads" },
    { id: "social",   icon: Users,            label: "Social" },
    { id: "reports",  icon: FileText,         label: "Reportes" },
    { id: "settings", icon: Settings,         label: "Ajustes" },
  ];

  return (
    <div className="min-h-screen bg-[#F5F7FA] flex">
      {/* Sidebar */}
      <div className="w-64 bg-[#1A2332] shrink-0 flex flex-col hidden lg:flex">
        <div className="p-5 border-b border-white/10">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center">
              <Rocket size={15} className="text-white" />
            </div>
            <div>
              <div className="font-extrabold text-white text-base leading-tight">RankDash Pro</div>
              <div className="text-[10px] text-white/40">by RankPro Solutions</div>
            </div>
          </div>
        </div>

        <nav className="flex-1 p-4 space-y-1">
          {sideNav.map((item) => (
            <button key={item.id} onClick={() => setActiveNav(item.id)}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 ${activeNav === item.id ? "bg-[#0F9D6E] text-white" : "text-white/50 hover:text-white hover:bg-white/10"}`}>
              <item.icon size={17} />
              {item.label}
            </button>
          ))}
        </nav>

        <div className="p-4 border-t border-white/10">
          <div className="flex items-center gap-3 mb-4">
            <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=40&h=40&fit=crop" alt="User" className="w-9 h-9 rounded-full object-cover" />
            <div>
              <div className="text-white text-sm font-semibold">Carlos García</div>
              <div className="text-white/40 text-xs">FashionMX · Plan Growth</div>
            </div>
          </div>
          <button onClick={() => setPage("home")} className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-white/50 hover:text-white hover:bg-white/10 transition-colors text-sm">
            <LogOut size={15} />Cerrar sesión
          </button>
        </div>
      </div>

      {/* Main */}
      <div className="flex-1 overflow-auto">
        {/* Top header */}
        <div className="bg-white border-b border-black/5 px-6 py-4 flex items-center justify-between sticky top-0 z-10">
          <div>
            <h1 className="text-xl font-extrabold text-[#1A2332]">Dashboard General</h1>
            <p className="text-xs text-[#6B7280]">Período: 1 Jun 2024 – 15 Jun 2024 · Actualizado hace 5 min</p>
          </div>
          <div className="flex items-center gap-3">
            <button className="flex items-center gap-2 px-4 py-2 rounded-xl border border-black/10 text-sm font-medium text-[#1A2332] hover:bg-[#F5F7FA] transition-colors">
              <RefreshCw size={14} />Actualizar
            </button>
            <button className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-sm font-semibold shadow-md shadow-emerald-200">
              <Download size={14} />Exportar PDF
            </button>
            <div className="relative">
              <button className="w-9 h-9 rounded-xl border border-black/10 flex items-center justify-center hover:bg-[#F5F7FA] transition-colors">
                <Bell size={16} className="text-[#1A2332]" />
              </button>
              <span className="absolute -top-1 -right-1 w-4 h-4 bg-[#0F9D6E] rounded-full text-white text-[9px] flex items-center justify-center font-bold">3</span>
            </div>
          </div>
        </div>

        <div className="p-6 space-y-6">
          {/* Alerts */}
          <div className="space-y-2">
            {alerts.map((a, i) => (
              <div key={i} className={`flex items-start gap-3 px-4 py-3 rounded-xl text-sm border ${a.type === "success" ? "bg-emerald-50 border-emerald-200 text-emerald-800" : a.type === "warning" ? "bg-amber-50 border-amber-200 text-amber-800" : "bg-blue-50 border-blue-200 text-blue-800"}`}>
                {a.type === "success" ? <CheckCircle size={16} className="shrink-0 mt-0.5" /> : a.type === "warning" ? <AlertCircle size={16} className="shrink-0 mt-0.5" /> : <Info size={16} className="shrink-0 mt-0.5" />}
                {a.msg}
              </div>
            ))}
          </div>

          {/* KPI cards */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {kpis.map((kpi) => (
              <div key={kpi.label} className="bg-white rounded-2xl p-5 border border-black/5 shadow-sm">
                <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${kpi.color} flex items-center justify-center mb-4`}>
                  <kpi.icon size={18} className="text-white" />
                </div>
                <div className="text-2xl font-extrabold text-[#1A2332]">{kpi.value}</div>
                <div className="text-xs text-[#6B7280] mt-0.5 mb-2">{kpi.label}</div>
                <div className={`flex items-center gap-1 text-xs font-semibold ${kpi.up ? "text-emerald-600" : "text-red-500"}`}>
                  {kpi.up ? <ChevronUp size={13} /> : <ChevronDown size={13} />}
                  {kpi.change} vs mes anterior
                </div>
              </div>
            ))}
          </div>

          {/* Charts row */}
          <div className="grid lg:grid-cols-3 gap-6">
            {/* Traffic chart */}
            <div className="lg:col-span-2 bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
              <div className="flex items-center justify-between mb-6">
                <div>
                  <h3 className="font-bold text-[#1A2332]">Tráfico por canal</h3>
                  <p className="text-xs text-[#6B7280]">Últimos 6 meses</p>
                </div>
                <div className="flex gap-4 text-xs">
                  {[["Orgánico","#0F9D6E"],["Pagado","#34D399"],["Directo","#10B981"]].map(([l,c]) => (
                    <div key={l as string} className="flex items-center gap-1.5">
                      <div className="w-3 h-3 rounded-full" style={{ background: c as string }} />
                      <span className="text-[#6B7280]">{l}</span>
                    </div>
                  ))}
                </div>
              </div>
              <ResponsiveContainer width="100%" height={220}>
                <AreaChart data={trafficData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="mes" tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} tickFormatter={(v) => `${(v/1000).toFixed(0)}k`} />
                  <Tooltip formatter={(v: number) => v.toLocaleString()} contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 12 }} />
                  <Area type="monotone" dataKey="organico" stroke="#0F9D6E" strokeWidth={2} fill="#0F9D6E" fillOpacity={0.12} name="Orgánico" />
                  <Area type="monotone" dataKey="pagado"   stroke="#34D399" strokeWidth={2} fill="#34D399" fillOpacity={0.10} name="Pagado" />
                  <Area type="monotone" dataKey="directo"  stroke="#10B981" strokeWidth={2} fill="none"                       name="Directo" strokeDasharray="4 2" />
                </AreaChart>
              </ResponsiveContainer>
            </div>

            {/* Pie chart */}
            <div className="bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
              <h3 className="font-bold text-[#1A2332] mb-1">Distribución de canales</h3>
              <p className="text-xs text-[#6B7280] mb-4">Participación en tráfico total</p>
              <ResponsiveContainer width="100%" height={180}>
                <RPieChart>
                  <Pie data={channelData} cx="50%" cy="50%" innerRadius={50} outerRadius={80} dataKey="value" paddingAngle={3}>
                    {channelData.map((entry) => <Cell key={entry.name} fill={entry.color} />)}
                  </Pie>
                  <Tooltip formatter={(v: number) => `${v}%`} contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 12 }} />
                </RPieChart>
              </ResponsiveContainer>
              <div className="space-y-2 mt-3">
                {channelData.map((d) => (
                  <div key={d.name} className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-2">
                      <div className="w-2.5 h-2.5 rounded-full" style={{ background: d.color }} />
                      <span className="text-[#6B7280]">{d.name}</span>
                    </div>
                    <span className="font-semibold text-[#1A2332]">{d.value}%</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* ROI chart + Campaigns */}
          <div className="grid lg:grid-cols-5 gap-6">
            {/* ROI */}
            <div className="lg:col-span-2 bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
              <h3 className="font-bold text-[#1A2332] mb-1">ROI Google Ads</h3>
              <p className="text-xs text-[#6B7280] mb-4">Evolución mensual (%)</p>
              <ResponsiveContainer width="100%" height={180}>
                <BarChart data={roiData} barSize={28}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="mes" tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 11, fill: "#6B7280" }} axisLine={false} tickLine={false} unit="%" />
                  <Tooltip formatter={(v: number) => `${v}%`} contentStyle={{ borderRadius: "12px", border: "1px solid rgba(0,0,0,0.08)", fontSize: 12 }} />
                  <Bar dataKey="roi" radius={[6, 6, 0, 0]} name="ROI">
                    {roiData.map((entry, i) => <Cell key={entry.mes} fill={i === roiData.length - 1 ? "#0F9D6E" : "#d1fae5"} />)}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>

            {/* Campaigns table */}
            <div className="lg:col-span-3 bg-white rounded-2xl p-6 border border-black/5 shadow-sm overflow-x-auto">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-bold text-[#1A2332]">Campañas activas</h3>
                <button className="flex items-center gap-1.5 text-xs font-medium text-[#0F9D6E] hover:underline"><Filter size={12} />Filtrar</button>
              </div>
              <table className="w-full min-w-[500px] text-sm">
                <thead>
                  <tr className="border-b border-black/5 text-[#6B7280] text-xs">
                    <th className="text-left pb-3 font-medium">Campaña</th>
                    <th className="text-center pb-3 font-medium">Estado</th>
                    <th className="text-right pb-3 font-medium">Gasto</th>
                    <th className="text-right pb-3 font-medium">Conv.</th>
                    <th className="text-right pb-3 font-medium">CPL</th>
                    <th className="text-right pb-3 font-medium">ROAS</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-black/5">
                  {campaigns.map((c) => (
                    <tr key={c.name} className="hover:bg-[#F5F7FA] transition-colors">
                      <td className="py-3 font-medium text-[#1A2332] text-xs">{c.name}</td>
                      <td className="py-3 text-center">
                        <span className={`inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full ${c.status === "Activa" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"}`}>
                          <span className={`w-1.5 h-1.5 rounded-full ${c.status === "Activa" ? "bg-emerald-500" : "bg-amber-500"}`} />
                          {c.status}
                        </span>
                      </td>
                      <td className="py-3 text-right text-[#1A2332] font-semibold">{c.spend}</td>
                      <td className="py-3 text-right text-[#1A2332]">{c.conv}</td>
                      <td className="py-3 text-right text-[#6B7280]">{c.cpl}</td>
                      <td className="py-3 text-right">
                        <span className="font-bold text-[#0F9D6E]">{c.roas}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* SEO Positions */}
          <div className="bg-white rounded-2xl p-6 border border-black/5 shadow-sm">
            <div className="flex items-center justify-between mb-5">
              <div>
                <h3 className="font-bold text-[#1A2332]">Posicionamiento SEO — Top Keywords</h3>
                <p className="text-xs text-[#6B7280]">Posiciones en Google México (desktop)</p>
              </div>
              <button className="flex items-center gap-1.5 text-xs font-medium text-[#0F9D6E] border border-[#0F9D6E] px-3 py-1.5 rounded-lg hover:bg-emerald-50 transition-colors">
                <ExternalLink size={12} />Ver todo
              </button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[600px] text-sm">
                <thead>
                  <tr className="border-b border-black/5 text-[#6B7280] text-xs">
                    <th className="text-left pb-3 font-medium">Palabra clave</th>
                    <th className="text-center pb-3 font-medium">Posición actual</th>
                    <th className="text-center pb-3 font-medium">Cambio</th>
                    <th className="text-center pb-3 font-medium">Volumen/mes</th>
                    <th className="text-center pb-3 font-medium">Dificultad</th>
                    <th className="text-right pb-3 font-medium">Tráfico est.</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-black/5">
                  {[
                    { kw: "agencia marketing digital mexico", pos: 2, change: +1, vol: "8,100", diff: 62, traffic: "1,420" },
                    { kw: "google ads mexico",               pos: 3, change: +2, vol: "5,400", diff: 58, traffic: "940"   },
                    { kw: "posicionamiento seo cdmx",        pos: 1, change: 0,  vol: "2,900", diff: 51, traffic: "780"   },
                    { kw: "diseño web profesional mexico",   pos: 4, change: -1, vol: "4,400", diff: 67, traffic: "620"   },
                    { kw: "agencia seo mexico",              pos: 5, change: +3, vol: "3,600", diff: 70, traffic: "510"   },
                  ].map((row) => (
                    <tr key={row.kw} className="hover:bg-[#F5F7FA] transition-colors">
                      <td className="py-3 font-medium text-[#1A2332] text-xs">{row.kw}</td>
                      <td className="py-3 text-center">
                        <span className={`font-extrabold text-sm ${row.pos <= 3 ? "text-[#0F9D6E]" : "text-[#1A2332]"}`}>#{row.pos}</span>
                      </td>
                      <td className="py-3 text-center text-xs font-semibold">
                        <span className={row.change > 0 ? "text-emerald-600" : row.change < 0 ? "text-red-500" : "text-[#6B7280]"}>
                          {row.change > 0 ? `↑ +${row.change}` : row.change < 0 ? `↓ ${row.change}` : "→ 0"}
                        </span>
                      </td>
                      <td className="py-3 text-center text-[#6B7280] text-xs">{row.vol}</td>
                      <td className="py-3 text-center">
                        <div className="flex items-center gap-2">
                          <div className="flex-1 h-1.5 bg-[#F5F7FA] rounded-full overflow-hidden">
                            <div className="h-full bg-gradient-to-r from-[#0F9D6E] to-[#34D399] rounded-full" style={{ width: `${row.diff}%` }} />
                          </div>
                          <span className="text-xs text-[#6B7280] w-6">{row.diff}</span>
                        </div>
                      </td>
                      <td className="py-3 text-right font-semibold text-[#1A2332] text-xs">{row.traffic}/mes</td>
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
