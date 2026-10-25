@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/integraciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Documentación de la API</h1>
            <p class="page-header__subtitle">Cómo conectar n8n (u otra herramienta) con RankPro.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.integraciones.api.index') }}" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> API y tokens</a>
            <a href="{{ url('/api/v1/openapi.json') }}" target="_blank" class="btn btn--primary"><i class="fa-solid fa-file-code"></i> Ver especificación OpenAPI</a>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="margin-top:0;">1. Autenticación</h2>
        <p style="color: var(--text-secondary);">
            Base URL: <code>{{ url('/api/v1') }}</code>. Cada petición lleva la cabecera:
        </p>
        <pre style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; overflow-x:auto;">Authorization: Bearer &lt;tu-token&gt;
Accept: application/json</pre>
        <p style="color: var(--text-secondary);">
            Crea tokens desde <a href="{{ route('admin.integraciones.api.index') }}">API y tokens</a>. Cada
            token tiene habilidades por módulo (<code>{modulo}:leer</code> / <code>{modulo}:escribir</code> / <code>*</code>).
            Un token sin la habilidad necesaria recibe <code>403</code> con <code>{"habilidad_requerida": "..."}</code>.
        </p>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="margin-top:0;">2. Formato de respuesta</h2>
        <p style="color: var(--text-secondary);">Listados paginados:</p>
        <pre style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; overflow-x:auto;">{
  "data": [ ... ],
  "meta": { "pagina": 1, "por_pagina": 50, "total": 120, "ultima_pagina": 3 },
  "links": { "siguiente": "...", "anterior": null }
}</pre>
        <p style="color: var(--text-secondary);">Un solo recurso: <code>{"data": {...}}</code>. Borrado: <code>{"data": {"deleted": true}}</code>.
        Filtros comunes en los listados: <code>search</code>, <code>sort</code> (o <code>-campo</code> para descendente),
        <code>per_page</code> (máx. 200), <code>updated_after</code>, <code>incluir</code> (relaciones anidadas).</p>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="margin-top:0;">3. Módulos y habilidades</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Módulo</th><th>Habilidad de solo lectura</th><th>Habilidad de escritura</th></tr></thead>
                <tbody>
                    @foreach ($modulos as $m)
                        <tr><td>{{ $etiquetas[$m] }}</td><td><code>{{ $m }}:leer</code></td><td><code>{{ $m }}:escribir</code></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">4. Verificar la firma de un webhook entrante (nodo n8n)</h2>
        <p style="color: var(--text-secondary);">
            Cada webhook lleva las cabeceras <code>X-RankPro-Event</code>, <code>X-RankPro-Delivery</code>,
            <code>X-RankPro-Timestamp</code> y <code>X-RankPro-Signature: sha256=&lt;firma&gt;</code>. La firma es:
        </p>
        <pre style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; overflow-x:auto;">firma = HMAC_SHA256(secreto, "{timestamp}.{cuerpo_json}")</pre>
        <p style="color: var(--text-secondary);">
            En n8n: un nodo <strong>Crypto</strong> (Action: HMAC, Type: SHA256) sobre el texto
            <code>@{{ $json.headers['x-rankpro-timestamp'] }}.@{{ $json.body_raw }}</code> con el
            secreto del webhook (se ve una sola vez al crearlo en
            <a href="{{ route('admin.integraciones.webhooks.index') }}">Webhooks</a>), comparado contra el header
            <code>X-RankPro-Signature</code> (quitando el prefijo <code>sha256=</code>).
        </p>
    </div>

    <div class="card" style="margin-top: 1.5rem;">
        <h2 style="margin-top:0;">5. Métricas, pendientes de captura y reportes programados</h2>
        <p style="color: var(--text-secondary);">
            Habilidades: <code>metricas:leer</code> / <code>metricas:escribir</code>, <code>captura:leer</code> y las de
            <code>reportes:*</code>. Los meses van como <code>YYYY-MM</code> (se guardan en el día 1).
        </p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Método</th><th>Ruta</th><th>Qué hace</th></tr></thead>
                <tbody>
                    <tr><td>GET</td><td><code>/metricas</code></td><td>Lista definiciones. Filtros: <code>cliente_id</code>, <code>activa</code> (0/1), <code>search</code>, <code>per_page</code>.</td></tr>
                    <tr><td>POST</td><td><code>/metricas</code></td><td>Crea una definición (<code>cliente_id</code>, <code>nombre</code>; opcionales <code>unidad</code>, <code>meta</code>, <code>sentido</code> mayor|menor, <code>umbral_amarillo_pct</code>, <code>activa</code>, <code>orden</code>).</td></tr>
                    <tr><td>GET · PUT · DELETE</td><td><code>/metricas/{id}</code></td><td>Detalle, edición parcial y borrado (borra también su histórico).</td></tr>
                    <tr><td>GET</td><td><code>/metricas/{id}/valores?desde=2026-01&amp;hasta=2026-10</code></td><td>Valores con el semáforo (<code>verde|amarillo|rojo|sin_meta|sin_dato</code>). Por defecto, los últimos 12 meses; máximo 60.</td></tr>
                    <tr><td>PUT</td><td><code>/metricas/{id}/valores/{YYYY-MM}</code></td><td>Upsert de un mes con <code>{"valor": 120, "nota": "GA4"}</code>. <strong><code>valor</code> vacío o <code>null</code> borra</strong> el valor existente; <code>0</code> se guarda. Sin <code>nota</code> conserva la que había. Reintentar no duplica.</td></tr>
                    <tr><td>POST</td><td><code>/clientes/{cliente}/metricas/valores</code></td><td>Lote atómico de un mes (ver 6). Si una entrada no resuelve a una métrica del cliente responde <code>422</code> con el índice (<code>errors["valores.2"]</code>) y no guarda nada.</td></tr>
                    <tr><td>GET</td><td><code>/captura?mes=2026-10&amp;solo_pendientes=1</code></td><td>Tablero de pendientes de captura (ver 7).</td></tr>
                    <tr><td>GET · POST</td><td><code>/reportes/programados</code></td><td>Lista (con <code>ultimo_ok</code>, <code>ultimo_resultado</code>, <code>proximo_envio_en</code>, <code>ultima_prueba</code>; filtros <code>cliente_id</code>, <code>area</code>, <code>activo</code>, <code>ultimo_ok</code>) y alta.</td></tr>
                    <tr><td>GET · PUT · DELETE</td><td><code>/reportes/programados/{id}</code></td><td>Detalle, edición parcial (lo no enviado se conserva) y borrado.</td></tr>
                    <tr><td>POST</td><td><code>/reportes/programados/{id}/pausar</code> · <code>/activar</code></td><td>Idempotentes. Al reactivar, el próximo envío se recalcula desde hoy.</td></tr>
                    <tr><td>POST</td><td><code>/reportes/programados/{id}/enviar</code></td><td>"Enviar ahora". <strong>Síncrono</strong>: bloquea la petición mientras se genera el PDF y sale el correo (igual que <code>enviar</code> de Correo). <code>422</code> si no salió; el motivo queda en <code>ultimo_resultado</code>.</td></tr>
                    <tr><td>POST</td><td><code>/reportes/programados/{id}/probar</code></td><td>Síncrono. Manda el reporte solo al correo del dueño del token y no toca el último envío.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top: 1.5rem;">
        <h2 style="margin-top:0;">6. n8n: empujar cifras mensuales</h2>
        <p style="color: var(--text-secondary);">
            Nodo <strong>HTTP Request</strong> al final del flujo que lee Search Console, GA4, Ads… Es idempotente: si el flujo
            se reintenta, las cifras se sobrescriben, no se duplican. Crea la credencial una vez
            (<em>Header Auth</em>: nombre <code>Authorization</code>, valor <code>Bearer rp_…</code>) con un token que tenga <code>metricas:escribir</code>.
        </p>
        <pre style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; overflow-x:auto;">Method:          POST
URL:             {{ url('/api/v1') }}/clientes/12/metricas/valores
Authentication:  Generic Credential Type -> Header Auth
Headers:         Accept: application/json
Send Body:       JSON

{
  "periodo": "2026-10",
  "valores": [
    { "nombre": "Llamadas",       "valor": 120, "nota": "GA4" },
    { "nombre": "Clics WhatsApp", "valor": 45 },
    { "metrica_id": 7,            "valor": 0 },
    { "nombre": "Cotizaciones",   "valor": null }
  ]
}</pre>
        <p style="color: var(--text-secondary);">
            Cada entrada usa <code>metrica_id</code> o <code>nombre</code> (coincidencia exacta, sin importar mayúsculas, dentro de ese cliente).
            <code>valor: null</code> borra el valor de ese mes; <code>0</code> se guarda como un cero real. Respuesta
            <code>200</code>: <code>data[]</code> con <code>metrica_id, nombre, periodo, valor, nota, meta, semaforo</code> y
            <code>accion</code> (<code>guardado|borrado</code>). Un nombre inexistente devuelve <code>422</code> con
            <code>errors["valores.3"]</code> y no se guarda nada. Para un solo dato:
            <code>PUT {{ url('/api/v1') }}/metricas/7/valores/2026-10</code> con <code>{"valor": 120}</code>.
        </p>
    </div>

    <div class="card" style="margin-top: 1.5rem;">
        <h2 style="margin-top:0;">7. n8n: consultar pendientes y avisar</h2>
        <p style="color: var(--text-secondary);">
            Un <strong>Schedule Trigger</strong> (p. ej. el día 3 de cada mes) seguido de un <strong>HTTP Request</strong>
            con un token que tenga <code>captura:leer</code>; después recorre <code>data.pendientes</code> con un nodo de WhatsApp o correo.
        </p>
        <pre style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; overflow-x:auto;">Method:   GET
URL:      {{ url('/api/v1') }}/captura
Query:    mes = 2026-10         (opcional; por defecto el mes actual)
          solo_pendientes = 1
Headers:  Authorization: Bearer rp_xxxxxxxx
          Accept: application/json</pre>
        <p style="color: var(--text-secondary);">Respuesta (estable):</p>
        <pre style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; overflow-x:auto;">{
  "data": {
    "mes": "2026-10",
    "resumen": { "pendientes": 3, "total": 8 },
    "filas": [
      { "cliente_id": 12, "cliente": "Hotel Sol", "pendientes": 2,
        "celdas": [ { "fuente": "keywords|seo|ads|metricas", "etiqueta": "...",
                      "estado": "completo|parcial|falta|no_aplica",
                      "hechas": 0, "total": 2, "url": "https://.../admin/..." } ] }
    ],
    "pendientes": [
      { "cliente_id": 12, "cliente": "Hotel Sol", "fuente": "metricas",
        "etiqueta": "Métricas personalizadas", "estado": "falta",
        "hechas": 0, "total": 3, "url": "https://.../admin/metricas?cliente=12&amp;mes=2026-10" }
    ]
  }
}</pre>
        <p style="color: var(--text-secondary);">
            <code>resumen</code> es "N de M pendientes" de todo el mes (aunque <code>solo_pendientes</code> oculte filas).
            <code>filas</code> trae un elemento por cliente activo; <code>pendientes</code> es la lista plana solo con
            estado <code>falta|parcial</code>, lista para un <em>Split Out</em> y un mensaje por elemento con
            <code>cliente</code>, <code>etiqueta</code>, <code>estado</code> y <code>url</code> (donde se captura).
            Todas las rutas nuevas aparecen en <code>openapi.json</code>.
        </p>
    </div>
@endsection
