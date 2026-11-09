import { Target, Search, Code2, Gauge, BarChart2, Instagram, Rocket, Shield, Zap, BedDouble, Smile, Workflow } from "lucide-react";
import type { Page } from "./types";

// ─── Shared Data ──────────────────────────────────────────────────────────────
export const navServices = [
  { icon: Target,    title: "SEM · Ads",      desc: "Campañas rentables en Google",  page: "service-sem"       as Page },
  { icon: Search,    title: "SEO Orgánico",   desc: "Top 3 en Google garantizado",  page: "service-seo"       as Page },
  { icon: Code2,     title: "Web a Medida",   desc: "Diseño + código premium",       page: "service-web"       as Page },
  { icon: Gauge,     title: "PageSpeed",      desc: "Velocidad 90+ en todo",         page: "service-pagespeed" as Page },
  { icon: BarChart2, title: "Analytics",      desc: "Datos que generan decisiones",  page: "service-analytics" as Page },
  { icon: Instagram, title: "Redes Sociales", desc: "Gestión y crecimiento orgánico", page: "service-social"   as Page },
  { icon: Workflow,  title: "Automatización de Procesos", desc: "Flujos con n8n, CRM y notificaciones", page: "it-automatizacion" as Page },
];

export const navProducts = [
  { icon: Rocket,    title: "RankDash Pro",      desc: "Panel de control unificado",       page: "productos" as Page },
  { icon: BarChart2, title: "Analytics Suite",   desc: "Reportes automáticos y KPIs",      page: "productos" as Page },
  { icon: Shield,    title: "SEO Audit Tool",    desc: "Auditorías técnicas al instante",   page: "productos" as Page },
  { icon: Zap,       title: "Ads Manager",       desc: "Gestión multicanal de anuncios",   page: "productos" as Page },
  { icon: BedDouble, title: "PMS Hotelero",      desc: "Gestión hotelera todo en uno",     page: "pms-hotelero" as Page },
  { icon: Smile,     title: "DPMS Odontológico", desc: "Software clínico para dentistas",  page: "dpms-odontologico" as Page },
];

export const globalStats = [
  { value: "500+",   label: "Clientes satisfechos" },
  { value: "8 años", label: "De experiencia en México" },
  { value: "98%",    label: "Tasa de retención" },
  { value: "$120M+", label: "En ventas generadas" },
];

export const testimonials = [
  { name: "Sofía Ramírez",  role: "CEO, FashionMX",              photo: "https://images.unsplash.com/photo-1580489944761-15a19d654956?w=80&h=80&fit=crop&auto=format", text: "RankPro transformó nuestra presencia digital. En 4 meses triplicamos el tráfico orgánico y nuestras ventas online subieron un 180%. El equipo es excepcional.", stars: 5 },
  { name: "Carlos Mendoza", role: "Director de Marketing, TechCorp MX",  photo: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&auto=format", text: "Llevamos 2 años con RankPro y los resultados hablan solos: 40% menos costo por adquisición, 3x más leads calificados y un ROI de 520% en Google Ads.", stars: 5 },
  { name: "Ana Gutiérrez",  role: "Fundadora, Clínica Estética Lumina",  photo: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&h=80&fit=crop&auto=format", text: "Antes teníamos apenas 5 citas nuevas al mes. Ahora gestionamos más de 60 gracias a la estrategia SEO local y los anuncios de Google que RankPro configuró.", stars: 5 },
];

export const posts = [
  { id: 1, cat: "SEO", title: "Cómo posicionar tu empresa en el Top 3 de Google México en 2024", excerpt: "Guía práctica con las estrategias de SEO técnico, contenido y link building que están funcionando hoy en el mercado mexicano.", img: "https://images.unsplash.com/photo-1432888498266-38ffec3eaf0a?w=600&h=340&fit=crop&auto=format", author: "Valeria Mora", date: "10 Jun 2024", readTime: "8 min", featured: true },
  { id: 2, cat: "Google Ads", title: "Google Ads en México: Cómo reducir tu CPC un 30% sin perder conversiones", excerpt: "Técnicas avanzadas de optimización de pujas, quality score y estructura de campañas para el mercado hispanohablante.", img: "https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=600&h=340&fit=crop&auto=format", author: "Diego Sánchez", date: "5 Jun 2024", readTime: "6 min", featured: false },
  { id: 3, cat: "Web Dev", title: "Core Web Vitals 2024: La guía definitiva para alcanzar 90+ en PageSpeed", excerpt: "Todo lo que necesitas saber sobre LCP, FID, CLS y cómo optimizar tu sitio para cumplir con los nuevos estándares de Google.", img: "https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&h=340&fit=crop&auto=format", author: "Fernanda López", date: "1 Jun 2024", readTime: "10 min", featured: false },
  { id: 4, cat: "Analytics", title: "GA4 para empresas mexicanas: Migración, configuración y primeros reportes", excerpt: "Después del fin de Universal Analytics, aquí está todo lo que debes configurar en Google Analytics 4 desde cero.", img: "https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&h=340&fit=crop&auto=format", author: "Andrés Castillo", date: "28 May 2024", readTime: "7 min", featured: false },
  { id: 5, cat: "Social Media", title: "Instagram vs TikTok para negocios en México: ¿Dónde invertir en 2024?", excerpt: "Análisis de datos reales de 150 clientes mexicanos: tasas de conversión, CPM y engagement por plataforma y sector.", img: "https://images.unsplash.com/photo-1611162616305-c69b3fa7fbe0?w=600&h=340&fit=crop&auto=format", author: "Camila Ríos", date: "22 May 2024", readTime: "5 min", featured: false },
  { id: 6, cat: "Estrategia", title: "Marketing digital para e-commerce en México: Guía completa 2024", excerpt: "Desde la captación hasta la retención: el embudo de ventas digital completo para tiendas online en el mercado mexicano.", img: "https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&h=340&fit=crop&auto=format", author: "Ricardo Torres", date: "15 May 2024", readTime: "12 min", featured: false },
];

export const catColors: Record<string, string> = {
  SEO: "bg-emerald-50 text-emerald-700",
  "Google Ads": "bg-blue-50 text-blue-700",
  "Web Dev": "bg-purple-50 text-purple-700",
  Analytics: "bg-amber-50 text-amber-700",
  "Social Media": "bg-pink-50 text-pink-700",
  Estrategia: "bg-teal-50 text-teal-700",
};

export const trafficData = [
  { mes: "Ene", organico: 21000, pagado: 8500, directo: 4200 },
  { mes: "Feb", organico: 24500, pagado: 9200, directo: 4800 },
  { mes: "Mar", organico: 28000, pagado: 11000, directo: 5100 },
  { mes: "Abr", organico: 31200, pagado: 10500, directo: 5600 },
  { mes: "May", organico: 38000, pagado: 12300, directo: 6200 },
  { mes: "Jun", organico: 42891, pagado: 14200, directo: 7100 },
];

export const roiData = [
  { mes: "Ene", roi: 280 }, { mes: "Feb", roi: 310 }, { mes: "Mar", roi: 350 },
  { mes: "Abr", roi: 390 }, { mes: "May", roi: 405 }, { mes: "Jun", roi: 420 },
];

export const channelData = [
  { name: "SEO Orgánico", value: 58, color: "#0F9D6E" },
  { name: "Google Ads",   value: 24, color: "#34D399" },
  { name: "Social Media", value: 12, color: "#10B981" },
  { name: "Directo",      value: 6,  color: "#6EE7B7" },
];
