{{--
    Error 500 · Error interno del servidor
    ------------------------------------------------------------------
    Deliberadamente autónoma: NO extiende layouts.app, no incluye parciales,
    no usa @vite ni App\Support\Servicios. Si el error 500 viene justamente
    del layout, de la compilación de assets o del catálogo de servicios,
    esta vista tiene que poder renderizarse igual. Solo estilos en línea y
    enlaces estáticos a / y /contacto.

    Los colores replican --brand (#0F9D6E) de resources/css/web/app.css.
--}}
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Error del servidor · RankPro</title>
    <meta name="description" content="Ocurrió un error inesperado en el servidor. Intenta de nuevo en unos minutos o escríbenos.">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <meta name="theme-color" content="#0F9D6E">
    <style>
        :root { --brand: #0F9D6E; --brand-dark: #059669; --muted: #6B7280; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            background: #F9FAFB;
            color: #111827;
            font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
        }
        .box { max-width: 560px; width: 100%; text-align: center; }
        .mark {
            width: 56px; height: 56px; margin: 0 auto 24px;
            border-radius: 14px; background: var(--brand);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 22px; letter-spacing: -0.02em;
        }
        h1 { font-size: 28px; line-height: 1.25; margin: 0 0 16px; letter-spacing: -0.02em; }
        p { margin: 0 0 16px; color: var(--muted); font-size: 16px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 28px; }
        .btn {
            display: inline-block; padding: 12px 24px; border-radius: 10px;
            text-decoration: none; font-weight: 600; font-size: 15px;
            border: 2px solid var(--brand);
        }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
        .btn-outline { color: var(--brand); background: transparent; }
        .btn-outline:hover { background: var(--brand); color: #fff; }
    </style>
</head>
<body>
    <main class="box">
        <div class="mark" aria-hidden="true">RP</div>
        <h1>Error 500: algo falló de nuestro lado</h1>
        <p>
            Ocurrió un error inesperado en el servidor al procesar tu solicitud. No es un problema
            de tu conexión ni de la dirección que abriste.
        </p>
        <p>
            Ya quedó registrado y lo estamos revisando. Vuelve a intentarlo en unos minutos; si el
            error continúa, escríbenos y lo resolvemos.
        </p>
        <div class="actions">
            <a class="btn btn-primary" href="/">Volver al inicio</a>
            <a class="btn btn-outline" href="/contacto">Contactar a RankPro</a>
        </div>
    </main>
</body>
</html>
