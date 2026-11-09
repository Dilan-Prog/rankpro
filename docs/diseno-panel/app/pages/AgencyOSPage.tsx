import { useState, useMemo, useEffect } from "react";
import {
  LayoutDashboard, Building2, Boxes, Search, KeyRound, Megaphone, Filter, Code2,
  Wallet, FolderOpen, ShieldCheck, Plug, Bell, Sun, Moon, ChevronDown, ChevronRight,
  MoreHorizontal, Plus, Download, ArrowUpRight, ArrowDownRight, CheckCircle2, Circle,
  AlertTriangle, X, TrendingUp, Users, Clock, Zap, ExternalLink, Copy, Pencil,
  Trash2, Check, LogOut, Settings, UserCog, Workflow, MessageSquare, Calculator, FileText, Gauge
} from "lucide-react";
import {
  AreaChart, Area, BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend
} from "recharts";

/* ─────────────────────────  tokens  ───────────────────────── */
const dark = {
  app: "#0D1117", sidebar: "#090D16", card: "#161C28", raised: "#1B2331",
  text: "#E2E8F0", dim: "#64748B", mid: "#94A3B8",
  line: "rgba(255,255,255,.07)", lineStrong: "rgba(255,255,255,.13)",
  hover: "rgba(255,255,255,.04)", inputBg: "#0F1521",
};
const light = {
  app: "#F6F8FA", sidebar: "#FFFFFF", card: "#FFFFFF", raised: "#F1F5F9",
  text: "#0F172A", dim: "#64748B", mid: "#475569",
  line: "rgba(15,23,42,.09)", lineStrong: "rgba(15,23,42,.16)",
  hover: "rgba(15,23,42,.035)", inputBg: "#F1F5F9",
};
const brand = "#0F9D6E", brandHover = "#0B7A56", accent = "#10B981";
const ok = "#34D399", err = "#F87171", warn = "#FBBF24", info = "#3B82F6";
const mono = '"JetBrains Mono", ui-monospace, monospace';

/* ─────────────────────────  datos mock  ───────────────────────── */
const modules = [
  { key: "dashboard", label: "Dashboard", icon: LayoutDashboard, group: "General" },
  { key: "crm", label: "CRM Clientes", icon: Building2, group: "General" },
  { key: "servicios", label: "Servicios", icon: Boxes, group: "General" },
  { key: "seo", label: "SEO", icon: Search, group: "Operación" },
  { key: "keywords", label: "Keywords", icon: KeyRound, group: "Operación" },
  { key: "ads", label: "Ads", icon: Megaphone, group: "Operación" },
  { key: "conversiones", label: "Conversiones", icon: Filter, group: "Operación" },
  { key: "reportes", label: "Reportes", icon: FileText, group: "Operación" },
  { key: "automatizaciones", label: "Automatizaciones", icon: Workflow, group: "Operación" },
  { key: "desarrollo", label: "Desarrollo", icon: Code2, group: "Operación" },
  { key: "finanzas", label: "Finanzas", icon: Wallet, group: "Administración" },
  { key: "archivos", label: "Archivos", icon: FolderOpen, group: "Administración" },
  { key: "roles", label: "Roles y Usuarios", icon: ShieldCheck, group: "Administración" },
  { key: "integraciones", label: "Integraciones", icon: Plug, group: "Administración" },
];

const clientes = [
  { empresa: "Grupo Lumina", contacto: "Ana Gutiérrez", servicios: ["SEO", "Google Ads"], mrr: 68000, vence: "12 sep 2026", estado: "Activo" },
  { empresa: "TechCorp MX", contacto: "Carlos Mendoza", servicios: ["Google Ads", "Software"], mrr: 145000, vence: "01 oct 2026", estado: "Activo" },
  { empresa: "FashionMX", contacto: "Sofía Ramírez", servicios: ["Meta Ads", "TikTok Ads"], mrr: 54000, vence: "28 ago 2026", estado: "Por vencer" },
  { empresa: "Hotelera Costa", contacto: "Luis Palma", servicios: ["SEO", "Rediseño"], mrr: 92000, vence: "15 nov 2026", estado: "Activo" },
  { empresa: "Dental Sonrisa", contacto: "Marta Ríos", servicios: ["Google Ads"], mrr: 28000, vence: "05 sep 2026", estado: "Pago pendiente" },
  { empresa: "Constructora Vela", contacto: "Iván Cruz", servicios: ["Software"], mrr: 210000, vence: "20 ene 2027", estado: "Activo" },
  { empresa: "Café Origen", contacto: "Paula Nava", servicios: ["Meta Ads", "SEO"], mrr: 36000, vence: "30 ago 2026", estado: "Por vencer" },
  { empresa: "Legal & Partners", contacto: "Diego Ruiz", servicios: ["SEO"], mrr: 42000, vence: "18 dic 2026", estado: "Pausado" },
];

const servicios = [
  { cliente: "Grupo Lumina", servicio: "SEO", responsable: "V. Mora", precio: 38000, inicio: "01 mar 2026", estado: "Activo" },
  { cliente: "Grupo Lumina", servicio: "Google Ads", responsable: "R. Torres", precio: 30000, inicio: "01 mar 2026", estado: "Activo" },
  { cliente: "TechCorp MX", servicio: "Google Ads", responsable: "R. Torres", precio: 65000, inicio: "15 ene 2026", estado: "Activo" },
  { cliente: "TechCorp MX", servicio: "Software", responsable: "J. Beltrán", precio: 80000, inicio: "01 feb 2026", estado: "En desarrollo" },
  { cliente: "FashionMX", servicio: "Meta Ads", responsable: "L. Ortiz", precio: 32000, inicio: "10 abr 2026", estado: "Activo" },
  { cliente: "FashionMX", servicio: "TikTok Ads", responsable: "L. Ortiz", precio: 22000, inicio: "10 abr 2026", estado: "Revisión" },
  { cliente: "Hotelera Costa", servicio: "Rediseño", responsable: "M. Salas", precio: 54000, inicio: "20 may 2026", estado: "En desarrollo" },
  { cliente: "Café Origen", servicio: "SEO", responsable: "V. Mora", precio: 18000, inicio: "01 jun 2026", estado: "Activo" },
];

const keywords = [
  { kw: "software hotelero méxico", vol: 2400, dif: 42, cpc: 38.5, intencion: "Comercial", url: "/pms-hotelero", pos: 4, cliente: "Hotelera Costa", fuente: "Semrush" },
  { kw: "agencia google ads cdmx", vol: 1900, dif: 58, cpc: 96.0, intencion: "Transaccional", url: "/servicios/sem", pos: 7, cliente: "Grupo Lumina", fuente: "GSC" },
  { kw: "crm para constructoras", vol: 880, dif: 34, cpc: 54.2, intencion: "Comercial", url: "/software/crm", pos: 12, cliente: "Constructora Vela", fuente: "Semrush" },
  { kw: "clinica dental polanco", vol: 3200, dif: 29, cpc: 41.8, intencion: "Local", url: "/sucursal-polanco", pos: 2, cliente: "Dental Sonrisa", fuente: "GBP" },
  { kw: "ropa mexicana sustentable", vol: 5400, dif: 61, cpc: 12.4, intencion: "Informativa", url: "/blog/sustentable", pos: 18, cliente: "FashionMX", fuente: "Ahrefs" },
  { kw: "café de especialidad oaxaca", vol: 1600, dif: 25, cpc: 9.8, intencion: "Comercial", url: "/tienda/oaxaca", pos: 5, cliente: "Café Origen", fuente: "Semrush" },
  { kw: "despacho legal corporativo", vol: 720, dif: 47, cpc: 128.0, intencion: "Transaccional", url: "/corporativo", pos: 9, cliente: "Legal & Partners", fuente: "GSC" },
];

const adsKeywords = [
  { kw: "software hotelero", match: "Frase", cpc: 38.5, clics: 412, ctr: 6.4, conv: 28, cpa: 566, estado: "Activa" },
  { kw: "pms para hoteles", match: "Exacta", cpc: 44.1, clics: 268, ctr: 7.9, conv: 22, cpa: 537, estado: "Activa" },
  { kw: "sistema de reservas", match: "Amplia", cpc: 21.7, clics: 934, ctr: 3.1, conv: 12, cpa: 1689, estado: "Pausada" },
  { kw: "check in digital hotel", match: "Frase", cpc: 26.3, clics: 187, ctr: 5.2, conv: 9, cpa: 546, estado: "Activa" },
  { kw: "software hotel gratis", match: "Negativa", cpc: 0, clics: 0, ctr: 0, conv: 0, cpa: 0, estado: "Excluida" },
];

const conversiones = [
  { id: "CV-1042", cliente: "Hotelera Costa", canal: "Google Ads", valor: 84000, etapa: "Propuesta", owner: "R. Torres", fecha: "18 ago" },
  { id: "CV-1041", cliente: "TechCorp MX", canal: "SEO", valor: 220000, etapa: "Negociación", owner: "V. Mora", fecha: "17 ago" },
  { id: "CV-1039", cliente: "Dental Sonrisa", canal: "Meta Ads", valor: 32000, etapa: "Contactado", owner: "L. Ortiz", fecha: "16 ago" },
  { id: "CV-1036", cliente: "Café Origen", canal: "Google Ads", valor: 18000, etapa: "Ganada", owner: "R. Torres", fecha: "14 ago" },
  { id: "CV-1033", cliente: "Legal & Partners", canal: "SEO", valor: 46000, etapa: "Nuevo", owner: "V. Mora", fecha: "13 ago" },
  { id: "CV-1030", cliente: "FashionMX", canal: "TikTok Ads", valor: 24000, etapa: "Perdida", owner: "L. Ortiz", fecha: "11 ago" },
  { id: "CV-1028", cliente: "Grupo Lumina", canal: "Google Ads", valor: 68000, etapa: "Negociación", owner: "R. Torres", fecha: "10 ago" },
];

const etapas = ["Nuevo", "Contactado", "Propuesta", "Negociación", "Ganada", "Perdida"];

const ingresos = [
  { m: "Mar", ingresos: 620, inversion: 410 }, { m: "Abr", ingresos: 705, inversion: 448 },
  { m: "May", ingresos: 742, inversion: 462 }, { m: "Jun", ingresos: 810, inversion: 489 },
  { m: "Jul", ingresos: 884, inversion: 502 }, { m: "Ago", ingresos: 951, inversion: 528 },
];

const gastos = [
  { m: "Mar", ingresos: 620, gastos: 388 }, { m: "Abr", ingresos: 705, gastos: 402 },
  { m: "May", ingresos: 742, gastos: 421 }, { m: "Jun", ingresos: 810, gastos: 437 },
  { m: "Jul", ingresos: 884, gastos: 455 }, { m: "Ago", ingresos: 951, gastos: 468 },
];

const alertas = [
  { tipo: "error", texto: "Dental Sonrisa · pago vencido hace 6 días ($28,000)" },
  { tipo: "warn", texto: "FashionMX · contrato vence en 5 días, sin renovación firmada" },
  { tipo: "warn", texto: "Café Origen · reporte de agosto pendiente de aprobación" },
  { tipo: "info", texto: "TechCorp MX · nueva fase de desarrollo lista para QA" },
];

const fasesSEO = [
  { fase: "Auditoría", estado: "completa", items: [["Rastreo técnico", true], ["Core Web Vitals", true], ["Análisis de contenido", true], ["Aprobación del cliente", true]] },
  { fase: "Estrategia", estado: "completa", items: [["Keyword research", true], ["Arquitectura propuesta", true], ["Calendario editorial", true], ["Aprobación del cliente", true]] },
  { fase: "Ejecución", estado: "activa", items: [["Correcciones técnicas", true], ["Publicación de contenido", true], ["Link building", false], ["Optimización on-page", false]] },
  { fase: "Reporte", estado: "pendiente", items: [["Consolidado de posiciones", false], ["Informe de tráfico", false], ["Junta de resultados", false], ["Aprobación del ciclo", false]] },
];

const fasesDev = [
  { fase: "Descubrimiento", estado: "completa", items: [["Levantamiento", true], ["Alcance firmado", true], ["Accesos entregados", true]] },
  { fase: "Diseño UI", estado: "completa", items: [["Wireframes", true], ["UI final", true], ["Aprobación", true]] },
  { fase: "Desarrollo", estado: "activa", items: [["Módulo reservas", true], ["Pasarela de pago", true], ["Panel admin", false], ["Integración ERP", false]] },
  { fase: "QA y pruebas", estado: "pendiente", items: [["Pruebas funcionales", false], ["Pruebas de carga", false], ["Aceptación cliente", false]] },
];

const integraciones = [
  { nombre: "Google Ads", estado: "Conectado", desc: "Métricas de campañas y conversiones" },
  { nombre: "Google Analytics 4", estado: "Conectado", desc: "Sesiones, eventos y embudos" },
  { nombre: "Google Search Console", estado: "Conectado", desc: "Posiciones y clics orgánicos" },
  { nombre: "Meta Ads", estado: "Conectado", desc: "Campañas de Facebook e Instagram" },
  { nombre: "TikTok Ads", estado: "Próximamente", desc: "Campañas y creativos" },
  { nombre: "HubSpot", estado: "Próximamente", desc: "Sincronización de contactos y deals" },
  { nombre: "Stripe", estado: "Próximamente", desc: "Cobros y suscripciones" },
  { nombre: "Slack", estado: "Próximamente", desc: "Alertas de operación" },
  { nombre: "n8n", estado: "Próximamente", desc: "Automatizaciones internas" },
];

const usuarios = [
  { nombre: "Ricardo Torres", email: "ricardo@rankpro.mx", rol: "Administrador", estado: "Activo", ultimo: "hace 4 min" },
  { nombre: "Valeria Mora", email: "valeria@rankpro.mx", rol: "Líder SEO", estado: "Activo", ultimo: "hace 1 h" },
  { nombre: "Luis Ortiz", email: "luis@rankpro.mx", rol: "Paid Media", estado: "Activo", ultimo: "hace 3 h" },
  { nombre: "Jorge Beltrán", email: "jorge@rankpro.mx", rol: "Desarrollo", estado: "Activo", ultimo: "ayer" },
  { nombre: "Marta Ríos", email: "marta@rankpro.mx", rol: "Finanzas", estado: "Inactivo", ultimo: "hace 12 días" },
];

const roles = [
  { rol: "Administrador", usuarios: 2, permisos: 12 },
  { rol: "Líder SEO", usuarios: 3, permisos: 7 },
  { rol: "Paid Media", usuarios: 4, permisos: 6 },
  { rol: "Desarrollo", usuarios: 5, permisos: 5 },
  { rol: "Finanzas", usuarios: 2, permisos: 4 },
];

const archivos = [
  { nombre: "Contrato_GrupoLumina_2026.pdf", cliente: "Grupo Lumina", tipo: "Contrato", peso: "412 KB", fecha: "01 mar 2026" },
  { nombre: "Propuesta_TechCorp_Software.pdf", cliente: "TechCorp MX", tipo: "Propuesta", peso: "1.2 MB", fecha: "22 ene 2026" },
  { nombre: "Reporte_SEO_Agosto.pdf", cliente: "Café Origen", tipo: "Reporte", peso: "864 KB", fecha: "05 ago 2026" },
  { nombre: "Brief_Rediseño_Costa.pdf", cliente: "Hotelera Costa", tipo: "Brief", peso: "228 KB", fecha: "18 may 2026" },
];

/* ── datos: reportes de crecimiento ── */
const reporteSerie = [
  { m: "Mar", trafico: 12.4, impresiones: 184, conv: 96, cpa: 1620, roas: 3.4, ctr: 3.8, top10: 14, inversion: 410, ingresos: 620, horas: 28 },
  { m: "Abr", trafico: 13.8, impresiones: 206, conv: 104, cpa: 1548, roas: 3.7, ctr: 4.1, top10: 17, inversion: 448, ingresos: 705, horas: 34 },
  { m: "May", trafico: 15.1, impresiones: 228, conv: 112, cpa: 1470, roas: 3.9, ctr: 4.4, top10: 19, inversion: 462, ingresos: 742, horas: 41 },
  { m: "Jun", trafico: 16.4, impresiones: 251, conv: 121, cpa: 1382, roas: 4.2, ctr: 4.9, top10: 22, inversion: 489, ingresos: 810, horas: 52 },
  { m: "Jul", trafico: 17.6, impresiones: 274, conv: 132, cpa: 1284, roas: 4.5, ctr: 5.6, top10: 25, inversion: 502, ingresos: 884, horas: 61 },
  { m: "Ago", trafico: 18.4, impresiones: 296, conv: 142, cpa: 1186, roas: 4.8, ctr: 6.4, top10: 28, inversion: 528, ingresos: 951, horas: 74 },
];

const leadsCanal = [
  { canal: "SEO", leads: 186, cerrados: 42 },
  { canal: "Google Ads", leads: 264, cerrados: 71 },
  { canal: "Meta Ads", leads: 148, cerrados: 26 },
  { canal: "WhatsApp", leads: 212, cerrados: 58 },
  { canal: "Referidos", leads: 64, cerrados: 24 },
];

const comparativo = [
  { metrica: "Tráfico orgánico", actual: "18,400", anterior: "17,600", delta: 4.5, meta: "20,000" },
  { metrica: "Keywords en top 10", actual: "28", anterior: "25", delta: 12.0, meta: "32" },
  { metrica: "Conversiones", actual: "142", anterior: "132", delta: 7.6, meta: "150" },
  { metrica: "Costo por conversión", actual: "$1,186", anterior: "$1,284", delta: 7.6, meta: "$1,100" },
  { metrica: "ROAS", actual: "4.8x", anterior: "4.5x", delta: 6.7, meta: "5.0x" },
  { metrica: "CTR de Ads", actual: "6.4%", anterior: "5.6%", delta: 14.3, meta: "6.0%" },
  { metrica: "Impresiones", actual: "296K", anterior: "274K", delta: 8.0, meta: "300K" },
  { metrica: "Inversión publicitaria", actual: "$528K", anterior: "$502K", delta: -5.2, meta: "$520K" },
];

const recomendaciones = [
  { id: "R-118", area: "SEO", hallazgo: "12 URLs con LCP mayor a 3.2 s en móvil", accion: "Comprimir imágenes hero y diferir scripts de terceros", impacto: "Alto", esfuerzo: "Medio", origen: "Regla", estado: "Aprobada", owner: "V. Mora" },
  { id: "R-117", area: "Ads", hallazgo: "Grupo Genéricos con CPA 38% arriba del objetivo", accion: "Excluir 14 términos y bajar puja 15%", impacto: "Alto", esfuerzo: "Bajo", origen: "Regla", estado: "Aprobada", owner: "R. Torres" },
  { id: "R-115", area: "SEO", hallazgo: "3 keywords en posición 11-14 sin contenido dedicado", accion: "Publicar landing por intención comercial", impacto: "Medio", esfuerzo: "Alto", origen: "Equipo", estado: "En revisión", owner: "V. Mora" },
  { id: "R-113", area: "Ads", hallazgo: "Creativo con CTR 4.1% por debajo del promedio", accion: "Rotar dos variantes nuevas de titular", impacto: "Medio", esfuerzo: "Bajo", origen: "Regla", estado: "Aprobada", owner: "L. Ortiz" },
  { id: "R-110", area: "SEO", hallazgo: "Canibalización entre blog y landing de PMS", accion: "Consolidar contenido y redirigir 301", impacto: "Medio", esfuerzo: "Medio", origen: "Regla", estado: "Descartada", owner: "V. Mora" },
  { id: "R-108", area: "Ads", hallazgo: "Sin campaña de marca activa", accion: "Lanzar campaña defensiva de marca con presupuesto bajo", impacto: "Bajo", esfuerzo: "Bajo", origen: "Equipo", estado: "En revisión", owner: "R. Torres" },
];

/* ── datos: automatizaciones ── */
const tiposAuto = [
  "WhatsApp: atención y respuestas",
  "WhatsApp: recordatorios y cobranza",
  "Captura de leads a CRM",
  "Facturación y conciliación",
  "Reportes automáticos",
  "Onboarding de clientes",
  "Integración ERP/POS",
  "Agentes con IA",
];

const flujos = [
  { id: "AU-041", cliente: "Hotelera Costa", nombre: "Confirmación y recordatorio de reserva", tipo: "WhatsApp: recordatorios y cobranza", complejidad: "Medio", integraciones: ["WhatsApp API", "PMS", "Google Sheets"], horas: 62, ejecuciones: 4820, exito: 99.2, estado: "Activo", fase: "Operación y mejora" },
  { id: "AU-039", cliente: "Dental Sonrisa", nombre: "Agenda y confirmación de citas", tipo: "WhatsApp: atención y respuestas", complejidad: "Simple", integraciones: ["WhatsApp API", "Google Calendar"], horas: 34, ejecuciones: 2140, exito: 98.4, estado: "Activo", fase: "Operación y mejora" },
  { id: "AU-036", cliente: "TechCorp MX", nombre: "Conciliación de facturas con ERP", tipo: "Facturación y conciliación", complejidad: "Complejo", integraciones: ["ERP", "Stripe", "Drive", "Slack"], horas: 118, ejecuciones: 940, exito: 97.1, estado: "Activo", fase: "Implementación" },
  { id: "AU-034", cliente: "Grupo Lumina", nombre: "Leads de Ads a CRM con scoring", tipo: "Captura de leads a CRM", complejidad: "Medio", integraciones: ["Google Ads", "HubSpot", "WhatsApp API"], horas: 48, ejecuciones: 3160, exito: 99.6, estado: "Activo", fase: "Operación y mejora" },
  { id: "AU-030", cliente: "FashionMX", nombre: "Recuperación de carrito por WhatsApp", tipo: "WhatsApp: recordatorios y cobranza", complejidad: "Medio", integraciones: ["Shopify", "WhatsApp API"], horas: 41, ejecuciones: 5210, exito: 96.8, estado: "Revisión", fase: "Implementación" },
  { id: "AU-028", cliente: "Constructora Vela", nombre: "Onboarding de obra y accesos", tipo: "Onboarding de clientes", complejidad: "Complejo", integraciones: ["ERP", "Drive", "Slack", "Firma electrónica"], horas: 96, ejecuciones: 320, exito: 99.0, estado: "Activo", fase: "Operación y mejora" },
  { id: "AU-025", cliente: "Café Origen", nombre: "Reporte semanal de ventas a dirección", tipo: "Reportes automáticos", complejidad: "Simple", integraciones: ["POS", "Sheets", "Correo"], horas: 22, ejecuciones: 148, exito: 100, estado: "Activo", fase: "Operación y mejora" },
  { id: "AU-022", cliente: "Legal & Partners", nombre: "Agente IA de primer contacto", tipo: "Agentes con IA", complejidad: "Complejo", integraciones: ["WhatsApp API", "CRM", "Base de conocimiento"], horas: 74, ejecuciones: 1860, exito: 94.2, estado: "Pausado", fase: "Diseño del flujo" },
];

const fasesAuto = [
  { fase: "Descubrimiento", estado: "completa", items: [["Mapeo del proceso actual", true], ["Inventario de sistemas y accesos", true], ["Estimación de horas ahorradas", true], ["Aprobación del cliente", true]] },
  { fase: "Diseño del flujo", estado: "completa", items: [["Diagrama de disparadores y ramas", true], ["Plantillas de WhatsApp aprobadas", true], ["Reglas de escalamiento a humano", true], ["Aprobación del cliente", true]] },
  { fase: "Implementación", estado: "activa", items: [["Conexión de integraciones", true], ["Construcción del flujo en n8n", true], ["Pruebas con datos reales", false], ["Manejo de errores y reintentos", false]] },
  { fase: "Operación y mejora", estado: "pendiente", items: [["Monitoreo de 30 días", false], ["Capacitación del equipo del cliente", false], ["Informe de horas ahorradas", false], ["Aprobación del ciclo", false]] },
];

/* tarifas de ejemplo · ajustables por el equipo */
const tarifas = {
  simple: 8000,
  medio: 18000,
  complejo: 35000,
  integracion: 4500,
  whatsapp: 6500,
  ia: 12000,
  capacitacion: 5500,
  soporte: { "Sin mantenimiento": 0, "Básico · 8x5": 6000, "Prioritario · 24x7": 12500 },
  setupPct: 0.25,
  horaEquipo: 750,
};

const money = (n: number) => "$" + n.toLocaleString("es-MX");
const estadoColor = (e: string) => {
  if (["Activo", "Activa", "Conectado", "Ganada", "completa"].includes(e)) return ok;
  if (["Por vencer", "Revisión", "En desarrollo", "Negociación", "activa"].includes(e)) return warn;
  if (["Pago pendiente", "Pausado", "Pausada", "Perdida", "Inactivo", "Excluida"].includes(e)) return err;
  return info;
};

const clienteFields = [
  { key: "empresa", label: "Empresa" },
  { key: "contacto", label: "Contacto" },
  { key: "email", label: "Correo", type: "email", placeholder: "contacto@empresa.mx" },
  { key: "telefono", label: "Teléfono", placeholder: "+52 55 0000 0000" },
  { key: "mrr", label: "MRR mensual", type: "number", mono: true },
  { key: "vence", label: "Vencimiento", placeholder: "12 sep 2026" },
  { key: "servicios", label: "Servicios contratados", type: "checks", options: ["SEO", "Google Ads", "Meta Ads", "TikTok Ads", "Rediseño", "Software"], wide: true },
  { key: "notas", label: "Notas internas", type: "textarea", wide: true, placeholder: "Contexto de la cuenta, acuerdos, riesgos…" },
];

const servicioFields = [
  { key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) },
  { key: "servicio", label: "Servicio", type: "select", options: ["SEO", "Google Ads", "Meta Ads", "TikTok Ads", "Rediseño", "Software"] },
  { key: "responsable", label: "Responsable", type: "select", options: ["V. Mora", "R. Torres", "L. Ortiz", "J. Beltrán", "M. Salas"] },
  { key: "precio", label: "Precio mensual", type: "number", mono: true },
  { key: "inicio", label: "Inicio", placeholder: "01 sep 2026" },
  { key: "estado", label: "Estado", type: "select", options: ["Activo", "En desarrollo", "Revisión"] },
  { key: "alcance", label: "Alcance y entregables", type: "textarea", wide: true },
];

const nuevoClienteModal = (ui: any, onDone?: any) => ui.form({
  title: "Nuevo cliente",
  subtitle: "Alta en CRM, contrato base y asignación de servicios",
  cta: "Crear cliente",
  fields: clienteFields,
  onSubmit: (v: any) => { onDone && onDone(v); ui.toast("Cliente " + (v.empresa || "nuevo") + " creado"); },
});

/* ─────────────────────────  primitivas  ───────────────────────── */
function Badge({ label, color, t }: any) {
  return (
    <span style={{
      display: "inline-flex", alignItems: "center", gap: 6, fontSize: 11, fontWeight: 600,
      color, background: color + "1f", border: "1px solid " + color + "33",
      padding: "3px 9px", borderRadius: 999, whiteSpace: "nowrap",
    }}>
      <span style={{ width: 5, height: 5, borderRadius: 999, background: color }} />{label}
    </span>
  );
}

function Card({ t, children, pad = 20, style }: any) {
  return (
    <div style={{ background: t.card, border: "1px solid " + t.line, borderRadius: 14, padding: pad, ...style }}>
      {children}
    </div>
  );
}

function SectionTitle({ t, title, sub, action }: any) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
      <div>
        <h2 style={{ color: t.text }} className="text-[19px] font-bold tracking-tight">{title}</h2>
        {sub && <p style={{ color: t.dim }} className="text-[13px] mt-1">{sub}</p>}
      </div>
      {action}
    </div>
  );
}

function Btn({ t, children, variant = "ghost", onClick, icon: Icon, size = "md" }: any) {
  const base: any = {
    display: "inline-flex", alignItems: "center", gap: 7, fontWeight: 600, borderRadius: 9,
    fontSize: size === "sm" ? 12 : 13, padding: size === "sm" ? "6px 11px" : "8px 14px",
    cursor: "pointer", transition: "all .15s", whiteSpace: "nowrap",
  };
  const styles: any = {
    primary: { ...base, background: brand, color: "#fff", border: "1px solid " + brand },
    ghost: { ...base, background: "transparent", color: t.mid, border: "1px solid " + t.line },
    soft: { ...base, background: t.raised, color: t.text, border: "1px solid " + t.line },
  };
  return (
    <button onClick={onClick} style={styles[variant]}
      onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.background = variant === "primary" ? brandHover : t.hover; }}
      onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.background = variant === "primary" ? brand : variant === "soft" ? t.raised : "transparent"; }}>
      {Icon && <Icon size={size === "sm" ? 13 : 14} />}{children}
    </button>
  );
}

function Field({ t, field, value, onChange }: any) {
  const base: any = {
    width: "100%", background: t.inputBg, border: "1px solid " + t.line, borderRadius: 9,
    color: t.text, fontSize: 13, padding: "9px 11px", outline: "none",
    fontFamily: field.mono ? mono : undefined,
  };
  return (
    <label style={{ display: "block", gridColumn: field.wide ? "1 / -1" : undefined }}>
      <span style={{ color: t.dim, fontSize: 11, fontWeight: 700, letterSpacing: ".05em", textTransform: "uppercase" }}>
        {field.label}
      </span>
      <div style={{ marginTop: 6 }}>
        {field.type === "select" ? (
          <select value={value} onChange={(e) => onChange(e.target.value)} style={base}>
            {field.options.map((o: string) => <option key={o} value={o}>{o}</option>)}
          </select>
        ) : field.type === "textarea" ? (
          <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={field.rows || 3}
            placeholder={field.placeholder} style={{ ...base, resize: "vertical" }} />
        ) : field.type === "checks" ? (
          <div style={{ display: "flex", flexWrap: "wrap", gap: 7 }}>
            {field.options.map((o: string) => {
              const on = (value || []).includes(o);
              return (
                <button key={o} type="button"
                  onClick={() => onChange(on ? value.filter((x: string) => x !== o) : [...(value || []), o])}
                  style={{
                    display: "inline-flex", alignItems: "center", gap: 6, fontSize: 12, fontWeight: 600,
                    padding: "6px 11px", borderRadius: 999, cursor: "pointer",
                    color: on ? "#fff" : t.mid, background: on ? brand : "transparent",
                    border: "1px solid " + (on ? brand : t.line),
                  }}>
                  {on && <Check size={12} />}{o}
                </button>
              );
            })}
          </div>
        ) : (
          <input type={field.type || "text"} value={value} placeholder={field.placeholder}
            onChange={(e) => onChange(e.target.value)} style={base} />
        )}
      </div>
      {field.hint && <span style={{ color: t.dim, fontSize: 11, display: "block", marginTop: 5 }}>{field.hint}</span>}
    </label>
  );
}

function Modal({ t, modal, close }: any) {
  const [vals, setVals] = useState<Record<string, any>>(() => {
    const init: Record<string, any> = {};
    (modal.fields || []).forEach((f: any) => { init[f.key] = modal.values?.[f.key] ?? (f.type === "checks" ? [] : f.type === "select" ? f.options[0] : ""); });
    return init;
  });

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") close(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const danger = modal.tone === "danger";

  return (
    <div onClick={close}
      style={{ position: "fixed", inset: 0, zIndex: 90, background: "rgba(2,6,12,.66)", display: "flex", alignItems: "center", justifyContent: "center", padding: "5vh 5vw" }}>
      <div onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true"
        style={{ width: "80vw", maxWidth: 1180, maxHeight: "80vh", display: "flex", flexDirection: "column", background: t.card, border: "1px solid " + t.lineStrong, borderRadius: 16, boxShadow: "0 40px 90px -30px rgba(0,0,0,.75)" }}>
        <div className="flex items-start justify-between gap-4 p-5" style={{ borderBottom: "1px solid " + t.line }}>
          <div>
            <h3 style={{ color: t.text }} className="text-[17px] font-bold tracking-tight">{modal.title}</h3>
            {modal.subtitle && <p style={{ color: t.dim }} className="text-[12.5px] mt-1">{modal.subtitle}</p>}
          </div>
          <button onClick={close} aria-label="Cerrar"
            style={{ color: t.dim, background: "transparent", border: 0, cursor: "pointer", padding: 2 }}><X size={18} /></button>
        </div>

        <div className="p-6" style={{ flex: 1, overflowY: "auto" }}>
          {modal.kind === "detail" && (
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(340px, 1fr))", columnGap: 36 }}>
              {modal.rows.map(([k, v]: any, i: number) => (
                <div key={k} className="flex items-start justify-between gap-6 py-3"
                  style={{ borderBottom: "1px solid " + t.line }}>
                  <span style={{ color: t.dim }} className="text-[12.5px]">{k}</span>
                  <span style={{ color: t.text, fontFamily: mono, textAlign: "right" }} className="text-[12.5px]">{String(v)}</span>
                </div>
              ))}
            </div>
          )}

          {modal.kind === "confirm" && (
            <div className="flex items-start gap-3" style={{ maxWidth: 620 }}>
              <AlertTriangle size={20} color={danger ? err : warn} style={{ marginTop: 2, flexShrink: 0 }} />
              <p style={{ color: t.mid }} className="text-[14px] leading-relaxed">{modal.text}</p>
            </div>
          )}

          {modal.kind === "form" && (
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: 18 }}>
              {modal.fields.map((f: any) => (
                <Field key={f.key} t={t} field={f} value={vals[f.key]} onChange={(v: any) => setVals({ ...vals, [f.key]: v })} />
              ))}
            </div>
          )}

          {modal.kind === "custom" && modal.content}
        </div>

        <div className="flex items-center justify-end gap-2 p-5" style={{ borderTop: "1px solid " + t.line }}>
          {(modal.actions || []).map((a: any) => (
            <Btn key={a.label} t={t} onClick={() => { a.onClick && a.onClick(); close(); }}>{a.label}</Btn>
          ))}
          <Btn t={t} onClick={close}>{modal.kind === "form" || modal.kind === "confirm" ? "Cancelar" : "Cerrar"}</Btn>
          {(modal.kind === "form" || modal.kind === "confirm") && (
            <button onClick={() => { modal.onSubmit && modal.onSubmit(vals); close(); }}
              style={{
                display: "inline-flex", alignItems: "center", gap: 7, fontWeight: 700, fontSize: 13,
                padding: "9px 15px", borderRadius: 9, cursor: "pointer", color: "#fff",
                background: danger ? err : brand, border: "1px solid " + (danger ? err : brand),
              }}>
              {danger ? <Trash2 size={14} /> : <Check size={14} />}{modal.cta || "Guardar"}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

function Toasts({ t, toasts }: any) {
  return (
    <div style={{ position: "fixed", right: 18, bottom: 18, zIndex: 95, display: "flex", flexDirection: "column", gap: 9 }}>
      {toasts.map((x: any) => (
        <div key={x.id} style={{
          display: "flex", alignItems: "center", gap: 10, background: t.card,
          border: "1px solid " + (x.tone === "error" ? err + "66" : t.lineStrong), borderRadius: 11,
          padding: "11px 14px", minWidth: 264, boxShadow: "0 22px 50px -18px rgba(0,0,0,.7)",
        }}>
          {x.tone === "error" ? <AlertTriangle size={15} color={err} /> : <CheckCircle2 size={15} color={ok} />}
          <span style={{ color: t.text }} className="text-[12.5px]">{x.msg}</span>
        </div>
      ))}
    </div>
  );
}

function KpiCard({ t, label, value, delta, up, hint, icon: Icon }: any) {
  return (
    <Card t={t} pad={18}>
      <div className="flex items-start justify-between">
        <span style={{ color: t.dim }} className="text-[12px] font-semibold tracking-wide uppercase">{label}</span>
        <div style={{ width: 28, height: 28, borderRadius: 8, background: brand + "1f", display: "flex", alignItems: "center", justifyContent: "center" }}>
          <Icon size={14} color={accent} />
        </div>
      </div>
      <div style={{ color: t.text, fontFamily: mono }} className="text-[26px] font-bold mt-3 leading-none tracking-tight">{value}</div>
      <div className="flex items-center gap-2 mt-2.5">
        {delta && (
          <span style={{ color: up ? ok : err, fontFamily: mono }} className="text-[12px] font-semibold flex items-center gap-1">
            {up ? <ArrowUpRight size={12} /> : <ArrowDownRight size={12} />}{delta}
          </span>
        )}
        <span style={{ color: t.dim }} className="text-[12px]">{hint}</span>
      </div>
    </Card>
  );
}

/* patrón reutilizable de tabla de datos */
function DataTable({ t, columns, rows, filters = [], searchKeys = [], pageSize = 6, dense = false, onRowClick, editable = false, ui, entity = "registro", labelKey, onDelete, onEdit, editFields, onDetail, extraActions }: any) {
  const [q, setQ] = useState("");
  const [active, setActive] = useState<Record<string, string>>({});
  const [page, setPage] = useState(0);
  const [openFilters, setOpenFilters] = useState(false);
  const [edits, setEdits] = useState<Record<string, any>>({});
  const [menu, setMenu] = useState<number | null>(null);

  const filtered = useMemo(() => {
    return rows.filter((r: any) => {
      const matchQ = !q || searchKeys.some((k: string) => String(r[k] ?? "").toLowerCase().includes(q.toLowerCase()));
      const matchF = filters.every((f: any) => !active[f.key] || String(r[f.key]) === active[f.key]);
      return matchQ && matchF;
    });
  }, [rows, q, active]);

  const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const view = filtered.slice(page * pageSize, page * pageSize + pageSize);

  const th: any = {
    textAlign: "left", fontSize: 11, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase",
    color: t.dim, padding: dense ? "8px 12px" : "11px 14px", borderBottom: "1px solid " + t.line, whiteSpace: "nowrap",
  };
  const td: any = {
    fontSize: 13, color: t.text, padding: dense ? "7px 12px" : "12px 14px",
    borderBottom: "1px solid " + t.line, whiteSpace: "nowrap",
  };

  return (
    <div style={{ background: t.card, border: "1px solid " + t.line, borderRadius: 14, overflow: "hidden" }}>
      <div className="flex flex-wrap items-center gap-2 p-3" style={{ borderBottom: "1px solid " + t.line }}>
        <div className="flex items-center gap-2 flex-1 min-w-[180px]" style={{ background: t.inputBg, border: "1px solid " + t.line, borderRadius: 9, padding: "7px 11px" }}>
          <Search size={14} color={t.dim} />
          <input value={q} onChange={(e) => { setQ(e.target.value); setPage(0); }} placeholder="Buscar…"
            style={{ background: "transparent", border: 0, outline: "none", color: t.text, fontSize: 13, width: "100%" }} />
        </div>
        {filters.length > 0 && (
          <Btn t={t} icon={Filter} size="sm" onClick={() => setOpenFilters(!openFilters)}>
            Filtros{Object.values(active).filter(Boolean).length > 0 ? ` · ${Object.values(active).filter(Boolean).length}` : ""}
          </Btn>
        )}
        <Btn t={t} icon={Download} size="sm" onClick={() => ui && ui.toast(`Exportando ${filtered.length} ${entity}s a Excel…`)}>Excel</Btn>
        <span style={{ color: t.dim, fontFamily: mono }} className="text-[12px] ml-1">{filtered.length} registros</span>
      </div>

      {openFilters && filters.length > 0 && (
        <div className="flex flex-wrap gap-4 px-3 py-3" style={{ borderBottom: "1px solid " + t.line, background: t.raised }}>
          {filters.map((f: any) => (
            <div key={f.key}>
              <div style={{ color: t.dim }} className="text-[10px] font-bold uppercase tracking-wider mb-1.5">{f.label}</div>
              <div className="flex flex-wrap gap-1.5">
                {["", ...f.options].map((opt: string) => {
                  const on = (active[f.key] || "") === opt;
                  return (
                    <button key={opt || "all"} onClick={() => { setActive({ ...active, [f.key]: opt }); setPage(0); }}
                      style={{
                        fontSize: 12, fontWeight: 600, padding: "4px 10px", borderRadius: 999, cursor: "pointer",
                        color: on ? "#fff" : t.mid, background: on ? brand : "transparent",
                        border: "1px solid " + (on ? brand : t.line),
                      }}>
                      {opt || "Todos"}
                    </button>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      )}

      <div style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", minWidth: 720 }}>
          <thead>
            <tr>
              {columns.map((c: any) => <th key={c.key} style={{ ...th, textAlign: c.align || "left" }}>{c.label}</th>)}
              <th style={{ ...th, width: 44 }} />
            </tr>
          </thead>
          <tbody>
            {view.map((r: any, i: number) => (
              <tr key={i} onClick={() => onRowClick && onRowClick(r)}
                style={{ cursor: onRowClick ? "pointer" : "default", transition: "background .12s" }}
                onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.background = t.hover; }}
                onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.background = "transparent"; }}>
                {columns.map((c: any) => {
                  const key = i + ":" + c.key;
                  const val = edits[key] ?? r[c.key];
                  return (
                    <td key={c.key} style={{ ...td, textAlign: c.align || "left", fontFamily: c.mono ? mono : undefined, color: c.dim ? t.mid : t.text }}>
                      {c.render ? c.render(r, t)
                        : editable && c.edit
                          ? <input value={val} onChange={(e) => setEdits({ ...edits, [key]: e.target.value })}
                              onClick={(e) => e.stopPropagation()}
                              style={{ background: "transparent", border: "1px solid transparent", borderRadius: 6, color: t.text, fontSize: 13, padding: "3px 6px", width: c.width || 110, fontFamily: c.mono ? mono : undefined }}
                              onFocus={(e) => { e.currentTarget.style.borderColor = brand; e.currentTarget.style.background = t.inputBg; }}
                              onBlur={(e) => {
                                e.currentTarget.style.borderColor = "transparent";
                                e.currentTarget.style.background = "transparent";
                                if (ui && edits[key] !== undefined && edits[key] !== r[c.key]) ui.toast("Celda actualizada");
                              }} />
                          : String(val)}
                    </td>
                  );
                })}
                <td style={{ ...td, textAlign: "right", position: "relative" }}>
                  <button onClick={(e) => { e.stopPropagation(); setMenu(menu === i ? null : i); }}
                    style={{ color: t.dim, padding: 4, borderRadius: 6, cursor: "pointer", background: "transparent", border: 0 }}>
                    <MoreHorizontal size={15} />
                  </button>
                  {menu === i && (
                    <div style={{
                      position: "absolute", right: 10, top: "100%", zIndex: 20, background: t.raised,
                      border: "1px solid " + t.lineStrong, borderRadius: 10, padding: 5, minWidth: 158,
                      boxShadow: "0 18px 40px -12px rgba(0,0,0,.55)",
                    }}>
                      {[
                        ["Ver detalle", ExternalLink, () => (onDetail ? onDetail(r) : ui && ui.detail({
                          title: String(r[labelKey || columns[0].key]),
                          subtitle: "Detalle del " + entity,
                          rows: columns.map((c: any) => [c.label, Array.isArray(r[c.key]) ? r[c.key].join(", ") : r[c.key]]),
                          actions: extraActions ? extraActions(r) : undefined,
                        }))],
                        ["Editar", Pencil, () => ui && ui.form({
                          title: "Editar " + entity,
                          subtitle: String(r[labelKey || columns[0].key]),
                          fields: editFields || columns.filter((c: any) => !c.render).map((c: any) => ({ key: c.key, label: c.label })),
                          values: r,
                          cta: "Guardar cambios",
                          onSubmit: (v: any) => { onEdit ? onEdit(r, v) : ui.toast("Cambios guardados"); },
                        })],
                        ["Duplicar", Copy, () => ui && ui.toast(`${entity.charAt(0).toUpperCase() + entity.slice(1)} duplicado`)],
                        ["Eliminar", Trash2, () => ui && ui.confirm({
                          title: "Eliminar " + entity,
                          text: `Se eliminará "${r[labelKey || columns[0].key]}". Esta acción no se puede deshacer.`,
                          cta: "Eliminar",
                          onConfirm: () => { onDelete ? onDelete(r) : ui.toast("Registro eliminado"); },
                        })],
                      ].map(([label, Ic, action]: any) => (
                        <button key={label} onClick={(e) => { e.stopPropagation(); setMenu(null); action && action(); }}
                          style={{ display: "flex", alignItems: "center", gap: 8, width: "100%", textAlign: "left", fontSize: 12.5, color: t.text, padding: "7px 9px", borderRadius: 7, background: "transparent", border: 0, cursor: "pointer" }}
                          onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.background = t.hover; }}
                          onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.background = "transparent"; }}>
                          <Ic size={13} color={label === "Eliminar" ? err : t.dim} />
                          <span style={{ color: label === "Eliminar" ? err : t.text }}>{label}</span>
                        </button>
                      ))}
                    </div>
                  )}
                </td>
              </tr>
            ))}
            {view.length === 0 && (
              <tr><td colSpan={columns.length + 1} style={{ ...td, textAlign: "center", color: t.dim, padding: 40 }}>Sin resultados con estos filtros</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="flex items-center justify-between gap-3 p-3" style={{ borderTop: "1px solid " + t.line }}>
        <span style={{ color: t.dim, fontFamily: mono }} className="text-[12px]">
          {filtered.length === 0 ? 0 : page * pageSize + 1}–{Math.min(filtered.length, (page + 1) * pageSize)} de {filtered.length}
        </span>
        <div className="flex items-center gap-1.5">
          <Btn t={t} size="sm" onClick={() => setPage(Math.max(0, page - 1))}>Anterior</Btn>
          <span style={{ color: t.mid, fontFamily: mono }} className="text-[12px] px-2">{page + 1} / {pages}</span>
          <Btn t={t} size="sm" onClick={() => setPage(Math.min(pages - 1, page + 1))}>Siguiente</Btn>
        </div>
      </div>
    </div>
  );
}

/* patrón de flujo por fases con checklist */
function PhaseFlow({ t, fases, ciclo, ui }: any) {
  const [sel, setSel] = useState(fases.findIndex((f: any) => f.estado === "activa"));
  const [checks, setChecks] = useState<Record<string, boolean>>({});
  const fase = fases[sel < 0 ? 0 : sel];
  const isDone = (label: string, def: boolean) => (checks[fase.fase + "|" + label] === undefined ? def : checks[fase.fase + "|" + label]);
  const toggle = (label: string, def: boolean) => {
    const next = !isDone(label, def);
    setChecks({ ...checks, [fase.fase + "|" + label]: next });
    ui && ui.toast((next ? "Completado: " : "Reabierto: ") + label);
  };
  const done = fase.items.filter((i: any) => isDone(i[0], i[1])).length;

  return (
    <Card t={t} pad={0}>
      <div className="flex flex-wrap items-center gap-3 p-4" style={{ borderBottom: "1px solid " + t.line }}>
        {fases.map((f: any, i: number) => {
          const on = i === sel;
          const col = f.estado === "completa" ? ok : f.estado === "activa" ? warn : t.dim;
          return (
            <div key={f.fase} className="flex items-center gap-3">
              <button onClick={() => setSel(i)}
                style={{
                  display: "flex", alignItems: "center", gap: 8, padding: "7px 13px", borderRadius: 999,
                  background: on ? col + "1c" : "transparent", border: "1px solid " + (on ? col + "55" : t.line),
                  color: on ? t.text : t.mid, fontSize: 13, fontWeight: 600, cursor: "pointer",
                }}>
                {f.estado === "completa" ? <CheckCircle2 size={14} color={ok} /> : <Circle size={14} color={col} />}
                {f.fase}
              </button>
              {i < fases.length - 1 && <ChevronRight size={14} color={t.dim} />}
            </div>
          );
        })}
        {ciclo && (
          <div className="ml-auto flex items-center gap-2">
            <span style={{ color: t.dim }} className="text-[12px]">Ciclo</span>
            <Badge label={ciclo} color={info} t={t} />
          </div>
        )}
      </div>

      <div className="grid lg:grid-cols-[1.4fr_1fr]">
        <div className="p-5" style={{ borderRight: "1px solid " + t.line }}>
          <div className="flex items-center justify-between mb-4">
            <div>
              <div style={{ color: t.text }} className="text-[15px] font-bold">Checklist · {fase.fase}</div>
              <div style={{ color: t.dim }} className="text-[12px] mt-0.5">{done} de {fase.items.length} completados</div>
            </div>
            <span style={{ color: estadoColor(fase.estado), fontFamily: mono }} className="text-[13px] font-bold">
              {Math.round((done / fase.items.length) * 100)}%
            </span>
          </div>
          <div style={{ height: 4, background: t.raised, borderRadius: 999, overflow: "hidden" }}>
            <div style={{ height: "100%", width: `${(done / fase.items.length) * 100}%`, background: brand }} />
          </div>
          <div className="mt-5 space-y-1">
            {fase.items.map(([label, def]: any) => {
              const checked = isDone(label, def);
              return (
                <button key={label} type="button" onClick={() => toggle(label, def)}
                  className="flex items-center gap-3 px-3 py-2.5 rounded-lg w-full text-left"
                  style={{ background: checked ? t.hover : "transparent", border: 0, cursor: "pointer" }}>
                  {checked ? <CheckCircle2 size={16} color={ok} /> : <Circle size={16} color={t.dim} />}
                  <span style={{ color: checked ? t.mid : t.text, textDecoration: checked ? "line-through" : "none" }} className="text-[13px]">{label}</span>
                  {label.includes("Aprobación") && !checked && <span className="ml-auto"><Badge label="Requiere cliente" color={warn} t={t} /></span>}
                </button>
              );
            })}
          </div>
        </div>
        <div className="p-5">
          <div style={{ color: t.text }} className="text-[15px] font-bold">Aprobaciones y ciclo</div>
          <div className="mt-4 space-y-3">
            {[
              ["Responsable", "V. Mora"],
              ["Inicio de fase", "05 ago 2026"],
              ["Compromiso", "29 ago 2026"],
              ["Aprobado por", fase.estado === "completa" ? "Cliente · 04 ago" : "—"],
            ].map(([k, v]) => (
              <div key={k} className="flex items-center justify-between">
                <span style={{ color: t.dim }} className="text-[12.5px]">{k}</span>
                <span style={{ color: t.text, fontFamily: mono }} className="text-[12.5px]">{v}</span>
              </div>
            ))}
          </div>
          <div className="mt-5 flex flex-wrap gap-2">
            <Btn t={t} variant="primary" size="sm" icon={CheckCircle2}
              onClick={() => ui && ui.confirm({ title: "Marcar fase completa", text: "La fase " + fase.fase + " se cerrará con " + done + " de " + fase.items.length + " puntos completados y avanzará el ciclo.", cta: "Cerrar fase", onConfirm: () => ui.toast("Fase " + fase.fase + " marcada como completa") })}>Marcar fase completa</Btn>
            <Btn t={t} size="sm"
              onClick={() => ui && ui.form({ title: "Solicitar aprobación", subtitle: "Fase " + fase.fase, cta: "Enviar solicitud", fields: [{ key: "destinatario", label: "Destinatario", type: "select", options: ["Contacto principal", "Dirección de marketing", "Dirección general"] }, { key: "fecha", label: "Fecha límite", placeholder: "29 ago 2026" }, { key: "nota", label: "Mensaje", type: "textarea", wide: true, placeholder: "Resumen de entregables de la fase…" }], onSubmit: () => ui.toast("Solicitud de aprobación enviada") })}>Solicitar aprobación</Btn>
          </div>
        </div>
      </div>
    </Card>
  );
}

/* patrón kanban */
function Kanban({ t, cards, setCards, ui }: any) {
  const [drag, setDrag] = useState<string | null>(null);
  const [over, setOver] = useState<string | null>(null);

  return (
    <div style={{ display: "flex", gap: 12, overflowX: "auto", paddingBottom: 8 }}>
      {etapas.map((et) => {
        const items = cards.filter((c: any) => c.etapa === et);
        const total = items.reduce((s: number, c: any) => s + c.valor, 0);
        const isOver = over === et;
        return (
          <div key={et} onDragOver={(e) => { e.preventDefault(); setOver(et); }} onDragLeave={() => setOver(null)}
            onDrop={() => { if (drag) setCards(cards.map((c: any) => (c.id === drag ? { ...c, etapa: et } : c))); setDrag(null); setOver(null); }}
            style={{
              minWidth: 236, flex: "0 0 236px", background: isOver ? brand + "12" : t.card,
              border: "1px solid " + (isOver ? brand + "55" : t.line), borderRadius: 14, padding: 12,
            }}>
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <span style={{ width: 6, height: 6, borderRadius: 999, background: estadoColor(et) }} />
                <span style={{ color: t.text }} className="text-[13px] font-bold">{et}</span>
                <span style={{ color: t.dim, fontFamily: mono }} className="text-[11px]">{items.length}</span>
              </div>
              <button type="button" title="Nueva oportunidad"
                onClick={() => ui && ui.form({ title: "Nueva oportunidad", subtitle: "Etapa " + et, cta: "Crear oportunidad", fields: [{ key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) }, { key: "canal", label: "Canal", type: "select", options: ["Google Ads", "Meta Ads", "TikTok Ads", "SEO"] }, { key: "valor", label: "Valor", type: "number", mono: true }, { key: "owner", label: "Responsable", type: "select", options: ["R. Torres", "V. Mora", "L. Ortiz"] }], onSubmit: (v: any) => { setCards([{ id: "CV-" + Math.floor(1050 + Math.random() * 90), cliente: v.cliente, canal: v.canal, valor: Number(v.valor) || 0, etapa: et, owner: v.owner, fecha: "23 ago" }, ...cards]); ui.toast("Oportunidad creada en " + et); } })}
                style={{ background: "transparent", border: 0, color: t.dim, cursor: "pointer", padding: 2, display: "flex" }}>
                <Plus size={13} />
              </button>
            </div>
            <div style={{ color: t.dim, fontFamily: mono }} className="text-[11px] mb-3">{money(total)}</div>
            <div className="space-y-2">
              {items.map((c: any) => (
                <div key={c.id} draggable onDragStart={() => setDrag(c.id)} onDragEnd={() => { setDrag(null); setOver(null); }}
                  onClick={() => ui && ui.detail({
                    title: c.cliente + " · " + c.id,
                    subtitle: "Oportunidad en etapa " + c.etapa,
                    rows: [["ID", c.id], ["Cliente", c.cliente], ["Canal", c.canal], ["Valor", money(c.valor)], ["Etapa", c.etapa], ["Responsable", c.owner], ["Última actividad", c.fecha]],
                    actions: [{ label: "Marcar ganada", onClick: () => { setCards(cards.map((x: any) => (x.id === c.id ? { ...x, etapa: "Ganada" } : x))); ui.toast(c.id + " marcada como ganada"); } }],
                  })}
                  style={{
                    background: t.raised, border: "1px solid " + t.line, borderRadius: 11, padding: 12,
                    cursor: "grab", opacity: drag === c.id ? 0.45 : 1,
                  }}>
                  <div className="flex items-center justify-between">
                    <span style={{ color: t.dim, fontFamily: mono }} className="text-[10.5px]">{c.id}</span>
                    <span style={{ color: t.dim }} className="text-[10.5px]">{c.fecha}</span>
                  </div>
                  <div style={{ color: t.text }} className="text-[13px] font-semibold mt-2 leading-snug">{c.cliente}</div>
                  <div style={{ color: accent, fontFamily: mono }} className="text-[14px] font-bold mt-1.5">{money(c.valor)}</div>
                  <div className="flex items-center justify-between mt-3">
                    <span style={{ color: t.dim }} className="text-[11px]">{c.canal}</span>
                    <span style={{
                      width: 22, height: 22, borderRadius: 999, background: brand + "26", color: accent,
                      fontSize: 9.5, fontWeight: 700, display: "flex", alignItems: "center", justifyContent: "center",
                    }}>{c.owner.replace(/[^A-Z]/g, "").slice(0, 2)}</span>
                  </div>
                </div>
              ))}
              {items.length === 0 && (
                <div style={{ border: "1px dashed " + t.line, borderRadius: 11, padding: 18, textAlign: "center", color: t.dim, fontSize: 11.5 }}>
                  Arrastra aquí
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ─────────────────────────  módulos  ───────────────────────── */
function Dashboard({ t, ui }: any) {
  return (
    <div className="space-y-5">
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="MRR activo" value="$675,000" delta="+8.4%" up hint="vs. julio" icon={TrendingUp} />
        <KpiCard t={t} label="Clientes activos" value="24" delta="+2" up hint="este mes" icon={Users} />
        <KpiCard t={t} label="Pagos pendientes" value="$96,000" delta="3 facturas" hint="2 vencidas" icon={Clock} />
        <KpiCard t={t} label="Campañas activas" value="38" delta="+5" up hint="SEO + Ads" icon={Zap} />
      </div>

      <div className="grid xl:grid-cols-[1.55fr_1fr] gap-5">
        <Card t={t}>
          <SectionTitle t={t} title="Ingresos vs. inversión publicitaria" sub="Miles de MXN · últimos 6 meses"
            action={<Btn t={t} size="sm" icon={Download} onClick={() => ui.toast("Serie de ingresos exportada a Excel")}>Exportar</Btn>} />
          <div style={{ height: 264 }} data-om-raster>
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={ingresos} margin={{ top: 5, right: 8, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="gIng" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={brand} stopOpacity={0.35} />
                    <stop offset="100%" stopColor={brand} stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" tick={{ fontSize: 11, fill: t.dim }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11, fill: t.dim, fontFamily: mono }} axisLine={false} tickLine={false} width={40} />
                <Tooltip contentStyle={{ background: t.raised, border: "1px solid " + t.lineStrong, borderRadius: 10, fontSize: 12, color: t.text }} />
                <Legend wrapperStyle={{ fontSize: 12, color: t.dim }} />
                <Area type="monotone" dataKey="ingresos" name="Ingresos" stroke={brand} strokeWidth={2.5} fill="url(#gIng)" />
                <Area type="monotone" dataKey="inversion" name="Inversión" stroke={info} strokeWidth={2} strokeDasharray="5 4" fill="transparent" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Card>

        <Card t={t}>
          <SectionTitle t={t} title="Alertas de operación" sub="4 requieren acción" />
          <div className="space-y-2">
            {alertas.map((a) => {
              const col = a.tipo === "error" ? err : a.tipo === "warn" ? warn : info;
              return (
                <button key={a.texto} type="button" onClick={() => ui.detail({
                    title: "Alerta de operación",
                    subtitle: a.tipo === "error" ? "Prioridad crítica" : a.tipo === "warn" ? "Prioridad alta" : "Informativa",
                    rows: [["Detalle", a.texto], ["Tipo", a.tipo === "error" ? "Crítica" : a.tipo === "warn" ? "Advertencia" : "Informativa"], ["Detectada", "hace 2 h"], ["Responsable", "R. Torres"]],
                    actions: [{ label: "Ver todas las alertas", onClick: () => ui.goto("alertas") }],
                  })}
                  className="flex items-start gap-3 p-3 rounded-xl w-full text-left"
                  style={{ background: t.raised, border: "1px solid " + t.line, cursor: "pointer" }}>
                  <AlertTriangle size={15} color={col} style={{ marginTop: 2, flexShrink: 0 }} />
                  <span style={{ color: t.text }} className="text-[12.5px] leading-relaxed">{a.texto}</span>
                </button>
              );
            })}
          </div>
          <div className="mt-4"><Btn t={t} size="sm" onClick={() => ui.goto("alertas")}>Ver todas las alertas</Btn></div>
        </Card>
      </div>

      <div>
        <SectionTitle t={t} title="Clientes con vencimiento próximo" sub="Renovaciones de los próximos 30 días"
          action={<Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => nuevoClienteModal(ui)}>Nuevo cliente</Btn>} />
        <DataTable t={t} pageSize={4} searchKeys={["empresa", "contacto"]}
          filters={[{ key: "estado", label: "Estado", options: ["Activo", "Por vencer", "Pago pendiente", "Pausado"] }]}
          columns={[
            { key: "empresa", label: "Empresa" },
            { key: "contacto", label: "Contacto", dim: true },
            { key: "mrr", label: "MRR", align: "right", mono: true, render: (r: any) => money(r.mrr) },
            { key: "vence", label: "Vence", dim: true },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={clientes} ui={ui} entity="cliente" labelKey="empresa" onDetail={(r: any) => ui.openCliente(r)} />
      </div>
    </div>
  );
}

function CRM({ t, ui }: any) {
  const [sel, setSel] = useState<any>(null);
  const [rows, setRows] = useState<any[]>(clientes);
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="CRM Clientes" sub="24 empresas · $675,000 MRR agregado"
        action={<div className="flex gap-2">
          <Btn t={t} size="sm" icon={Download} onClick={() => ui.toast("Exportando " + rows.length + " clientes a Excel…")}>Exportar</Btn>
          <Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => nuevoClienteModal(ui, (v: any) => setRows([{ empresa: v.empresa || "Cliente nuevo", contacto: v.contacto || "—", servicios: v.servicios && v.servicios.length ? v.servicios : ["SEO"], mrr: Number(v.mrr) || 0, vence: v.vence || "por definir", estado: "Activo" }, ...rows]))}>Nuevo cliente</Btn>
        </div>} />
      <DataTable t={t} onRowClick={setSel} searchKeys={["empresa", "contacto"]}
        ui={ui} entity="cliente" labelKey="empresa" editFields={clienteFields}
        onDetail={(r: any) => setSel(r)}
        extraActions={(r: any) => [{ label: "Abrir cuenta completa", onClick: () => ui.openCliente(r) }]}
        onEdit={(r: any, v: any) => { setRows(rows.map((x) => (x.empresa === r.empresa ? { ...x, ...v, mrr: Number(v.mrr) || x.mrr, servicios: v.servicios && v.servicios.length ? v.servicios : x.servicios } : x))); ui.toast("Ficha de " + r.empresa + " actualizada"); }}
        onDelete={(r: any) => { setRows(rows.filter((x) => x.empresa !== r.empresa)); ui.toast("Cliente " + r.empresa + " eliminado", "error"); }}
        filters={[
          { key: "estado", label: "Estado", options: ["Activo", "Por vencer", "Pago pendiente", "Pausado"] },
        ]}
        columns={[
          { key: "empresa", label: "Empresa" },
          { key: "contacto", label: "Contacto", dim: true },
          { key: "servicios", label: "Servicios contratados", render: (r: any, tt: any) => (
            <div className="flex gap-1.5">{r.servicios.map((s: string) => <Badge key={s} label={s} color={info} t={tt} />)}</div>
          ) },
          { key: "mrr", label: "MRR", align: "right", mono: true, render: (r: any) => money(r.mrr) },
          { key: "vence", label: "Vencimiento", dim: true },
          { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
        ]}
        rows={rows} />

      {sel && (
        <div style={{ position: "fixed", inset: 0, zIndex: 60, display: "flex", justifyContent: "flex-end", background: "rgba(2,6,12,.6)" }}
          onClick={() => setSel(null)}>
          <div onClick={(e) => e.stopPropagation()}
            style={{ width: "min(460px, 100%)", height: "100%", background: t.card, borderLeft: "1px solid " + t.lineStrong, overflowY: "auto" }}>
            <div className="flex items-start justify-between gap-4 p-5" style={{ borderBottom: "1px solid " + t.line }}>
              <div>
                <div style={{ color: t.dim }} className="text-[11px] font-bold uppercase tracking-wider">Ficha de cliente</div>
                <h3 style={{ color: t.text }} className="text-[19px] font-bold mt-1.5">{sel.empresa}</h3>
                <div style={{ color: t.dim }} className="text-[12.5px] mt-1">{sel.contacto}</div>
              </div>
              <button onClick={() => setSel(null)} style={{ color: t.dim, background: "transparent", border: 0, cursor: "pointer" }}><X size={18} /></button>
            </div>
            <div className="p-5 space-y-5">
              <div className="grid grid-cols-2 gap-3">
                <Card t={t} pad={14}>
                  <div style={{ color: t.dim }} className="text-[11px] uppercase font-bold tracking-wider">MRR</div>
                  <div style={{ color: t.text, fontFamily: mono }} className="text-[19px] font-bold mt-1.5">{money(sel.mrr)}</div>
                </Card>
                <Card t={t} pad={14}>
                  <div style={{ color: t.dim }} className="text-[11px] uppercase font-bold tracking-wider">Estado</div>
                  <div className="mt-2"><Badge label={sel.estado} color={estadoColor(sel.estado)} t={t} /></div>
                </Card>
              </div>
              <div>
                <div style={{ color: t.text }} className="text-[13px] font-bold mb-2.5">Servicios contratados</div>
                <div className="space-y-2">
                  {servicios.filter((s) => s.cliente === sel.empresa).map((s) => (
                    <div key={s.servicio} className="flex items-center justify-between p-3 rounded-xl" style={{ background: t.raised, border: "1px solid " + t.line }}>
                      <div>
                        <div style={{ color: t.text }} className="text-[13px] font-semibold">{s.servicio}</div>
                        <div style={{ color: t.dim }} className="text-[11.5px] mt-0.5">{s.responsable} · desde {s.inicio}</div>
                      </div>
                      <div style={{ color: accent, fontFamily: mono }} className="text-[13px] font-bold">{money(s.precio)}</div>
                    </div>
                  ))}
                  {servicios.filter((s) => s.cliente === sel.empresa).length === 0 && (
                    <div style={{ color: t.dim }} className="text-[12.5px]">Sin servicios registrados en el catálogo.</div>
                  )}
                </div>
              </div>
              <div>
                <div style={{ color: t.text }} className="text-[13px] font-bold mb-2.5">Actividad reciente</div>
                <div className="space-y-3">
                  {[["Reporte de agosto enviado", "hace 2 días"], ["Junta de resultados", "hace 1 semana"], ["Renovación firmada", "hace 3 semanas"]].map(([a, b]) => (
                    <div key={a} className="flex items-center justify-between">
                      <span style={{ color: t.mid }} className="text-[12.5px]">{a}</span>
                      <span style={{ color: t.dim, fontFamily: mono }} className="text-[11.5px]">{b}</span>
                    </div>
                  ))}
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                <Btn t={t} variant="primary" size="sm" onClick={() => { const c = sel; setSel(null); ui.openCliente(c); }}>Abrir cuenta completa</Btn>
                <Btn t={t} size="sm" onClick={() => ui.confirm({ title: "Generar contrato", text: "Se generará el contrato de " + sel.empresa + " con la plantilla vigente v4 y se enviará a firma electrónica.", cta: "Generar y enviar", onConfirm: () => ui.toast("Contrato de " + sel.empresa + " enviado a firma") })}>Generar contrato</Btn>
                <Btn t={t} size="sm" onClick={() => ui.form({ title: "Registrar pago", subtitle: sel.empresa, cta: "Registrar pago", fields: [{ key: "monto", label: "Monto", type: "number", mono: true }, { key: "metodo", label: "Método", type: "select", options: ["Transferencia", "Tarjeta", "Efectivo"] }, { key: "fecha", label: "Fecha", placeholder: "23 ago 2026" }, { key: "folio", label: "Folio", mono: true, placeholder: "F-2026-0900" }], onSubmit: () => ui.toast("Pago registrado para " + sel.empresa) })}>Registrar pago</Btn>
                <Btn t={t} size="sm" onClick={() => ui.confirm({ title: "Pausar cuenta", tone: "danger", text: "Se pausarán los servicios activos de " + sel.empresa + " y la facturación recurrente se detiene al cierre del ciclo.", cta: "Pausar cuenta", onConfirm: () => ui.toast("Cuenta de " + sel.empresa + " pausada", "error") })}>Pausar</Btn>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function Servicios({ t, ui }: any) {
  const [rows, setRows] = useState<any[]>(servicios);
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Servicios" sub="Asignaciones activas por cliente y responsable"
        action={<Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Asignar servicio", subtitle: "Nueva asignación con responsable y precio", cta: "Asignar servicio", fields: servicioFields, onSubmit: (v: any) => { setRows([{ cliente: v.cliente, servicio: v.servicio, responsable: v.responsable, precio: Number(v.precio) || 0, inicio: v.inicio || "01 sep 2026", estado: v.estado || "Activo" }, ...rows]); ui.toast(v.servicio + " asignado a " + v.cliente); } })}>Asignar servicio</Btn>} />
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="SEO" value="9" hint="clientes activos" icon={Search} />
        <KpiCard t={t} label="Google Ads" value="12" hint="cuentas gestionadas" icon={Megaphone} />
        <KpiCard t={t} label="Meta / TikTok" value="7" hint="cuentas sociales" icon={Zap} />
        <KpiCard t={t} label="Software" value="4" hint="proyectos activos" icon={Code2} />
      </div>
      <DataTable t={t} searchKeys={["cliente", "servicio", "responsable"]}
        ui={ui} entity="servicio" labelKey="servicio" editFields={servicioFields}
        onEdit={(r: any, v: any) => { setRows(rows.map((x) => (x === r ? { ...x, ...v, precio: Number(v.precio) || x.precio } : x))); ui.toast("Servicio actualizado"); }}
        onDelete={(r: any) => { setRows(rows.filter((x) => x !== r)); ui.toast("Asignación eliminada", "error"); }}
        filters={[
          { key: "servicio", label: "Tipo de servicio", options: ["SEO", "Google Ads", "Meta Ads", "TikTok Ads", "Rediseño", "Software"] },
          { key: "estado", label: "Estado", options: ["Activo", "En desarrollo", "Revisión"] },
        ]}
        columns={[
          { key: "cliente", label: "Cliente" },
          { key: "servicio", label: "Servicio", render: (r: any, tt: any) => <Badge label={r.servicio} color={info} t={tt} /> },
          { key: "responsable", label: "Responsable", dim: true },
          { key: "precio", label: "Precio mensual", align: "right", mono: true, render: (r: any) => money(r.precio) },
          { key: "inicio", label: "Inicio", dim: true },
          { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
        ]}
        rows={rows} />
    </div>
  );
}

function SEOModule({ t, ui }: any) {
  const [tab, setTab] = useState("fases");
  const tabs = [["fases", "Fases del ciclo"], ["posiciones", "Posiciones"], ["backlinks", "Backlinks"], ["contenido", "Contenido"]];
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="SEO · Hotelera Costa" sub="Ciclo mensual · agosto 2026 · responsable V. Mora"
        action={<div className="flex gap-2">
          <Btn t={t} size="sm" onClick={() => ui.form({ title: "Cambiar de cliente", subtitle: "Ciclo SEO a visualizar", cta: "Ver ciclo", fields: [{ key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa), wide: true }], onSubmit: (v: any) => ui.toast("Mostrando ciclo SEO de " + v.cliente) })}>Cambiar cliente</Btn>
          <Btn t={t} variant="primary" size="sm" onClick={() => ui.confirm({ title: "Cerrar ciclo de agosto", text: "Se consolidan posiciones, tráfico y entregables del mes. El cliente recibirá el reporte para aprobación.", cta: "Cerrar ciclo", onConfirm: () => ui.toast("Ciclo de agosto cerrado y reporte enviado") })}>Cerrar ciclo</Btn>
        </div>} />
      <div className="flex gap-1.5 flex-wrap">
        {tabs.map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            style={{
              fontSize: 13, fontWeight: 600, padding: "7px 14px", borderRadius: 9, cursor: "pointer",
              color: tab === k ? t.text : t.dim, background: tab === k ? t.card : "transparent",
              border: "1px solid " + (tab === k ? t.lineStrong : "transparent"),
            }}>{label}</button>
        ))}
      </div>

      {tab === "fases" && <PhaseFlow t={t} ui={ui} fases={fasesSEO} ciclo="Ago 2026 · 3/12" />}

      {tab === "posiciones" && (
        <DataTable t={t} ui={ui} entity="keyword" labelKey="kw" searchKeys={["kw"]} pageSize={5}
          columns={[
            { key: "kw", label: "Keyword" },
            { key: "pos", label: "Posición", align: "right", mono: true },
            { key: "vol", label: "Volumen", align: "right", mono: true },
            { key: "url", label: "URL asignada", dim: true },
            { key: "fuente", label: "Fuente", dim: true },
          ]}
          rows={keywords.filter((k) => k.cliente === "Hotelera Costa" || true)} />
      )}

      {tab === "backlinks" && (
        <DataTable t={t} ui={ui} entity="backlink" labelKey="dominio" pageSize={5} searchKeys={["dominio"]}
          columns={[
            { key: "dominio", label: "Dominio" },
            { key: "dr", label: "DR", align: "right", mono: true },
            { key: "tipo", label: "Tipo" },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={[
            { dominio: "expansion.mx", dr: 82, tipo: "Prensa", estado: "Activo" },
            { dominio: "hoteleria.com.mx", dr: 54, tipo: "Directorio", estado: "Activo" },
            { dominio: "blogviajes.mx", dr: 38, tipo: "Guest post", estado: "Revisión" },
            { dominio: "turismo-gob.mx", dr: 71, tipo: "Institucional", estado: "Activo" },
          ]} />
      )}

      {tab === "contenido" && (
        <DataTable t={t} ui={ui} entity="pieza" labelKey="titulo" pageSize={5} searchKeys={["titulo"]}
          columns={[
            { key: "titulo", label: "Pieza" },
            { key: "tipo", label: "Tipo", dim: true },
            { key: "palabras", label: "Palabras", align: "right", mono: true },
            { key: "entrega", label: "Entrega", dim: true },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={[
            { titulo: "Guía: qué es un PMS hotelero", tipo: "Pilar", palabras: 2400, entrega: "12 ago", estado: "Activo" },
            { titulo: "Check-in digital: 5 beneficios", tipo: "Blog", palabras: 1200, entrega: "19 ago", estado: "Revisión" },
            { titulo: "Landing: software para hoteles boutique", tipo: "Landing", palabras: 900, entrega: "26 ago", estado: "En desarrollo" },
          ]} />
      )}
    </div>
  );
}

function Keywords({ t, ui }: any) {
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Banco de keywords" sub="1,842 términos · 24 clientes · fuentes Semrush, GSC, Ahrefs y GBP"
        action={<div className="flex gap-2">
          <Btn t={t} size="sm" icon={Download} onClick={() => ui.toast("Exportando banco de keywords a Excel…")}>Excel</Btn>
          <Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Importar keywords", subtitle: "Alta masiva al banco del cliente", cta: "Importar", fields: [{ key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) }, { key: "fuente", label: "Fuente", type: "select", options: ["Semrush", "GSC", "Ahrefs", "GBP"] }, { key: "lista", label: "Keywords (una por línea)", type: "textarea", rows: 5, wide: true, placeholder: "software hotelero méxico\npms para hoteles" }], onSubmit: (v: any) => ui.toast("Keywords importadas desde " + v.fuente) })}>Importar</Btn>
        </div>} />
      <DataTable t={t} dense searchKeys={["kw", "cliente", "url"]} pageSize={7}
        ui={ui} entity="keyword" labelKey="kw"
        extraActions={(r: any) => [{ label: "Asignar URL", onClick: () => ui.form({ title: "Asignar URL", subtitle: r.kw, cta: "Guardar", fields: [{ key: "url", label: "URL destino", wide: true, mono: true }], values: { url: r.url }, onSubmit: () => ui.toast("URL asignada a " + r.kw) }) }]}
        filters={[
          { key: "intencion", label: "Intención", options: ["Transaccional", "Comercial", "Informativa", "Local"] },
          { key: "cliente", label: "Cliente", options: ["Hotelera Costa", "Grupo Lumina", "FashionMX", "Café Origen"] },
          { key: "fuente", label: "Fuente", options: ["Semrush", "GSC", "Ahrefs", "GBP"] },
        ]}
        columns={[
          { key: "kw", label: "Keyword" },
          { key: "vol", label: "Vol.", align: "right", mono: true },
          { key: "dif", label: "Dif.", align: "right", mono: true, render: (r: any, tt: any) => (
            <span style={{ fontFamily: mono, color: r.dif > 55 ? err : r.dif > 35 ? warn : ok }}>{r.dif}</span>
          ) },
          { key: "cpc", label: "CPC", align: "right", mono: true, render: (r: any) => "$" + r.cpc.toFixed(2) },
          { key: "intencion", label: "Intención", render: (r: any, tt: any) => <Badge label={r.intencion} color={info} t={tt} /> },
          { key: "url", label: "URL asignada", dim: true },
          { key: "pos", label: "Pos.", align: "right", mono: true, render: (r: any) => (
            <span style={{ fontFamily: mono, color: r.pos <= 3 ? ok : r.pos <= 10 ? warn : err }}>#{r.pos}</span>
          ) },
          { key: "cliente", label: "Cliente", dim: true },
          { key: "fuente", label: "Fuente", dim: true },
        ]}
        rows={keywords} />
    </div>
  );
}

function Ads({ t, ui }: any) {
  const [tab, setTab] = useState("fases");
  const tabs = [["fases", "Fases"], ["grupos", "Grupos y keywords"], ["creativos", "Creativos"], ["bitacora", "Bitácora"]];
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Ads · Hotelera Costa" sub="Google Ads · Performance Max + Search · presupuesto $180,000/mes"
        action={<div className="flex gap-2">
          <Btn t={t} size="sm" icon={ExternalLink} onClick={() => ui.detail({ title: "Cuenta de Google Ads", subtitle: "Hotelera Costa", rows: [["ID de cuenta", "412-889-2033"], ["Acceso", "MCC RankPro"], ["Presupuesto diario", money(6000)], ["Última sincronización", "hace 12 min"], ["Conversiones importadas", "142"]], actions: [{ label: "Sincronizar ahora", onClick: () => ui.toast("Sincronización con Google Ads en curso…") }] })}>Google Ads</Btn>
          <Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Nueva campaña", subtitle: "Hotelera Costa · Google Ads", cta: "Crear campaña", fields: [{ key: "nombre", label: "Nombre" }, { key: "tipo", label: "Tipo", type: "select", options: ["Search", "Performance Max", "Display", "Video"] }, { key: "presupuesto", label: "Presupuesto diario", type: "number", mono: true }, { key: "objetivo", label: "Objetivo", type: "select", options: ["Conversiones", "Clics", "Alcance"] }, { key: "cpa", label: "CPA objetivo", type: "number", mono: true }, { key: "inicio", label: "Inicio", placeholder: "01 sep 2026" }, { key: "notas", label: "Notas", type: "textarea", wide: true }], onSubmit: (v: any) => ui.toast("Campaña " + (v.nombre || "nueva") + " creada en borrador") })}>Nueva campaña</Btn>
        </div>} />

      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="Inversión mes" value="$168,400" delta="93% del budget" hint="a 22 días" icon={Wallet} />
        <KpiCard t={t} label="Conversiones" value="142" delta="+18.2%" up hint="vs. julio" icon={Filter} />
        <KpiCard t={t} label="CPA" value="$1,186" delta="-12.4%" up hint="mejora" icon={TrendingUp} />
        <KpiCard t={t} label="ROAS" value="4.8x" delta="+0.6" up hint="objetivo 4.0x" icon={Zap} />
      </div>

      <div className="flex gap-1.5 flex-wrap">
        {tabs.map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            style={{
              fontSize: 13, fontWeight: 600, padding: "7px 14px", borderRadius: 9, cursor: "pointer",
              color: tab === k ? t.text : t.dim, background: tab === k ? t.card : "transparent",
              border: "1px solid " + (tab === k ? t.lineStrong : "transparent"),
            }}>{label}</button>
        ))}
      </div>

      {tab === "fases" && <PhaseFlow t={t} ui={ui} fases={fasesSEO} ciclo="Ago 2026 · 8/12" />}

      {tab === "grupos" && (
        <div className="space-y-4">
          <div className="flex items-center gap-2 flex-wrap">
            <Badge label="Grupo: PMS Marca" color={ok} t={t} />
            <Badge label="Grupo: Genéricos" color={info} t={t} />
            <Badge label="Grupo: Competencia" color={warn} t={t} />
            <span style={{ color: t.dim }} className="text-[12px] ml-2">Columnas personalizables · edición en línea</span>
          </div>
          <DataTable t={t} dense editable searchKeys={["kw"]} pageSize={5} ui={ui} entity="keyword" labelKey="kw"
            extraActions={(r: any) => [{ label: r.estado === "Pausada" ? "Reactivar" : "Pausar", onClick: () => ui.toast(r.kw + (r.estado === "Pausada" ? " reactivada" : " pausada")) }]}
            filters={[{ key: "estado", label: "Estado", options: ["Activa", "Pausada", "Excluida"] }, { key: "match", label: "Concordancia", options: ["Exacta", "Frase", "Amplia", "Negativa"] }]}
            columns={[
              { key: "kw", label: "Keyword", edit: true, width: 170 },
              { key: "match", label: "Concordancia" },
              { key: "cpc", label: "CPC máx.", align: "right", mono: true, edit: true, width: 70 },
              { key: "clics", label: "Clics", align: "right", mono: true },
              { key: "ctr", label: "CTR %", align: "right", mono: true },
              { key: "conv", label: "Conv.", align: "right", mono: true },
              { key: "cpa", label: "CPA", align: "right", mono: true, render: (r: any) => (r.cpa ? money(r.cpa) : "—") },
              { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
            ]}
            rows={adsKeywords} />
        </div>
      )}

      {tab === "creativos" && (
        <div className="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
          {[
            { t1: "Software hotelero todo en uno", d: "Reservas, check-in y reportes en una sola plataforma.", ctr: "7.9%", estado: "Activo" },
            { t1: "Deja el Excel para tu hotel", d: "Prueba 14 días gratis. Sin tarjeta.", ctr: "6.2%", estado: "Activo" },
            { t1: "PMS con soporte en español", d: "Implementación en 7 días con tu equipo.", ctr: "4.1%", estado: "Revisión" },
          ].map((c) => (
            <Card t={t} key={c.t1}>
              <div className="flex items-center justify-between mb-3">
                <Badge label={c.estado} color={estadoColor(c.estado)} t={t} />
                <span style={{ color: t.dim, fontFamily: mono }} className="text-[11.5px]">CTR {c.ctr}</span>
              </div>
              <div style={{ color: info }} className="text-[15px] font-semibold leading-snug">{c.t1}</div>
              <div style={{ color: t.mid }} className="text-[12.5px] mt-1.5 leading-relaxed">{c.d}</div>
              <div className="mt-4 flex gap-2">
                <Btn t={t} size="sm" onClick={() => ui.form({ title: "Editar creativo", subtitle: "CTR actual " + c.ctr, cta: "Guardar creativo", fields: [{ key: "t1", label: "Título", wide: true }, { key: "d", label: "Descripción", type: "textarea", wide: true }, { key: "estado", label: "Estado", type: "select", options: ["Activo", "Revisión", "Pausado"] }], values: c, onSubmit: () => ui.toast("Creativo actualizado") })}>Editar</Btn>
                <Btn t={t} size="sm" onClick={() => ui.toast("Creativo duplicado en borrador")}>Duplicar</Btn>
              </div>
            </Card>
          ))}
        </div>
      )}

      {tab === "bitacora" && (
        <Card t={t} pad={0}>
          <div className="p-4" style={{ borderBottom: "1px solid " + t.line }}>
            <div style={{ color: t.text }} className="text-[15px] font-bold">Bitácora de optimizaciones</div>
            <div style={{ color: t.dim }} className="text-[12.5px] mt-1">Toda acción queda registrada con autor y fecha</div>
          </div>
          <div className="p-4 space-y-3">
            {[
              ["22 ago", "R. Torres", "Excluidos 14 términos irrelevantes en grupo Genéricos", "-9% gasto inútil"],
              ["19 ago", "R. Torres", "Subida de puja en 'pms para hoteles' a $46", "+22 conv."],
              ["15 ago", "L. Ortiz", "Nuevo creativo 'Deja el Excel' en rotación", "CTR 6.2%"],
              ["11 ago", "R. Torres", "Pausa de campaña Display por CPA alto", "-$18,400"],
            ].map(([f, autor, txt, res]) => (
              <div key={txt} className="flex flex-wrap items-center gap-3 p-3 rounded-xl" style={{ background: t.raised, border: "1px solid " + t.line }}>
                <span style={{ color: t.dim, fontFamily: mono }} className="text-[11.5px] w-14">{f}</span>
                <span style={{ color: t.text }} className="text-[12.5px] flex-1 min-w-[220px]">{txt}</span>
                <Badge label={res} color={ok} t={t} />
                <span style={{ color: t.dim }} className="text-[11.5px]">{autor}</span>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}

function Conversiones({ t, ui }: any) {
  const [vista, setVista] = useState("grid");
  const [cards, setCards] = useState(conversiones);
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Conversiones" sub="Vista global cruzando los 24 clientes · edición en línea"
        action={
          <div className="flex items-center gap-2">
            <div style={{ display: "flex", background: t.card, border: "1px solid " + t.line, borderRadius: 9, padding: 3 }}>
              {[["grid", "Tabla"], ["kanban", "Kanban"]].map(([k, label]) => (
                <button key={k} onClick={() => setVista(k)}
                  style={{
                    fontSize: 12, fontWeight: 700, padding: "5px 12px", borderRadius: 7, cursor: "pointer",
                    color: vista === k ? "#fff" : t.dim, background: vista === k ? brand : "transparent", border: 0,
                  }}>{label}</button>
              ))}
            </div>
            <Btn t={t} size="sm" icon={Download} onClick={() => ui.toast("Exportando pipeline a Excel…")}>Excel</Btn>
          </div>
        } />

      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="Pipeline abierto" value="$418,000" delta="7 oportunidades" hint="este mes" icon={Filter} />
        <KpiCard t={t} label="Ganadas ago" value="$186,000" delta="+24%" up hint="vs. julio" icon={CheckCircle2} />
        <KpiCard t={t} label="Tasa de cierre" value="31%" delta="+4 pts" up hint="últimos 90 días" icon={TrendingUp} />
        <KpiCard t={t} label="Ciclo promedio" value="18 días" delta="-3 días" up hint="lead a cierre" icon={Clock} />
      </div>

      {vista === "grid" ? (
        <DataTable t={t} dense editable searchKeys={["id", "cliente", "owner"]} pageSize={7}
          ui={ui} entity="oportunidad" labelKey="id"
          onEdit={(r: any, v: any) => { setCards(cards.map((x) => (x.id === r.id ? { ...x, ...v, valor: Number(v.valor) || x.valor } : x))); ui.toast("Oportunidad " + r.id + " actualizada"); }}
          onDelete={(r: any) => { setCards(cards.filter((x) => x.id !== r.id)); ui.toast("Oportunidad " + r.id + " eliminada", "error"); }}
          extraActions={(r: any) => [{ label: "Marcar ganada", onClick: () => { setCards(cards.map((x) => (x.id === r.id ? { ...x, etapa: "Ganada" } : x))); ui.toast(r.id + " marcada como ganada"); } }]}
          filters={[
            { key: "etapa", label: "Etapa del embudo", options: etapas },
            { key: "canal", label: "Canal", options: ["Google Ads", "Meta Ads", "TikTok Ads", "SEO"] },
          ]}
          columns={[
            { key: "id", label: "ID", mono: true, dim: true },
            { key: "cliente", label: "Cliente" },
            { key: "canal", label: "Canal", render: (r: any, tt: any) => <Badge label={r.canal} color={info} t={tt} /> },
            { key: "valor", label: "Valor", align: "right", mono: true, edit: true, width: 90 },
            { key: "etapa", label: "Etapa", render: (r: any, tt: any) => <Badge label={r.etapa} color={estadoColor(r.etapa)} t={tt} /> },
            { key: "owner", label: "Responsable", dim: true },
            { key: "fecha", label: "Última act.", dim: true },
          ]}
          rows={cards} />
      ) : (
        <Kanban t={t} ui={ui} cards={cards} setCards={setCards} />
      )}
    </div>
  );
}

function Desarrollo({ t, ui }: any) {
  const [tab, setTab] = useState("fases");
  const tabs = [["fases", "Fases"], ["tareas", "Tareas"], ["qa", "QA y bugs"], ["comunicacion", "Comunicación"]];
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Desarrollo · TechCorp MX" sub="Plataforma interna · sprint 6 de 10 · J. Beltrán"
        action={<Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Nueva tarea", subtitle: "TechCorp MX · sprint 6", cta: "Crear tarea", fields: [{ key: "tarea", label: "Tarea", wide: true }, { key: "asignado", label: "Asignado", type: "select", options: ["J. Beltrán", "S. Nieto", "M. Salas"] }, { key: "estimado", label: "Estimación (h)", type: "number", mono: true }, { key: "sprint", label: "Sprint", type: "select", options: ["6", "7", "8"] }, { key: "estado", label: "Estado", type: "select", options: ["Activo", "En desarrollo", "Revisión"] }, { key: "detalle", label: "Detalle", type: "textarea", wide: true }], onSubmit: (v: any) => ui.toast("Tarea creada: " + (v.tarea || "sin título")) })}>Nueva tarea</Btn>} />
      <div className="flex gap-1.5 flex-wrap">
        {tabs.map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            style={{
              fontSize: 13, fontWeight: 600, padding: "7px 14px", borderRadius: 9, cursor: "pointer",
              color: tab === k ? t.text : t.dim, background: tab === k ? t.card : "transparent",
              border: "1px solid " + (tab === k ? t.lineStrong : "transparent"),
            }}>{label}</button>
        ))}
      </div>

      {tab === "fases" && <PhaseFlow t={t} ui={ui} fases={fasesDev} ciclo="Sprint 6" />}

      {tab === "tareas" && (
        <DataTable t={t} ui={ui} entity="tarea" labelKey="tarea" searchKeys={["tarea"]} pageSize={6}
          filters={[{ key: "estado", label: "Estado", options: ["Activo", "En desarrollo", "Revisión"] }]}
          columns={[
            { key: "tarea", label: "Tarea" },
            { key: "asignado", label: "Asignado", dim: true },
            { key: "estimado", label: "Est.", align: "right", mono: true },
            { key: "sprint", label: "Sprint", dim: true },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={[
            { tarea: "Panel admin · gestión de roles", asignado: "J. Beltrán", estimado: "13 h", sprint: "6", estado: "En desarrollo" },
            { tarea: "Integración ERP · endpoint facturas", asignado: "S. Nieto", estimado: "21 h", sprint: "6", estado: "En desarrollo" },
            { tarea: "Pasarela de pago · reintentos", asignado: "J. Beltrán", estimado: "8 h", sprint: "5", estado: "Revisión" },
            { tarea: "Módulo reservas · calendario", asignado: "M. Salas", estimado: "18 h", sprint: "5", estado: "Activo" },
          ]} />
      )}

      {tab === "qa" && (
        <DataTable t={t} ui={ui} entity="hallazgo" labelKey="bug" searchKeys={["bug"]} pageSize={6}
          extraActions={(r: any) => [{ label: "Asignar a sprint", onClick: () => ui.form({ title: "Asignar a sprint", subtitle: r.bug, cta: "Asignar", fields: [{ key: "sprint", label: "Sprint", type: "select", options: ["6", "7", "8"] }, { key: "dev", label: "Responsable", type: "select", options: ["J. Beltrán", "S. Nieto", "M. Salas"] }], onSubmit: () => ui.toast("Hallazgo asignado") }) }]}
          filters={[{ key: "severidad", label: "Severidad", options: ["Crítica", "Media", "Baja"] }]}
          columns={[
            { key: "bug", label: "Hallazgo" },
            { key: "modulo", label: "Módulo", dim: true },
            { key: "severidad", label: "Severidad", render: (r: any, tt: any) => (
              <Badge label={r.severidad} color={r.severidad === "Crítica" ? err : r.severidad === "Media" ? warn : info} t={tt} />
            ) },
            { key: "reporta", label: "Reporta", dim: true },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={[
            { bug: "Doble cargo al reintentar pago", modulo: "Pagos", severidad: "Crítica", reporta: "QA interno", estado: "En desarrollo" },
            { bug: "Calendario no respeta zona horaria", modulo: "Reservas", severidad: "Media", reporta: "Cliente", estado: "Revisión" },
            { bug: "Etiqueta cortada en móvil", modulo: "Panel", severidad: "Baja", reporta: "QA interno", estado: "Activo" },
          ]} />
      )}

      {tab === "comunicacion" && (
        <Card t={t} pad={0}>
          <div className="p-4" style={{ borderBottom: "1px solid " + t.line }}>
            <div style={{ color: t.text }} className="text-[15px] font-bold">Comunicaciones con cliente</div>
          </div>
          <div className="p-4 space-y-3">
            {[
              ["21 ago", "Cliente", "Solicitan agregar rol de auditor solo lectura", warn],
              ["18 ago", "RankPro", "Demo de módulo reservas entregada en staging", ok],
              ["14 ago", "Cliente", "Aprobación de UI del panel admin", ok],
            ].map(([f, quien, txt, col]: any) => (
              <div key={txt} className="flex flex-wrap items-center gap-3 p-3 rounded-xl" style={{ background: t.raised, border: "1px solid " + t.line }}>
                <span style={{ color: t.dim, fontFamily: mono }} className="text-[11.5px] w-14">{f}</span>
                <Badge label={quien} color={col} t={t} />
                <span style={{ color: t.text }} className="text-[12.5px] flex-1 min-w-[200px]">{txt}</span>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}

function Finanzas({ t, ui }: any) {
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Finanzas" sub="Agosto 2026 · cierre estimado el 31"
        action={<div className="flex gap-2">
          <Btn t={t} size="sm" icon={Download} onClick={() => ui.toast("Cierre de agosto exportado (PDF + Excel)")}>Exportar cierre</Btn>
          <Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Nueva factura", subtitle: "Emisión manual", cta: "Emitir factura", fields: [{ key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) }, { key: "monto", label: "Monto", type: "number", mono: true }, { key: "emision", label: "Emisión", placeholder: "23 ago" }, { key: "concepto", label: "Concepto", wide: true }], onSubmit: (v: any) => ui.toast("Factura emitida para " + v.cliente) })}>Nueva factura</Btn>
        </div>} />
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="MRR" value="$675,000" delta="+8.4%" up hint="recurrente" icon={TrendingUp} />
        <KpiCard t={t} label="Cobrado" value="$579,000" delta="85.8%" up hint="del mes" icon={Wallet} />
        <KpiCard t={t} label="Pendiente de cobro" value="$96,000" delta="3 facturas" hint="2 vencidas" icon={Clock} />
        <KpiCard t={t} label="Utilidad" value="$483,000" delta="50.8%" up hint="margen" icon={Zap} />
      </div>
      <Card t={t}>
        <SectionTitle t={t} title="Ingresos vs. gastos" sub="Miles de MXN" />
        <div style={{ height: 280 }} data-om-raster>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={gastos} margin={{ top: 5, right: 8, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
              <XAxis dataKey="m" tick={{ fontSize: 11, fill: t.dim }} axisLine={false} tickLine={false} />
              <YAxis tick={{ fontSize: 11, fill: t.dim, fontFamily: mono }} axisLine={false} tickLine={false} width={40} />
              <Tooltip contentStyle={{ background: t.raised, border: "1px solid " + t.lineStrong, borderRadius: 10, fontSize: 12, color: t.text }} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="ingresos" name="Ingresos" fill={brand} radius={[6, 6, 0, 0]} barSize={22} />
              <Bar dataKey="gastos" name="Gastos" fill={t.dim} radius={[6, 6, 0, 0]} barSize={22} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </Card>
      <DataTable t={t} searchKeys={["cliente"]} pageSize={5} ui={ui} entity="factura" labelKey="folio"
        extraActions={(r: any) => [
          { label: "Registrar pago", onClick: () => ui.form({ title: "Registrar pago", subtitle: r.folio + " · " + r.cliente, cta: "Registrar", fields: [{ key: "monto", label: "Monto", type: "number", mono: true }, { key: "metodo", label: "Método", type: "select", options: ["Transferencia", "Tarjeta", "Efectivo"] }, { key: "fecha", label: "Fecha", placeholder: "23 ago 2026" }], values: { monto: r.monto }, onSubmit: () => ui.toast("Pago de " + r.folio + " registrado") }) },
          { label: "Enviar recordatorio", onClick: () => ui.toast("Recordatorio enviado a " + r.cliente) },
        ]}
        filters={[{ key: "estado", label: "Estado", options: ["Cobrado", "Pendiente", "Vencido"] }]}
        columns={[
          { key: "folio", label: "Folio", mono: true, dim: true },
          { key: "cliente", label: "Cliente" },
          { key: "monto", label: "Monto", align: "right", mono: true, render: (r: any) => money(r.monto) },
          { key: "emision", label: "Emisión", dim: true },
          { key: "estado", label: "Estado", render: (r: any, tt: any) => (
            <Badge label={r.estado} color={r.estado === "Cobrado" ? ok : r.estado === "Pendiente" ? warn : err} t={tt} />
          ) },
        ]}
        rows={[
          { folio: "F-2026-0841", cliente: "TechCorp MX", monto: 145000, emision: "01 ago", estado: "Cobrado" },
          { folio: "F-2026-0842", cliente: "Constructora Vela", monto: 210000, emision: "01 ago", estado: "Cobrado" },
          { folio: "F-2026-0847", cliente: "Dental Sonrisa", monto: 28000, emision: "05 ago", estado: "Vencido" },
          { folio: "F-2026-0851", cliente: "FashionMX", monto: 54000, emision: "10 ago", estado: "Pendiente" },
          { folio: "F-2026-0853", cliente: "Café Origen", monto: 36000, emision: "12 ago", estado: "Pendiente" },
        ]} />
    </div>
  );
}

function Archivos({ t, ui }: any) {
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Archivos" sub="Repositorio por cliente · generación de contratos y propuestas"
        action={<div className="flex gap-2">
          <Btn t={t} size="sm" icon={Plus} onClick={() => ui.form({ title: "Subir archivo", subtitle: "Se guarda en la carpeta del cliente", cta: "Subir", fields: [{ key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) }, { key: "tipo", label: "Tipo", type: "select", options: ["Contrato", "Propuesta", "Reporte", "Brief"] }, { key: "nombre", label: "Nombre del archivo", wide: true, mono: true }, { key: "notas", label: "Notas", type: "textarea", wide: true }], onSubmit: (v: any) => ui.toast("Archivo subido a " + v.cliente) })}>Subir</Btn>
          <Btn t={t} variant="primary" size="sm" onClick={() => ui.form({ title: "Generar documento", subtitle: "Contrato o propuesta desde plantilla", cta: "Generar PDF", fields: [{ key: "tipo", label: "Documento", type: "select", options: ["Contrato", "Propuesta", "Reporte mensual"] }, { key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) }, { key: "plantilla", label: "Plantilla", type: "select", options: ["v4 vigente", "v3 legado"] }, { key: "vigencia", label: "Vigencia (meses)", type: "number", mono: true }], onSubmit: (v: any) => ui.toast(v.tipo + " de " + v.cliente + " generado") })}>Generar PDF</Btn>
        </div>} />
      <div className="grid sm:grid-cols-3 gap-4">
        {[["Contratos", 24, "Plantilla vigente v4"], ["Propuestas", 41, "Generadas este año"], ["Reportes", 186, "Últimos 12 meses"]].map(([k, n, sub]: any) => (
          <Card t={t} key={k}>
            <div className="flex items-center justify-between">
              <span style={{ color: t.dim }} className="text-[12px] font-semibold uppercase tracking-wide">{k}</span>
              <FolderOpen size={15} color={accent} />
            </div>
            <div style={{ color: t.text, fontFamily: mono }} className="text-[24px] font-bold mt-3">{n}</div>
            <div style={{ color: t.dim }} className="text-[12px] mt-1">{sub}</div>
          </Card>
        ))}
      </div>
      <DataTable t={t} searchKeys={["nombre", "cliente"]} pageSize={5} ui={ui} entity="archivo" labelKey="nombre"
        extraActions={(r: any) => [
          { label: "Descargar", onClick: () => ui.toast("Descargando " + r.nombre) },
          { label: "Compartir enlace", onClick: () => ui.toast("Enlace temporal copiado (7 días)") },
        ]}
        filters={[{ key: "tipo", label: "Tipo", options: ["Contrato", "Propuesta", "Reporte", "Brief"] }]}
        columns={[
          { key: "nombre", label: "Archivo" },
          { key: "cliente", label: "Cliente", dim: true },
          { key: "tipo", label: "Tipo", render: (r: any, tt: any) => <Badge label={r.tipo} color={info} t={tt} /> },
          { key: "peso", label: "Peso", align: "right", mono: true, dim: true },
          { key: "fecha", label: "Fecha", dim: true },
        ]}
        rows={archivos} />
    </div>
  );
}

function Roles({ t, ui }: any) {
  const permisos = ["Dashboard", "CRM", "Servicios", "SEO", "Keywords", "Ads", "Conversiones", "Reportes", "Automatizaciones", "Desarrollo", "Finanzas", "Archivos", "Roles", "Integraciones"];
  const [matriz, setMatriz] = useState<Record<string, number[]>>({
    "Administrador": permisos.map(() => 2),
    "Líder SEO": [2, 1, 1, 2, 2, 0, 1, 2, 0, 0, 0, 1, 0, 0],
    "Paid Media": [2, 1, 1, 0, 1, 2, 2, 2, 0, 0, 0, 1, 0, 0],
    "Desarrollo": [1, 1, 1, 0, 0, 0, 0, 1, 2, 2, 0, 1, 0, 1],
    "Finanzas": [2, 1, 1, 0, 0, 0, 1, 1, 1, 0, 2, 1, 0, 0],
  });
  const nivel = ["—", "Lectura", "Total"];
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Roles y usuarios" sub="5 roles · 16 usuarios · permisos por módulo"
        action={<Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Invitar usuario", subtitle: "Se envía un correo con acceso temporal", cta: "Enviar invitación", fields: [{ key: "nombre", label: "Nombre" }, { key: "email", label: "Correo", type: "email" }, { key: "rol", label: "Rol", type: "select", options: Object.keys(matriz) }, { key: "modulos", label: "Módulos visibles", type: "checks", options: permisos, wide: true }], onSubmit: (v: any) => ui.toast("Invitación enviada a " + (v.email || "el usuario")) })}>Invitar usuario</Btn>} />

      <Card t={t} pad={0}>
        <div className="p-4" style={{ borderBottom: "1px solid " + t.line }}>
          <div style={{ color: t.text }} className="text-[15px] font-bold">Matriz de permisos</div>
          <div style={{ color: t.dim }} className="text-[12.5px] mt-1">Clic en una celda para alternar entre sin acceso, lectura y total</div>
        </div>
        <div style={{ overflowX: "auto" }}>
          <table style={{ borderCollapse: "collapse", minWidth: 900, width: "100%" }}>
            <thead>
              <tr>
                <th style={{ textAlign: "left", padding: "11px 14px", fontSize: 11, textTransform: "uppercase", letterSpacing: ".06em", color: t.dim, borderBottom: "1px solid " + t.line }}>Rol</th>
                {permisos.map((p) => (
                  <th key={p} style={{ padding: "11px 8px", fontSize: 10.5, color: t.dim, borderBottom: "1px solid " + t.line, whiteSpace: "nowrap" }}>{p}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {Object.entries(matriz).map(([rol, vals]) => (
                <tr key={rol}>
                  <td style={{ padding: "11px 14px", fontSize: 13, color: t.text, fontWeight: 600, borderBottom: "1px solid " + t.line, whiteSpace: "nowrap" }}>{rol}</td>
                  {vals.map((v, i) => (
                    <td key={i} style={{ padding: "9px 8px", textAlign: "center", borderBottom: "1px solid " + t.line }}>
                      <button type="button"
                        onClick={() => { const next = (v + 1) % 3; setMatriz({ ...matriz, [rol]: matriz[rol].map((x, j) => (j === i ? next : x)) }); ui.toast(rol + " · " + permisos[i] + ": " + nivel[next]); }}
                        style={{
                          fontSize: 10, fontWeight: 700, padding: "3px 7px", borderRadius: 6, cursor: "pointer",
                          color: v === 2 ? ok : v === 1 ? info : t.dim,
                          background: v === 2 ? ok + "1c" : v === 1 ? info + "1c" : "transparent",
                          border: "1px solid " + (v === 0 ? t.line : "transparent"),
                        }}>{nivel[v]}</button>
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <div className="grid xl:grid-cols-[1fr_320px] gap-5">
        <DataTable t={t} searchKeys={["nombre", "email", "rol"]} pageSize={5} ui={ui} entity="usuario" labelKey="nombre"
          editFields={[{ key: "nombre", label: "Nombre" }, { key: "email", label: "Correo", type: "email" }, { key: "rol", label: "Rol", type: "select", options: Object.keys(matriz) }, { key: "estado", label: "Estado", type: "select", options: ["Activo", "Inactivo"] }]}
          extraActions={(r: any) => [
            { label: "Restablecer contraseña", onClick: () => ui.toast("Enlace de restablecimiento enviado a " + r.email) },
            { label: r.estado === "Activo" ? "Desactivar" : "Activar", onClick: () => ui.toast(r.nombre + (r.estado === "Activo" ? " desactivado" : " activado")) },
          ]}
          filters={[{ key: "estado", label: "Estado", options: ["Activo", "Inactivo"] }, { key: "rol", label: "Rol", options: Object.keys(matriz) }]}
          columns={[
            { key: "nombre", label: "Usuario" },
            { key: "email", label: "Correo", dim: true },
            { key: "rol", label: "Rol", render: (r: any, tt: any) => <Badge label={r.rol} color={info} t={tt} /> },
            { key: "ultimo", label: "Última sesión", dim: true },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={usuarios} />
        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold">Roles</div>
          <div className="mt-4 space-y-2">
            {roles.map((r) => (
              <button key={r.rol} type="button"
                onClick={() => ui.detail({
                  title: r.rol,
                  subtitle: r.usuarios + " usuarios · " + r.permisos + " módulos con acceso",
                  rows: permisos.map((p, i) => [p, nivel[(matriz[r.rol] || [])[i] || 0]]),
                  actions: [{ label: "Editar rol", onClick: () => ui.form({ title: "Editar rol", subtitle: r.rol, cta: "Guardar rol", fields: [{ key: "rol", label: "Nombre del rol" }, { key: "modulos", label: "Módulos con acceso", type: "checks", options: permisos, wide: true }], values: { rol: r.rol }, onSubmit: () => ui.toast("Rol " + r.rol + " actualizado") }) }],
                })}
                className="flex items-center justify-between p-3 rounded-xl w-full text-left"
                style={{ background: t.raised, border: "1px solid " + t.line, cursor: "pointer" }}>
                <div>
                  <div style={{ color: t.text }} className="text-[13px] font-semibold">{r.rol}</div>
                  <div style={{ color: t.dim }} className="text-[11.5px] mt-0.5">{r.usuarios} usuarios · {r.permisos} módulos</div>
                </div>
                <ChevronRight size={15} color={t.dim} />
              </button>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}

function Integraciones({ t, ui }: any) {
  const [copiado, setCopiado] = useState(false);
  const script = '<script src="https://track.rankpro.mx/t.js" data-token="rp_live_8f24c1a9"></script>';
  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Integraciones" sub="4 conectadas · 5 próximamente" />
      <div className="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        {integraciones.map((i) => {
          const conectado = i.estado === "Conectado";
          return (
            <Card t={t} key={i.nombre} style={{ opacity: conectado ? 1 : 0.72 }}>
              <div className="flex items-start justify-between">
                <div style={{ width: 34, height: 34, borderRadius: 9, background: conectado ? brand + "1f" : t.raised, display: "flex", alignItems: "center", justifyContent: "center" }}>
                  <Plug size={16} color={conectado ? accent : t.dim} />
                </div>
                <Badge label={i.estado} color={conectado ? ok : t.dim} t={t} />
              </div>
              <div style={{ color: t.text }} className="text-[14px] font-bold mt-3.5">{i.nombre}</div>
              <div style={{ color: t.dim }} className="text-[12.5px] mt-1 leading-relaxed">{i.desc}</div>
              <div className="mt-4">
                {conectado
                  ? <Btn t={t} size="sm" icon={Settings} onClick={() => ui.form({ title: "Configurar " + i.nombre, subtitle: "Cuenta conectada · última señal hace 12 min", cta: "Guardar configuración", fields: [{ key: "cuenta", label: "Cuenta / ID", mono: true }, { key: "frecuencia", label: "Sincronización", type: "select", options: ["Cada 15 min", "Cada hora", "Diaria"] }, { key: "clientes", label: "Clientes vinculados", type: "checks", options: clientes.slice(0, 5).map((c) => c.empresa), wide: true }, { key: "metricas", label: "Métricas a importar", type: "checks", options: ["Clics", "Costo", "Conversiones", "Impresiones"], wide: true }], onSubmit: () => ui.toast(i.nombre + " actualizado") })}>Configurar</Btn>
                  : <Btn t={t} size="sm" onClick={() => ui.toast("Te avisaremos cuando " + i.nombre + " esté disponible")}>Avisarme</Btn>}
              </div>
            </Card>
          );
        })}
      </div>

      <Card t={t}>
        <SectionTitle t={t} title="Tracking por cliente" sub="Hotelera Costa · token de producción"
          action={<Btn t={t} size="sm" onClick={() => ui.form({ title: "Cambiar de cliente", subtitle: "Token de tracking a mostrar", cta: "Ver token", fields: [{ key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa), wide: true }], onSubmit: (v: any) => ui.toast("Mostrando tracking de " + v.cliente) })}>Cambiar cliente</Btn>} />
        <div className="grid sm:grid-cols-3 gap-4 mb-5">
          <KpiCard t={t} label="Clics registrados" value="18,442" delta="+12.6%" up hint="últimos 30 días" icon={Filter} />
          <KpiCard t={t} label="Eventos" value="642" delta="conversiones" hint="mismo periodo" icon={CheckCircle2} />
          <KpiCard t={t} label="Estado del script" value="OK" hint="última señal hace 2 min" icon={Zap} />
        </div>
        <div style={{ color: t.dim }} className="text-[11px] font-bold uppercase tracking-wider mb-2">Script de instalación</div>
        <div className="flex items-center gap-3 p-3 rounded-xl" style={{ background: t.inputBg, border: "1px solid " + t.line }}>
          <code style={{ color: t.text, fontFamily: mono, fontSize: 12, flex: 1, overflowX: "auto", whiteSpace: "nowrap" }}>{script}</code>
          <Btn t={t} size="sm" icon={Copy} onClick={() => { setCopiado(true); ui.toast("Script copiado al portapapeles"); setTimeout(() => setCopiado(false), 1600); }}>
            {copiado ? "Copiado" : "Copiar"}
          </Btn>
        </div>
      </Card>
    </div>
  );
}

/* ─────────────────────────  reportes de crecimiento  ───────────────────────── */
function ChartBox({ t, title, sub, height = 220, children, action }: any) {
  return (
    <Card t={t}>
      <div className="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
          <div style={{ color: t.text }} className="text-[14.5px] font-bold tracking-tight">{title}</div>
          {sub && <div style={{ color: t.dim }} className="text-[12px] mt-1">{sub}</div>}
        </div>
        {action}
      </div>
      <div style={{ height }} data-om-raster>
        <ResponsiveContainer width="100%" height="100%">{children}</ResponsiveContainer>
      </div>
    </Card>
  );
}

const ejeX = (t: any) => ({ tick: { fontSize: 11, fill: t.dim }, axisLine: false, tickLine: false });
const ejeY = (t: any) => ({ tick: { fontSize: 11, fill: t.dim, fontFamily: mono }, axisLine: false, tickLine: false, width: 40 });
const tip = (t: any) => ({ background: t.raised, border: "1px solid " + t.lineStrong, borderRadius: 10, fontSize: 12, color: t.text });

const hoja = { bg: "#FFFFFF", text: "#0F172A", dim: "#64748B", line: "rgba(15,23,42,.12)", raised: "#F1F5F9", card: "#FFFFFF", lineStrong: "rgba(15,23,42,.18)", mid: "#475569", inputBg: "#F1F5F9", hover: "rgba(15,23,42,.03)" };

function PaginaPDF({ children, nota }: any) {
  return (
    <div style={{ background: hoja.bg, color: hoja.text, borderRadius: 10, border: "1px solid " + hoja.line, padding: 34, boxShadow: "0 18px 40px -22px rgba(0,0,0,.5)" }}>
      {children}
      {nota && (
        <div className="flex items-center justify-between mt-8 pt-3" style={{ borderTop: "1px solid " + hoja.line }}>
          <span style={{ color: hoja.dim, fontSize: 10.5 }}>RankPro · rankpro.mx · reporte confidencial</span>
          <span style={{ color: hoja.dim, fontSize: 10.5, fontFamily: mono }}>{nota}</span>
        </div>
      )}
    </div>
  );
}

function ExportPreview({ t, ui, cliente, periodo, canal }: any) {
  const [vista, setVista] = useState("pdf");
  const cols = ["Mes", "Tráfico", "Impresiones", "Conversiones", "CPA", "ROAS", "CTR", "Top 10"];
  return (
    <div>
      <div className="flex flex-wrap items-center gap-2 mb-5">
        <div style={{ display: "flex", background: t.raised, border: "1px solid " + t.line, borderRadius: 9, padding: 3 }}>
          {[["pdf", "Documento PDF"], ["excel", "Libro de Excel"]].map(([k, label]) => (
            <button key={k} onClick={() => setVista(k)}
              style={{ fontSize: 12, fontWeight: 700, padding: "5px 12px", borderRadius: 7, cursor: "pointer", color: vista === k ? "#fff" : t.dim, background: vista === k ? brand : "transparent", border: 0 }}>{label}</button>
          ))}
        </div>
        <span style={{ color: t.dim }} className="text-[12px]">{cliente} · {periodo} · {canal}</span>
      </div>

      {vista === "pdf" ? (
        <div className="space-y-5">
          <PaginaPDF nota="Página 1 de 3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <div style={{ width: 30, height: 30, borderRadius: 8, background: brand, color: "#fff", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 800, fontSize: 14 }}>R</div>
                <div>
                  <div style={{ fontWeight: 800, fontSize: 15, letterSpacing: "-0.01em" }}>RankPro</div>
                  <div style={{ color: hoja.dim, fontSize: 10.5 }}>Marketing digital y software</div>
                </div>
              </div>
              <span style={{ color: hoja.dim, fontSize: 11, fontFamily: mono }}>{periodo}</span>
            </div>
            <div style={{ marginTop: 68 }}>
              <div style={{ color: brand, fontSize: 12, fontWeight: 700, letterSpacing: ".14em", textTransform: "uppercase" }}>Reporte de crecimiento</div>
              <div style={{ fontSize: 38, fontWeight: 800, letterSpacing: "-0.02em", lineHeight: 1.08, marginTop: 10 }}>{cliente}</div>
              <div style={{ color: hoja.dim, fontSize: 14, marginTop: 12 }}>{canal} · resultados del período y plan del siguiente ciclo</div>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 18, marginTop: 64 }}>
              {[["Tráfico orgánico", "18,400", "+4.5%"], ["Conversiones", "142", "+7.6%"], ["Costo por conversión", "$1,186", "-7.6%"], ["ROAS", "4.8x", "+6.7%"]].map(([k, v, d]: any) => (
                <div key={k}>
                  <div style={{ color: hoja.dim, fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".06em" }}>{k}</div>
                  <div style={{ fontSize: 22, fontWeight: 800, fontFamily: mono, marginTop: 6 }}>{v}</div>
                  <div style={{ color: brand, fontSize: 11.5, fontWeight: 700, marginTop: 3 }}>{d}</div>
                </div>
              ))}
            </div>
            <div style={{ marginTop: 60, color: hoja.dim, fontSize: 11.5 }}>Preparado por Ricardo Torres · Director de operación · 23 ago 2026</div>
          </PaginaPDF>

          <PaginaPDF nota="Página 2 de 3">
            <div style={{ fontSize: 17, fontWeight: 800, letterSpacing: "-0.01em" }}>Evolución del período</div>
            <div style={{ color: hoja.dim, fontSize: 12, marginTop: 4 }}>Seis meses comparables · datos de GA4, Search Console y Google Ads</div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: 22, marginTop: 22 }}>
              <div>
                <div style={{ fontSize: 12, fontWeight: 700, marginBottom: 8 }}>Tráfico orgánico (miles)</div>
                <div style={{ height: 150 }} data-om-raster>
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={reporteSerie}>
                      <CartesianGrid strokeDasharray="3 5" stroke={hoja.line} vertical={false} />
                      <XAxis dataKey="m" {...ejeX(hoja)} />
                      <YAxis {...ejeY(hoja)} />
                      <Area type="monotone" dataKey="trafico" stroke={brand} strokeWidth={2.5} fill={brand + "24"} />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              </div>
              <div>
                <div style={{ fontSize: 12, fontWeight: 700, marginBottom: 8 }}>Keywords en top 10</div>
                <div style={{ height: 150 }} data-om-raster>
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={reporteSerie}>
                      <CartesianGrid strokeDasharray="3 5" stroke={hoja.line} vertical={false} />
                      <XAxis dataKey="m" {...ejeX(hoja)} />
                      <YAxis {...ejeY(hoja)} />
                      <Bar dataKey="top10" fill={brand} radius={[5, 5, 0, 0]} barSize={18} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </div>
              <div>
                <div style={{ fontSize: 12, fontWeight: 700, marginBottom: 8 }}>Conversiones y costo por conversión</div>
                <div style={{ height: 150 }} data-om-raster>
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={reporteSerie}>
                      <CartesianGrid strokeDasharray="3 5" stroke={hoja.line} vertical={false} />
                      <XAxis dataKey="m" {...ejeX(hoja)} />
                      <YAxis {...ejeY(hoja)} />
                      <Line type="monotone" dataKey="conv" stroke={brand} strokeWidth={2.5} dot={false} />
                      <Line type="monotone" dataKey="cpa" stroke={info} strokeWidth={2} strokeDasharray="5 4" dot={false} yAxisId={0} />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              </div>
              <div>
                <div style={{ fontSize: 12, fontWeight: 700, marginBottom: 8 }}>Inversión vs. ingresos (miles)</div>
                <div style={{ height: 150 }} data-om-raster>
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={reporteSerie}>
                      <CartesianGrid strokeDasharray="3 5" stroke={hoja.line} vertical={false} />
                      <XAxis dataKey="m" {...ejeX(hoja)} />
                      <YAxis {...ejeY(hoja)} />
                      <Bar dataKey="ingresos" fill={brand} radius={[5, 5, 0, 0]} barSize={12} />
                      <Bar dataKey="inversion" fill={hoja.dim} radius={[5, 5, 0, 0]} barSize={12} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </div>
            </div>
          </PaginaPDF>

          <PaginaPDF nota="Página 3 de 3">
            <div style={{ fontSize: 17, fontWeight: 800, letterSpacing: "-0.01em" }}>Tabla de datos y plan del siguiente ciclo</div>
            <table style={{ width: "100%", borderCollapse: "collapse", marginTop: 18 }}>
              <thead>
                <tr>
                  {cols.map((c) => (
                    <th key={c} style={{ textAlign: c === "Mes" ? "left" : "right", fontSize: 10, textTransform: "uppercase", letterSpacing: ".06em", color: hoja.dim, padding: "8px 6px", borderBottom: "1px solid " + hoja.lineStrong }}>{c}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {reporteSerie.map((r) => (
                  <tr key={r.m}>
                    <td style={{ fontSize: 11.5, padding: "8px 6px", borderBottom: "1px solid " + hoja.line, fontWeight: 600 }}>{r.m} 2026</td>
                    {[r.trafico + "K", r.impresiones + "K", r.conv, money(r.cpa), r.roas + "x", r.ctr + "%", r.top10].map((v, i) => (
                      <td key={i} style={{ fontSize: 11.5, fontFamily: mono, textAlign: "right", padding: "8px 6px", borderBottom: "1px solid " + hoja.line }}>{v}</td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
            <div style={{ fontSize: 13, fontWeight: 800, marginTop: 26 }}>Acciones aprobadas para el siguiente ciclo</div>
            <div style={{ marginTop: 10 }}>
              {recomendaciones.filter((r) => r.estado === "Aprobada").map((r) => (
                <div key={r.id} style={{ display: "flex", gap: 10, padding: "9px 0", borderBottom: "1px solid " + hoja.line }}>
                  <span style={{ color: brand, fontSize: 11.5, fontFamily: mono, width: 52 }}>{r.id}</span>
                  <span style={{ fontSize: 11.5, flex: 1 }}>{r.accion}</span>
                  <span style={{ color: hoja.dim, fontSize: 11 }}>{r.area} · impacto {r.impacto}</span>
                </div>
              ))}
            </div>
          </PaginaPDF>
        </div>
      ) : (
        <div style={{ background: hoja.bg, borderRadius: 10, border: "1px solid " + hoja.line, overflow: "hidden" }}>
          <div className="flex items-center gap-2 px-4" style={{ height: 40, borderBottom: "1px solid " + hoja.line, background: hoja.raised }}>
            <span style={{ color: hoja.text, fontSize: 12, fontWeight: 700 }}>Reporte_Crecimiento_Ago2026.xlsx</span>
            <span style={{ color: hoja.dim, fontSize: 11 }}>· hojas: Resumen · Serie mensual · Recomendaciones</span>
          </div>
          <div style={{ overflowX: "auto" }}>
            <table style={{ borderCollapse: "collapse", width: "100%", minWidth: 780 }}>
              <thead>
                <tr>
                  <th style={{ width: 34, background: hoja.raised, borderBottom: "1px solid " + hoja.line, borderRight: "1px solid " + hoja.line }} />
                  {["A", "B", "C", "D", "E", "F", "G", "H"].map((c) => (
                    <th key={c} style={{ fontSize: 10.5, color: hoja.dim, fontFamily: mono, padding: "5px 8px", background: hoja.raised, borderBottom: "1px solid " + hoja.line, borderRight: "1px solid " + hoja.line }}>{c}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style={{ fontSize: 10.5, color: hoja.dim, fontFamily: mono, textAlign: "center", background: hoja.raised, borderRight: "1px solid " + hoja.line, borderBottom: "1px solid " + hoja.line }}>1</td>
                  {cols.map((c) => (
                    <td key={c} style={{ fontSize: 11, fontWeight: 700, color: hoja.text, padding: "6px 8px", borderRight: "1px solid " + hoja.line, borderBottom: "1px solid " + hoja.line, background: brand + "14" }}>{c}</td>
                  ))}
                </tr>
                {reporteSerie.map((r, i) => (
                  <tr key={r.m}>
                    <td style={{ fontSize: 10.5, color: hoja.dim, fontFamily: mono, textAlign: "center", background: hoja.raised, borderRight: "1px solid " + hoja.line, borderBottom: "1px solid " + hoja.line }}>{i + 2}</td>
                    {[r.m + " 2026", r.trafico, r.impresiones, r.conv, r.cpa, r.roas, r.ctr, r.top10].map((v, j) => (
                      <td key={j} style={{ fontSize: 11, color: hoja.text, fontFamily: j === 0 ? undefined : mono, textAlign: j === 0 ? "left" : "right", padding: "6px 8px", borderRight: "1px solid " + hoja.line, borderBottom: "1px solid " + hoja.line }}>{v}</td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex items-center gap-3 px-4" style={{ height: 36, borderTop: "1px solid " + hoja.line, background: hoja.raised }}>
            {["Resumen", "Serie mensual", "Recomendaciones", "Gráficas"].map((h, i) => (
              <span key={h} style={{ fontSize: 11, fontWeight: i === 1 ? 700 : 500, color: i === 1 ? hoja.text : hoja.dim, borderBottom: i === 1 ? "2px solid " + brand : "none", paddingBottom: 2 }}>{h}</span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function Reportes({ t, ui }: any) {
  const [vista, setVista] = useState("interno");
  const [canal, setCanal] = useState("Consolidado");
  const [cliente, setCliente] = useState("Hotelera Costa");
  const [periodo, setPeriodo] = useState("Mensual · agosto 2026");
  const [recs, setRecs] = useState<any[]>(recomendaciones);

  const abrirExport = () => ui.custom({
    title: "Exportación del reporte",
    subtitle: cliente + " · " + periodo + " · vista previa del entregable",
    content: <ExportPreview t={t} ui={ui} cliente={cliente} periodo={periodo} canal={canal} />,
    actions: [
      { label: "Descargar PDF", onClick: () => ui.toast("Reporte_Crecimiento_Ago2026.pdf generado") },
      { label: "Descargar Excel", onClick: () => ui.toast("Reporte_Crecimiento_Ago2026.xlsx generado") },
      { label: "Guardar en Archivos", onClick: () => ui.toast("Reporte guardado en la carpeta de " + cliente) },
    ],
  });

  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Reportes de crecimiento" sub={cliente + " · " + periodo + " · " + canal}
        action={<div className="flex flex-wrap gap-2">
          <Btn t={t} size="sm" onClick={() => ui.form({ title: "Configurar reporte", subtitle: "Cliente, canal y período del reporte", cta: "Aplicar", fields: [{ key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) }, { key: "canal", label: "Canal", type: "select", options: ["Consolidado", "SEO", "Ads"] }, { key: "periodicidad", label: "Periodicidad", type: "select", options: ["Mensual", "Quincenal", "Trimestral", "Personalizado"] }, { key: "desde", label: "Desde", placeholder: "01 ago 2026" }, { key: "hasta", label: "Hasta", placeholder: "31 ago 2026" }, { key: "comparar", label: "Comparar contra", type: "select", options: ["Período anterior", "Mismo período del año pasado", "Promedio de 6 meses"] }], values: { cliente: cliente, canal: canal }, onSubmit: (v: any) => { setCliente(v.cliente); setCanal(v.canal); setPeriodo(v.periodicidad + " · " + (v.desde || "01 ago") + " a " + (v.hasta || "31 ago 2026")); ui.toast("Reporte reconstruido"); } })}>Configurar</Btn>
          <Btn t={t} size="sm" icon={FileText} onClick={abrirExport}>Vista previa PDF / Excel</Btn>
          <Btn t={t} variant="primary" size="sm" onClick={() => ui.confirm({ title: "Enviar reporte al cliente", text: "Se enviará el reporte de " + periodo + " a " + cliente + " con la vista resumen y el PDF adjunto.", cta: "Enviar reporte", onConfirm: () => ui.toast("Reporte enviado a " + cliente) })}>Enviar al cliente</Btn>
        </div>} />

      <div className="flex flex-wrap items-center gap-2">
        <div style={{ display: "flex", background: t.card, border: "1px solid " + t.line, borderRadius: 9, padding: 3 }}>
          {[["interno", "Vista interna"], ["cliente", "Vista cliente"]].map(([k, label]) => (
            <button key={k} onClick={() => setVista(k)}
              style={{ fontSize: 12, fontWeight: 700, padding: "5px 13px", borderRadius: 7, cursor: "pointer", color: vista === k ? "#fff" : t.dim, background: vista === k ? brand : "transparent", border: 0 }}>{label}</button>
          ))}
        </div>
        <div className="flex gap-1.5">
          {["Consolidado", "SEO", "Ads"].map((c) => (
            <button key={c} onClick={() => setCanal(c)}
              style={{ fontSize: 12, fontWeight: 600, padding: "6px 12px", borderRadius: 999, cursor: "pointer", color: canal === c ? t.text : t.dim, background: canal === c ? t.card : "transparent", border: "1px solid " + (canal === c ? t.lineStrong : t.line) }}>{c}</button>
          ))}
        </div>
      </div>

      {vista === "interno" ? (
        <div className="space-y-5">
          <div className="grid sm:grid-cols-2 xl:grid-cols-5 gap-4">
            <KpiCard t={t} label="Tráfico orgánico" value="18.4K" delta="+4.5%" up hint="vs. julio" icon={TrendingUp} />
            <KpiCard t={t} label="Top 10" value="28" delta="+3" up hint="keywords" icon={Search} />
            <KpiCard t={t} label="Conversiones" value="142" delta="+7.6%" up hint="todos los canales" icon={Filter} />
            <KpiCard t={t} label="CPA" value="$1,186" delta="-7.6%" up hint="mejora" icon={Gauge} />
            <KpiCard t={t} label="ROAS" value="4.8x" delta="+0.3" up hint="meta 5.0x" icon={Zap} />
          </div>

          <div className="grid xl:grid-cols-2 gap-5">
            <ChartBox t={t} title="Tráfico orgánico e impresiones" sub="Miles · 6 meses comparables">
              <AreaChart data={reporteSerie}>
                <defs>
                  <linearGradient id="gTraf" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={brand} stopOpacity={0.34} />
                    <stop offset="100%" stopColor={brand} stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Area type="monotone" dataKey="trafico" name="Tráfico (K)" stroke={brand} strokeWidth={2.5} fill="url(#gTraf)" />
                <Area type="monotone" dataKey="impresiones" name="Impresiones (K)" stroke={info} strokeWidth={2} strokeDasharray="5 4" fill="transparent" />
              </AreaChart>
            </ChartBox>

            <ChartBox t={t} title="Conversiones vs. costo por conversión" sub="Volumen y eficiencia por mes">
              <LineChart data={reporteSerie}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Line type="monotone" dataKey="conv" name="Conversiones" stroke={brand} strokeWidth={2.5} dot={{ r: 3 }} />
                <Line type="monotone" dataKey="cpa" name="CPA ($)" stroke={warn} strokeWidth={2} strokeDasharray="5 4" dot={false} />
              </LineChart>
            </ChartBox>

            <ChartBox t={t} title="Posiciones ganadas" sub="Keywords en top 10 por mes">
              <BarChart data={reporteSerie}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Bar dataKey="top10" name="Top 10" fill={brand} radius={[6, 6, 0, 0]} barSize={22} />
              </BarChart>
            </ChartBox>

            <ChartBox t={t} title="ROAS y CTR de Ads" sub="Rentabilidad y relevancia del anuncio">
              <LineChart data={reporteSerie}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Line type="monotone" dataKey="roas" name="ROAS (x)" stroke={accent} strokeWidth={2.5} dot={{ r: 3 }} />
                <Line type="monotone" dataKey="ctr" name="CTR (%)" stroke={info} strokeWidth={2} dot={false} />
              </LineChart>
            </ChartBox>
          </div>

          <div className="grid xl:grid-cols-[1fr_1fr] gap-5">
            <Card t={t} pad={0}>
              <div className="p-4" style={{ borderBottom: "1px solid " + t.line }}>
                <div style={{ color: t.text }} className="text-[15px] font-bold">Período vs. período</div>
                <div style={{ color: t.dim }} className="text-[12.5px] mt-1">Agosto contra julio 2026 · meta del trimestre</div>
              </div>
              <div style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse", minWidth: 520 }}>
                  <thead>
                    <tr>
                      {["Métrica", "Actual", "Anterior", "Δ", "Meta"].map((c, i) => (
                        <th key={c} style={{ textAlign: i === 0 ? "left" : "right", fontSize: 11, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: t.dim, padding: "10px 14px", borderBottom: "1px solid " + t.line }}>{c}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {comparativo.map((r) => (
                      <tr key={r.metrica}>
                        <td style={{ fontSize: 12.5, color: t.text, padding: "10px 14px", borderBottom: "1px solid " + t.line }}>{r.metrica}</td>
                        <td style={{ fontSize: 12.5, color: t.text, fontFamily: mono, textAlign: "right", padding: "10px 14px", borderBottom: "1px solid " + t.line }}>{r.actual}</td>
                        <td style={{ fontSize: 12.5, color: t.dim, fontFamily: mono, textAlign: "right", padding: "10px 14px", borderBottom: "1px solid " + t.line }}>{r.anterior}</td>
                        <td style={{ fontSize: 12.5, fontFamily: mono, textAlign: "right", padding: "10px 14px", borderBottom: "1px solid " + t.line, color: r.delta >= 0 ? ok : err }}>{(r.delta >= 0 ? "+" : "") + r.delta + "%"}</td>
                        <td style={{ fontSize: 12.5, color: t.mid, fontFamily: mono, textAlign: "right", padding: "10px 14px", borderBottom: "1px solid " + t.line }}>{r.meta}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>

            <ChartBox t={t} title="Leads y cierres por canal" sub="Atribución del período" height={286}>
              <BarChart data={leadsCanal}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="canal" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Bar dataKey="leads" name="Leads" fill={brand} radius={[6, 6, 0, 0]} barSize={18} />
                <Bar dataKey="cerrados" name="Cerrados" fill={info} radius={[6, 6, 0, 0]} barSize={18} />
              </BarChart>
            </ChartBox>
          </div>

          <div>
            <SectionTitle t={t} title="Recomendaciones del ciclo" sub="Generadas por reglas y editables por el equipo antes de aprobar"
              action={<div className="flex gap-2">
                <Btn t={t} size="sm" onClick={() => ui.toast("Reglas re-evaluadas · 2 hallazgos nuevos")}>Re-evaluar reglas</Btn>
                <Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Nueva recomendación", subtitle: "Alta manual del equipo", cta: "Agregar", fields: [{ key: "area", label: "Área", type: "select", options: ["SEO", "Ads"] }, { key: "owner", label: "Responsable", type: "select", options: ["V. Mora", "R. Torres", "L. Ortiz"] }, { key: "impacto", label: "Impacto", type: "select", options: ["Alto", "Medio", "Bajo"] }, { key: "esfuerzo", label: "Esfuerzo", type: "select", options: ["Bajo", "Medio", "Alto"] }, { key: "hallazgo", label: "Hallazgo", type: "textarea", wide: true }, { key: "accion", label: "Acción recomendada", type: "textarea", wide: true }], onSubmit: (v: any) => { setRecs([{ id: "R-1" + Math.floor(20 + Math.random() * 70), area: v.area, hallazgo: v.hallazgo, accion: v.accion, impacto: v.impacto, esfuerzo: v.esfuerzo, origen: "Equipo", estado: "En revisión", owner: v.owner }, ...recs]); ui.toast("Recomendación agregada"); } })}>Nueva recomendación</Btn>
              </div>} />
            <DataTable t={t} ui={ui} entity="recomendación" labelKey="id" dense editable pageSize={6}
              searchKeys={["hallazgo", "accion", "id"]}
              filters={[{ key: "area", label: "Área", options: ["SEO", "Ads"] }, { key: "estado", label: "Estado", options: ["Aprobada", "En revisión", "Descartada"] }, { key: "origen", label: "Origen", options: ["Regla", "Equipo"] }]}
              extraActions={(r: any) => [
                { label: "Aprobar", onClick: () => { setRecs(recs.map((x) => (x.id === r.id ? { ...x, estado: "Aprobada" } : x))); ui.toast(r.id + " aprobada para el ciclo"); } },
                { label: "Descartar", onClick: () => { setRecs(recs.map((x) => (x.id === r.id ? { ...x, estado: "Descartada" } : x))); ui.toast(r.id + " descartada"); } },
              ]}
              onEdit={(r: any, v: any) => { setRecs(recs.map((x) => (x.id === r.id ? { ...x, ...v } : x))); ui.toast(r.id + " actualizada"); }}
              onDelete={(r: any) => { setRecs(recs.filter((x) => x.id !== r.id)); ui.toast(r.id + " eliminada", "error"); }}
              columns={[
                { key: "id", label: "ID", mono: true, dim: true },
                { key: "area", label: "Área", render: (r: any, tt: any) => <Badge label={r.area} color={info} t={tt} /> },
                { key: "hallazgo", label: "Hallazgo", edit: true, width: 260 },
                { key: "accion", label: "Acción recomendada", edit: true, width: 250 },
                { key: "impacto", label: "Impacto", render: (r: any, tt: any) => <Badge label={r.impacto} color={r.impacto === "Alto" ? ok : r.impacto === "Medio" ? warn : t.dim} t={tt} /> },
                { key: "esfuerzo", label: "Esfuerzo", dim: true },
                { key: "origen", label: "Origen", dim: true },
                { key: "owner", label: "Responsable", dim: true },
                { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={r.estado === "Aprobada" ? ok : r.estado === "En revisión" ? warn : err} t={tt} /> },
              ]}
              rows={recs} />
          </div>
        </div>
      ) : (
        <div className="space-y-5">
          <Card t={t} pad={26} style={{ background: "linear-gradient(135deg, " + brand + "1c, transparent 62%)" }}>
            <div className="flex flex-wrap items-start justify-between gap-5">
              <div style={{ maxWidth: 620 }}>
                <div className="flex items-center gap-2.5">
                  <div style={{ width: 28, height: 28, borderRadius: 8, background: brand, color: "#fff", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 800, fontSize: 13 }}>R</div>
                  <span style={{ color: t.dim }} className="text-[12px] font-bold uppercase tracking-[0.14em]">RankPro · reporte de crecimiento</span>
                </div>
                <h2 style={{ color: t.text }} className="text-[27px] font-bold tracking-tight mt-4 leading-tight">{cliente} creció 4.5% en visitas y bajó 7.6% su costo por cliente</h2>
                <p style={{ color: t.mid }} className="text-[14px] mt-3 leading-relaxed">
                  En agosto llegaron más personas desde Google y cada nuevo cliente costó menos que el mes pasado. Las campañas devolvieron 4.8 pesos por cada peso invertido.
                </p>
              </div>
              <div className="flex flex-col gap-2">
                <Btn t={t} variant="primary" size="sm" icon={Download} onClick={abrirExport}>Descargar PDF</Btn>
                <Btn t={t} size="sm" onClick={() => ui.toast("Enlace del reporte copiado")}>Copiar enlace</Btn>
              </div>
            </div>
          </Card>

          <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <KpiCard t={t} label="Visitas desde Google" value="18,400" delta="+4.5%" up hint="personas este mes" icon={TrendingUp} />
            <KpiCard t={t} label="Nuevos clientes potenciales" value="142" delta="+7.6%" up hint="contactos reales" icon={Users} />
            <KpiCard t={t} label="Costo por cliente" value="$1,186" delta="-7.6%" up hint="cada vez más barato" icon={Wallet} />
            <KpiCard t={t} label="Retorno de la inversión" value="4.8x" delta="+0.3" up hint="por cada peso invertido" icon={Zap} />
          </div>

          <div className="grid xl:grid-cols-2 gap-5">
            <ChartBox t={t} title="Cuánta gente te encuentra en Google" sub="Visitas orgánicas por mes (miles)" height={250}>
              <AreaChart data={reporteSerie}>
                <defs>
                  <linearGradient id="gCli" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={brand} stopOpacity={0.4} />
                    <stop offset="100%" stopColor={brand} stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Area type="monotone" dataKey="trafico" name="Visitas (K)" stroke={brand} strokeWidth={3} fill="url(#gCli)" />
              </AreaChart>
            </ChartBox>

            <ChartBox t={t} title="Palabras clave en la primera página" sub="Búsquedas donde apareces arriba" height={250}>
              <BarChart data={reporteSerie}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Bar dataKey="top10" name="En top 10" fill={brand} radius={[8, 8, 0, 0]} barSize={26} />
              </BarChart>
            </ChartBox>

            <ChartBox t={t} title="Clientes potenciales conseguidos" sub="Formularios, llamadas y mensajes" height={250}>
              <AreaChart data={reporteSerie}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Area type="monotone" dataKey="conv" name="Contactos" stroke={accent} strokeWidth={3} fill={accent + "22"} />
              </AreaChart>
            </ChartBox>

            <ChartBox t={t} title="Lo que inviertes y lo que regresa" sub="Miles de pesos por mes" height={250}>
              <BarChart data={reporteSerie}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Bar dataKey="ingresos" name="Ingresos" fill={brand} radius={[7, 7, 0, 0]} barSize={16} />
                <Bar dataKey="inversion" name="Inversión" fill={t.dim} radius={[7, 7, 0, 0]} barSize={16} />
              </BarChart>
            </ChartBox>

            <ChartBox t={t} title="Costo por cliente potencial" sub="Mientras más bajo, mejor" height={230}>
              <LineChart data={reporteSerie}>
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                <XAxis dataKey="m" {...ejeX(t)} />
                <YAxis {...ejeY(t)} />
                <Tooltip contentStyle={tip(t)} />
                <Line type="monotone" dataKey="cpa" name="Costo ($)" stroke={warn} strokeWidth={3} dot={{ r: 3 }} />
              </LineChart>
            </ChartBox>

            <ChartBox t={t} title="De dónde llegan tus clientes" sub="Contactos por canal en el mes" height={230}>
              <BarChart data={leadsCanal} layout="vertical">
                <CartesianGrid strokeDasharray="3 5" stroke={t.line} horizontal={false} />
                <XAxis type="number" {...ejeX(t)} />
                <YAxis type="category" dataKey="canal" tick={{ fontSize: 11, fill: t.dim }} axisLine={false} tickLine={false} width={86} />
                <Tooltip contentStyle={tip(t)} />
                <Bar dataKey="leads" name="Contactos" fill={brand} radius={[0, 7, 7, 0]} barSize={16} />
              </BarChart>
            </ChartBox>
          </div>

          <div className="grid xl:grid-cols-2 gap-5">
            <Card t={t}>
              <div style={{ color: t.text }} className="text-[15px] font-bold mb-3.5">Lo que hicimos este mes</div>
              <div className="space-y-2.5">
                {["Corregimos la velocidad de 12 páginas en celular", "Publicamos 3 contenidos nuevos para búsquedas con intención de compra", "Ajustamos las campañas para dejar de pagar por búsquedas irrelevantes", "Sumamos 3 palabras clave nuevas a la primera página de Google"].map((x) => (
                  <div key={x} className="flex items-start gap-2.5">
                    <CheckCircle2 size={16} color={ok} style={{ marginTop: 2, flexShrink: 0 }} />
                    <span style={{ color: t.mid }} className="text-[13px] leading-relaxed">{x}</span>
                  </div>
                ))}
              </div>
            </Card>
            <Card t={t}>
              <div style={{ color: t.text }} className="text-[15px] font-bold mb-3.5">Lo que sigue el próximo mes</div>
              <div className="space-y-2.5">
                {recs.filter((r) => r.estado === "Aprobada").slice(0, 4).map((r) => (
                  <div key={r.id} className="flex items-start gap-2.5">
                    <ArrowUpRight size={16} color={accent} style={{ marginTop: 2, flexShrink: 0 }} />
                    <span style={{ color: t.mid }} className="text-[13px] leading-relaxed">{r.accion}</span>
                  </div>
                ))}
              </div>
              <div className="mt-5"><Btn t={t} size="sm" onClick={() => ui.form({ title: "Agendar junta de resultados", subtitle: cliente, cta: "Agendar", fields: [{ key: "fecha", label: "Fecha", placeholder: "29 ago 2026" }, { key: "hora", label: "Hora", placeholder: "11:00" }, { key: "canal", label: "Canal", type: "select", options: ["Google Meet", "Presencial", "Teléfono"] }], onSubmit: () => ui.toast("Junta de resultados agendada") })}>Agendar junta de resultados</Btn></div>
            </Card>
          </div>
        </div>
      )}
    </div>
  );
}

/* ─────────────────────────  automatizaciones  ───────────────────────── */
function FichaFlujo({ t, ui, flujo }: any) {
  const ahorroMXN = flujo.horas * tarifas.horaEquipo;
  return (
    <div className="space-y-5">
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="Horas ahorradas / mes" value={String(flujo.horas)} delta={money(ahorroMXN) + " / mes"} up hint="al costo del equipo" icon={Clock} />
        <KpiCard t={t} label="Ejecuciones" value={flujo.ejecuciones.toLocaleString("es-MX")} hint="últimos 30 días" icon={Zap} />
        <KpiCard t={t} label="Tasa de éxito" value={flujo.exito + "%"} hint="sin intervención humana" icon={CheckCircle2} />
        <KpiCard t={t} label="Complejidad" value={flujo.complejidad} hint={flujo.integraciones.length + " integraciones"} icon={Gauge} />
      </div>

      <div className="grid xl:grid-cols-[1fr_1fr] gap-5">
        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold mb-3.5">Definición del flujo</div>
          <div className="space-y-2.5">
            {[["ID", flujo.id], ["Cliente", flujo.cliente], ["Tipo", flujo.tipo], ["Complejidad", flujo.complejidad], ["Fase actual", flujo.fase], ["Estado", flujo.estado], ["Disparador", "Mensaje entrante / evento del sistema"], ["Escalamiento", "A humano tras 2 intentos sin coincidencia"]].map(([k, v]: any) => (
              <div key={k} className="flex items-start justify-between gap-6 py-1.5" style={{ borderBottom: "1px solid " + t.line }}>
                <span style={{ color: t.dim }} className="text-[12.5px]">{k}</span>
                <span style={{ color: t.text, fontFamily: mono, textAlign: "right" }} className="text-[12px]">{v}</span>
              </div>
            ))}
          </div>
          <div className="mt-4 flex flex-wrap gap-1.5">
            {flujo.integraciones.map((i: string) => <Badge key={i} label={i} color={info} t={t} />)}
          </div>
        </Card>

        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold mb-3.5">Ejecuciones recientes</div>
          <div className="space-y-2">
            {[["hoy 09:41", "Recordatorio enviado · 128 destinatarios", ok], ["hoy 08:10", "3 mensajes escalados a atención humana", warn], ["ayer 19:22", "Sincronización con el sistema del cliente", ok], ["ayer 12:04", "Reintento por timeout de la API", warn]].map(([f, txt, col]: any) => (
              <div key={txt} className="flex items-center gap-3 p-2.5 rounded-xl" style={{ background: t.raised, border: "1px solid " + t.line }}>
                <span style={{ color: t.dim, fontFamily: mono }} className="text-[11px] w-20">{f}</span>
                <span style={{ color: t.text }} className="text-[12.5px] flex-1">{txt}</span>
                <span style={{ width: 6, height: 6, borderRadius: 999, background: col }} />
              </div>
            ))}
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            <Btn t={t} size="sm" onClick={() => ui.toast("Flujo ejecutado en modo prueba")}>Probar flujo</Btn>
            <Btn t={t} size="sm" onClick={() => ui.toast(flujo.estado === "Pausado" ? "Flujo reactivado" : "Flujo pausado")}>{flujo.estado === "Pausado" ? "Reactivar" : "Pausar"}</Btn>
            <Btn t={t} size="sm" onClick={() => ui.toast("Bitácora completa exportada")}>Exportar bitácora</Btn>
          </div>
        </Card>
      </div>
    </div>
  );
}

function Automatizaciones({ t, ui }: any) {
  const [tab, setTab] = useState("flujos");
  const [rows, setRows] = useState<any[]>(flujos);
  const tabs = [["flujos", "Flujos"], ["clientes", "Clientes"], ["fases", "Fases del proyecto"], ["cotizador", "Calculadora de precios"]];
  const horas = rows.reduce((a, f) => a + f.horas, 0);
  const porCliente = clientes.map((c) => {
    const fs = rows.filter((f) => f.cliente === c.empresa);
    return { empresa: c.empresa, flujos: fs.length, horas: fs.reduce((a, f) => a + f.horas, 0), ahorro: fs.reduce((a, f) => a + f.horas, 0) * tarifas.horaEquipo, estado: fs.length ? (fs.some((f) => f.estado === "Pausado") ? "Pausado" : "Activo") : "Sin automatizar" };
  }).filter((c) => c.flujos > 0);

  const abrirFicha = (f: any) => ui.custom({
    title: f.nombre,
    subtitle: f.cliente + " · " + f.id + " · " + f.tipo,
    content: <FichaFlujo t={t} ui={ui} flujo={f} />,
    actions: [{ label: "Ver fases del proyecto", onClick: () => setTab("fases") }],
  });

  const flujoFields = [
    { key: "cliente", label: "Cliente", type: "select", options: clientes.map((c) => c.empresa) },
    { key: "nombre", label: "Nombre del flujo" },
    { key: "tipo", label: "Tipo", type: "select", options: tiposAuto, wide: true },
    { key: "complejidad", label: "Complejidad", type: "select", options: ["Simple", "Medio", "Complejo"] },
    { key: "horas", label: "Horas ahorradas / mes", type: "number", mono: true },
    { key: "integraciones", label: "Integraciones", type: "checks", options: ["WhatsApp API", "CRM", "ERP", "POS", "Google Sheets", "Google Calendar", "Stripe", "Drive", "Slack", "Base de conocimiento"], wide: true },
    { key: "notas", label: "Proceso que reemplaza", type: "textarea", wide: true },
  ];

  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Automatizaciones" sub={rows.length + " flujos · " + horas + " horas ahorradas al mes · WhatsApp y procesos de negocio"}
        action={<div className="flex flex-wrap gap-2">
          <Btn t={t} size="sm" icon={Calculator} onClick={() => setTab("cotizador")}>Cotizar</Btn>
          <Btn t={t} variant="primary" size="sm" icon={Plus} onClick={() => ui.form({ title: "Nueva automatización", subtitle: "Alta del flujo y arranque en fase de descubrimiento", cta: "Crear flujo", fields: flujoFields, onSubmit: (v: any) => { setRows([{ id: "AU-0" + Math.floor(42 + Math.random() * 50), cliente: v.cliente, nombre: v.nombre || "Flujo nuevo", tipo: v.tipo, complejidad: v.complejidad, integraciones: v.integraciones && v.integraciones.length ? v.integraciones : ["WhatsApp API"], horas: Number(v.horas) || 0, ejecuciones: 0, exito: 100, estado: "Revisión", fase: "Descubrimiento" }, ...rows]); ui.toast("Automatización creada para " + v.cliente); } })}>Nueva automatización</Btn>
        </div>} />

      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="Flujos activos" value={String(rows.filter((f) => f.estado === "Activo").length)} delta={"+" + rows.filter((f) => f.estado === "Revisión").length + " en revisión"} up hint="en 7 clientes" icon={Workflow} />
        <KpiCard t={t} label="Horas ahorradas / mes" value={String(horas)} delta="+18%" up hint="vs. julio" icon={Clock} />
        <KpiCard t={t} label="Ahorro estimado" value={money(horas * tarifas.horaEquipo)} hint="al costo hora del equipo" icon={Wallet} />
        <KpiCard t={t} label="Mensajes WhatsApp" value="18.4K" delta="99.1% entregados" up hint="últimos 30 días" icon={MessageSquare} />
      </div>

      <div className="flex gap-1.5 flex-wrap">
        {tabs.map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            style={{ fontSize: 13, fontWeight: 600, padding: "7px 14px", borderRadius: 9, cursor: "pointer", color: tab === k ? t.text : t.dim, background: tab === k ? t.card : "transparent", border: "1px solid " + (tab === k ? t.lineStrong : "transparent") }}>{label}</button>
        ))}
      </div>

      {tab === "flujos" && (
        <DataTable t={t} ui={ui} entity="flujo" labelKey="nombre" pageSize={6} onRowClick={abrirFicha} onDetail={abrirFicha}
          searchKeys={["nombre", "cliente", "tipo", "id"]}
          filters={[
            { key: "complejidad", label: "Complejidad", options: ["Simple", "Medio", "Complejo"] },
            { key: "estado", label: "Estado", options: ["Activo", "Revisión", "Pausado"] },
            { key: "fase", label: "Fase", options: fasesAuto.map((f) => f.fase) },
          ]}
          editFields={flujoFields}
          extraActions={(r: any) => [
            { label: r.estado === "Pausado" ? "Reactivar flujo" : "Pausar flujo", onClick: () => { setRows(rows.map((x) => (x.id === r.id ? { ...x, estado: r.estado === "Pausado" ? "Activo" : "Pausado" } : x))); ui.toast(r.id + (r.estado === "Pausado" ? " reactivado" : " pausado")); } },
            { label: "Ver ficha completa", onClick: () => abrirFicha(r) },
          ]}
          onEdit={(r: any, v: any) => { setRows(rows.map((x) => (x.id === r.id ? { ...x, ...v, horas: Number(v.horas) || x.horas, integraciones: v.integraciones && v.integraciones.length ? v.integraciones : x.integraciones } : x))); ui.toast(r.id + " actualizado"); }}
          onDelete={(r: any) => { setRows(rows.filter((x) => x.id !== r.id)); ui.toast(r.id + " eliminado", "error"); }}
          columns={[
            { key: "id", label: "ID", mono: true, dim: true },
            { key: "nombre", label: "Flujo" },
            { key: "cliente", label: "Cliente", dim: true },
            { key: "tipo", label: "Tipo", render: (r: any, tt: any) => <Badge label={r.tipo.replace("WhatsApp: ", "WA · ")} color={info} t={tt} /> },
            { key: "complejidad", label: "Complejidad", render: (r: any, tt: any) => <Badge label={r.complejidad} color={r.complejidad === "Complejo" ? err : r.complejidad === "Medio" ? warn : ok} t={tt} /> },
            { key: "integraciones", label: "Integraciones", align: "right", mono: true, render: (r: any) => String(r.integraciones.length) },
            { key: "horas", label: "Horas / mes", align: "right", mono: true },
            { key: "ejecuciones", label: "Ejecuciones", align: "right", mono: true, render: (r: any) => r.ejecuciones.toLocaleString("es-MX") },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={rows} />
      )}

      {tab === "clientes" && (
        <div className="space-y-5">
          <DataTable t={t} ui={ui} entity="cliente" labelKey="empresa" pageSize={6} searchKeys={["empresa"]}
            onDetail={(r: any) => { const c = clientes.find((x) => x.empresa === r.empresa); c && ui.openCliente(c); }}
            extraActions={(r: any) => [{ label: "Cotizar más flujos", onClick: () => setTab("cotizador") }]}
            columns={[
              { key: "empresa", label: "Cliente" },
              { key: "flujos", label: "Flujos", align: "right", mono: true },
              { key: "horas", label: "Horas / mes", align: "right", mono: true },
              { key: "ahorro", label: "Ahorro estimado", align: "right", mono: true, render: (r: any) => money(r.ahorro) },
              { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
            ]}
            rows={porCliente} />
          <ChartBox t={t} title="Horas ahorradas por tipo de automatización" sub="Total mensual acumulado" height={260}>
            <BarChart data={tiposAuto.map((tp) => ({ tipo: tp.replace("WhatsApp: ", "WA · ").replace(" y respuestas", "").replace(" y cobranza", ""), horas: rows.filter((f) => f.tipo === tp).reduce((a, f) => a + f.horas, 0) }))}>
              <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
              <XAxis dataKey="tipo" tick={{ fontSize: 10, fill: t.dim }} axisLine={false} tickLine={false} interval={0} angle={-14} textAnchor="end" height={58} />
              <YAxis {...ejeY(t)} />
              <Tooltip contentStyle={tip(t)} />
              <Bar dataKey="horas" name="Horas / mes" fill={brand} radius={[6, 6, 0, 0]} barSize={26} />
            </BarChart>
          </ChartBox>
        </div>
      )}

      {tab === "fases" && (
        <div className="space-y-5">
          <PhaseFlow t={t} ui={ui} fases={fasesAuto} ciclo="AU-036 · TechCorp MX" />
          <Card t={t} pad={0}>
            <div className="p-4" style={{ borderBottom: "1px solid " + t.line }}>
              <div style={{ color: t.text }} className="text-[15px] font-bold">Proyectos por fase</div>
              <div style={{ color: t.dim }} className="text-[12.5px] mt-1">Cada flujo avanza con checklist y aprobación del cliente, igual que SEO y Ads</div>
            </div>
            <div className="grid sm:grid-cols-2 xl:grid-cols-4">
              {fasesAuto.map((f, i) => (
                <div key={f.fase} className="p-4" style={{ borderRight: i < 3 ? "1px solid " + t.line : "none", borderTop: "1px solid " + t.line }}>
                  <div className="flex items-center gap-2 mb-3">
                    <span style={{ width: 6, height: 6, borderRadius: 999, background: f.estado === "completa" ? ok : f.estado === "activa" ? warn : t.dim }} />
                    <span style={{ color: t.text }} className="text-[13px] font-bold">{f.fase}</span>
                  </div>
                  <div className="space-y-1.5">
                    {rows.filter((x) => x.fase === f.fase).map((x) => (
                      <button key={x.id} onClick={() => abrirFicha(x)} className="w-full text-left p-2.5 rounded-lg"
                        style={{ background: t.raised, border: "1px solid " + t.line, cursor: "pointer" }}>
                        <div style={{ color: t.text }} className="text-[12.5px] font-semibold leading-snug">{x.nombre}</div>
                        <div style={{ color: t.dim }} className="text-[11px] mt-1">{x.cliente} · {x.horas} h/mes</div>
                      </button>
                    ))}
                    {rows.filter((x) => x.fase === f.fase).length === 0 && (
                      <div style={{ border: "1px dashed " + t.line, borderRadius: 10, padding: 14, textAlign: "center", color: t.dim, fontSize: 11.5 }}>Sin proyectos</div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </Card>
        </div>
      )}

      {tab === "cotizador" && <Cotizador t={t} ui={ui} />}
    </div>
  );
}

/* ─────────────────────────  calculadora de precios  ───────────────────────── */
function HojaCotizacion({ lineas, total, cliente, mensual }: any) {
  return (
    <div style={{ background: hoja.bg, color: hoja.text, borderRadius: 10, border: "1px solid " + hoja.line, padding: 34 }}>
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-2.5">
          <div style={{ width: 30, height: 30, borderRadius: 8, background: brand, color: "#fff", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 800, fontSize: 14 }}>R</div>
          <div>
            <div style={{ fontWeight: 800, fontSize: 15 }}>RankPro</div>
            <div style={{ color: hoja.dim, fontSize: 10.5 }}>Automatización de procesos</div>
          </div>
        </div>
        <div style={{ textAlign: "right" }}>
          <div style={{ fontSize: 11, color: hoja.dim, fontFamily: mono }}>COT-2026-0148</div>
          <div style={{ fontSize: 11, color: hoja.dim }}>23 ago 2026 · vigencia 15 días</div>
        </div>
      </div>

      <div style={{ marginTop: 30 }}>
        <div style={{ color: brand, fontSize: 11, fontWeight: 700, letterSpacing: ".14em", textTransform: "uppercase" }}>Cotización de automatizaciones</div>
        <div style={{ fontSize: 26, fontWeight: 800, letterSpacing: "-0.02em", marginTop: 8 }}>{cliente}</div>
      </div>

      <table style={{ width: "100%", borderCollapse: "collapse", marginTop: 26 }}>
        <thead>
          <tr>
            {["Concepto", "Detalle", "Cantidad", "Importe"].map((c, i) => (
              <th key={c} style={{ textAlign: i > 1 ? "right" : "left", fontSize: 10, textTransform: "uppercase", letterSpacing: ".06em", color: hoja.dim, padding: "8px 6px", borderBottom: "1px solid " + hoja.lineStrong }}>{c}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {lineas.map((l: any, i: number) => (
            <tr key={i}>
              <td style={{ fontSize: 11.5, fontWeight: 600, padding: "9px 6px", borderBottom: "1px solid " + hoja.line }}>{l.concepto}</td>
              <td style={{ fontSize: 11.5, color: hoja.dim, padding: "9px 6px", borderBottom: "1px solid " + hoja.line }}>{l.detalle}</td>
              <td style={{ fontSize: 11.5, fontFamily: mono, textAlign: "right", padding: "9px 6px", borderBottom: "1px solid " + hoja.line }}>{l.cant}</td>
              <td style={{ fontSize: 11.5, fontFamily: mono, textAlign: "right", padding: "9px 6px", borderBottom: "1px solid " + hoja.line, color: l.importe < 0 ? "#B91C1C" : hoja.text }}>{money(l.importe)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="flex justify-end" style={{ marginTop: 18 }}>
        <div style={{ width: 280 }}>
          <div className="flex items-center justify-between" style={{ padding: "8px 0", borderTop: "1px solid " + hoja.lineStrong }}>
            <span style={{ fontSize: 12, fontWeight: 700 }}>Inversión del proyecto</span>
            <span style={{ fontSize: 17, fontWeight: 800, fontFamily: mono }}>{money(total)}</span>
          </div>
          <div className="flex items-center justify-between" style={{ padding: "6px 0" }}>
            <span style={{ fontSize: 11.5, color: hoja.dim }}>Mantenimiento mensual</span>
            <span style={{ fontSize: 12, fontFamily: mono, color: hoja.dim }}>{money(mensual)}</span>
          </div>
          <div style={{ fontSize: 10.5, color: hoja.dim, marginTop: 6 }}>Precios en MXN antes de IVA. 50% al inicio y 50% contra entrega.</div>
        </div>
      </div>
    </div>
  );
}

function Cotizador({ t, ui }: any) {
  const [cliente, setCliente] = useState("Hotelera Costa");
  const [items, setItems] = useState<any[]>([
    { tipo: "WhatsApp: recordatorios y cobranza", complejidad: "Medio", integraciones: 3, horas: 40 },
    { tipo: "Captura de leads a CRM", complejidad: "Simple", integraciones: 2, horas: 18 },
  ]);
  const [extras, setExtras] = useState<Record<string, boolean>>({ whatsapp: true, ia: false, capacitacion: true });
  const [soporte, setSoporte] = useState("Básico · 8x5");

  const precioFlujo = (c: string) => (c === "Complejo" ? tarifas.complejo : c === "Medio" ? tarifas.medio : tarifas.simple);
  const base = items.reduce((a, i) => a + precioFlujo(i.complejidad), 0);
  const integraciones = items.reduce((a, i) => a + Math.max(0, i.integraciones - 1), 0);
  const costoIntegraciones = integraciones * tarifas.integracion;
  const costoExtras = (extras.whatsapp ? tarifas.whatsapp : 0) + (extras.ia ? tarifas.ia : 0) + (extras.capacitacion ? tarifas.capacitacion : 0);
  const setup = Math.round((base + costoIntegraciones) * tarifas.setupPct);
  const subtotal = base + costoIntegraciones + costoExtras + setup;
  const descPct = items.length >= 7 ? 0.15 : items.length >= 4 ? 0.1 : 0;
  const descuento = Math.round(subtotal * descPct);
  const total = subtotal - descuento;
  const mensual = (tarifas.soporte as any)[soporte];
  const horasMes = items.reduce((a, i) => a + Number(i.horas || 0), 0);
  const ahorroMes = horasMes * tarifas.horaEquipo;
  const retorno = ahorroMes > 0 ? (total / ahorroMes).toFixed(1) : "—";

  const lineas = [
    ...items.map((i, n) => ({ concepto: "Flujo " + (n + 1) + " · " + i.complejidad, detalle: i.tipo, cant: "1", importe: precioFlujo(i.complejidad) })),
    ...(costoIntegraciones ? [{ concepto: "Integraciones adicionales", detalle: "Conectores más allá del primero por flujo", cant: String(integraciones), importe: costoIntegraciones }] : []),
    ...(extras.whatsapp ? [{ concepto: "Alta de WhatsApp Business API", detalle: "Número verificado y plantillas aprobadas", cant: "1", importe: tarifas.whatsapp }] : []),
    ...(extras.ia ? [{ concepto: "Agente con IA", detalle: "Base de conocimiento y reglas de escalamiento", cant: "1", importe: tarifas.ia }] : []),
    ...(extras.capacitacion ? [{ concepto: "Capacitación del equipo", detalle: "2 sesiones y manual de operación", cant: "1", importe: tarifas.capacitacion }] : []),
    { concepto: "Implementación y pruebas", detalle: String(Math.round(tarifas.setupPct * 100)) + "% sobre construcción", cant: "1", importe: setup },
    ...(descuento ? [{ concepto: "Descuento por volumen", detalle: items.length + " flujos en el mismo proyecto", cant: "-" + Math.round(descPct * 100) + "%", importe: -descuento }] : []),
  ];

  const set = (n: number, k: string, v: any) => setItems(items.map((x, i) => (i === n ? { ...x, [k]: v } : x)));
  const selStyle: any = { width: "100%", background: t.inputBg, border: "1px solid " + t.line, borderRadius: 9, color: t.text, fontSize: 12.5, padding: "8px 10px", outline: "none" };

  return (
    <div className="grid xl:grid-cols-[1.35fr_1fr] gap-5">
      <div className="space-y-4">
        <Card t={t}>
          <div className="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div>
              <div style={{ color: t.text }} className="text-[15px] font-bold">Configura la cotización</div>
              <div style={{ color: t.dim }} className="text-[12px] mt-1">El precio se actualiza en vivo · tarifas de ejemplo en MXN</div>
            </div>
            <select value={cliente} onChange={(e) => setCliente(e.target.value)} style={{ ...selStyle, width: 200 }}>
              {clientes.map((c) => <option key={c.empresa} value={c.empresa}>{c.empresa}</option>)}
            </select>
          </div>

          <div className="space-y-3">
            {items.map((i, n) => (
              <div key={n} className="p-3.5 rounded-xl" style={{ background: t.raised, border: "1px solid " + t.line }}>
                <div className="flex items-center justify-between mb-3">
                  <span style={{ color: t.text }} className="text-[13px] font-bold">Flujo {n + 1}</span>
                  <div className="flex items-center gap-3">
                    <span style={{ color: accent, fontFamily: mono }} className="text-[13px] font-bold">{money(precioFlujo(i.complejidad))}</span>
                    <button onClick={() => setItems(items.filter((_, j) => j !== n))} title="Quitar flujo"
                      style={{ background: "transparent", border: 0, color: t.dim, cursor: "pointer", display: "flex" }}><Trash2 size={14} /></button>
                  </div>
                </div>
                <div className="grid sm:grid-cols-2 gap-3">
                  <label>
                    <span style={{ color: t.dim }} className="text-[10.5px] font-bold uppercase tracking-wider">Tipo de automatización</span>
                    <select value={i.tipo} onChange={(e) => set(n, "tipo", e.target.value)} style={{ ...selStyle, marginTop: 5 }}>
                      {tiposAuto.map((x) => <option key={x} value={x}>{x}</option>)}
                    </select>
                  </label>
                  <label>
                    <span style={{ color: t.dim }} className="text-[10.5px] font-bold uppercase tracking-wider">Complejidad</span>
                    <div className="flex gap-1.5" style={{ marginTop: 5 }}>
                      {["Simple", "Medio", "Complejo"].map((c) => (
                        <button key={c} onClick={() => set(n, "complejidad", c)}
                          style={{ flex: 1, fontSize: 12, fontWeight: 600, padding: "7px 4px", borderRadius: 8, cursor: "pointer", color: i.complejidad === c ? "#fff" : t.mid, background: i.complejidad === c ? brand : "transparent", border: "1px solid " + (i.complejidad === c ? brand : t.line) }}>{c}</button>
                      ))}
                    </div>
                  </label>
                  <label>
                    <span style={{ color: t.dim }} className="text-[10.5px] font-bold uppercase tracking-wider">Integraciones ({i.integraciones})</span>
                    <input type="range" min={1} max={8} value={i.integraciones} onChange={(e) => set(n, "integraciones", Number(e.target.value))}
                      style={{ width: "100%", marginTop: 10, accentColor: brand }} />
                  </label>
                  <label>
                    <span style={{ color: t.dim }} className="text-[10.5px] font-bold uppercase tracking-wider">Horas ahorradas al mes</span>
                    <input type="number" value={i.horas} onChange={(e) => set(n, "horas", Number(e.target.value))} style={{ ...selStyle, marginTop: 5, fontFamily: mono }} />
                  </label>
                </div>
              </div>
            ))}
          </div>

          <div className="mt-4">
            <Btn t={t} size="sm" icon={Plus} onClick={() => setItems([...items, { tipo: tiposAuto[0], complejidad: "Simple", integraciones: 1, horas: 12 }])}>Agregar flujo</Btn>
          </div>
        </Card>

        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold mb-3.5">Complementos</div>
          <div className="space-y-1">
            {[["whatsapp", "Alta de WhatsApp Business API", "Número verificado y plantillas aprobadas", tarifas.whatsapp], ["ia", "Agente con IA", "Respuestas con base de conocimiento", tarifas.ia], ["capacitacion", "Capacitación del equipo", "2 sesiones y manual de operación", tarifas.capacitacion]].map(([k, label, hint, precio]: any) => (
              <div key={k} className="flex items-center gap-4 py-2.5" style={{ borderBottom: "1px solid " + t.line }}>
                <div className="flex-1">
                  <div style={{ color: t.text }} className="text-[13px] font-semibold">{label}</div>
                  <div style={{ color: t.dim }} className="text-[11.5px] mt-0.5">{hint}</div>
                </div>
                <span style={{ color: t.mid, fontFamily: mono }} className="text-[12.5px]">{money(precio)}</span>
                <Toggle t={t} on={extras[k]} onChange={(v: boolean) => setExtras({ ...extras, [k]: v })} />
              </div>
            ))}
          </div>
          <div className="mt-4">
            <span style={{ color: t.dim }} className="text-[10.5px] font-bold uppercase tracking-wider">Mantenimiento mensual</span>
            <div className="flex flex-wrap gap-1.5 mt-2">
              {Object.keys(tarifas.soporte).map((k) => (
                <button key={k} onClick={() => setSoporte(k)}
                  style={{ fontSize: 12, fontWeight: 600, padding: "7px 12px", borderRadius: 999, cursor: "pointer", color: soporte === k ? "#fff" : t.mid, background: soporte === k ? brand : "transparent", border: "1px solid " + (soporte === k ? brand : t.line) }}>{k}</button>
              ))}
            </div>
          </div>
        </Card>
      </div>

      <div className="space-y-4">
        <Card t={t} style={{ position: "sticky", top: 78 }}>
          <div style={{ color: t.dim }} className="text-[11px] font-bold uppercase tracking-[0.1em]">Inversión del proyecto</div>
          <div style={{ color: t.text, fontFamily: mono }} className="text-[34px] font-bold tracking-tight mt-2 leading-none">{money(total)}</div>
          <div style={{ color: t.dim }} className="text-[12px] mt-2">+ {money(mensual)} / mes de mantenimiento · {items.length} flujos</div>

          <div className="mt-5 space-y-0">
            {lineas.map((l, i) => (
              <div key={i} className="flex items-start justify-between gap-4 py-2" style={{ borderBottom: "1px solid " + t.line }}>
                <div className="flex-1">
                  <div style={{ color: t.text }} className="text-[12.5px] font-semibold leading-snug">{l.concepto}</div>
                  <div style={{ color: t.dim }} className="text-[11px] mt-0.5">{l.detalle}</div>
                </div>
                <span style={{ color: l.importe < 0 ? err : t.text, fontFamily: mono }} className="text-[12.5px] font-semibold whitespace-nowrap">{money(l.importe)}</span>
              </div>
            ))}
          </div>

          <div className="flex items-center justify-between py-3" style={{ borderBottom: "1px solid " + t.lineStrong }}>
            <span style={{ color: t.mid }} className="text-[12.5px] font-semibold">Subtotal</span>
            <span style={{ color: t.text, fontFamily: mono }} className="text-[13px] font-bold">{money(subtotal)}</span>
          </div>

          <div className="grid grid-cols-3 gap-3 mt-4">
            {[["Horas / mes", String(horasMes)], ["Ahorro / mes", money(ahorroMes)], ["Retorno", retorno + " meses"]].map(([k, v]) => (
              <div key={k}>
                <div style={{ color: t.dim }} className="text-[10px] font-bold uppercase tracking-wider">{k}</div>
                <div style={{ color: accent, fontFamily: mono }} className="text-[14px] font-bold mt-1">{v}</div>
              </div>
            ))}
          </div>

          <div className="mt-5 flex flex-wrap gap-2">
            <Btn t={t} variant="primary" size="sm" icon={FileText}
              onClick={() => ui.custom({
                title: "Cotización para " + cliente,
                subtitle: items.length + " flujos · " + money(total) + " + " + money(mensual) + " / mes",
                content: <HojaCotizacion lineas={lineas} total={total} cliente={cliente} mensual={mensual} />,
                actions: [
                  { label: "Descargar PDF", onClick: () => ui.toast("Cotizacion_" + cliente.replace(/ /g, "") + ".pdf generada") },
                  { label: "Guardar en Archivos", onClick: () => ui.toast("Propuesta guardada en la carpeta de " + cliente) },
                  { label: "Enviar al cliente", onClick: () => ui.toast("Cotización enviada a " + cliente) },
                ],
              })}>Generar cotización</Btn>
            <Btn t={t} size="sm" onClick={() => ui.toast("Propuesta guardada en Archivos · " + cliente)}>Guardar propuesta</Btn>
          </div>
        </Card>

        <Card t={t} pad={16}>
          <div style={{ color: t.text }} className="text-[13px] font-bold mb-2">Cómo se calcula</div>
          <div style={{ color: t.dim }} className="text-[11.5px] leading-relaxed">
            Flujo simple {money(tarifas.simple)} · medio {money(tarifas.medio)} · complejo {money(tarifas.complejo)}. Cada integración extra suma {money(tarifas.integracion)}. Implementación y pruebas: {Math.round(tarifas.setupPct * 100)}% de la construcción. Descuento de 10% desde 4 flujos y 15% desde 7. Ahorro valuado a {money(tarifas.horaEquipo)} por hora de equipo.
          </div>
        </Card>
      </div>
    </div>
  );
}

/* ─────────────────────────  vistas de sistema  ───────────────────────── */
function Toggle({ t, on, onChange }: any) {
  return (
    <button type="button" onClick={() => onChange(!on)}
      style={{ width: 38, height: 22, borderRadius: 999, background: on ? brand : t.raised, border: "1px solid " + (on ? brand : t.line), position: "relative", cursor: "pointer", flexShrink: 0 }}>
      <span style={{ position: "absolute", top: 2, left: on ? 18 : 2, width: 16, height: 16, borderRadius: 999, background: "#fff", transition: "left .16s" }} />
    </button>
  );
}

function ClienteCuenta({ t, ui, cliente, back }: any) {
  const [tab, setTab] = useState("resumen");
  const tabs = [["resumen", "Resumen"], ["servicios", "Servicios"], ["facturacion", "Facturación"], ["desempeno", "Desempeño"], ["archivos", "Archivos"], ["actividad", "Actividad"]];
  const svc = servicios.filter((s: any) => s.cliente === cliente.empresa);
  const kws = keywords.filter((k: any) => k.cliente === cliente.empresa);
  const docs = archivos.filter((a: any) => a.cliente === cliente.empresa);
  const facturas = [
    { folio: "F-2026-0902", monto: cliente.mrr, emision: "01 sep", estado: cliente.estado === "Pago pendiente" ? "Vencido" : "Pendiente" },
    { folio: "F-2026-0841", monto: cliente.mrr, emision: "01 ago", estado: "Cobrado" },
    { folio: "F-2026-0798", monto: cliente.mrr, emision: "01 jul", estado: "Cobrado" },
    { folio: "F-2026-0754", monto: cliente.mrr, emision: "01 jun", estado: "Cobrado" },
  ];
  const serie = ingresos.map((x: any, i: number) => ({ m: x.m, ingresos: Math.round(cliente.mrr / 1000) + i * 3, inversion: Math.round(cliente.mrr / 1600) + i * 2 }));

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <Btn t={t} size="sm" onClick={back}>Volver al CRM</Btn>
        <span style={{ color: t.dim }} className="text-[12.5px]">CRM Clientes · ficha completa</span>
      </div>

      <Card t={t}>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-4">
            <div style={{ width: 46, height: 46, borderRadius: 12, background: brand + "1f", color: accent, display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 800, fontSize: 15, flexShrink: 0 }}>
              {cliente.empresa.slice(0, 2).toUpperCase()}
            </div>
            <div>
              <div className="flex flex-wrap items-center gap-2.5">
                <h2 style={{ color: t.text }} className="text-[21px] font-bold tracking-tight">{cliente.empresa}</h2>
                <Badge label={cliente.estado} color={estadoColor(cliente.estado)} t={t} />
              </div>
              <div style={{ color: t.dim }} className="text-[13px] mt-1">{cliente.contacto} · contrato vence {cliente.vence}</div>
              <div className="flex flex-wrap gap-1.5 mt-2.5">
                {cliente.servicios.map((sv: string) => <Badge key={sv} label={sv} color={info} t={t} />)}
              </div>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Btn t={t} size="sm" icon={Pencil}
              onClick={() => ui.form({ title: "Editar ficha", subtitle: cliente.empresa, cta: "Guardar cambios", fields: clienteFields, values: cliente, onSubmit: () => ui.toast("Ficha de " + cliente.empresa + " actualizada") })}>Editar ficha</Btn>
            <Btn t={t} size="sm"
              onClick={() => ui.confirm({ title: "Enviar reporte mensual", text: "Se enviará el reporte de agosto 2026 a " + cliente.contacto + " con posiciones, campañas y estado de cuenta.", cta: "Enviar reporte", onConfirm: () => ui.toast("Reporte enviado a " + cliente.contacto) })}>Enviar reporte</Btn>
            <Btn t={t} variant="primary" size="sm" icon={Plus}
              onClick={() => ui.form({ title: "Asignar servicio", subtitle: cliente.empresa, cta: "Asignar servicio", fields: servicioFields, values: { cliente: cliente.empresa }, onSubmit: (v: any) => ui.toast((v.servicio || "Servicio") + " asignado a " + cliente.empresa) })}>Asignar servicio</Btn>
          </div>
        </div>
      </Card>

      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="MRR" value={money(cliente.mrr)} delta="+6.1%" up hint="recurrente" icon={TrendingUp} />
        <KpiCard t={t} label="Servicios activos" value={String(svc.length || cliente.servicios.length)} hint="en operación" icon={Boxes} />
        <KpiCard t={t} label="Antigüedad" value="6 meses" hint="desde mar 2026" icon={Clock} />
        <KpiCard t={t} label="Valor acumulado" value={money(cliente.mrr * 6)} hint="facturado a la fecha" icon={Wallet} />
      </div>

      <div className="flex gap-1.5 flex-wrap">
        {tabs.map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            style={{
              fontSize: 13, fontWeight: 600, padding: "7px 14px", borderRadius: 9, cursor: "pointer",
              color: tab === k ? t.text : t.dim, background: tab === k ? t.card : "transparent",
              border: "1px solid " + (tab === k ? t.lineStrong : "transparent"),
            }}>{label}</button>
        ))}
      </div>

      {tab === "resumen" && (
        <div className="grid xl:grid-cols-[1.5fr_1fr] gap-5">
          <Card t={t}>
            <SectionTitle t={t} title="Resultados de la cuenta" sub="Miles de MXN · ingresos atribuidos vs. inversión" />
            <div style={{ height: 250 }} data-om-raster>
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={serie} margin={{ top: 5, right: 8, left: 0, bottom: 0 }}>
                  <defs>
                    <linearGradient id="gCuenta" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor={brand} stopOpacity={0.32} />
                      <stop offset="100%" stopColor={brand} stopOpacity={0.02} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 5" stroke={t.line} vertical={false} />
                  <XAxis dataKey="m" tick={{ fontSize: 11, fill: t.dim }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 11, fill: t.dim, fontFamily: mono }} axisLine={false} tickLine={false} width={38} />
                  <Tooltip contentStyle={{ background: t.raised, border: "1px solid " + t.lineStrong, borderRadius: 10, fontSize: 12, color: t.text }} />
                  <Legend wrapperStyle={{ fontSize: 12 }} />
                  <Area type="monotone" dataKey="ingresos" name="Ingresos" stroke={brand} strokeWidth={2.5} fill="url(#gCuenta)" />
                  <Area type="monotone" dataKey="inversion" name="Inversión" stroke={info} strokeWidth={2} strokeDasharray="5 4" fill="transparent" />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </Card>
          <div className="space-y-5">
            <Card t={t}>
              <div style={{ color: t.text }} className="text-[15px] font-bold mb-3.5">Datos de contacto</div>
              <div className="space-y-2.5">
                {[["Contacto", cliente.contacto], ["Correo", cliente.contacto.split(" ")[0].toLowerCase() + "@" + cliente.empresa.split(" ")[0].toLowerCase() + ".mx"], ["Teléfono", "+52 55 4820 1190"], ["Ejecutivo", "R. Torres"], ["Alta", "01 mar 2026"]].map(([k, v]: any) => (
                  <div key={k} className="flex items-center justify-between gap-4">
                    <span style={{ color: t.dim }} className="text-[12.5px]">{k}</span>
                    <span style={{ color: t.text, fontFamily: mono }} className="text-[12px] text-right">{v}</span>
                  </div>
                ))}
              </div>
              <div className="mt-4 flex gap-2">
                <Btn t={t} size="sm" onClick={() => ui.toast("Correo abierto en el cliente de mail")}>Enviar correo</Btn>
                <Btn t={t} size="sm" onClick={() => ui.form({ title: "Agendar reunión", subtitle: cliente.empresa, cta: "Agendar", fields: [{ key: "tipo", label: "Tipo", type: "select", options: ["Junta de resultados", "Revisión de estrategia", "Onboarding"] }, { key: "fecha", label: "Fecha", placeholder: "29 ago 2026" }, { key: "hora", label: "Hora", placeholder: "11:00" }, { key: "canal", label: "Canal", type: "select", options: ["Google Meet", "Presencial", "Teléfono"] }], onSubmit: () => ui.toast("Reunión agendada con " + cliente.contacto) })}>Agendar</Btn>
              </div>
            </Card>
            <Card t={t}>
              <div style={{ color: t.text }} className="text-[15px] font-bold mb-3">Riesgos y pendientes</div>
              <div className="space-y-2">
                {[[cliente.estado === "Pago pendiente" ? "Factura vencida sin cobrar" : "Sin adeudos vigentes", cliente.estado === "Pago pendiente" ? err : ok], ["Renovación de contrato en " + cliente.vence, warn], ["Reporte de agosto pendiente de envío", info]].map(([txt, col]: any) => (
                  <div key={txt} className="flex items-start gap-2.5 p-2.5 rounded-xl" style={{ background: t.raised, border: "1px solid " + t.line }}>
                    <AlertTriangle size={14} color={col} style={{ marginTop: 2, flexShrink: 0 }} />
                    <span style={{ color: t.text }} className="text-[12.5px] leading-relaxed">{txt}</span>
                  </div>
                ))}
              </div>
            </Card>
          </div>
        </div>
      )}

      {tab === "servicios" && (
        <DataTable t={t} ui={ui} entity="servicio" labelKey="servicio" pageSize={5} searchKeys={["servicio", "responsable"]}
          columns={[
            { key: "servicio", label: "Servicio", render: (r: any, tt: any) => <Badge label={r.servicio} color={info} t={tt} /> },
            { key: "responsable", label: "Responsable", dim: true },
            { key: "precio", label: "Precio mensual", align: "right", mono: true, render: (r: any) => money(r.precio) },
            { key: "inicio", label: "Inicio", dim: true },
            { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={estadoColor(r.estado)} t={tt} /> },
          ]}
          rows={svc.length ? svc : cliente.servicios.map((sv: string) => ({ servicio: sv, responsable: "R. Torres", precio: Math.round(cliente.mrr / cliente.servicios.length), inicio: "01 mar 2026", estado: "Activo" }))} />
      )}

      {tab === "facturacion" && (
        <div className="space-y-5">
          <div className="grid sm:grid-cols-3 gap-4">
            <KpiCard t={t} label="Facturado 2026" value={money(cliente.mrr * 6)} hint="6 ciclos" icon={Wallet} />
            <KpiCard t={t} label="Por cobrar" value={money(cliente.estado === "Pago pendiente" ? cliente.mrr : 0)} hint={cliente.estado === "Pago pendiente" ? "1 factura vencida" : "sin adeudos"} icon={Clock} />
            <KpiCard t={t} label="Método" value="SPEI" hint="pago mensual día 5" icon={CheckCircle2} />
          </div>
          <DataTable t={t} ui={ui} entity="factura" labelKey="folio" pageSize={4} searchKeys={["folio"]}
            extraActions={(r: any) => [
              { label: "Registrar pago", onClick: () => ui.form({ title: "Registrar pago", subtitle: r.folio, cta: "Registrar", fields: [{ key: "monto", label: "Monto", type: "number", mono: true }, { key: "metodo", label: "Método", type: "select", options: ["Transferencia", "Tarjeta", "Efectivo"] }], values: { monto: r.monto }, onSubmit: () => ui.toast("Pago de " + r.folio + " registrado") }) },
              { label: "Descargar PDF", onClick: () => ui.toast("Descargando " + r.folio + ".pdf") },
            ]}
            columns={[
              { key: "folio", label: "Folio", mono: true, dim: true },
              { key: "monto", label: "Monto", align: "right", mono: true, render: (r: any) => money(r.monto) },
              { key: "emision", label: "Emisión", dim: true },
              { key: "estado", label: "Estado", render: (r: any, tt: any) => <Badge label={r.estado} color={r.estado === "Cobrado" ? ok : r.estado === "Pendiente" ? warn : err} t={tt} /> },
            ]}
            rows={facturas} />
        </div>
      )}

      {tab === "desempeno" && (
        <div className="space-y-5">
          <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <KpiCard t={t} label="Keywords en top 10" value={String(kws.filter((k: any) => k.pos <= 10).length || 3)} delta="+2" up hint="vs. julio" icon={Search} />
            <KpiCard t={t} label="Tráfico orgánico" value="18.4K" delta="+12.6%" up hint="sesiones" icon={TrendingUp} />
            <KpiCard t={t} label="Conversiones" value="142" delta="+18%" up hint="mes actual" icon={Filter} />
            <KpiCard t={t} label="ROAS" value="4.8x" delta="+0.6" up hint="objetivo 4.0x" icon={Zap} />
          </div>
          <DataTable t={t} ui={ui} entity="keyword" labelKey="kw" pageSize={5} searchKeys={["kw"]}
            columns={[
              { key: "kw", label: "Keyword" },
              { key: "pos", label: "Pos.", align: "right", mono: true, render: (r: any) => <span style={{ fontFamily: mono, color: r.pos <= 3 ? ok : r.pos <= 10 ? warn : err }}>#{r.pos}</span> },
              { key: "vol", label: "Volumen", align: "right", mono: true },
              { key: "url", label: "URL", dim: true },
              { key: "fuente", label: "Fuente", dim: true },
            ]}
            rows={kws.length ? kws : keywords.slice(0, 4)} />
        </div>
      )}

      {tab === "archivos" && (
        <DataTable t={t} ui={ui} entity="archivo" labelKey="nombre" pageSize={5} searchKeys={["nombre"]}
          extraActions={(r: any) => [{ label: "Descargar", onClick: () => ui.toast("Descargando " + r.nombre) }]}
          columns={[
            { key: "nombre", label: "Archivo" },
            { key: "tipo", label: "Tipo", render: (r: any, tt: any) => <Badge label={r.tipo} color={info} t={tt} /> },
            { key: "peso", label: "Peso", align: "right", mono: true, dim: true },
            { key: "fecha", label: "Fecha", dim: true },
          ]}
          rows={docs.length ? docs : [{ nombre: "Contrato_" + cliente.empresa.replace(/ /g, "") + "_2026.pdf", tipo: "Contrato", peso: "398 KB", fecha: "01 mar 2026" }, { nombre: "Reporte_Agosto_" + cliente.empresa.replace(/ /g, "") + ".pdf", tipo: "Reporte", peso: "742 KB", fecha: "05 ago 2026" }]} />
      )}

      {tab === "actividad" && (
        <Card t={t} pad={0}>
          <div className="p-4" style={{ borderBottom: "1px solid " + t.line }}>
            <div style={{ color: t.text }} className="text-[15px] font-bold">Bitácora de la cuenta</div>
            <div style={{ color: t.dim }} className="text-[12.5px] mt-1">Toda interacción queda registrada con autor y fecha</div>
          </div>
          <div className="p-4 space-y-3">
            {[["22 ago", "R. Torres", "Reporte de agosto enviado al contacto principal"], ["18 ago", "V. Mora", "Publicación de 2 piezas de contenido del calendario"], ["12 ago", "Sistema", "Factura " + facturas[1].folio + " marcada como cobrada"], ["05 ago", "R. Torres", "Junta de resultados · acuerdos registrados"], ["01 mar", "Sistema", "Alta de la cuenta y firma de contrato v4"]].map(([f, autor, txt]: any) => (
              <div key={txt} className="flex flex-wrap items-center gap-3 p-3 rounded-xl" style={{ background: t.raised, border: "1px solid " + t.line }}>
                <span style={{ color: t.dim, fontFamily: mono }} className="text-[11.5px] w-14">{f}</span>
                <span style={{ color: t.text }} className="text-[12.5px] flex-1 min-w-[220px]">{txt}</span>
                <span style={{ color: t.dim }} className="text-[11.5px]">{autor}</span>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}

const alertasFull = [
  { tipo: "error", texto: "Dental Sonrisa · pago vencido hace 6 días ($28,000)", cliente: "Dental Sonrisa", modulo: "Finanzas", fecha: "hace 2 h" },
  { tipo: "warn", texto: "FashionMX · contrato vence en 5 días, sin renovación firmada", cliente: "FashionMX", modulo: "CRM", fecha: "hace 5 h" },
  { tipo: "warn", texto: "Café Origen · reporte de agosto pendiente de aprobación", cliente: "Café Origen", modulo: "SEO", fecha: "ayer" },
  { tipo: "info", texto: "TechCorp MX · nueva fase de desarrollo lista para QA", cliente: "TechCorp MX", modulo: "Desarrollo", fecha: "ayer" },
  { tipo: "warn", texto: "Hotelera Costa · presupuesto de Ads al 93% a 22 días del mes", cliente: "Hotelera Costa", modulo: "Ads", fecha: "hace 2 días" },
  { tipo: "info", texto: "Grupo Lumina · 3 keywords entraron a top 3", cliente: "Grupo Lumina", modulo: "Keywords", fecha: "hace 3 días" },
  { tipo: "error", texto: "Legal & Partners · script de tracking sin señal desde el 19 ago", cliente: "Legal & Partners", modulo: "Integraciones", fecha: "hace 4 días" },
];

function Alertas({ t, ui }: any) {
  const [filtro, setFiltro] = useState("Todas");
  const [resueltas, setResueltas] = useState<string[]>([]);
  const chips = ["Todas", "Críticas", "Advertencias", "Informativas", "Resueltas"];
  const visibles = alertasFull.filter((a) => {
    const res = resueltas.includes(a.texto);
    if (filtro === "Resueltas") return res;
    if (res) return false;
    if (filtro === "Críticas") return a.tipo === "error";
    if (filtro === "Advertencias") return a.tipo === "warn";
    if (filtro === "Informativas") return a.tipo === "info";
    return true;
  });

  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Centro de alertas" sub={alertasFull.length - resueltas.length + " abiertas · reglas de operación automáticas"}
        action={<div className="flex gap-2">
          <Btn t={t} size="sm" onClick={() => ui.form({ title: "Nueva regla de alerta", subtitle: "Se evalúa cada hora", cta: "Crear regla", fields: [{ key: "modulo", label: "Módulo", type: "select", options: ["Finanzas", "CRM", "SEO", "Ads", "Desarrollo", "Integraciones"] }, { key: "condicion", label: "Condición", type: "select", options: ["Pago vencido", "Contrato por vencer", "Presupuesto excedido", "Sin señal de tracking"] }, { key: "umbral", label: "Umbral (días o %)", type: "number", mono: true }, { key: "canal", label: "Notificar por", type: "select", options: ["Panel", "Correo", "Slack"] }], onSubmit: () => ui.toast("Regla de alerta creada") })}>Nueva regla</Btn>
          <Btn t={t} variant="primary" size="sm" onClick={() => { setResueltas(alertasFull.map((a) => a.texto)); ui.toast("Todas las alertas marcadas como resueltas"); }}>Resolver todas</Btn>
        </div>} />

      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard t={t} label="Críticas" value={String(alertasFull.filter((a) => a.tipo === "error" && !resueltas.includes(a.texto)).length)} hint="requieren acción hoy" icon={AlertTriangle} />
        <KpiCard t={t} label="Advertencias" value={String(alertasFull.filter((a) => a.tipo === "warn" && !resueltas.includes(a.texto)).length)} hint="esta semana" icon={Clock} />
        <KpiCard t={t} label="Informativas" value={String(alertasFull.filter((a) => a.tipo === "info" && !resueltas.includes(a.texto)).length)} hint="sin acción" icon={Bell} />
        <KpiCard t={t} label="Resueltas" value={String(resueltas.length)} hint="en esta sesión" icon={CheckCircle2} />
      </div>

      <div className="flex flex-wrap gap-1.5">
        {chips.map((c) => (
          <button key={c} onClick={() => setFiltro(c)}
            style={{ fontSize: 12, fontWeight: 600, padding: "6px 12px", borderRadius: 999, cursor: "pointer", color: filtro === c ? "#fff" : t.mid, background: filtro === c ? brand : "transparent", border: "1px solid " + (filtro === c ? brand : t.line) }}>{c}</button>
        ))}
      </div>

      <div className="space-y-2">
        {visibles.map((a) => {
          const col = a.tipo === "error" ? err : a.tipo === "warn" ? warn : info;
          const res = resueltas.includes(a.texto);
          return (
            <Card t={t} key={a.texto} pad={14}>
              <div className="flex flex-wrap items-center gap-3">
                <AlertTriangle size={16} color={col} style={{ flexShrink: 0 }} />
                <div className="flex-1 min-w-[240px]">
                  <div style={{ color: t.text }} className="text-[13px] leading-snug">{a.texto}</div>
                  <div style={{ color: t.dim }} className="text-[11.5px] mt-1">{a.modulo} · {a.fecha}</div>
                </div>
                <Badge label={a.tipo === "error" ? "Crítica" : a.tipo === "warn" ? "Advertencia" : "Informativa"} color={col} t={t} />
                <div className="flex gap-2">
                  <Btn t={t} size="sm" onClick={() => { const c = clientes.find((x) => x.empresa === a.cliente); c ? ui.openCliente(c) : ui.toast("Cliente no disponible"); }}>Ver cliente</Btn>
                  <Btn t={t} size="sm" onClick={() => ui.form({ title: "Posponer alerta", subtitle: a.texto, cta: "Posponer", fields: [{ key: "hasta", label: "Retomar el", placeholder: "26 ago 2026" }, { key: "nota", label: "Nota", type: "textarea", wide: true }], onSubmit: () => ui.toast("Alerta pospuesta") })}>Posponer</Btn>
                  {!res && <Btn t={t} variant="primary" size="sm" icon={Check} onClick={() => { setResueltas([...resueltas, a.texto]); ui.toast("Alerta resuelta"); }}>Resolver</Btn>}
                </div>
              </div>
            </Card>
          );
        })}
        {visibles.length === 0 && (
          <Card t={t}><div style={{ color: t.dim }} className="text-[13px] text-center py-6">Sin alertas en este filtro</div></Card>
        )}
      </div>
    </div>
  );
}

function Ajustes({ t, ui }: any) {
  const [vals, setVals] = useState<Record<string, any>>({ nombre: "RankPro", dominio: "rankpro.mx", moneda: "MXN", zona: "América/Ciudad de México", correo: "hola@rankpro.mx" });
  const [flags, setFlags] = useState<Record<string, boolean>>({ pagos: true, contratos: true, reportes: false, ads: true, resumen: true, dosfa: false });
  const set = (k: string) => (v: any) => setVals({ ...vals, [k]: v });

  return (
    <div className="space-y-5">
      <SectionTitle t={t} title="Ajustes" sub="Perfil de la agencia, notificaciones, marca y plan"
        action={<Btn t={t} variant="primary" size="sm" icon={Check} onClick={() => ui.toast("Ajustes guardados")}>Guardar cambios</Btn>} />

      <div className="grid xl:grid-cols-2 gap-5">
        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold mb-4">Perfil de la agencia</div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(2, minmax(0,1fr))", gap: 14 }}>
            <Field t={t} field={{ key: "nombre", label: "Nombre comercial" }} value={vals.nombre} onChange={set("nombre")} />
            <Field t={t} field={{ key: "dominio", label: "Dominio", mono: true }} value={vals.dominio} onChange={set("dominio")} />
            <Field t={t} field={{ key: "correo", label: "Correo de contacto", type: "email" }} value={vals.correo} onChange={set("correo")} />
            <Field t={t} field={{ key: "moneda", label: "Moneda", type: "select", options: ["MXN", "USD", "EUR"] }} value={vals.moneda} onChange={set("moneda")} />
            <Field t={t} field={{ key: "zona", label: "Zona horaria", type: "select", options: ["América/Ciudad de México", "América/Bogotá", "América/Madrid"], wide: true }} value={vals.zona} onChange={set("zona")} />
          </div>
        </Card>

        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold mb-4">Notificaciones</div>
          <div className="space-y-1">
            {[["pagos", "Pagos vencidos", "Aviso inmediato al superar la fecha"], ["contratos", "Contratos por vencer", "30, 15 y 5 días antes"], ["reportes", "Reportes pendientes", "Cada lunes a las 9:00"], ["ads", "Presupuesto de Ads", "Al llegar al 90% del mes"], ["resumen", "Resumen semanal", "Consolidado de los 24 clientes"]].map(([k, label, hint]: any) => (
              <div key={k} className="flex items-center gap-4 py-2.5" style={{ borderBottom: "1px solid " + t.line }}>
                <div className="flex-1">
                  <div style={{ color: t.text }} className="text-[13px] font-semibold">{label}</div>
                  <div style={{ color: t.dim }} className="text-[11.5px] mt-0.5">{hint}</div>
                </div>
                <Toggle t={t} on={flags[k]} onChange={(v: boolean) => { setFlags({ ...flags, [k]: v }); ui.toast(label + (v ? " activado" : " desactivado")); }} />
              </div>
            ))}
          </div>
        </Card>

        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold mb-4">Marca del panel</div>
          <div className="flex flex-wrap items-center gap-3 mb-5">
            {[brand, "#2563EB", "#7C3AED", "#DB2777", "#EA580C"].map((c) => (
              <button key={c} onClick={() => ui.toast("Color de acento actualizado")}
                style={{ width: 34, height: 34, borderRadius: 9, background: c, border: "2px solid " + (c === brand ? t.text : "transparent"), cursor: "pointer" }} />
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <Btn t={t} size="sm" onClick={() => ui.toast("Selecciona un archivo PNG o SVG")}>Subir logotipo</Btn>
            <Btn t={t} size="sm" onClick={() => ui.form({ title: "Dominio del portal de clientes", cta: "Guardar dominio", fields: [{ key: "dominio", label: "Subdominio", mono: true, wide: true, placeholder: "clientes.rankpro.mx" }], onSubmit: () => ui.toast("Dominio en verificación DNS") })}>Portal de clientes</Btn>
          </div>
        </Card>

        <Card t={t}>
          <div style={{ color: t.text }} className="text-[15px] font-bold mb-4">Plan y seguridad</div>
          <div className="p-3.5 rounded-xl mb-4" style={{ background: t.raised, border: "1px solid " + t.line }}>
            <div className="flex items-center justify-between">
              <div>
                <div style={{ color: t.text }} className="text-[13.5px] font-bold">Plan Agencia · 16 usuarios</div>
                <div style={{ color: t.dim }} className="text-[11.5px] mt-0.5">Renovación el 01 sep 2026 · {money(18900)} / mes</div>
              </div>
              <Badge label="Activo" color={ok} t={t} />
            </div>
          </div>
          <div className="flex items-center gap-4 py-2.5" style={{ borderBottom: "1px solid " + t.line }}>
            <div className="flex-1">
              <div style={{ color: t.text }} className="text-[13px] font-semibold">Doble factor obligatorio</div>
              <div style={{ color: t.dim }} className="text-[11.5px] mt-0.5">Aplica a todos los usuarios del panel</div>
            </div>
            <Toggle t={t} on={flags.dosfa} onChange={(v: boolean) => { setFlags({ ...flags, dosfa: v }); ui.toast("Doble factor " + (v ? "obligatorio" : "opcional")); }} />
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            <Btn t={t} size="sm" onClick={() => ui.detail({ title: "Plan Agencia", subtitle: "Facturación mensual", rows: [["Usuarios incluidos", "20"], ["Usuarios en uso", "16"], ["Clientes gestionados", "24 de 50"], ["Almacenamiento", "48 GB de 200 GB"], ["Próximo cargo", "01 sep 2026"]], actions: [{ label: "Cambiar plan", onClick: () => ui.toast("Un asesor te contactará para el cambio de plan") }] })}>Ver plan</Btn>
            <Btn t={t} size="sm" onClick={() => ui.confirm({ title: "Cerrar todas las sesiones", tone: "danger", text: "Se cerrará la sesión de los 16 usuarios en todos los dispositivos. Deberán volver a iniciar sesión.", cta: "Cerrar sesiones", onConfirm: () => ui.toast("Sesiones cerradas", "error") })}>Cerrar sesiones</Btn>
          </div>
        </Card>
      </div>
    </div>
  );
}

function NotifPanel({ t, ui, close }: any) {
  return (
    <div style={{ position: "absolute", top: 44, right: 0, width: 340, background: t.card, border: "1px solid " + t.lineStrong, borderRadius: 13, boxShadow: "0 26px 60px -20px rgba(0,0,0,.6)", zIndex: 60, overflow: "hidden" }}>
      <div className="flex items-center justify-between p-3.5" style={{ borderBottom: "1px solid " + t.line }}>
        <span style={{ color: t.text }} className="text-[13.5px] font-bold">Notificaciones</span>
        <button onClick={() => { ui.toast("Notificaciones marcadas como leídas"); close(); }}
          style={{ background: "transparent", border: 0, color: accent, fontSize: 11.5, fontWeight: 600, cursor: "pointer" }}>Marcar leídas</button>
      </div>
      <div style={{ maxHeight: 320, overflowY: "auto" }}>
        {alertasFull.slice(0, 5).map((a) => {
          const col = a.tipo === "error" ? err : a.tipo === "warn" ? warn : info;
          return (
            <button key={a.texto} onClick={() => { close(); ui.goto("alertas"); }}
              className="flex items-start gap-2.5 w-full text-left p-3.5"
              style={{ background: "transparent", border: 0, borderBottom: "1px solid " + t.line, cursor: "pointer" }}>
              <span style={{ width: 6, height: 6, borderRadius: 999, background: col, marginTop: 6, flexShrink: 0 }} />
              <span className="flex-1">
                <span style={{ color: t.text, display: "block" }} className="text-[12.5px] leading-snug">{a.texto}</span>
                <span style={{ color: t.dim, display: "block" }} className="text-[11px] mt-1">{a.modulo} · {a.fecha}</span>
              </span>
            </button>
          );
        })}
      </div>
      <div className="p-3">
        <Btn t={t} size="sm" onClick={() => { close(); ui.goto("alertas"); }}>Ver centro de alertas</Btn>
      </div>
    </div>
  );
}

function UserMenu({ t, ui, close }: any) {
  const items = [
    ["Mi perfil", UserCog, () => ui.form({ title: "Mi perfil", subtitle: "Ricardo Torres · Administrador", cta: "Guardar perfil", fields: [{ key: "nombre", label: "Nombre" }, { key: "email", label: "Correo", type: "email" }, { key: "puesto", label: "Puesto" }, { key: "telefono", label: "Teléfono" }], values: { nombre: "Ricardo Torres", email: "ricardo@rankpro.mx", puesto: "Director de operación", telefono: "+52 55 1188 4402" }, onSubmit: () => ui.toast("Perfil actualizado") })],
    ["Ajustes del panel", Settings, () => ui.goto("ajustes")],
    ["Roles y usuarios", ShieldCheck, () => ui.goto("roles")],
    ["Cambiar de rol", Users, () => ui.form({ title: "Cambiar de rol", subtitle: "Vista previa de permisos", cta: "Aplicar vista", fields: [{ key: "rol", label: "Ver el panel como", type: "select", options: ["Administrador", "Líder SEO", "Paid Media", "Desarrollo", "Finanzas"], wide: true }], onSubmit: (v: any) => ui.toast("Viendo el panel como " + v.rol) })],
    ["Cerrar sesión", LogOut, () => ui.confirm({ title: "Cerrar sesión", tone: "danger", text: "Se cerrará tu sesión en este dispositivo.", cta: "Cerrar sesión", onConfirm: () => ui.toast("Sesión cerrada", "error") })],
  ];
  return (
    <div style={{ position: "absolute", top: 44, right: 0, width: 236, background: t.card, border: "1px solid " + t.lineStrong, borderRadius: 13, padding: 6, boxShadow: "0 26px 60px -20px rgba(0,0,0,.6)", zIndex: 60 }}>
      <div className="px-3 py-2.5" style={{ borderBottom: "1px solid " + t.line, marginBottom: 4 }}>
        <div style={{ color: t.text }} className="text-[13px] font-bold">Ricardo Torres</div>
        <div style={{ color: t.dim }} className="text-[11.5px] mt-0.5">ricardo@rankpro.mx</div>
      </div>
      {items.map(([label, Ic, action]: any) => (
        <button key={label} onClick={() => { close(); action(); }}
          className="flex items-center gap-2.5 w-full text-left"
          style={{ fontSize: 12.5, color: label === "Cerrar sesión" ? err : t.text, padding: "8px 10px", borderRadius: 8, background: "transparent", border: 0, cursor: "pointer" }}
          onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.background = t.hover; }}
          onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.background = "transparent"; }}>
          <Ic size={14} color={label === "Cerrar sesión" ? err : t.dim} />{label}
        </button>
      ))}
    </div>
  );
}

function CommandPalette({ t, ui, close }: any) {
  const [q, setQ] = useState("");
  const ql = q.toLowerCase();
  const mods = modules.filter((m) => m.label.toLowerCase().includes(ql));
  const cls = clientes.filter((c) => c.empresa.toLowerCase().includes(ql) || c.contacto.toLowerCase().includes(ql));
  const kws = keywords.filter((k) => k.kw.toLowerCase().includes(ql)).slice(0, 4);
  const acciones = [
    { label: "Nuevo cliente", run: () => nuevoClienteModal(ui) },
    { label: "Asignar servicio", run: () => ui.form({ title: "Asignar servicio", cta: "Asignar servicio", fields: servicioFields, onSubmit: (v: any) => ui.toast((v.servicio || "Servicio") + " asignado a " + v.cliente) }) },
    { label: "Centro de alertas", run: () => ui.goto("alertas") },
    { label: "Ajustes del panel", run: () => ui.goto("ajustes") },
  ].filter((a) => a.label.toLowerCase().includes(ql));

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") close(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const row = (key: string, label: string, meta: string, run: any) => (
    <button key={key} onClick={() => { close(); run(); }}
      className="flex items-center justify-between gap-4 w-full text-left"
      style={{ padding: "9px 12px", borderRadius: 9, background: "transparent", border: 0, cursor: "pointer" }}
      onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.background = t.hover; }}
      onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.background = "transparent"; }}>
      <span style={{ color: t.text }} className="text-[13px]">{label}</span>
      <span style={{ color: t.dim, fontFamily: mono }} className="text-[11px]">{meta}</span>
    </button>
  );

  const group = (title: string, children: any) => (
    <div className="mb-2">
      <div style={{ color: t.dim }} className="text-[10px] font-bold uppercase tracking-[0.1em] px-3 mb-1">{title}</div>
      {children}
    </div>
  );

  return (
    <div onClick={close} style={{ position: "fixed", inset: 0, zIndex: 92, background: "rgba(2,6,12,.62)", display: "flex", justifyContent: "center", padding: "12vh 16px" }}>
      <div onClick={(e) => e.stopPropagation()}
        style={{ width: "min(560px, 100%)", height: "fit-content", maxHeight: "70vh", background: t.card, border: "1px solid " + t.lineStrong, borderRadius: 15, boxShadow: "0 40px 90px -30px rgba(0,0,0,.75)", overflow: "hidden", display: "flex", flexDirection: "column" }}>
        <div className="flex items-center gap-2.5 px-4" style={{ height: 52, borderBottom: "1px solid " + t.line }}>
          <Search size={16} color={t.dim} />
          <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Busca módulos, clientes, keywords o acciones…"
            style={{ background: "transparent", border: 0, outline: "none", color: t.text, fontSize: 14, width: "100%" }} />
          <span style={{ color: t.dim, fontFamily: mono, fontSize: 10.5, border: "1px solid " + t.line, borderRadius: 5, padding: "1px 5px" }}>ESC</span>
        </div>
        <div style={{ overflowY: "auto", padding: 8 }}>
          {acciones.length > 0 && group("Acciones", acciones.map((a) => row("a" + a.label, a.label, "acción", a.run)))}
          {mods.length > 0 && group("Módulos", mods.map((m) => row("m" + m.key, m.label, m.group, () => ui.goto(m.key))))}
          {cls.length > 0 && group("Clientes", cls.map((c) => row("c" + c.empresa, c.empresa, money(c.mrr), () => ui.openCliente(c))))}
          {kws.length > 0 && group("Keywords", kws.map((k) => row("k" + k.kw, k.kw, "#" + k.pos + " · " + k.cliente, () => ui.goto("keywords"))))}
          {acciones.length + mods.length + cls.length + kws.length === 0 && (
            <div style={{ color: t.dim, padding: 24, textAlign: "center" }} className="text-[13px]">Sin resultados para "{q}"</div>
          )}
        </div>
      </div>
    </div>
  );
}

/* ─────────────────────────  shell  ───────────────────────── */
export default function AgencyOSPage() {
  const [theme, setTheme] = useState<"dark" | "light">("dark");
  const [active, setActive] = useState("dashboard");
  const [collapsed, setCollapsed] = useState(false);
  const [modal, setModal] = useState<any>(null);
  const [toasts, setToasts] = useState<any[]>([]);
  const [notif, setNotif] = useState(false);
  const [menu, setMenu] = useState(false);
  const [palette, setPalette] = useState(false);
  const [cuenta, setCuenta] = useState<any>(null);
  const t = theme === "dark" ? dark : light;

  const toast = (msg: string, tone?: string) => {
    const id = Math.random().toString(36).slice(2);
    setToasts((x) => [...x, { id, msg, tone }]);
    setTimeout(() => setToasts((x) => x.filter((y) => y.id !== id)), 2800);
  };

  const ui = {
    toast,
    detail: (o: any) => setModal({ kind: "detail", ...o }),
    form: (o: any) => setModal({ kind: "form", ...o }),
    confirm: (o: any) => setModal({ kind: "confirm", ...o, onSubmit: () => o.onConfirm && o.onConfirm() }),
    custom: (o: any) => setModal({ kind: "custom", ...o }),
    goto: (k: string) => { setCuenta(null); setActive(k); },
    openCliente: (c: any) => { setCuenta(c); setActive("crm"); },
  };

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") { e.preventDefault(); setPalette(true); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const groups = ["General", "Operación", "Administración"];
  const extras = [{ key: "alertas", label: "Alertas", icon: Bell }, { key: "ajustes", label: "Ajustes", icon: Settings }];
  const current = modules.find((m) => m.key === active) || extras.find((m) => m.key === active) || modules[0];

  const body: any = {
    dashboard: <Dashboard t={t} ui={ui} />, crm: <CRM t={t} ui={ui} />, servicios: <Servicios t={t} ui={ui} />,
    seo: <SEOModule t={t} ui={ui} />, keywords: <Keywords t={t} ui={ui} />, ads: <Ads t={t} ui={ui} />,
    conversiones: <Conversiones t={t} ui={ui} />, desarrollo: <Desarrollo t={t} ui={ui} />,
    finanzas: <Finanzas t={t} ui={ui} />, archivos: <Archivos t={t} ui={ui} />, roles: <Roles t={t} ui={ui} />,
    integraciones: <Integraciones t={t} ui={ui} />, alertas: <Alertas t={t} ui={ui} />, ajustes: <Ajustes t={t} ui={ui} />,
    reportes: <Reportes t={t} ui={ui} />, automatizaciones: <Automatizaciones t={t} ui={ui} />,
  };

  // chrome claro: sidebar + header siempre en blanco
  const c = { ...t, app: "#FFFFFF", sidebar: "#FFFFFF", text: "#0F172A", dim: "#64748B", mid: "#475569", line: "rgba(15,23,42,.10)", hover: "rgba(15,23,42,.05)", inputBg: "#F1F5F9" };

  const navBtn = (m: any, on: boolean) => (
    <button key={m.key} onClick={() => ui.goto(m.key)} title={m.label}
      style={{
        display: "flex", alignItems: "center", gap: 10, width: "100%", padding: collapsed ? "9px 0" : "8px 11px",
        justifyContent: collapsed ? "center" : "flex-start", borderRadius: 8, cursor: "pointer",
        background: on ? brand + "1f" : "transparent", border: 0,
        color: on ? brand : c.mid, fontSize: 13, fontWeight: on ? 700 : 500,
        borderLeft: on && !collapsed ? "2px solid " + brand : "2px solid transparent",
      }}
      onMouseEnter={(e) => { if (!on) (e.currentTarget as HTMLElement).style.background = c.hover; }}
      onMouseLeave={(e) => { if (!on) (e.currentTarget as HTMLElement).style.background = "transparent"; }}>
      <m.icon size={16} />{!collapsed && m.label}
    </button>
  );

  return (
    <div style={{ minHeight: "100vh", background: c.app, color: c.text, fontFamily: '"DM Sans", system-ui, sans-serif', display: "flex" }}>
      <aside style={{
        width: collapsed ? 62 : 208, flex: "0 0 " + (collapsed ? 62 : 208) + "px", background: c.sidebar,
        borderRight: "1px solid " + c.line, position: "sticky", top: 0, height: "100vh",
        display: "flex", flexDirection: "column", transition: "width .18s",
      }}>
        <div className="flex items-center gap-2.5 px-4" style={{ height: 56, borderBottom: "1px solid " + c.line }}>
          <div style={{ width: 26, height: 26, borderRadius: 7, background: brand, display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
            <span style={{ color: "#fff", fontWeight: 800, fontSize: 13 }}>A</span>
          </div>
          {!collapsed && (
            <div>
              <div style={{ color: c.text }} className="text-[13.5px] font-bold leading-tight">AgencyOS</div>
              <div style={{ color: c.dim }} className="text-[10px] leading-tight">RankPro interno</div>
            </div>
          )}
        </div>

        <nav style={{ flex: 1, overflowY: "auto", padding: "12px 8px" }}>
          {groups.map((g) => (
            <div key={g} className="mb-4">
              {!collapsed && (
                <div style={{ color: c.dim }} className="text-[10px] font-bold uppercase tracking-[0.1em] px-3 mb-1.5">{g}</div>
              )}
              <div className="space-y-0.5">
                {modules.filter((m) => m.group === g).map((m) => navBtn(m, m.key === active && !cuenta))}
              </div>
            </div>
          ))}
          <div className="mb-2">
            {!collapsed && <div style={{ color: c.dim }} className="text-[10px] font-bold uppercase tracking-[0.1em] px-3 mb-1.5">Sistema</div>}
            <div className="space-y-0.5">{extras.map((m) => navBtn(m, m.key === active))}</div>
          </div>
        </nav>

        <div style={{ padding: 10, borderTop: "1px solid " + c.line }}>
          <button onClick={() => setCollapsed(!collapsed)}
            style={{ display: "flex", alignItems: "center", gap: 9, width: "100%", justifyContent: collapsed ? "center" : "flex-start", padding: "8px 11px", borderRadius: 8, background: "transparent", border: 0, color: c.dim, fontSize: 12.5, cursor: "pointer" }}>
            <ChevronRight size={15} style={{ transform: collapsed ? "none" : "rotate(180deg)", transition: "transform .18s" }} />
            {!collapsed && "Colapsar"}
          </button>
        </div>
      </aside>

      <div style={{ flex: 1, minWidth: 0 }}>
        <header style={{
          height: 56, background: "#FFFFFFe6", borderBottom: "1px solid " + c.line,
          position: "sticky", top: 0, zIndex: 40, backdropFilter: "blur(8px)",
        }} className="flex items-center gap-4 px-5">
          <div className="flex items-center gap-2 text-[13px]" style={{ color: c.dim }}>
            <span>{current.group || "Sistema"}</span><ChevronRight size={13} />
            {cuenta ? (
              <>
                <button onClick={() => setCuenta(null)} style={{ background: "transparent", border: 0, color: c.dim, fontSize: 13, cursor: "pointer", padding: 0 }}>CRM Clientes</button>
                <ChevronRight size={13} />
                <span style={{ color: c.text, fontWeight: 600 }}>{cuenta.empresa}</span>
              </>
            ) : (
              <span style={{ color: c.text, fontWeight: 600 }}>{current.label}</span>
            )}
          </div>

          <button onClick={() => setPalette(true)}
            className="hidden md:flex items-center gap-2 ml-auto"
            style={{ background: c.inputBg, border: "1px solid " + c.line, borderRadius: 9, padding: "0 11px", height: 34, flex: "0 1 268px", minWidth: 0, cursor: "pointer" }}>
            <Search size={14} color={c.dim} style={{ flexShrink: 0 }} />
            <span style={{ color: c.dim, fontSize: 12.5, flex: 1, minWidth: 0, textAlign: "left", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>Buscar clientes, campañas, keywords…</span>
            <span style={{ color: c.dim, fontFamily: mono, fontSize: 10.5, border: "1px solid " + c.line, borderRadius: 5, padding: "1px 5px", flexShrink: 0 }}>⌘K</span>
          </button>

          <div className="flex items-center gap-2 md:ml-0 ml-auto">
            <button onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
              style={{ width: 32, height: 32, borderRadius: 8, background: "transparent", border: "1px solid " + c.line, color: c.mid, display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer" }}>
              {theme === "dark" ? <Sun size={15} /> : <Moon size={15} />}
            </button>

            <div style={{ position: "relative" }}>
              <button onClick={() => { setNotif(!notif); setMenu(false); }}
                style={{ width: 32, height: 32, borderRadius: 8, background: notif ? c.hover : "transparent", border: "1px solid " + c.line, color: c.mid, display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", position: "relative" }}>
                <Bell size={15} />
                <span style={{ position: "absolute", top: 6, right: 7, width: 6, height: 6, borderRadius: 999, background: err }} />
              </button>
              {notif && <NotifPanel t={c} ui={ui} close={() => setNotif(false)} />}
            </div>

            <div style={{ position: "relative" }}>
              <button onClick={() => { setMenu(!menu); setNotif(false); }}
                className="flex items-center gap-2.5 pl-2"
                style={{ borderLeft: "1px solid " + c.line, background: "transparent", border: 0, cursor: "pointer" }}>
                <div style={{ width: 28, height: 28, borderRadius: 999, background: brand, color: "#fff", fontSize: 11, fontWeight: 700, display: "flex", alignItems: "center", justifyContent: "center" }}>RT</div>
                <div className="hidden sm:block text-left">
                  <div style={{ color: c.text }} className="text-[12.5px] font-semibold leading-tight">Ricardo Torres</div>
                  <div style={{ color: c.dim }} className="text-[10.5px] leading-tight">Administrador</div>
                </div>
                <ChevronDown size={13} color={c.dim} />
              </button>
              {menu && <UserMenu t={c} ui={ui} close={() => setMenu(false)} />}
            </div>
          </div>
        </header>

        <main style={{ padding: 22 }}>
          {cuenta
            ? <ClienteCuenta t={t} ui={ui} cliente={cuenta} back={() => setCuenta(null)} />
            : body[active]}
        </main>
      </div>

      {modal && <Modal key={(modal.kind || "") + (modal.title || "")} t={t} modal={modal} close={() => setModal(null)} />}
      {palette && <CommandPalette t={t} ui={ui} close={() => setPalette(false)} />}
      <Toasts t={t} toasts={toasts} />
    </div>
  );
}
