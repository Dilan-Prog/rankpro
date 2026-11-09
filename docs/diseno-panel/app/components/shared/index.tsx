import { useState, useEffect, useRef } from "react";
import {
  Phone, Mail, MessageCircle, User, Menu, X, ChevronDown, ChevronRight,
  Rocket, Instagram, Twitter, Linkedin, Facebook
} from "lucide-react";
import type { Page } from "../../types";
import { navServices, navProducts } from "../../data";

// ─── Logo ─────────────────────────────────────────────────────────────────────
export function Logo({ onClick }: { onClick?: () => void }) {
  return (
    <button onClick={onClick} className="flex items-center gap-2.5 text-left">
      <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center shadow-lg shadow-emerald-200">
        <Rocket size={18} className="text-white" />
      </div>
      <div>
        <div className="font-extrabold text-[#1A2332] text-lg leading-tight">RankPro</div>
        <div className="text-[10px] text-[#6B7280] leading-tight font-medium tracking-wide">Digital Solutions & Services</div>
      </div>
    </button>
  );
}

// ─── GreenBadge ───────────────────────────────────────────────────────────────
export function GreenBadge({ children }: { children: React.ReactNode }) {
  return (
    <div className="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-[#0F9D6E] text-xs font-semibold px-4 py-2 rounded-full mb-4">
      {children}
    </div>
  );
}

// ─── SectionHeader ────────────────────────────────────────────────────────────
export function SectionHeader({ badge, badgeIcon: BadgeIcon, title, highlight, sub }: {
  badge: string; badgeIcon: React.ElementType; title: React.ReactNode; highlight?: string; sub?: string;
}) {
  return (
    <div className="text-center mb-14">
      <GreenBadge><BadgeIcon size={12} />{badge}</GreenBadge>
      <h2 className="text-4xl font-extrabold text-[#1A2332] tracking-tight">{title}</h2>
      {highlight && <span className="text-[#0F9D6E]"> {highlight}</span>}
      {sub && <p className="mt-4 text-[#6B7280] text-base max-w-xl mx-auto leading-relaxed">{sub}</p>}
    </div>
  );
}

// ─── WhatsAppFAB ──────────────────────────────────────────────────────────────
export function WhatsAppFAB() {
  return (
    <a href="https://wa.me/525512345678" target="_blank" rel="noopener noreferrer"
      className="fixed bottom-6 right-6 z-50 w-14 h-14 bg-[#25D366] rounded-full flex items-center justify-center shadow-xl shadow-green-400/40 hover:scale-110 hover:shadow-2xl hover:shadow-green-400/50 transition-all duration-300"
      aria-label="WhatsApp">
      <MessageCircle size={26} className="text-white fill-white" />
    </a>
  );
}

// ─── Top Bar ──────────────────────────────────────────────────────────────────
export function TopBar() {
  return (
    <div className="bg-[#0F9D6E] text-white text-sm py-2 px-4">
      <div className="max-w-7xl mx-auto flex items-center justify-between">
        <span className="font-medium tracking-wide hidden sm:block">Agencia de Marketing Digital en México</span>
        <div className="flex items-center gap-5 ml-auto sm:ml-0">
          <a href="tel:+525512345678" className="flex items-center gap-1.5 hover:text-emerald-100 transition-colors"><Phone size={13} /><span className="hidden md:inline">+52 55 1234 5678</span></a>
          <a href="https://wa.me/525512345678" className="flex items-center gap-1.5 hover:text-emerald-100 transition-colors"><MessageCircle size={13} /><span className="hidden md:inline">WhatsApp</span></a>
          <a href="mailto:hola@rankpro.mx" className="flex items-center gap-1.5 hover:text-emerald-100 transition-colors"><Mail size={13} /><span className="hidden md:inline">hola@rankpro.mx</span></a>
        </div>
      </div>
    </div>
  );
}

// ─── Mega Menu Panel ──────────────────────────────────────────────────────────
export function MegaMenuPanel({ items, highlight, onNavigate, defaultPage = "service-detail" }: {
  items: (typeof navServices[0] & { page?: Page })[]; highlight: { title: string; desc: string }; onNavigate: (p: Page) => void; defaultPage?: Page;
}) {
  return (
    <div role="menu" className="w-full bg-white rounded-2xl shadow-2xl shadow-black/15 ring-1 ring-black/5 border border-black/5 p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
      <div className="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-1">
        {items.map((item) => (
          <button key={item.title} role="menuitem" onClick={() => onNavigate((item as any).page ?? defaultPage)}
            className="group flex items-start gap-3 p-3 rounded-xl hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9D6E] transition-colors duration-200 text-left">
            <div className="w-9 h-9 rounded-lg bg-emerald-50 group-hover:bg-[#0F9D6E] flex items-center justify-center transition-colors duration-200 shrink-0 mt-0.5">
              <item.icon size={16} className="text-[#0F9D6E] group-hover:text-white transition-colors duration-200" />
            </div>
            <div className="min-w-0">
              <div className="font-semibold text-[#1A2332] text-sm leading-snug">{item.title}</div>
              <div className="text-xs text-[#6B7280] mt-0.5 leading-snug">{item.desc}</div>
            </div>
          </button>
        ))}
      </div>
      <div className="bg-gradient-to-br from-[#0F9D6E] to-[#34D399] rounded-xl p-5 flex flex-col justify-between">
        <div>
          <div className="text-white font-bold text-base leading-snug">{highlight.title}</div>
          <div className="text-emerald-100 text-sm mt-2 leading-relaxed">{highlight.desc}</div>
        </div>
        <button onClick={() => onNavigate("agendar")} className="mt-4 bg-white text-[#0F9D6E] text-sm font-semibold px-4 py-2 rounded-lg hover:bg-emerald-50 transition-colors w-full">
          Agendar Consultoría
        </button>
      </div>
    </div>
  );
}

// ─── Navbar ───────────────────────────────────────────────────────────────────
export function Navbar({ setPage }: { setPage: (p: Page) => void }) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [activeMenu, setActiveMenu] = useState<string | null>(null);
  const [scrolled, setScrolled] = useState(false);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const navRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 10);
    window.addEventListener("scroll", onScroll);
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  const enter = (id: string) => { if (timeoutRef.current) clearTimeout(timeoutRef.current); setActiveMenu(id); };
  const leave = () => { if (timeoutRef.current) clearTimeout(timeoutRef.current); timeoutRef.current = setTimeout(() => setActiveMenu(null), 220); };
  const close = () => { if (timeoutRef.current) clearTimeout(timeoutRef.current); setActiveMenu(null); };

  useEffect(() => {
    if (!activeMenu) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") close(); };
    const onDown = (e: MouseEvent) => {
      if (navRef.current && !navRef.current.contains(e.target as Node)) close();
    };
    window.addEventListener("keydown", onKey);
    window.addEventListener("mousedown", onDown);
    return () => { window.removeEventListener("keydown", onKey); window.removeEventListener("mousedown", onDown); };
  }, [activeMenu]);

  const navItems = [
    { label: "Inicio",      page: "home" as Page },
    { label: "Nosotros",    page: "nosotros" as Page },
    { label: "Servicios",   page: "servicios" as Page, mega: "servicios" },
    { label: "Productos",   page: "productos" as Page, mega: "productos" },
    { label: "Promociones", page: "promociones" as Page },
    { label: "Blog",        page: "blog" as Page },
  ];

  return (
    <nav className={`sticky top-0 z-50 bg-white transition-shadow duration-300 ${scrolled ? "shadow-md shadow-black/5" : "border-b border-black/5"}`}>
      <div className="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between gap-6">
        <Logo onClick={() => setPage("home")} />

        {/* Center nav links */}
        <div ref={navRef} className="hidden lg:flex relative items-center gap-0.5"
          onMouseLeave={leave}>
          {navItems.map((item) => (
            <div key={item.page}
              onMouseEnter={() => (item.mega ? enter(item.mega) : close())}>
              <button
                aria-haspopup={item.mega ? "true" : undefined}
                aria-expanded={item.mega ? activeMenu === item.mega : undefined}
                onClick={() => {
                  if (item.mega) { activeMenu === item.mega ? close() : enter(item.mega); }
                  else { close(); setPage(item.page); }
                }}
                className={`flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9D6E] ${activeMenu === item.mega ? "text-[#0F9D6E] bg-emerald-50" : "text-[#1A2332] hover:text-[#0F9D6E] hover:bg-emerald-50"}`}>
                {item.label}
                {item.mega && <ChevronDown size={13} className={`transition-transform duration-200 ${activeMenu === item.mega ? "rotate-180" : ""}`} />}
              </button>
            </div>
          ))}

          {activeMenu && (
            <div
              onMouseEnter={() => enter(activeMenu)}
              className="absolute top-full left-1/2 -translate-x-1/2 pt-3 w-[min(780px,calc(100vw-3rem))] z-50 origin-top"
              style={{ animation: "rpMega 160ms cubic-bezier(0.22,1,0.36,1) both" }}>
              <MegaMenuPanel
                items={activeMenu === "servicios" ? navServices : navProducts}
                highlight={activeMenu === "servicios"
                  ? { title: "¿Listo para crecer?", desc: "Agenda una consultoría gratuita de 30 min con nuestros expertos." }
                  : { title: "Gestiona todo desde RankDash", desc: "Panel unificado para todas tus campañas. Pruébalo gratis 14 días." }}
                onNavigate={(p) => { close(); setPage(p); }}
                defaultPage={activeMenu === "servicios" ? "service-detail" : "productos"}
              />
            </div>
          )}
        </div>

        {/* Right actions */}
        <div className="hidden lg:flex items-center gap-2 shrink-0">
          <button onClick={() => setPage("login")}
            className="flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-[#1A2332] hover:text-[#0F9D6E] transition-colors rounded-lg hover:bg-emerald-50">
            <User size={15} />Iniciar sesión
          </button>
          <button onClick={() => setPage("agendar")}
            className="px-4 py-2 rounded-lg text-sm font-semibold bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white shadow-md shadow-emerald-200 hover:shadow-lg hover:scale-105 transition-all duration-200">
            Agendar gratis
          </button>
        </div>

        <button className="lg:hidden p-2 rounded-lg hover:bg-emerald-50 text-[#1A2332]" onClick={() => setMobileOpen(!mobileOpen)}>
          {mobileOpen ? <X size={22} /> : <Menu size={22} />}
        </button>
      </div>

      {mobileOpen && (
        <div className="lg:hidden bg-white border-t border-black/5 px-4 py-4 space-y-1">
          {navItems.map((item) => (
            <button key={item.page} onClick={() => { setPage(item.page); setMobileOpen(false); }}
              className="w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium text-[#1A2332] hover:bg-emerald-50 hover:text-[#0F9D6E] transition-colors">
              {item.label}<ChevronRight size={15} />
            </button>
          ))}
          <div className="pt-3 space-y-2 border-t border-black/5">
            <button onClick={() => { setPage("login"); setMobileOpen(false); }} className="w-full px-4 py-3 rounded-xl text-sm font-medium text-[#1A2332] border border-black/10 hover:bg-emerald-50 transition-colors">Iniciar sesión</button>
            <button onClick={() => { setPage("dashboard"); setMobileOpen(false); }} className="w-full px-4 py-3 rounded-xl text-sm font-semibold bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white">Acceder al Panel</button>
          </div>
        </div>
      )}
    </nav>
  );
}

// ─── Footer ───────────────────────────────────────────────────────────────────
export function Footer({ setPage }: { setPage: (p: Page) => void }) {
  return (
    <footer className="bg-[#1A2332] text-white pt-16 pb-8 px-4">
      <div className="max-w-7xl mx-auto">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-8 pb-12 border-b border-white/10">
          <div className="col-span-2 md:col-span-1">
            <div className="flex items-center gap-2.5 mb-4">
              <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#0F9D6E] to-[#34D399] flex items-center justify-center">
                <Rocket size={18} className="text-white" />
              </div>
              <span className="font-extrabold text-lg">RankPro</span>
            </div>
            <p className="text-white/50 text-sm leading-relaxed mb-5">Agencia de marketing digital líder en México. Resultados reales, estrategia probada.</p>
            <div className="flex gap-3">
              {[Instagram, Twitter, Linkedin, Facebook].map((Icon, i) => (
                <button key={i} className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center hover:bg-[#0F9D6E] transition-colors duration-200"><Icon size={14} /></button>
              ))}
            </div>
          </div>
          {[
            { title: "Empresa",   items: [["Nosotros","nosotros"],["Servicios","servicios"],["Productos","productos"],["Blog","blog"]] },
            { title: "Servicios", items: [["Google Ads","servicios"],["SEO Orgánico","servicios"],["Desarrollo Web","servicios"],["PageSpeed","servicios"]] },
            { title: "Productos", items: [["RankDash Pro","productos"],["Analytics Suite","productos"],["SEO Audit","productos"],["Ads Manager","productos"]] },
            { title: "Contacto",  items: [["Agendar cita","agendar"],["Contacto","contacto"],["Promociones","promociones"],["WhatsApp","contacto"]] },
          ].map((col) => (
            <div key={col.title}>
              <div className="font-semibold text-sm mb-4">{col.title}</div>
              <ul className="space-y-2.5">
                {col.items.map(([label, page]) => (
                  <li key={label}><button onClick={() => setPage(page as Page)} className="text-white/50 text-sm hover:text-white transition-colors">{label}</button></li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <div className="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p className="text-white/40 text-xs">© 2024 RankPro Solutions · Todos los derechos reservados · México</p>
          <div className="flex gap-5 text-xs text-white/40">
            <button className="hover:text-white transition-colors">Aviso de privacidad</button>
            <button className="hover:text-white transition-colors">Términos de uso</button>
            <button className="hover:text-white transition-colors">Cookies</button>
          </div>
        </div>
      </div>
    </footer>
  );
}

// ─── Page Shell (navbar + footer) ─────────────────────────────────────────────
export function Shell({ setPage, children }: { setPage: (p: Page) => void; children: React.ReactNode }) {
  return (
    <>
      <TopBar />
      <Navbar setPage={setPage} />
      <main>{children}</main>
      <Footer setPage={setPage} />
      <WhatsAppFAB />
    </>
  );
}
