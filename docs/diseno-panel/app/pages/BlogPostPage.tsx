import { ChevronRight, Twitter, Linkedin, Facebook } from "lucide-react";
import type { Page } from "../types";
import { posts, catColors } from "../data";

export default function BlogPostPage({ setPage }: { setPage: (p: Page) => void }) {
  const post = posts[0];

  return (
    <>
      <section className="bg-white py-8 px-4 border-b border-black/5">
        <div className="max-w-4xl mx-auto">
          <button onClick={() => setPage("blog")} className="flex items-center gap-1.5 text-sm text-[#6B7280] hover:text-[#0F9D6E] mb-6 transition-colors">
            <ChevronRight size={14} className="rotate-180" />Volver al Blog
          </button>
          <span className={`text-xs font-semibold px-3 py-1 rounded-full mb-4 inline-block ${catColors[post.cat]}`}>{post.cat}</span>
          <h1 className="text-4xl font-extrabold text-[#1A2332] leading-tight mb-5">{post.title}</h1>
          <div className="flex items-center gap-4 text-sm text-[#6B7280] mb-8">
            <div className="flex items-center gap-2">
              <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=40&h=40&fit=crop" alt="" className="w-9 h-9 rounded-full object-cover" />
              <div><div className="font-semibold text-[#1A2332]">Valeria Mora</div><div className="text-xs">Directora de SEO · RankPro</div></div>
            </div>
            <span>·</span><span>{post.date}</span><span>·</span><span>{post.readTime} de lectura</span>
          </div>
          <img src={post.img} alt={post.title} className="w-full rounded-2xl object-cover h-80 shadow-xl shadow-black/5 mb-10" />
        </div>
      </section>

      <section className="py-12 px-4 bg-white">
        <div className="max-w-4xl mx-auto grid lg:grid-cols-4 gap-10">
          {/* Article */}
          <article className="lg:col-span-3 prose max-w-none">
            <p className="text-[#1A2332] text-lg leading-relaxed mb-6 font-medium">
              El mercado digital mexicano es uno de los más competitivos de América Latina, con más de 92 millones de usuarios de internet y un e-commerce que crece a doble dígito año tras año. Posicionarse en el Top 3 de Google no es un lujo, es una necesidad estratégica.
            </p>
            {[
              { h: "1. Auditoría técnica: el punto de partida", p: "Antes de cualquier acción de SEO, necesitas saber dónde estás. Una auditoría técnica completa con herramientas como Screaming Frog, Google Search Console y PageSpeed Insights te dará el diagnóstico real de tu sitio. Errores 404, páginas de carga lenta, problemas de indexación o estructura de URLs deficiente son los enemigos silenciosos del posicionamiento." },
              { h: "2. Investigación de palabras clave para México", p: "No basta con traducir una estrategia de keywords al español. El mercado mexicano tiene sus propias búsquedas, regionalismos y patrones de consumo. Usa herramientas como Semrush o Ahrefs configuradas para Google México (google.com.mx) y prioriza keywords con intención transaccional para tus páginas de servicio o producto." },
              { h: "3. Contenido que responde preguntas reales", p: "En 2024, Google premia el contenido que demuestra E-E-A-T (Experiencia, Expertise, Autoridad y Confianza). Para empresas mexicanas, esto significa crear contenido que refleje conocimiento local: casos de estudio con clientes reales, datos del mercado nacional, y respuestas específicas a las preguntas que hacen tus clientes potenciales en México." },
              { h: "4. Link building white hat en el mercado hispano", p: "Los backlinks siguen siendo uno de los factores de posicionamiento más poderosos. Para México, enfócate en conseguir enlaces de medios digitales mexicanos relevantes (El Economista Digital, Merca2.0, Forbes México), directorios empresariales locales, y colaboraciones con otras empresas no competidoras que tengan audiencias similares a la tuya." },
            ].map((sec) => (
              <div key={sec.h} className="mb-8">
                <h2 className="text-2xl font-extrabold text-[#1A2332] mb-3">{sec.h}</h2>
                <p className="text-[#6B7280] leading-relaxed">{sec.p}</p>
              </div>
            ))}

            <div className="bg-emerald-50 border-l-4 border-[#0F9D6E] rounded-r-2xl p-6 my-8">
              <p className="font-bold text-[#1A2332] mb-2">💡 Tip de experto</p>
              <p className="text-[#6B7280] text-sm leading-relaxed">
                En nuestra experiencia con más de 200 clientes mexicanos, el 80% del tráfico orgánico proviene de apenas el 20% de las palabras clave. Identifica esas keywords de alto impacto y optimiza primero las páginas que ya están en posición 4–10: son las más fáciles de escalar al Top 3.
              </p>
            </div>

            <div className="flex gap-3 mt-10 pt-8 border-t border-black/5">
              <span className="text-sm text-[#6B7280]">Compartir:</span>
              {[Twitter, Linkedin, Facebook].map((Icon, i) => (
                <button key={i} className="w-8 h-8 rounded-lg bg-[#F5F7FA] flex items-center justify-center hover:bg-[#0F9D6E] hover:text-white text-[#6B7280] transition-colors">
                  <Icon size={14} />
                </button>
              ))}
            </div>
          </article>

          {/* Sidebar */}
          <aside className="space-y-6">
            <div className="bg-gradient-to-br from-[#0F9D6E] to-[#34D399] rounded-2xl p-6 text-white">
              <h3 className="font-bold text-lg mb-2">¿Necesitas ayuda con tu SEO?</h3>
              <p className="text-emerald-100 text-sm mb-4 leading-relaxed">Agenda una consultoría gratuita y recibe un análisis personalizado de tu sitio.</p>
              <button onClick={() => setPage("agendar")} className="w-full bg-white text-[#0F9D6E] font-semibold py-2.5 rounded-xl text-sm hover:bg-emerald-50 transition-colors">
                Agendar gratis →
              </button>
            </div>

            <div className="bg-[#F5F7FA] rounded-2xl p-5 border border-black/5">
              <h4 className="font-bold text-[#1A2332] text-sm mb-4">Artículos relacionados</h4>
              <div className="space-y-4">
                {posts.slice(1, 4).map((p) => (
                  <button key={p.id} onClick={() => setPage("blog-post")} className="group text-left w-full">
                    <div className="text-sm font-medium text-[#1A2332] group-hover:text-[#0F9D6E] transition-colors leading-snug mb-1">{p.title}</div>
                    <div className="text-xs text-[#6B7280]">{p.readTime} · {p.cat}</div>
                  </button>
                ))}
              </div>
            </div>

            <div className="bg-[#F5F7FA] rounded-2xl p-5 border border-black/5">
              <h4 className="font-bold text-[#1A2332] text-sm mb-3">Newsletter semanal</h4>
              <p className="text-xs text-[#6B7280] mb-3">Estrategias de marketing digital cada semana.</p>
              <input type="email" placeholder="tu@email.com" className="w-full px-3 py-2.5 rounded-xl border border-black/10 text-xs mb-2 focus:outline-none focus:border-[#0F9D6E] bg-white" />
              <button className="w-full bg-gradient-to-r from-[#0F9D6E] to-[#34D399] text-white text-xs font-semibold py-2.5 rounded-xl hover:shadow-md hover:shadow-emerald-200 transition-all">
                Suscribirme gratis
              </button>
            </div>
          </aside>
        </div>
      </section>
    </>
  );
}
