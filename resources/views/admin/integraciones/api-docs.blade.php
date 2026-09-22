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
            <code>{{ '{{ $json.headers[\'x-rankpro-timestamp\'] }}' }}.{{ '{{ $json.body_raw }}' }}</code> con el
            secreto del webhook (se ve una sola vez al crearlo en
            <a href="{{ route('admin.integraciones.webhooks.index') }}">Webhooks</a>), comparado contra el header
            <code>X-RankPro-Signature</code> (quitando el prefijo <code>sha256=</code>).
        </p>
    </div>
@endsection
