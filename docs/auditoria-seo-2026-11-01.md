# Auditoría SEO rankprosolutions.com.mx — 2026-11-01

Fuentes: HTML en vivo (curl/WebFetch), sitemap, robots, código del repo.
**No obtenido:** Semrush (exige iniciar sesión; no puedo crear cuenta ni meter credenciales) y Search Console real (la BD local no tiene conexión ni propiedades; la integración nunca se ha probado contra Google).

## Lo bueno
- Todas las páginas revisadas dan 200, con title, description, canonical y robots `index, follow`.
- JSON-LD correcto: Organization, WebSite, Service, FAQPage, BreadcrumbList, ContactPage, Blog.
- `www` redirige 301, `llms.txt` responde 200, el 404 devuelve 404 real, sitemap con 19 URLs y robots.txt sano.
- Un solo H1 por página y las imágenes revisadas traen `alt`.

## Hallazgos (por severidad)
1. **Blog vacío.** "Todavía no hay artículos publicados" con 6 categorías, sin contenido que posicione (cluster informacional).
2. **Cifras no verificadas publicadas** en `/servicios/sem-google-ads`: ROAS 4.8x, 312 cuentas, -38% CPL. El propio código dice que no están verificadas. Va contra la regla del proyecto y contra E-E-A-T. La calculadora muestra 5.9x cuando con sus datos de ejemplo da 7.8x.
3. **Sin formulario de contacto.** Todo el CTA va a WhatsApp (nofollow) o /agendar. Se pierde atribución y los usuarios sin WhatsApp.
4. **Sin señales locales:** no hay dirección ni teléfono visibles en texto en la home; no hay página por ciudad/nicho.
5. **Home genérica:** H2 "dominar el mundo digital" sin keywords; anclas "Ver más" ×6; ancla vacía al WhatsApp final; promete "Top 3" y a la vez dice que no garantiza posiciones.
6. **Pocos enlaces internos** de servicios a blog/casos.
7. **Rendimiento:** primera carga de la home 2.7 s (luego 0.4 s en otras páginas). Hay que medir con PageSpeed/CrUX.
8. **Sitemap:** las 19 URLs llevan el mismo `lastmod` (2026-10-25) porque usa `filemtime` del blade; Google lo ignora si no es confiable.
9. **Casos de éxito:** 3 tests fallando; solo 2 casos con nombre.
10. **Landings:** 7 servicios publicados, pero ninguna landing por intención transaccional local ("agencia de SEO en Cuernavaca/CDMX", "Google Ads para hoteles").

## Kanban

### Backlog
- Landing "Google Ads para hoteles" y "SEO para hoteles" (usa el caso Fratelli y la metodología propia)
- Páginas por ciudad (según dónde estén los clientes reales)
- Comparativas / alternativas
- Reseñas reales en Google Business Profile + schema AggregateRating solo si son verificables
- Portal de cliente

### Por hacer (esta semana)
- Quitar o verificar cifras 4.8x/312/-38% y corregir la calculadora
- Conectar Search Console real (cuenta de servicio) y verificar la propiedad de dominio
- Instalar GA4 + conversiones (clic WhatsApp, /agendar completado) vía GTM antes de gastar un peso
- Medir PageSpeed/CrUX de las 4 URLs clave
- Arreglar anclas ("Ver más", WhatsApp vacío) y H2 de la home con keywords
- Dirección/teléfono visibles + completar Google Business Profile

### En proceso (mes 1)
- Publicar 8–12 artículos del blog (2 por categoría) con enlaces a su servicio
- Formulario de contacto con ruta POST y evento de conversión
- `lastmod` real en el sitemap
- Arreglar los 3 tests de `CasosExitoTest`

### Revisión
- Enlazado interno servicio ↔ blog ↔ casos
- Redirección y canonical de URLs con UTM de las campañas

### Hecho
- Meta, canonical, robots y JSON-LD base
- llms.txt, sitemap, Consent Mode v2

## Search Console / publicidad ($10k)
No puedo ver datos reales. Para que me los des: exporta de Search Console (Rendimiento, últimos 16 meses) consultas y páginas en CSV, o crea la cuenta de servicio y la conectamos en `/admin/integraciones/search-console`.

Sin datos, lo que sí se puede afirmar:
- El blog vacío y las 19 URLs indican que el tráfico orgánico depende de pocas páginas; es probable que haya pocas impresiones. Confírmalo en Search Console.
- Un sitio con poca señal de marca se beneficia más de **búsqueda de pago con intención** que de redes frías.

Reparto orientativo (hipótesis, ajustar con datos tras 2–3 semanas):
| Canal | % | Uso |
|---|---|---|
| Google Ads (búsqueda) | 55–60 % | keywords de servicio + "agencia de seo/google ads" a /servicios y /agendar |
| Meta (Facebook/Instagram) | 25–30 % | remarketing + lead ads / video a /agendar |
| TikTok | 10 % | prueba pequeña de awareness; el B2B suele rendir menos |
| Reserva para medir | 5 % | pruebas A/B de landing |

Condiciones antes de gastar: conversiones en GA4/GTM funcionando, cifras falsas retiradas, formulario o /agendar con evento. Aprendizaje propio: en Aguascalientes 2026 las llamadas rindieron más que WhatsApp; considera un número de llamada con seguimiento.

---
## Anexo: datos reales (Chrome, 2026-11-01)

**Semrush (MX, escritorio, 31-oct):** Authority Score n/d, tráfico orgánico 0, tráfico de pago 0, 2 keywords orgánicas, 0 backlinks, 0 dominios de referencia, 0 menciones en IA (ChatGPT, Gemini, AI Overviews).

**Search Console (31-jul a 30-oct, 90 días):** 2 clics, 160 impresiones, CTR 1,2 %, posición media 45,1.
- Consultas: "posicionamiento organico" 50 imp., "seo organico" 28, "servicios de posicionamiento organico seo" (1 clic, 4 imp.), marca 11. Todo es SEO orgánico, nada de Google Ads ni desarrollo web.
- Páginas: `/blog/categoria/seo-organico` 133 imp. y 0 clics; `/servicios/seo-organico` 1 clic; `/` 1 clic; `/agendar` 13 imp. (no está en el sitemap).
- Indexación: 13 indexadas, 13 sin indexar: 12 por `noindex` (validación "Iniciada") y 1 redirección. Hoy las URLs revisadas responden `index, follow`, así que el noindex es el rastro del incidente de agosto.
