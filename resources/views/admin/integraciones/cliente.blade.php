@extends('layouts.admin')

@section('content')
    @vite('resources/css/admin/integraciones.css')

    @php
        $semaforo = 'red';
        $textoSenal = 'Sin señal registrada todavía';
        if ($ultimaSenal) {
            $horas = $ultimaSenal->diffInHours(now());
            $semaforo = $horas < 24 ? 'green' : ($horas < 168 ? 'amber' : 'red');
            $textoSenal = 'Última señal recibida: '.$ultimaSenal->diffForHumans();
        }
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">Integraciones — {{ $cliente->nombre }}</h1>
            <p class="page-header__subtitle">Tracking de clics y conversiones de Google Ads</p>
        </div>
        <a href="{{ route('admin.clientes.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Clientes
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="kpi-grid">
        <x-stat-card label="Estado del token" value="{{ $cliente->api_token ? 'Activo' : 'Sin generar' }}" icon="fa-key" color="{{ $cliente->api_token ? 'emerald' : 'amber' }}" />
        <x-stat-card label="Conversiones pendientes" value="{{ $pendientesCount }}" sub="por exportar a Google Ads" icon="fa-file-export" color="primary" />
    </div>

    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <div style="display:flex; align-items:center; gap: var(--space-2); margin-bottom: var(--space-4);">
            <span style="width:10px; height:10px; border-radius:50%; background:{{ ['green' => 'var(--text-success)', 'amber' => 'var(--text-warning)', 'red' => 'var(--text-danger)'][$semaforo] }};"></span>
            <span style="font-size:var(--text-sm); color:var(--color-muted-foreground);">{{ $textoSenal }}</span>
        </div>

        @if ($cliente->api_token)
            <label class="field__label">Script para instalar en el sitio del cliente</label>
            <div style="display:flex; gap: var(--space-2); align-items:center;">
                <input type="text" class="input u-mono" readonly value="&lt;script src=&quot;{{ $scriptUrl }}&quot;&gt;&lt;/script&gt;" onclick="this.select()" style="flex:1;">
                <button type="button" class="btn btn--secondary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">
                    <i class="fa-solid fa-copy"></i> Copiar
                </button>
            </div>
            <p class="field__hint" style="margin-top: var(--space-2);">
                Pégalo en el <code>&lt;head&gt;</code> del sitio del cliente. Para registrar conversiones (WhatsApp, llamadas, formularios), el sitio debe llamar a
                <code>window.RankProTracking.trackConversion('whatsapp')</code> (o <code>'llamada'</code>, <code>'formulario'</code>, <code>'compra'</code>) en el evento correspondiente.
            </p>

            <div style="margin-top: var(--space-4); display:flex; gap: var(--space-2);">
                <a href="{{ route('admin.clientes.clics', $cliente) }}" class="btn btn--secondary"><i class="fa-solid fa-arrow-pointer"></i> Ver clics</a>
                <a href="{{ route('admin.clientes.conversiones', $cliente) }}" class="btn btn--secondary"><i class="fa-solid fa-bullseye"></i> Ver conversiones</a>
            </div>

            <form method="POST" action="{{ route('admin.clientes.integraciones.token', $cliente) }}" style="margin-top: var(--space-4);" data-confirm="¿Regenerar el token? El script instalado con el token anterior dejará de funcionar hasta actualizarlo.">
                @csrf
                <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate"></i> Regenerar token</button>
            </form>
        @else
            <p style="color:var(--color-muted-foreground); font-size:var(--text-sm); margin-bottom: var(--space-4);">
                Genera un token para obtener el script de tracking de este cliente.
            </p>
            <form method="POST" action="{{ route('admin.clientes.integraciones.token', $cliente) }}">
                @csrf
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-key"></i> Generar Token</button>
            </form>
        @endif
    </div>

    {{-- Guía de instalación: vive aquí y no en un PDF aparte para que el
         equipo la copie con el token ya puesto y siempre esté al día con lo
         que el snippet realmente hace (ver resources/views/tracking/snippet.blade.php). --}}
    <div class="card card--padded tracking-guia">
        <h2 class="tracking-guia__title"><i class="fa-solid fa-book"></i> Guía de instalación del tracking</h2>
        <p class="tracking-guia__intro">
            El script identifica a cada visitante, guarda el <code>gclid</code> de Google Ads durante 90 días y envía las conversiones a RankPro
            con esa atribución. Registra clics de Ads <strong>solo</strong>; las conversiones las tiene que disparar el sitio del cliente con
            <code>trackConversion()</code>. Todas las peticiones llevan el token del cliente, así que no hay nada más que configurar.
        </p>

        <div class="tracking-guia__tipos">
            <span class="tracking-guia__tipos-label">Tipos de conversión válidos</span>
            @foreach (\App\Enums\TipoConversion::cases() as $tipo)
                <code>'{{ $tipo->value }}'</code>
            @endforeach
            <span class="field__hint">— cualquier otro valor devuelve 422 y no se guarda.</span>
        </div>

        <details class="tracking-guia__paso" open>
            <summary><span class="tracking-guia__num">1</span> Instalar el script (obligatorio)</summary>
            <p>Pégalo en el <code>&lt;head&gt;</code> de <strong>todas</strong> las páginas, antes de cualquier otro script de medición. En WordPress: <em>Apariencia → Editor de temas → header.php</em> o un plugin de "Insert Headers and Footers"; en Shopify: <em>theme.liquid</em>; con GTM: etiqueta HTML personalizada en <em>Todas las páginas</em> (funciona, pero el listener de abajo tiene que ir en otra etiqueta que se dispare después).</p>
            <pre class="tracking-guia__code"><code>@if ($cliente->api_token)&lt;script src="{{ $scriptUrl }}"&gt;&lt;/script&gt;@else&lt;script src="https://TU-DOMINIO/api/tracking/snippet/{token}.js"&gt;&lt;/script&gt;@endif</code></pre>
            <p class="field__hint">Si el token es inválido el script responde igual con un JS vacío (200) para no romper el sitio del cliente: revisa el semáforo de "última señal" de arriba para confirmar que llegan datos.</p>
        </details>

        <details class="tracking-guia__paso" open>
            <summary><span class="tracking-guia__num">2</span> WhatsApp y llamadas (recomendado para todos los clientes)</summary>
            <p>Un solo listener registra cualquier clic a <code>wa.me</code>, <code>api.whatsapp.com</code> o <code>tel:</code> aunque los botones se agreguen después. Pégalo justo debajo del script del paso 1.</p>
            <pre class="tracking-guia__code"><code>&lt;script&gt;
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[href]');
  if (!a || !window.RankProTracking) return;
  var href = a.getAttribute('href') || '';
  if (/wa\.me|api\.whatsapp\.com|whatsapp:/i.test(href)) {
    window.RankProTracking.trackConversion('whatsapp');
  } else if (/^tel:/i.test(href)) {
    window.RankProTracking.trackConversion('llamada');
  }
});
&lt;/script&gt;</code></pre>
            <p class="field__hint">Se registra el <strong>clic</strong>, no la conversación. Usa un mensaje prellenado (<code>wa.me/52…?text=Hola,%20vi%20su%20anuncio</code>) para que recepción reconozca de dónde viene.</p>
        </details>

        <details class="tracking-guia__paso">
            <summary><span class="tracking-guia__num">3</span> Formularios</summary>
            <p>Llama a <code>trackConversion('formulario')</code> cuando el envío sea exitoso: en la página de gracias, o en el evento de éxito del plugin de formularios.</p>
            <pre class="tracking-guia__code"><code>&lt;!-- Opción A: página de gracias (/gracias) --&gt;
&lt;script&gt;window.RankProTracking &amp;&amp; window.RankProTracking.trackConversion('formulario');&lt;/script&gt;

&lt;!-- Opción B: Contact Form 7 (WordPress) --&gt;
&lt;script&gt;
document.addEventListener('wpcf7mailsent', function () {
  window.RankProTracking &amp;&amp; window.RankProTracking.trackConversion('formulario');
});
&lt;/script&gt;

&lt;!-- Opción C: formulario propio --&gt;
&lt;script&gt;
document.querySelector('#cotizacion').addEventListener('submit', function () {
  window.RankProTracking &amp;&amp; window.RankProTracking.trackConversion('formulario');
});
&lt;/script&gt;</code></pre>
        </details>

        <details class="tracking-guia__paso">
            <summary><span class="tracking-guia__num">4</span> Compras y reservas con valor</summary>
            <p>El segundo parámetro es el <strong>valor</strong> (número, MXN). Va en la página de confirmación del motor de reservas o del checkout. Si el motor está en otro dominio, el visitante conserva su ID por cookie/localStorage solo dentro del mismo dominio: pide al proveedor que exponga un evento de confirmación en tu dominio, o usa la página de gracias propia.</p>
            <pre class="tracking-guia__code"><code>&lt;script&gt;
window.RankProTracking &amp;&amp; window.RankProTracking.trackConversion('compra', 3200);
&lt;/script&gt;</code></pre>
        </details>

        <details class="tracking-guia__paso">
            <summary><span class="tracking-guia__num">5</span> Verificar la instalación</summary>
            <ol class="tracking-guia__lista">
                <li>Abre el sitio del cliente con <code>?gclid=prueba123</code> al final de la URL. En <a href="{{ route('admin.clientes.clics', $cliente) }}">Ver clics</a> debe aparecer un clic con ese <code>gclid</code> en menos de un minuto.</li>
                <li>Haz clic en el botón de WhatsApp. En <a href="{{ route('admin.clientes.conversiones', $cliente) }}">Ver conversiones</a> debe aparecer una conversión <code>whatsapp</code> asociada a ese clic.</li>
                <li>El semáforo de arriba pasa a verde con la primera señal. Borra las conversiones de prueba antes de exportar a Google Ads.</li>
                <li>Si no llega nada: consola del navegador (F12) → pestaña Red → filtra <code>tracking</code>. Un 401 es token incorrecto; un 422 es un tipo inválido; nada en la lista es que el script no cargó o un bloqueador lo frenó.</li>
            </ol>
        </details>

        <details class="tracking-guia__paso">
            <summary><span class="tracking-guia__num">6</span> Qué guarda y límites</summary>
            <ul class="tracking-guia__lista">
                <li><strong>Clic</strong>: solo cuando la URL trae <code>gclid</code>, <code>gbraid</code> o <code>wbraid</code>. Guarda UTMs, landing, referrer, IP y user agent, y lo asigna a una campaña de Ads si <code>utm_campaign</code> coincide exactamente con el nombre de una campaña del cliente en RankPro (si hay varias, queda sin asignar y se asigna a mano desde Ver clics).</li>
                <li><strong>Conversión</strong>: se enlaza al último clic del mismo visitante; si el navegador perdió el <code>gclid</code>, se recupera del clic. Las conversiones sin clic previo (tráfico orgánico) también se guardan, sin campaña.</li>
                <li>Identificador de visitante: 730 días; ventana de atribución del <code>gclid</code>: 90 días (la de Google Ads).</li>
                <li>Límite de 60 peticiones por minuto por IP. Los bloqueadores de anuncios pueden frenar la petición: la cifra real de contactos será algo mayor que la registrada.</li>
                <li>Regenerar el token invalida el script instalado: hay que volver a pegarlo.</li>
            </ul>
        </details>
    </div>
@endsection
