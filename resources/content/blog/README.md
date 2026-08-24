# Respaldo del contenido del blog

Los articulos viven en la tabla `articulos` de la base de datos, no aqui. Este
directorio es el respaldo versionado en git, porque el contenido en base de datos
no tiene diff, ni revision en PR, ni rollback, ni paridad entre entornos.

## Flujo

```bash
php artisan blog:exportar    # BD  -> resources/content/blog/{slug}.md
php artisan blog:importar    # .md -> BD  (updateOrCreate por slug)
php artisan blog:validar     # metadatos, enlaces internos y estandar editorial
```

Exportar despues de publicar o actualizar un articulo, y commitear el `.md`
resultante junto al resto del cambio.

## Que se exporta

El front-matter lleva los metadatos editables y el cuerpo lleva el Markdown
original. **No** se exportan `contenido_html`, `toc` ni `palabras`: son derivados
y se recalculan al importar. Incluirlos generaria diffs de ruido en cada guardado.

El autor se guarda por email y no por id, porque los ids no son estables entre
entornos.

## Nota

Este directorio esta vacio a proposito: los articulos de demo que genera
`ArticuloSeeder` son texto de relleno y no se versionan. Los `.md` reales
apareceran aqui conforme se publiquen los articulos del plan editorial.
