import { useState } from "react";
import { BookOpen } from "lucide-react";
import type { Page } from "../types";
import { posts, catColors } from "../data";
import { GreenBadge } from "../components/shared";

export default function BlogPage({ setPage }: { setPage: (p: Page) => void }) {
  const [activeCategory, setActiveCategory] = useState("Todos");
  const cats = ["Todos", "SEO", "Google Ads", "Web Dev", "Analytics", "Social Media", "Estrategia"];
  const featured = posts[0];
  const rest = posts.slice(1).filter((p) => activeCategory === "Todos" || p.cat === activeCategory);

  return (
    <>
      <section className="bg-[#F5F7FA] py-20 px-4 border-b border-black/5">
        <div className="max-w-4xl mx-auto text-center">
          <GreenBadge><BookOpen size={12} />BLOG DE MARKETING DIGITAL</GreenBadge>
          <h1 className="text-5xl font-extrabold text-[#1A2332] mb-4">Conocimiento que<br /><span className="text-[#0F9D6E]">genera resultados</span></h1>
          <p className="text-[#6B7280] text-lg">Estrategias, tutoriales y casos de estudio del equipo de expertos de RankPro para el mercado digital mexicano.</p>
        </div>
      </section>

      {/* Featured */}
      <section className="py-16 px-4 bg-white">
        <div className="max-w-7xl mx-auto">
          <div onClick={() => setPage("blog-post")} className="group grid lg:grid-cols-2 gap-8 bg-[#F5F7FA] rounded-3xl overflow-hidden border border-black/5 cursor-pointer hover:shadow-2xl hover:shadow-emerald-100 transition-all duration-300">
            <div className="relative overflow-hidden h-72 lg:h-auto">
              <img src={featured.img} alt={featured.title} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
              <div className="absolute top-4 left-4 bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-xs font-bold px-3 py-1.5 rounded-full">
                ⭐ Artículo destacado
              </div>
            </div>
            <div className="p-10 flex flex-col justify-center">
              <span className={`text-xs font-semibold px-3 py-1 rounded-full mb-4 self-start ${catColors[featured.cat]}`}>{featured.cat}</span>
              <h2 className="text-3xl font-extrabold text-[#1A2332] leading-tight mb-4 group-hover:text-[#0F9D6E] transition-colors">{featured.title}</h2>
              <p className="text-[#6B7280] leading-relaxed mb-6">{featured.excerpt}</p>
              <div className="flex items-center gap-4 text-xs text-[#6B7280]">
                <div className="flex items-center gap-1.5"><img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=30&h=30&fit=crop" alt="" className="w-7 h-7 rounded-full object-cover" />{featured.author}</div>
                <span>·</span><span>{featured.date}</span><span>·</span><span>{featured.readTime} lectura</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Filters + grid */}
      <section className="pb-20 px-4 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-wrap gap-2 mb-10">
            {cats.map((c) => (
              <button key={c} onClick={() => setActiveCategory(c)}
                className={`px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 ${activeCategory === c ? "bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white shadow-md shadow-emerald-200" : "bg-[#F5F7FA] text-[#6B7280] hover:text-[#0F9D6E]"}`}>
                {c}
              </button>
            ))}
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {rest.map((post) => (
              <div key={post.id} onClick={() => setPage("blog-post")}
                className="group bg-white border border-black/5 rounded-2xl overflow-hidden hover:shadow-2xl hover:shadow-emerald-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div className="relative h-48 overflow-hidden">
                  <img src={post.img} alt={post.title} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                  <span className={`absolute top-3 left-3 text-xs font-semibold px-3 py-1 rounded-full ${catColors[post.cat]}`}>{post.cat}</span>
                </div>
                <div className="p-6">
                  <h3 className="font-bold text-[#1A2332] text-lg leading-snug mb-3 group-hover:text-[#0F9D6E] transition-colors">{post.title}</h3>
                  <p className="text-[#6B7280] text-sm leading-relaxed mb-4 line-clamp-2">{post.excerpt}</p>
                  <div className="flex items-center justify-between text-xs text-[#6B7280] border-t border-black/5 pt-4">
                    <span>{post.author}</span>
                    <div className="flex items-center gap-2"><span>{post.date}</span><span>·</span><span>{post.readTime}</span></div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Newsletter */}
      <section className="py-16 px-4 bg-gradient-to-br from-[#0F9D6E] to-[#059669]">
        <div className="max-w-2xl mx-auto text-center">
          <h2 className="text-3xl font-extrabold text-white mb-3">Recibe estrategias en tu email</h2>
          <p className="text-emerald-100 mb-7">Cada semana, las mejores tácticas de marketing digital para empresas mexicanas. Sin spam, solo valor.</p>
          <div className="flex gap-3 max-w-md mx-auto">
            <input type="email" placeholder="tu@empresa.com"
              className="flex-1 px-4 py-3.5 rounded-xl border-0 text-sm focus:outline-none focus:ring-2 focus:ring-white/50 bg-white text-[#1A2332]" />
            <button className="px-5 py-3.5 bg-[#1A2332] text-white rounded-xl font-semibold text-sm hover:bg-[#243040] transition-colors shrink-0">
              Suscribirme
            </button>
          </div>
          <p className="text-emerald-200 text-xs mt-3">+3,200 suscriptores. Cancela cuando quieras.</p>
        </div>
      </section>
    </>
  );
}
