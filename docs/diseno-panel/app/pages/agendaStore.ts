// Almacén compartido entre la página pública de Agendar y el panel administrativo.
export type BookingStatus = "confirmada" | "pendiente" | "completada" | "no-show" | "cancelada";
export type Booking = {
  id: string; date: string; time: string;
  name: string; email: string; phone: string; company: string;
  topic: string; note: string; status: BookingStatus; createdAt: string; source: string;
};
export type DayRule = { on: boolean; from: string; to: string };
export type Availability = {
  days: Record<number, DayRule>; // 0=Dom … 6=Sáb
  duration: number; buffer: number; leadHours: number; horizonDays: number;
  blocked: string[];
};

export const TOPICS = ["SEO & Posicionamiento", "Google Ads / SEM", "Desarrollo Web", "Redes Sociales", "Analytics & Data", "Estrategia 360°"];
export const DOW = ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];
export const DOW_LONG = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
export const MONTHS = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];

const KB = "rankpro-agenda-bookings-v1";
const KA = "rankpro-agenda-availability-v1";

export const pad = (n: number) => String(n).padStart(2, "0");
export const ymd = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const parseYmd = (s: string) => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d); };
export const toMin = (t: string) => { const [h, m] = t.split(":").map(Number); return h * 60 + m; };
export const fromMin = (m: number) => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
export const fmtTime = (t: string) => { const m = toMin(t); const h = Math.floor(m / 60); return `${((h + 11) % 12) + 1}:${pad(m % 60)} ${h < 12 ? "a.m." : "p.m."}`; };
export const fmtDateLong = (s: string) => { const d = parseYmd(s); return `${DOW_LONG[d.getDay()]} ${d.getDate()} de ${MONTHS[d.getMonth()]}`; };
export const fmtDateShort = (s: string) => { const d = parseYmd(s); return `${DOW[d.getDay()]} ${d.getDate()} ${MONTHS[d.getMonth()].slice(0, 3)}`; };
export const addDays = (d: Date, n: number) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };

export const DEFAULT_AVAIL: Availability = {
  days: {
    0: { on: false, from: "09:00", to: "14:00" },
    1: { on: true, from: "09:00", to: "18:00" },
    2: { on: true, from: "09:00", to: "18:00" },
    3: { on: true, from: "09:00", to: "18:00" },
    4: { on: true, from: "09:00", to: "18:00" },
    5: { on: true, from: "09:00", to: "15:00" },
    6: { on: false, from: "10:00", to: "13:00" },
  },
  duration: 30, buffer: 0, leadHours: 2, horizonDays: 45, blocked: [],
};

function seed(): Booking[] {
  const t = new Date();
  const people = [
    ["Mariana López", "mariana@clinicasol.mx", "+52 55 4812 3390", "Clínica Sol", 0, "SEO & Posicionamiento"],
    ["Diego Herrera", "diego@hotelbrisa.com", "+52 998 221 7710", "Hotel Brisa", 1, "Google Ads / SEM"],
    ["Ana Ruiz", "ana.ruiz@mueblesra.mx", "+52 33 1180 4455", "Muebles RA", 2, "Desarrollo Web"],
    ["Carlos Mendoza", "cmendoza@legalmx.com", "+52 55 9012 3321", "Legal MX", 3, "Estrategia 360°"],
    ["Sofía Castro", "sofia@dentalplus.mx", "+52 81 3345 1209", "Dental Plus", 4, "Redes Sociales"],
    ["Jorge Salinas", "jorge@autopartesjs.mx", "+52 55 7781 0023", "Autopartes JS", -1, "Google Ads / SEM"],
    ["Lucía Navarro", "lucia@boutiqueln.com", "+52 55 6621 9087", "Boutique LN", -2, "Analytics & Data"],
    ["Ricardo Peña", "ricardo@constructorarp.mx", "+52 442 190 5566", "Constructora RP", -3, "SEO & Posicionamiento"],
    ["Valeria Ortiz", "vortiz@spaorquidea.mx", "+52 55 1234 8876", "Spa Orquídea", 0, "Redes Sociales"],
    ["Fernando Gil", "fgil@logisticafg.com", "+52 55 3398 1120", "Logística FG", 6, "Desarrollo Web"],
  ] as const;
  const times = ["10:00", "12:30", "16:00", "09:30", "11:00", "13:00", "17:00", "10:30", "15:00", "11:30"];
  return people.map(([name, email, phone, company, off, topic], i) => {
    let d = addDays(t, off as number);
    while (d.getDay() === 0 || d.getDay() === 6) d = addDays(d, 1);
    const past = (off as number) < 0;
    const status: BookingStatus = past ? (i === 6 ? "no-show" : "completada") : i === 4 ? "pendiente" : "confirmada";
    return { id: "b" + (1000 + i), date: ymd(d), time: times[i], name, email, phone, company, topic, note: i % 3 === 0 ? "Queremos más leads para el Q4." : "", status, createdAt: new Date().toISOString(), source: i % 2 ? "Google Ads" : "Sitio web" } as Booking;
  });
}

export function loadBookings(): Booking[] {
  try { const r = localStorage.getItem(KB); if (r) return JSON.parse(r); } catch {}
  const s = seed(); saveBookings(s); return s;
}
export function saveBookings(b: Booking[]) { try { localStorage.setItem(KB, JSON.stringify(b)); } catch {} }
export function loadAvail(): Availability {
  try { const r = localStorage.getItem(KA); if (r) return { ...DEFAULT_AVAIL, ...JSON.parse(r) }; } catch {}
  return DEFAULT_AVAIL;
}
export function saveAvail(a: Availability) { try { localStorage.setItem(KA, JSON.stringify(a)); } catch {} }

export function slotsFor(date: string, a: Availability, bookings: Booking[], ignoreId?: string): string[] {
  const d = parseYmd(date);
  const rule = a.days[d.getDay()];
  if (!rule || !rule.on || a.blocked.includes(date)) return [];
  const taken = new Set(bookings.filter((b) => b.date === date && b.status !== "cancelada" && b.id !== ignoreId).map((b) => b.time));
  const minStart = Date.now() + a.leadHours * 3600e3;
  const out: string[] = [];
  for (let m = toMin(rule.from); m + a.duration <= toMin(rule.to); m += a.duration + a.buffer) {
    const t = fromMin(m);
    const at = new Date(d); at.setHours(Math.floor(m / 60), m % 60);
    if (at.getTime() < minStart || taken.has(t)) continue;
    out.push(t);
  }
  return out;
}
