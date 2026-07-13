@extends('layouts.admin')

@section('content')
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
@endsection
