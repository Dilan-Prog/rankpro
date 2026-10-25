@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/search-console.css')
@endsection

{{--
    Integración con Google Search Console (cuenta de servicio). Formularios clásicos con redirect: lo
    importante (último resultado de la prueba y de cada sincronización) vive en la BD y se pinta aquí
    de forma persistente, no como toast. El diseño visual se rehará aparte: por eso HTML semántico y
    atributos data-gsc-* en lugar de estilos propios.
--}}
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Search Console</h1>
            <p class="page-header__subtitle">Importa clics, impresiones y posiciones directamente de Google, sin capturarlos a mano.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.integraciones.index') }}" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> Integraciones</a>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status" data-gsc-aviso="ok"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @if (session('error'))
        <div class="form-status form-status--error" data-gsc-aviso="error"><i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i><span>{{ session('error') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="form-status form-status--error" data-gsc-aviso="error"><i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i><span>{{ $errors->first() }}</span></div>
    @endif

    {{-- ---------- Guía paso a paso ---------- --}}
    <details class="card card--padded gsc-guia" data-gsc-guia @if (! $tieneLlave) open @endif>
        <summary><strong>Cómo conectarlo (una sola vez)</strong></summary>
        <ol>
            <li>Entra a <strong>Google Cloud Console</strong> (console.cloud.google.com) y crea un proyecto, o usa uno existente.</li>
            <li>Ve a <strong>APIs y servicios &gt; Biblioteca</strong>, busca <strong>"Google Search Console API"</strong> y pulsa <strong>Habilitar</strong>.</li>
            <li>Ve a <strong>IAM y administración &gt; Cuentas de servicio</strong> y crea una cuenta de servicio (no necesita roles de proyecto).</li>
            <li>Abre esa cuenta, pestaña <strong>Claves &gt; Agregar clave &gt; Crear clave nueva &gt; JSON</strong>. Se descarga un archivo <code>.json</code>.</li>
            <li>Pega aquí abajo el contenido de ese archivo (o súbelo) y pulsa <strong>Guardar llave</strong>. Se guarda cifrada y no se vuelve a mostrar.</li>
            <li>En <strong>Google Search Console</strong>, abre cada propiedad &gt; <strong>Configuración &gt; Usuarios y permisos &gt; Añadir usuario</strong> y escribe el correo de la cuenta de servicio (el que aparece abajo) con permiso <strong>Restringido</strong>.</li>
            <li>Pulsa <strong>Probar conexión</strong>, asigna a cada cliente su propiedad y pulsa <strong>Sincronizar ahora</strong>. Después se actualiza solo cada madrugada.</li>
        </ol>
        <p class="field__hint">Los datos de Google llevan 2 o 3 días de retraso y solo se conservan unos 16 meses. El mes en curso se guarda como parcial y no genera medición de posición.</p>
    </details>

    {{-- ---------- Conexión ---------- --}}
    <div class="card card--padded gsc-conexion" data-gsc-conexion data-estado="{{ $conexion?->estado ?? 'sin_llave' }}">
        <h2 class="integraciones-section-title">Llave de Google</h2>

        @if ($tieneLlave)
            <p data-gsc-llave-guardada>
                <i class="fa-solid fa-lock"></i> Llave guardada (cifrada) para
                <code class="u-mono" data-gsc-correo>{{ $conexion->client_email }}</code>
            </p>
            <p class="field__hint">Añade este correo como usuario en cada propiedad de Search Console.</p>

            @if ($conexion->ultima_prueba_en)
                <div class="form-status {{ $conexion->ultima_prueba_ok ? '' : 'form-status--error' }}" data-gsc-prueba data-ok="{{ $conexion->ultima_prueba_ok ? 1 : 0 }}">
                    <i class="fa-solid {{ $conexion->ultima_prueba_ok ? 'fa-circle-check' : 'fa-circle-exclamation' }}" style="margin-top:2px"></i>
                    <span>
                        <strong>Última prueba ({{ $conexion->ultima_prueba_en->diffForHumans() }}):</strong>
                        {{ $conexion->ultima_prueba_resultado }}
                    </span>
                </div>
            @else
                <p class="field__hint" data-gsc-prueba-pendiente>La llave aún no se ha probado.</p>
            @endif

            <div class="gsc-acciones">
                <form method="POST" action="{{ route('admin.integraciones.search-console.probar') }}">
                    @csrf
                    <button type="submit" class="btn btn--primary" data-gsc-probar><i class="fa-solid fa-plug-circle-check"></i> Probar conexión</button>
                </form>
                <form method="POST" action="{{ route('admin.integraciones.search-console.llave.quitar') }}" onsubmit="return confirm('¿Quitar la llave? Las sincronizaciones se detendrán hasta pegar otra.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn--ghost btn--danger" data-gsc-quitar-llave><i class="fa-solid fa-trash"></i> Quitar llave</button>
                </form>
            </div>

            @if ($detectadas->isNotEmpty())
                <p class="field__hint" style="margin-top: var(--space-3)">Propiedades que ve Google con esta llave: {{ $detectadas->implode(', ') }}</p>
            @endif
        @else
            <p class="empty-state__text" data-gsc-sin-llave>Aún no hay llave.</p>
        @endif

        <form method="POST" action="{{ route('admin.integraciones.search-console.llave.guardar') }}" enctype="multipart/form-data" class="gsc-form-llave" data-gsc-form-llave autocomplete="off">
            @csrf
            @method('PUT')
            <div class="field">
                <label class="field__label" for="gsc_llave_json">{{ $tieneLlave ? 'Reemplazar la llave (pega el JSON)' : 'Pega el JSON de la cuenta de servicio' }}</label>
                <textarea class="input u-mono" id="gsc_llave_json" name="llave_json" rows="5" spellcheck="false" autocomplete="off" placeholder='{"type": "service_account", "client_email": "...", "private_key": "..."}'></textarea>
            </div>
            <div class="field">
                <label class="field__label" for="gsc_llave_archivo">...o sube el archivo .json</label>
                <input class="input" type="file" id="gsc_llave_archivo" name="llave_archivo" accept=".json,application/json">
            </div>
            <button type="submit" class="btn btn--secondary" data-gsc-guardar-llave><i class="fa-solid fa-key"></i> Guardar llave</button>
        </form>
    </div>

    {{-- ---------- Clientes y propiedades ---------- --}}
    <div class="integraciones-section-head">
        <h2 class="integraciones-section-title">Clientes y propiedades</h2>
    </div>

    <datalist id="gsc_propiedades_detectadas">
        @foreach ($detectadas as $sitio)
            <option value="{{ $sitio }}"></option>
        @endforeach
    </datalist>

    @if ($clientes->isEmpty())
        <div class="card empty-state">
            <p class="empty-state__text">Aún no hay clientes registrados.</p>
        </div>
    @else
        <x-data-table :headers="['Cliente', 'Propiedad de Search Console', 'Última sincronización', '']">
            @foreach ($clientes as $cliente)
                @php
                    $propiedad = $propiedades->get($cliente->id);
                @endphp
                <tr data-gsc-fila="{{ $cliente->id }}" @if ($propiedad) data-propiedad-id="{{ $propiedad->id }}" @endif>
                    <td>{{ $cliente->nombre }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.integraciones.search-console.propiedad.guardar', $cliente) }}" class="gsc-form-propiedad">
                            @csrf
                            @method('PUT')
                            <input class="input u-mono" type="text" name="site_url" list="gsc_propiedades_detectadas" maxlength="255"
                                   placeholder="https://dominio.com/ o sc-domain:dominio.com" value="{{ $propiedad?->site_url }}" required aria-label="Propiedad de {{ $cliente->nombre }}">
                            <input type="hidden" name="activa" value="0">
                            <label class="checkbox-item"><input type="checkbox" name="activa" value="1" @checked($propiedad?->activa ?? true)> Activa</label>
                            <button type="submit" class="btn btn--secondary btn--sm">Guardar</button>
                        </form>
                        @unless ($propiedad)
                            <span class="field__hint" data-gsc-sin-propiedad>Sin propiedad asignada</span>
                        @endunless
                    </td>
                    <td>
                        @if (! $propiedad)
                            <span class="field__hint">—</span>
                        @elseif ($propiedad->ultima_sync_en === null)
                            <span class="field__hint" data-gsc-sin-sync>Aún no se ha sincronizado.</span>
                        @else
                            <div class="form-status {{ $propiedad->ultimo_ok ? '' : 'form-status--error' }}" data-gsc-resultado data-ok="{{ $propiedad->ultimo_ok ? 1 : 0 }}">
                                <i class="fa-solid {{ $propiedad->ultimo_ok ? 'fa-circle-check' : 'fa-circle-exclamation' }}" style="margin-top:2px"></i>
                                <span><strong>{{ $propiedad->ultima_sync_en->diffForHumans() }}:</strong> {{ $propiedad->ultimo_resultado }}</span>
                            </div>
                        @endif

                        @if ($propiedad && $propiedad->metricas->isNotEmpty())
                            <details data-gsc-meses>
                                <summary>Últimos meses importados</summary>
                                <table class="table gsc-meses">
                                    <thead><tr><th>Mes</th><th>Clics</th><th>Impresiones</th><th>CTR</th><th>Posición media</th></tr></thead>
                                    <tbody>
                                        @foreach ($propiedad->metricas as $m)
                                            <tr data-periodo="{{ $m->periodo->format('Y-m') }}">
                                                <td>{{ $m->periodo->format('Y-m') }} @if ($m->parcial)<span class="badge badge--warning">parcial</span>@endif</td>
                                                <td>{{ number_format($m->clics) }}</td>
                                                <td>{{ number_format($m->impresiones) }}</td>
                                                <td>{{ number_format($m->ctr * 100, 2) }}%</td>
                                                <td>{{ $m->posicion_media !== null ? number_format($m->posicion_media, 1) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif
                    </td>
                    <td style="white-space: nowrap;">
                        @if ($propiedad)
                            <form method="POST" action="{{ route('admin.integraciones.search-console.sincronizar', $propiedad) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn--primary btn--sm" data-gsc-sincronizar @disabled(! $tieneLlave || ! $propiedad->activa)
                                        title="{{ ! $tieneLlave ? 'Aún no hay llave' : (! $propiedad->activa ? 'Propiedad pausada' : 'Traer los datos ahora') }}">
                                    <i class="fa-solid fa-rotate"></i> Sincronizar ahora
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.integraciones.search-console.propiedad.quitar', $propiedad) }}" style="display:inline" onsubmit="return confirm('¿Desvincular la propiedad? Se borran sus totales mensuales; las mediciones de posición se conservan.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn--icon btn--danger" title="Desvincular propiedad"><i class="fa-solid fa-link-slash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    @endif
@endsection
