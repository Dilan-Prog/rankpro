# Auditoría técnica SEO: 12 páginas nuevas (2026-11-23)

Stack: Laravel 10 / PHP 8.1 / Blade + Vite, Hostinger (hcdn). Revisado sobre el commit `5981ee1` y sobre lo que sirve producción. **Aplicados el 2026-11-23:** A, B (solo la validación), C, D, G, I, J, K y L, con tests. **Pendientes:** B (cambiar el campo en el panel), E, F, H y lo que no lleva parche. Las líneas citadas son las del commit `5981ee1`. El parche I exige volver a guardar las guías.

## 1. Resumen ejecutivo

1. **FAQPage ausente en las 6 guías** (alta): la plantilla lo excluye a propósito; las 40 preguntas visibles permiten generarlo del mismo HTML.
2. **Contenido de las guías solo en producción** (alta): `resources/content/blog` solo tiene el README. Sin `blog:exportar` no hay copia versionada.
3. **Contraste insuficiente** (alta): blanco sobre el verde claro 1.92:1, verde de marca sobre blanco 3.46:1 (mínimo AA 4.5:1 en texto normal).
4. **Autor sin respaldo** (media): el Person está en la guía, pero `/nosotros`, a donde apunta, no lo nombra.
5. **"[2026]" en el title** (media): viene del campo `meta_title` en la base; la plantilla está bien.

## 2. Hallazgos

| # | Sev. | Área | Archivo / URL | Problema | Corrección |
|---|---|---|---|---|---|
| 1 | Alta | Schema | `pages/blog/_schema.blade.php:6-7` | Sin FAQPage. El motivo anotado («un FAQPage duplicado entre URLs penaliza») no aplica si las preguntas son propias de la página | Parche A |
| 2 | Alta | Mantenibilidad | `resources/content/blog/` | Las 6 guías no están versionadas | `php artisan blog:exportar` en el servidor y commitear los .md |
| 3 | Alta | Accesibilidad | `app.css:12-14,123-125`, `topbar.css:1-3` | Botón primario: 1.92:1 en el extremo claro. Barra superior y textos verdes: 3.46:1 | Parche F (decisión de marca) |
| 4 | Media | E-E-A-T | `_schema.blade.php:20,62-66`, `show.blade.php:82`, `/nosotros` | El @id resuelve dentro de la guía y lleva name y url, pero el destino no menciona al autor | Parche E |
| 5 | Media | Title | BD `articulos.meta_title` de `que-es-seo`; se pinta en `show.blade.php:9` | Corchetes y año que caduca | Parche B |
| 6 | Media | Enlazado | `BlogController.php:79-84` | «Sigue leyendo» son siempre los 3 más nuevos: las guías viejas pierden enlaces. `herramientas-seo` no recibe enlaces de consultor, seo-local ni precios | Parche C |
| 7 | Media | Enlazado | `/blog/seo-local` y demás guías | Enlazan a las ciudades solo desde el pie | Añadir en el contenido de `seo-local` un párrafo con las 5 ciudades |
| 8 | Media | Validador | `ValidarBlog.php:376-381` | Rutas `servicios.ciudad` y `casos.show` caen en `default => true`: un enlace a una ciudad inexistente pasa | Parche D |
| 9 | Media | Schema/OG | `meta.blade.php:33,86`, `_schema.blade.php:25` | Las 12 comparten `og-rankpro.jpg`, con alt igual al title; las guías no emiten `image` (recomendado en BlogPosting) | Subir imagen por guía (1200×630) en `imagen_destacada` y Parche G para el alt |
| 10 | Media | Seguridad | `public/.htaccess:53-60` | Sin HSTS. El motivo anotado (www no resuelve) ya no se cumple: www responde 301 por HTTPS | Parche H |
| 11 | Media | Rendimiento | `Kernel.php:35-42`, `layouts/app.blade.php:6` | Toda página pública crea sesión y 2 cookies; respuesta `no-cache, private` y CDN `DYNAMIC` | Ver nota de sesión |
| 12 | Media | Accesibilidad | `RenderizadorMarkdown.php:50-60` | El «#» de cada encabezado es un enlace enfocable con `aria-hidden="true"` | Parche I |
| 13 | Baja | Fechas | `BlogController (admin):132`, `BlogApiController` | `dateModified` es manual: coincide con lo visible y con la BD y no cambia solo, pero puede olvidarse. Las 12 fechas actuales son coherentes | Mantener la decisión; avisar al guardar si cambió el contenido y no la fecha |
| 14 | Baja | Schema | `_schema.blade.php:45,48` | `isPartOf` y `about` apuntan a @id definidos en otras páginas. Es válido, pero no se resuelven en la propia | Opcional: añadir `@type`, `name` y `url` |
| 15 | Baja | Migas | `show.blade.php:57,60` / `_schema.blade.php:73-74` | Visible «SEO» y título cortado con «…»; schema «SEO orgánico» y título completo | Parche J |
| 16 | Baja | Migas | 27 líneas con `breadcrumb__sep` en 15 vistas | El separador es un `<li>` (con aria-hidden, en el blog) | Moverlo a CSS `::before` |
| 17 | Baja | Encabezados | `footer.blade.php:68`, `toc.blade.php:5`, `show.blade.php:161` | 4 H2 del pie + «En este artículo» + CTA compiten con el contenido. No hay saltos de nivel | Parche K |
| 18 | Baja | Gráfica | `seo-organico.blade.php:221,230` | El eje Y no dice la unidad, y el aria-label dice «proyectadas», contra la nota «ni una proyección». El eje X **no** está duplicado (Mes 0/3/6/9/12 una vez) | Parche L |
| 19 | Baja | SERP ficticia | `seo-organico.blade.php:271-273` | Promete «Reseñas ★» y «FAQ enriquecidas», que Google ya no muestra en la mayoría de los sitios | Cambiar los chips |
| 20 | Baja | Contraste | `seo-ciudad.blade.php:112`, `seo-organico.blade.php:138` | Notas del hero en blanco al 45 % y 13 px | Subir a 0.7 |
| 21 | Baja | Redirección | `.htaccess:26-37` | `http://www` hace 2 saltos | Parche H |
| 22 | Baja | Fuentes | `layouts/app.blade.php:17-20` | CSS de Google Fonts bloqueante (con `display=swap`) y petición a un tercero | Alojar Manrope localmente |
| 23 | Baja | Sitemap | `SitemapController.php` `FECHAS_VISTA['pages.casos.show']` | Los 3 casos comparten lastmod, aunque el 23-nov solo cambió uno | Fecha por caso en `CasosExito` |
| 24 | Baja | Captación | `administracion@` en topbar, pie, schema, landings | Correo genérico administrativo para leads | Crear `hola@` o `ventas@` antes de cambiarlo |

**Nota de sesión (#11).** El `csrf-token` público no es un riesgo. El único JS público que lo usa es `agendar.js:38`. Quitar la sesión exige un grupo de middleware sin `StartSession` y sacar `csrf_token()` del layout (lanza error sin sesión). Conviene hacerlo aparte y con pruebas; el TTFB medido (0.3-0.9 s) no lo vuelve urgente.

### Está bien
- Títulos y metas únicos en las 12; OG y Twitter con título, descripción y URL por página; `lang="es-MX"`. Con un solo idioma no hace falta hreflang.
- Un solo `Organization` y `WebSite` por página, con @id estables; `Service` con `City`, `provider` por @id y url; sin `LocalBusiness`.
- Las 5 ciudades enlazan al padre, a `seo-local` y a la guía de precios. Todas las guías enlazan al servicio.
- Byline con autor y `<time datetime>`. «Actualizado el» sale solo si es posterior a la publicación.
- Imágenes con alt y dimensiones; TOC y «#» aparte, ninguna ancla genérica.
- Sitemap con las 12 y solo publicadas; robots sin bloqueos accidentales; https, www, barra final y /public con 301; HTML y CSS con Brotli; CSS y JS con hash e `immutable`.
- 2 scripts propios (módulos, no bloqueantes) y 66-89 KB de HTML sin comprimir por página. CSP existente: solo `upgrade-insecure-requests`, del hosting. Consent Mode v2 en código.

## 3. Parches

### A. FAQPage desde el HTML visible
**Expectativa:** desde agosto de 2023 Google limita el resultado enriquecido de FAQ a sitios de salud y gobierno. Sirve para que las máquinas entiendan la página, no para ganar un desplegable en Google.

`app/Support/Contenido/FaqDelArticulo.php` (nuevo):

```php
<?php

namespace App\Support\Contenido;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Preguntas frecuentes leídas del HTML que se pinta.
 *
 * Se extraen de contenido_html, no de un campo aparte: así el FAQPage no puede
 * decir algo distinto de lo visible. Formato reconocido, bajo el primer <h2>
 * que empiece por "Preguntas frecuentes" y hasta el siguiente <h2>:
 *
 *   <p><strong>¿Pregunta?</strong> Respuesta</p>   (el que usan las guías)
 *   <h3>¿Pregunta?</h3><p>Respuesta</p>...
 */
class FaqDelArticulo
{
    /** @return list<array{p: string, r: string}> */
    public static function extraer(?string $html): array
    {
        if (trim((string) $html) === '') {
            return [];
        }

        $doc = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        // El permalink "#" del encabezado no forma parte del texto.
        $xpath = new DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('//a[contains(@class,"ancla-titulo")]')) as $ancla) {
            $ancla->parentNode->removeChild($ancla);
        }

        $titulo = null;
        foreach ($xpath->query('//h2') as $h2) {
            if (preg_match('/^preguntas frecuentes/iu', self::texto($h2->textContent))) {
                $titulo = $h2;
                break;
            }
        }

        $faqs = [];
        $abierta = false;

        for ($n = $titulo?->nextSibling; $n !== null; $n = $n->nextSibling) {
            if (! $n instanceof DOMElement) {
                continue;
            }
            if ($n->nodeName === 'h2') {
                break;
            }
            if ($n->nodeName === 'h3') {
                $faqs[] = ['p' => self::texto($n->textContent), 'r' => ''];
                $abierta = true;

                continue;
            }

            $primero = $n->firstChild;
            $pregunta = $n->nodeName === 'p' && $primero instanceof DOMElement && $primero->nodeName === 'strong'
                ? self::texto($primero->textContent)
                : '';

            if (str_ends_with($pregunta, '?')) {
                $n->removeChild($primero);
                $faqs[] = ['p' => $pregunta, 'r' => self::texto($n->textContent)];
                $abierta = true;
            } elseif ($abierta && in_array($n->nodeName, ['p', 'ul', 'ol'], true)) {
                $i = array_key_last($faqs);
                $faqs[$i]['r'] = trim($faqs[$i]['r'].' '.self::texto($n->textContent));
            } else {
                $abierta = false;
            }
        }

        return array_values(array_filter($faqs, static fn (array $f): bool => $f['r'] !== ''));
    }

    private static function texto(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }
}
```

`resources/views/pages/blog/_schema.blade.php`: borrar el punto «No hay FAQPage» del comentario y, antes de `@endphp`:

```php
$faqsArticulo = \App\Support\Contenido\FaqDelArticulo::extraer($articulo->contenido_html);
```

Sustituir el cierre del `@graph` (tras el BreadcrumbList) por:

```php
                ],
                ...($faqsArticulo === [] ? [] : [[
                    '@type' => 'FAQPage',
                    '@id' => $articulo->url().'#faq',
                    'mainEntity' => array_map(static fn (array $f): array => [
                        '@type' => 'Question',
                        'name' => $f['p'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['r']],
                    ], $faqsArticulo),
                ]]),
            ],
```

### B. Title sin corchetes
En el panel, Blog → «¿Qué es SEO?» → Meta title: `¿Qué es SEO? Guía completa para empresas | RankPro` (50 caracteres). Para que no se repita, en `Admin/BlogController.php:193` y `BlogApiController.php:148`:

```php
'meta_title' => ['required', 'string', 'max:60', 'not_regex:/[\[\]]/'],
```

### C. Relacionados que reparten enlaces
`app/Http/Controllers/BlogController.php:79-84`:

```php
if ($relacionados->isEmpty()) {
    // Los 3 que siguen por id, en círculo: cada guía recibe tantos enlaces como
    // da. Con "los más recientes" las viejas se quedaban sin ninguno.
    $delCluster = Articulo::publicados()->reorder('id')
        ->delCluster($articulo->cluster)
        ->whereKeyNot($articulo->getKey())
        ->get();

    [$siguientes, $anteriores] = $delCluster->partition(fn (Articulo $a) => $a->id > $articulo->id);
    $relacionados = $siguientes->concat($anteriores)->take(3)->values();
}
```

### D. Validador de enlaces
`app/Console/Commands/ValidarBlog.php:376`, dos casos más en el `match`:

```php
'servicios.ciudad' => \App\Support\CiudadesSeo::encontrar((string) ($parametros['slugCiudad'] ?? '')) !== null,
'casos.show' => \App\Support\CasosExito::porSlug((string) ($parametros['slug'] ?? '')) !== null,
```

### E. Autor en /nosotros
`resources/views/pages/nosotros.blade.php`, antes de la sección de la línea 195. Cargo y biografía los da el dueño; no se inventan:

```blade
<h2 id="person-dilan-yovani-garcia-gonzalez">Dilan Yovani Garcia Gonzalez</h2>
<p>[DATO REAL: cargo y experiencia comprobable]</p>
```

Y en `_schema.blade.php:65`: `'url' => $autorId,`.

### F. Contraste
Opción mínima, en `resources/css/web/app.css` (`#047857` sobre blanco: 5.48:1). Requiere `npm run build` y revisar que no se apliquen colores a mano:

```css
.btn-primary { background: #047857; }
.topbar { background: #047857; } /* en components/topbar.css */
```

`cv-btn--primary` y `cv-section--brand` de `servicios-conversion.css` llevan el mismo degradado y el mismo arreglo.

### G. Alt de la imagen social
`components/seo/meta.blade.php:86`:

```blade
<meta property="og:image:alt" content="{{ trim($__env->yieldContent('og_image_alt')) ?: 'RankPro, agencia de marketing digital en México' }}">
```

En `show.blade.php`, dentro del `@if ($ogImage)`: `@section('og_image_alt', $articulo->imagen_alt ?: $articulo->titulo)`.

### H. HSTS y un solo salto
`public/.htaccess:60` (empezar corto y sin subdominios; ampliar tras confirmar):

```apache
Header always set Strict-Transport-Security "max-age=86400" env=HTTPS
```

`.htaccess` raíz: poner la regla de www (líneas 35-37) **antes** de la de HTTPS. Como ya redirige a `https://%1`, `http://www` queda en un salto. Repetirlo en `public/.htaccess`.

### I. Permalink fuera del tabulador
`RenderizadorMarkdown::procesar()` y `soloHtml()`:

```php
$html = str_replace('class="ancla-titulo"', 'class="ancla-titulo" tabindex="-1"', (string) $this->converter->convert($markdown));
```

Solo afecta al guardar: hay que volver a guardar las 6 guías.

### J. Migas iguales
`show.blade.php:57`: `{{ $cluster['nombre'] }}`; línea 60: `{{ $articulo->titulo }}` (cortar con CSS `text-overflow` si hace falta).

### K. Encabezados que no son del contenido
`footer.blade.php:68`: `<p class="site-footer__col-title">`. `toc.blade.php:5`: `<p class="toc__titulo" id="toc-titulo">`. El CSS va por clase en ambos; comprobar márgenes.

### L. Gráfica
`seo-organico.blade.php:221`: `aria-label="Ejemplo ilustrativo de sesiones orgánicas al mes durante 12 meses: sin cambios frente a con trabajo de SEO"`. Tras `</defs>`:

```blade
<text x="40" y="22" font-size="12" fill="#6B7280">Sesiones orgánicas al mes (ejemplo)</text>
```

### Tests (`tests/Feature/BlogTest.php`)

```php
public function test_el_faqpage_sale_de_las_preguntas_visibles(): void
{
    $html = '<h2>Preguntas frecuentes</h2><p><strong>¿Qué es?</strong> Esto.</p><h2>Cierre</h2><p><strong>¿No?</strong> No cuenta.</p>';
    $this->assertSame([['p' => '¿Qué es?', 'r' => 'Esto.']], \App\Support\Contenido\FaqDelArticulo::extraer($html));
    $this->assertSame([], \App\Support\Contenido\FaqDelArticulo::extraer('<p>Sin preguntas</p>'));
}
```

Las páginas de servicio y ciudad ya tienen test de FAQ visible = schema (`LandingSeoTest`, `CiudadesSeoTest`); `SeoTest` cubre title, meta, H1, canonical y JSON-LD. Para el contenido real de producción: `php artisan blog:validar --publicados` en cada despliegue.

### Fuente de cada dato

| Página | Title y meta | FAQ visible | Schema |
|---|---|---|---|
| Servicio | `Servicios::todos()` | `faqs` del mismo arreglo | `_schema` lee `faqs` |
| Ciudad | `CiudadesSeo::todos()` | `faqs` del mismo arreglo | `seo-ciudad` lee `faqs` |
| Guía | BD `articulos` | `contenido_html` | Hoy no hay; con Parche A, el mismo `contenido_html` |

## 4. Plan de validación

| Prueba | Dónde | Qué mirar |
|---|---|---|
| Schema | https://validator.schema.org/ con las 12 URL | FAQPage sin errores (aquí sí aparece siempre) |
| Rich Results | https://search.google.com/test/rich-results?url=https%3A%2F%2Frankprosolutions.com.mx%2Fblog%2Fque-es-seo | Article y migas; que no salga FAQ es normal |
| Search Console | Inspección de URL de las 12 | Canónica elegida por Google = declarada; Mejoras: migas |
| PageSpeed | https://pagespeed.web.dev/analysis?url=https%3A%2F%2Frankprosolutions.com.mx%2Fservicios%2Fseo-organico y lo mismo con `/blog/que-es-seo` y `/servicios/seo-organico/cdmx` | LCP, CLS, accesibilidad (contraste) en móvil |
| Redirecciones | `curl -sIL http://www.rankprosolutions.com.mx/blog/seo-local` | Un solo 301 tras Parche H |
| Manual | https://rankprosolutions.com.mx/blog/seo-local | Tabulador: foco visible, «#» fuera del recorrido |

## 5. Lo que no pude revisar

- **Search Console, GA4 y datos de campo (CrUX):** necesito acceso o capturas. LCP, CLS e INP no se midieron.
- **Base de datos de producción:** `meta_title`, fechas y relacionados se infirieron del HTML servido.
- **hPanel, `.env` y caché de Hostinger.**
- **Consentimiento y etiquetas en vivo:** revisado el código, no la carga real en navegador.
- **Contraste y foco de todos los componentes:** solo se calcularon los colores principales.
- **Contenido de los backlinks y penalizaciones.**
