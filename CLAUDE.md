# RankPro — instrucciones para Claude Code

Laravel 10 / PHP 8.1 / MySQL / Blade + JS vanilla. Sitio público de la agencia + panel `/admin`. Todo el código, los nombres y los commits van **en español**.

## Documentación en Obsidian (leer siempre)

La documentación viva del proyecto está en una bóveda de Obsidian, fuera del repo:

```
G:\My Drive\Emprendimiento\RankPro\Documentos\Documentacion - Obsidian\RankPro Solutions\
```

Un hook `SessionStart` (`scripts/claude-contexto-obsidian.php`) inyecta `00 Inicio.md` y `01 Estado del proyecto.md` al arrancar. Si el hook no pudo cargar la bóveda, avisa al usuario antes de seguir.

Reglas de trabajo con la bóveda:

1. **Antes de tocar un módulo**, lee su nota (`Módulos admin/<Módulo>.md` o `Sitio público/<Pieza>.md`) y `02 Convenciones.md`. Ahí están las reglas de negocio, los archivos implicados y los pendientes conocidos.
2. **Al terminar una tarea** que cambie el sistema:
   - Actualiza la nota del módulo (funciones, reglas, archivos, pendientes) y su campo `actualizado:`.
   - Si cambió el estado de algo (✅/🟢/🟡/❌), actualiza la fila en `01 Estado del proyecto.md`.
   - Añade una línea arriba del todo en `03 Bitácora.md`: `- **YYYY-MM-DD** — [[Nota]]: qué se hizo.`
   - Si descubriste una convención o decisión de diseño nueva, anótala en `02 Convenciones.md` o en la nota del módulo, con el **por qué**.
3. Módulo nuevo → nueva nota copiando `Plantillas/Plantilla de módulo.md`, enlace en `00 Inicio.md` y fila en `01 Estado del proyecto.md`.
4. Las notas usan enlaces `[[Nombre de nota]]` (Obsidian) y frontmatter `tags`, `actualizado`, `estado`. No cambies los nombres de archivo: romperías los enlaces.
5. La bóveda documenta **lo que existe en el código**, no deseos. Si la nota y el código no coinciden, manda el código: corrige la nota y dilo.

## Convenciones de código (resumen; detalle en `02 Convenciones.md`)

- Módulo admin = rutas en `routes/web.php` (grupo `admin`, `show` al final del grupo), controlador en `app/Http/Controllers/Admin/`, modelo con `$fillable` y casts a `app/Enums/`, vistas en `resources/views/admin/{modulo}/` con parciales `_x`, JS en `resources/js/{modulo}.js`, CSS en `resources/css/admin/{modulo}.css`, ambos registrados en `vite.config.js`, test en `tests/Feature/Admin/`.
- Servicios por fases (SEO, Ads, Automatizaciones, Desarrollo): enum `Fase*` + `*FaseController` con `guardar/aprobar/retroceder(/nuevoCiclo/cerrar/pausar)`.
- Estructura del sitio público sin BD: `App\Support\{Servicios,Clusters,CasosExito,Navigation}`.
- Los comentarios explican el **por qué**; mantener el estilo de docblocks existentes.
- Sitio público: nada de cifras/testimonios sin fuente verificable; enlaces de WhatsApp con `nofollow`; `SEO_INDEXABLE` explícito en producción.

## Comandos

```bash
php artisan test                  # suite completa (varios minutos); usar --filter en el día a día
npm run build                     # public/build se versiona (el hosting no tiene Node)
php artisan blog:exportar         # tras publicar/editar artículos; commitear el .md
```

## Git

Commits en español con el módulo como prefijo (`Reportes: ...`, `Casos de exito: ...`). Rama única `main`. No commitear ni pushear sin que el usuario lo pida.
