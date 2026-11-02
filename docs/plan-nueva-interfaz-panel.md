# Plan: nueva interfaz del panel /admin

Fecha: 2026-11-01. Origen: prototipo «AgencyOS» de Claude Design (React/Vite/TS), exportado en `docs/diseno-panel/` (referencia; no se compila ni se despliega).

## Qué es el prototipo (leído del código)

- `app/AgencyDashboard.tsx` (8,779 líneas) contiene los 23 módulos del panel; el resto de `app/pages/` es el sitio público, fuera de alcance.
- Datos de demostración fijos (clientes ficticios, fechas de 2024); no hay backend. Sirve como diseño, no como lógica.
- **Tokens** (`LIGHT_THEME` y `DARK_THEME`, líneas 8629 a 8684): verde de marca `#0F9D6E`; claro con fondo `#F6F8FA`, tarjetas `#FFFFFF`, borde `rgba(15,23,42,.10)`; oscuro con fondo `#0D1117`, tarjetas `#161C28`, menú `#090D16`. Tipografía DM Sans y JetBrains Mono. Menú lateral de 208 px, contenido con relleno de 20 a 24 px.
- Insignias de estado (`BADGE_MAP`): Activo, Pausado, Cancelado, En progreso, Completado, Pendiente, Pagado, Vencido, Abierto, Resuelto, En uso, Tracking, Descartado, Enviado, Programado, Borrador, Fallido, y Alta/Media/Baja.
- Componentes propios reutilizables: `Kpi`, `Card`, `SubTabs`, tabla con paginación y encabezado fijo (`TWrap`), `Modal` (con título, subtítulo, insignia y pie), `Field`, botones primario y fantasma.
- Menú (`NAV`): Inicio · **Agencia** (Dashboard, Chats «próximamente», Mi espacio, Calendario, Clientes, Servicios) · **Marca** con selector (Identidad de marca, Audiencia, SEO Plan, Content Manager, Proyectos, Métricas, Ads «próximamente») · y, ocultos en el prototipo, SEO, Keywords, Ads, Desarrollo, Finanzas, Archivos, Plantillas de correo, Enviar correo, Usuarios, Roles, Integraciones.

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Alcance | Solo el panel `/admin`. El sitio público no se toca. |
| Tecnología | Blade + JS vanilla + CSS por módulo. El prototipo es referencia visual; se traducen sus clases Tailwind a CSS propio. El hosting no tiene Node. |
| Lo existente | «Reemplazar todo», por etapas: base primero y cada módulo cuando llegue su diseño. |
| Rama | Directo en `main`, commits pequeños que dejen la app funcionando. |
| Menú | Estilo del prototipo con **todos** los módulos de RankPro visibles, agrupados por sección. |
| «Marca» | Es el **cliente activo** del CRM: un selector en el menú elige el cliente y los módulos de la sección Marca trabajan con él. |
| Módulos sin diseño en el prototipo (Propuestas, Reportes, Automatizaciones, Conversiones, Blog admin, Search Console, Pendientes de captura, Agenda, Configuración de correo) | Se ubican en la sección **Agencia** del menú con el estilo base; se rediseñan cuando haya su pantalla. *Interpretación de «ponlos en mi agencia»; confirmar.* |
| Módulos nuevos del prototipo | **Se construyen todos, por fases**: Inicio, Mi espacio, Calendario, Identidad de marca, Audiencia, SEO Plan, Content Manager y Proyectos. |
| Extras del prototipo | PMS Hotelero, DPMS Odontológico, Productos, Promociones, AgencyOS, IT Automation: solo backlog. |
| Cobertura | Responsive móvil, modo oscuro y permisos por módulo. |
| Orden de lo existente | 1 Dashboard, 2 Clientes, 3 SEO + Keywords + Search Console, 4 Ads + Reportes + Correo. |

## Qué hay hoy en el panel (verificado en el código)

- Layout único `resources/views/layouts/admin.blade.php` (53 líneas) y 28 hojas en `resources/css/admin/` (~11,000 líneas; `global.css` 1,791 y `sidebar.css` 445 son la base).
- **El modo claro/oscuro ya existe**: tokens en `global.css`, selector en `sidebar.css` y `resources/js/theme.js`. Se rehacen los tokens, no se construye desde cero.
- Rediseñados el 2026-10-26: Clientes (formulario), Servicios, SEO, Keywords y Correo (plantillas y envíos).

## Correspondencia prototipo → panel actual

| Prototipo | Panel actual |
|---|---|
| Dashboard, Clientes, Servicios, SEO, Keywords, Ads, Desarrollo, Finanzas, Archivos, Usuarios, Roles, Integraciones | Existen: se restilizan |
| Plantillas de correo, Enviar correo | Módulo Correo (existe) |
| Métricas | [[Métricas]] (existe) |
| Inicio, Mi espacio, Calendario, Identidad de marca, Audiencia, SEO Plan, Content Manager, Proyectos | **No existen**: se construyen |
| Chats, Ads «próximamente» | Marcador sin función |
| (sin pantalla) | Propuestas, Reportes, Automatizaciones, Conversiones, Blog admin, Search Console, Captura, Agenda, SMTP |

## Fases

**Fase 0 — Inventario.** Mapa pantalla del diseño → vista Blade → módulo; lista de componentes que ya existen en `global.css` y los que faltan. Sin tocar código.

**Fase 1 — Base visual.**
- Tokens claro/oscuro nuevos en `global.css`, conservando los nombres de variable actuales para no mover módulos aún sin rediseñar.
- DM Sans y JetBrains Mono locales.
- Layout, menú lateral (secciones Agencia, Marca, Operación, Administración), selector de cliente activo y barra superior; búsqueda Ctrl+K.
- Componentes base: KPI, tarjeta, subpestañas, tabla paginada, modal, campo, insignias de estado, botones.
- Responsive: menú colapsable en móvil.
- Salida: todos los módulos funcionan y se ven coherentes.

**Fase 2 — Dashboard.** **Fase 3 — Clientes.** **Fase 4 — SEO, Keywords y Search Console** (no tocar la lógica pura de `keywords.js`). **Fase 5 — Ads, Reportes y Correo** (el HTML del correo y `RenderizadorCorreo` no cambian).

**Fase 6 — Resto de módulos existentes** (Finanzas, Archivos, Usuarios, Roles, Desarrollo, Integraciones, Servicios, y los sin pantalla en cuanto lleguen).

**Fase 7 — Módulos nuevos** (cada uno: migración, modelo, controlador, vistas, JS, CSS, test y nota en la bóveda). Orden propuesto, de menos a más complejo:
1. **Selector de cliente activo** (sesión): pieza común de todos los módulos Marca.
2. Mi espacio (tareas con vistas tarjetas, kanban, tabla, calendario, línea de tiempo) y Calendario.
3. Inicio (resumen del día: tareas, calendario y alertas).
4. Identidad de marca y Audiencia (personas).
5. SEO Plan y Proyectos.
6. **Content Manager**: el más grande (~2,000 líneas del prototipo: tablas editables tipo hoja, pizarra, editor de documentos, calendario de contenido). Valorar dividirlo en entregas.

**Fase P — Permisos por módulo** (backend, independiente de lo visual): hoy solo existe `auth` y ningún middleware consulta `role_permissions`. Middleware `permiso:{modulo}` leyendo `Navigation::groups()`; el menú oculta lo no permitido. Antes de aplicarlo hay que definir qué ve cada rol, para no bloquear a usuarios actuales. Va aparte y con tests. Conviene hacerla **antes** de la Fase 7, para que los módulos nuevos nazcan con su permiso.

## Resultado del inventario (Fase 0, 2026-11-01)

Siete revisiones en paralelo comparando cada módulo del prototipo con el código actual. **Conclusión general: los módulos existentes ya son casi idénticos al prototipo** (la base visual, el menú de 208 px y la cabecera de 44 px coinciden; el modo oscuro es el mismo). El trabajo grande no es restilizar: son los módulos nuevos y los permisos.

### Módulos existentes: qué cambia de verdad

| Módulo | Diferencia real | Esfuerzo |
|---|---|---|
| Dashboard | Flechas de tendencia en los 4 KPI (hoy `trend => null`); si el mes anterior es 0, la tendencia es `null`, no un porcentaje inventado. | S |
| Clientes | Pie del detalle con Eliminar y Editar; ajustes de CSS. No tocar los chips de servicios en el alta (rompe «MRR = suma de servicios activos»). | S |
| Servicios | Columna Responsable (ya viene en `toRow`); decidir si se funde o se quita «Tipo». | S |
| SEO | Core Web Vitals evaluados contra umbrales. Lista de errores prioritarios y campos nuevos (INP, TTFB, anchor, autor) requieren migración: opcional. | S |
| Keywords | Vista de cliente y exportar CSV (fuera del bloque `// <<pura>>`). | M |
| Search Console | Sin cambios. | — |
| Ads | Tarjeta de cuenta seleccionada (MRR, contrato), reparto con 4 filas resumen, columnas Presup./CTR/CPC/CPA. Conviene no duplicar `AdsMetricaController`. | S-M |
| Desarrollo | Mostrar stack, repositorio, días a la entrega y asignado. **No adoptar las 7 fases del prototipo** (chocan con las 4 etapas Munch Galindo). | M |
| Finanzas | Resumen de 6 meses y fila de totales. **No adoptar IVA fijo al 16 %** (cifra sin fuente). | S |
| Archivos | Casi nada; conservar Generar contrato/propuesta. | S |
| Usuarios | KPI y panel de permisos heredados. **No adoptar «eliminar»** (la regla es desactivar). | S |
| Roles | Rediseño completo: 7 acciones globales + módulos visibles (ver Fase P). | L |
| Integraciones | Sin cambios; el código es más completo que el prototipo. | — |
| Correo (plantillas y envío) | KPI «En pausa», ficha con timeline. **No tocar** `RenderizadorCorreo`: el prototipo tiene una réplica distinta del HTML (sin `bgcolor`/`color-scheme`). | S |

Cuidado: las cifras, nombres y fechas del prototipo son de demostración; no se copian.

### Módulos nuevos: cómo se construyen (menos de lo que parecía)

- **Proyectos → se funde con Desarrollo.** Ya tiene proyectos, tareas y kanban. Solo se suman las vistas filas, tabla y línea de tiempo. Se puede renombrar la entrada del menú a «Proyectos».
- **Mi espacio → reutiliza `tareas`.** `proyecto_id` pasa a nullable; se agregan `user_id`, `cliente_id`, `fecha_inicio` y `orden`; se amplía `EstadoTarea` con «en revisión». Una sola tabla, dos pantallas. *(Columnas reales sin verificar: confirmar en las migraciones.)*
- **Calendario → convive con Agenda.** Agenda sigue como motor de reservas; el Calendario agrega reuniones, entregas, pagos y campañas, más eventos manuales (`eventos_calendario`).
- **Inicio → pantalla de resumen** con datos existentes (KPI, alertas, reuniones de hoy); lo nuevo son «Mis tareas» y «Actividad reciente». Las 6 pestañas «próximamente» del prototipo no se construyen.
- **Identidad de marca** (S): una fila por cliente (logo, slogan, paleta, fuentes, voz, misión, visión, valores); encaja como pestaña de la ficha del cliente.
- **Audiencia** (S): `buyer_personas` por cliente, captura manual. El «generador» del prototipo es una plantilla de texto, no IA.
- **SEO Plan** (L en pantalla, M en datos): fusión parcial, sin tablas paralelas. Solo nuevos: `keyword_clusters` y columnas en `SeoOnPageAccion` (tipo, cluster, fecha límite, antes/después) más notas. Las metas usan `MetricaCliente`. Versión 1 con kanban y lista; sin línea de tiempo ni calendario.
- **Métricas del prototipo** (M): no son las métricas personalizadas; es un tablero de lectura por cliente. Va como sección de Reportes, sin tabla nueva.
- **Content Manager** (L): `cm_items` en árbol con contenido en JSON. Entregas: 1) árbol y archivos (reutiliza `Archivo`), 2) documentos (reutiliza el editor Markdown del Blog), 3) tabla editable (la pieza más difícil), 4) monitores con 3 o 4 fuentes reales. **Se recortan:** pizarra, formas, Canva/Drive, galería, hoja de cálculo y reportes de Ads/Social como tipos propios.
- Lo más difícil de portar a JS vanilla: arrastrar y soltar con orden persistente, línea de tiempo tipo Gantt (un solo JS compartido para Mi espacio y Proyectos) y la tabla editable del Content Manager.

### Fase P — Permisos (la más grande)

- Hoy ningún middleware consulta `role_permissions`; el seeder tiene 21 permisos. La nota «Usuarios y Roles» dice que faltan 4: **está desactualizada, hay que verificarla contra el código**.
- El prototipo define 7 acciones globales (crear, editar, borrar, consultar, exportar, ver KPIs, ver finanzas) y una lista de módulos visibles por rol. Sus 13 módulos no coinciden con los 21 del código.
- Plan: middleware `permiso:{modulo}` por grupo de rutas, `User::tienePermiso()`, filtrar `Navigation::groups()` y la paleta Ctrl+K, y derivar la acción del método HTTP.
- Riesgos: tests con usuarios sin rol darán 403; proteger al admin para que no se quede sin acceso; ocultar KPIs o finanzas toca vistas de muchos módulos; el rol «Cliente» exige acotar datos por cliente, que hoy no existe.
- Esfuerzo L. Va antes de los módulos nuevos.

### Orden recomendado (actualizado)

1. Fase 1 (tokens del modo claro, pequeña) y ajustes S de Dashboard, Clientes, Servicios, Finanzas, Archivos, Usuarios y Correo.
2. Fase P (permisos).
3. Ads, Keywords, Desarrollo y SEO (S-M).
4. Tareas unificadas, Mi espacio, vistas de Proyectos y Calendario.
5. Inicio, Identidad y Audiencia.
6. SEO Plan y Métricas en Reportes.
7. Content Manager por entregas.

## Reglas para todas las fases

- Un módulo existente se reemplaza sin cambiar rutas, controladores ni nombres de campo: solo vista, CSS y JS de presentación.
- Tras cada módulo: `php artisan test --filter=<Modulo>`, `npm run build` (se versiona `public/build`) y revisión en navegador en escritorio, móvil, claro y oscuro.
- La suite completa (~80 min) se corre al cerrar cada fase grande, no en cada commit.
- Al terminar cada módulo: nota del módulo, `01 Estado del proyecto.md` y `03 Bitácora.md` en la bóveda.
- Commits en español con el módulo de prefijo; solo cuando el usuario lo pida.

## Riesgos

- Directo en `main`: un commit a medias afecta el panel real. Mitigación: fases pequeñas y probadas.
- El prototipo usa Tailwind y React; se traduce a CSS propio y JS vanilla, no se copia. Las interacciones ricas (kanban, pizarra, tablas editables) son las más caras de portar.
- Construir 8 módulos nuevos es construir producto, no solo interfaz: es la parte más grande del plan y puede superar al resto junta.
- Las 11,000 líneas de CSS actuales están acopladas; cambiar tokens puede mover módulos no rediseñados.
- Durante un tiempo el panel mezclará dos lenguajes visuales.
- Los permisos pueden dejar sin acceso a usuarios que hoy ven todo.

## Pendiente de preguntar

- Confirmar dónde van los módulos sin diseño («Agencia»).
- Qué permisos tiene cada rol (Admin, Operador, etc.) por módulo.
- Quién usa los módulos nuevos: ¿solo tú, el equipo, o también los clientes?
- Qué guarda «Identidad de marca» y «Audiencia» hoy en el prototipo (logos, colores, tono; personas) y si debe alimentar al módulo de Correo.
- Fecha objetivo y si el logo actual se conserva.

## Estado de la ejecución (2026-11-01)

Hecho: base (tokens del modo claro, menú por secciones con cliente activo), permisos por módulo aplicados con protección del último admin, ajustes de Dashboard, Clientes, Servicios, Finanzas, Ads, Keywords, SEO, Desarrollo, Usuarios, Roles y Correo, y los módulos nuevos Inicio, Mi espacio, Calendario, Identidad, Audiencia, SEO Plan, Contenido y Tablero por cliente. Suite: 1534 pruebas pasan; fallan las 3 de `CasosExitoTest` que ya fallaban.

Pendiente o recortado a propósito: modelo de 7 acciones por rol y rol «Cliente»; columna Asignado en bugs; vista por cliente de Keywords; línea de tiempo y calendario en SEO Plan; pizarra, hoja de cálculo, galería, Canva/Drive y monitores en Contenido; INP/TTFB; generador de personas. Faltan por probar en navegador: kanban y arrastrar y soltar, línea de tiempo, importar CSV y la vista móvil de los módulos nuevos. Sin commit ni despliegue.
