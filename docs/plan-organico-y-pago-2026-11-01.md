# Plan de posicionamiento orgánico + inversión de pago — rankprosolutions.com.mx

Fecha: 2026-11-01. Punto de partida (Semrush + Search Console): 0 tráfico en Semrush, 2 keywords, 0 backlinks, 160 impresiones y 2 clics en 90 días, posición media 45, 12 URLs aún con rastro de `noindex` por validar. La demanda ya detectada es de SEO orgánico ("posicionamiento orgánico", "seo orgánico").

Supuestos: presupuesto de **$10,000 MXN en total**, repartidos en 8 semanas. Si es mensual, multiplica las cifras por el mes, no cambia el orden de las fases.

---

## Parte 1. Orgánico (se hace siempre, ritmo mínimo viable)

**Mínimo semanal aunque haya poco tiempo:** 1 artículo, 1 enlace o mención externa, 20 minutos revisando Search Console.

### Semana 1 — Arreglos técnicos (1 día)
- [ ] Agregar `/agendar` al sitemap (hoy tiene impresiones y no está).
- [ ] `lastmod` real por URL en el sitemap (hoy las 19 tienen la misma fecha).
- [ ] Reenviar sitemap en Search Console y pedir indexar las 6 URLs principales.
- [ ] Verificar que la validación del `noindex` termine ("Iniciada" hoy).
- [ ] Home: H2 con keywords, quitar los 6 "Ver más", poner texto al enlace de WhatsApp, quitar "Top 3".
- [ ] Dirección, teléfono y zona de servicio visibles en texto (home, contacto, footer).
- [ ] Crear o completar Google Business Profile con los mismos datos (NAP idéntico).
- [ ] Quitar cifras no verificadas de `/servicios/sem-google-ads` y corregir la calculadora.
- [ ] GA4 + GTM: eventos de clic a WhatsApp, `/agendar` completado y llamada.

### Semanas 2 a 8 — Contenido (1 artículo por semana como mínimo, 2 si se puede)
Prioridad por lo que Google ya muestra:
1. "Posicionamiento orgánico: qué es y cuánto cuesta en México" (pilar, enlaza a `/servicios/seo-organico`).
2. "SEO orgánico vs SEM: cuál te conviene".
3. "Cuánto cuesta el SEO en México (precios reales)" — responde la consulta "costo orgánico seo".
4. "Cuánto tarda el SEO en dar resultados".
5. "Cómo elegir una agencia de SEO" (intención comercial).
6. "SEO para hoteles: guía" (usa la metodología propia y el caso Hotel Fratelli).
7. "Google Ads para hoteles: llamadas vs WhatsApp" (con el aprendizaje de Aguascalientes, si el cliente lo permite).
8. "PageSpeed y Core Web Vitals en México".

Reglas por artículo: 1,200+ palabras, enlace al servicio con ancla descriptiva, enlace a un caso, FAQ con schema, autor visible, sin cifras sin fuente. Una vez publicado: `php artisan blog:exportar` y commit del `.md`.

La categoría `/blog/categoria/seo-organico` ya tiene 133 impresiones y 0 clics porque está vacía: los artículos 1 a 5 deben ir ahí primero.

### Autoridad (1 acción por semana)
- [ ] Perfiles con enlace: GBP, LinkedIn, Facebook, Instagram, YouTube (`sameAs` ya existe).
- [ ] Directorios mexicanos de agencias y Clutch.
- [ ] Pedir a los clientes con caso publicado un enlace "sitio desarrollado/posicionado por RankPro" (con su permiso).
- [ ] Una colaboración o artículo invitado en un sitio del sector (hoteles, industria).
- [ ] 3 reseñas reales en Google de clientes.

### Revisión
Cada lunes, 20 minutos: Search Console (consultas nuevas, páginas con impresiones y 0 clics, indexación).

### Metas realistas (hipótesis)
| Plazo | Meta |
|---|---|
| 30 días | 19+ URLs indexadas, 4 artículos, GBP activo, 400+ impresiones al mes |
| 60 días | 8 artículos, 3 a 5 backlinks, primeras consultas en posición 11 a 20 |
| 90 días | 10+ keywords en top 20, 1 en top 10, 30 a 80 clics al mes |

SEO en un dominio sin historial rara vez da leads antes de 3 a 4 meses; por eso el pago cubre ese hueco.

---

## Parte 2. Pago ($10,000 MXN)

### Reparto
| Canal | Monto | % | Para qué |
|---|---|---|---|
| Google Ads (búsqueda) | $6,500 | 65 % | Captar la demanda que ya existe |
| Meta (remarketing + 1 prueba fría) | $2,000 | 20 % | Recuperar visitas, probar un anuncio de video |
| Reserva | $1,000 | 10 % | Ir al canal que funcione |
| TikTok | $500 | 5 % | Solo si sobra tiempo; si no, pasa a la reserva |

Meta empieza en la **semana 3**: sin tráfico propio no hay audiencia de remarketing todavía.

### Google Ads — estructura
- **Campaña única de búsqueda**, México, español, solo búsqueda (sin Display ni socios de búsqueda).
- **Grupo 1 — SEO:** "agencia seo", "posicionamiento orgánico", "servicios de posicionamiento seo", "seo orgánico precio", "costo seo". Destino: `/servicios/seo-organico`.
- **Grupo 2 — Google Ads:** "agencia google ads", "gestión de google ads", "campañas google ads". Destino: `/servicios/sem-google-ads`.
- **Grupo 3 — Hoteles** (tu nicho con caso): "marketing digital para hoteles", "seo para hoteles", "google ads hoteles". Destino: página de servicio o caso Fratelli hasta tener landing propia.
- Concordancia de frase y exacta; sin amplia al principio.
- **Negativas desde el día 1:** gratis, curso, cursos, qué es, tutorial, pdf, empleo, vacante, herramienta, software, plantilla, universidad, ejemplos.
- Presupuesto: ~$115 al día. Puja: maximizar clics con tope de CPC las primeras 2 semanas; pasar a maximizar conversiones cuando haya 15 a 30 conversiones.
- Anuncios: 2 por grupo (15 títulos, 4 descripciones), extensiones de llamada, ubicación y fragmentos. Un número de llamada con seguimiento (las llamadas rindieron más que WhatsApp en Aguascalientes).
- Conversión principal: `/agendar` completado y llamada. WhatsApp como secundaria.

### Meta (desde semana 3)
- Remarketing de visitantes 30 días: $1,200.
- Una prueba fría: video corto (~30 s) de "qué revisamos en una auditoría", objetivo clientes potenciales o visita a `/agendar`: $800.

### Expectativas honestas (no promesas)
Con CPC típico de 15 a 40 MXN en estos términos, $6,500 dan unos 160 a 430 clics. Con 3 a 5 % de conversión son 5 a 20 contactos y cerrar 1 o 2 ya amortiza la inversión. Son estimaciones; la primera semana de datos reales las reemplaza.

### Reglas de decisión
- Semana 2: pausar términos con 20+ clics y 0 conversiones; añadir negativas desde el informe de términos de búsqueda.
- Semana 4: mover dinero al grupo con menor costo por conversión.
- Semana 8: decidir si Meta y TikTok siguen, con datos.

---

## Calendario
| Semana | Orgánico | Pago |
|---|---|---|
| 1 | Arreglos técnicos, GBP, GA4/GTM, quitar cifras | Preparar cuenta, conversiones, negativas |
| 2 | Artículo 1, perfiles externos | Lanzar Google Ads |
| 3 | Artículo 2, directorios | Primera limpieza de términos; Meta remarketing |
| 4 | Artículo 3, reseñas | Revisión: costo por conversión |
| 5 a 8 | Artículos 4 a 8, 1 backlink por semana | Optimizar; prueba fría de Meta; decidir reserva |

## Qué mido
Search Console (impresiones, clics, posición, indexación), Google Ads (CPC, conversiones, costo por conversión), GA4 (agendas y llamadas). Este documento y la auditoría se actualizan al cierre de cada mes.
