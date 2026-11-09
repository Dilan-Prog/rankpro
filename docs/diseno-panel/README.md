# RankPro — Proyecto Vite

Sitio y plataforma RankPro en React + TypeScript + Tailwind v4.

## Uso

```bash
npm install
npm run dev      # http://localhost:5173
npm run build    # genera dist/
npm run preview
```

## Estructura

- `index.html` / `src/main.tsx` — entrada de la app
- `app/App.tsx` — router por estado (`Page` en `app/types.ts`)
- `app/pages/` — 30 paginas del sitio (home, servicios, productos, blog, legales, PMS/DPMS, AgencyOS)
- `app/AgencyDashboard.tsx` — plataforma interna (modo `platform`)
- `app/components/ui/` — kit de UI (shadcn/Radix)
- `app/components/shared/` — Shell (header, nav, footer)
- `app/data.ts` — contenido y datos de demo
- `styles/` — fuentes, Tailwind y tokens del tema
- `export/` — versiones HTML estaticas de cada pagina (referencia)

## Notas

- Alias `@/` apunta a `app/`.
- Tailwind v4 se carga con `@tailwindcss/vite`; los tokens viven en `styles/theme.css`.
- La navegacion es por estado, sin react-router: `setPage("servicios")`.
