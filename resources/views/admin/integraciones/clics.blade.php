@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Clics — {{ $cliente->nombre }}</h1>
            <p class="page-header__subtitle">Historial de visitas provenientes de anuncios de Google</p>
        </div>
        <a href="{{ route('admin.clientes.integraciones', $cliente) }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Integraciones
        </a>
    </div>

    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <form method="GET" class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="fecha_desde">Desde</label>
                <input class="input" type="date" name="fecha_desde" id="fecha_desde" value="{{ request('fecha_desde') }}">
            </div>
            <div class="field">
                <label class="field__label" for="fecha_hasta">Hasta</label>
                <input class="input" type="date" name="fecha_hasta" id="fecha_hasta" value="{{ request('fecha_hasta') }}">
            </div>
            <div class="field">
                <label class="field__label" for="ads_campana_id">Campaña</label>
                <select class="select" name="ads_campana_id" id="ads_campana_id">
                    <option value="">— Todas —</option>
                    <option value="sin_asignar" @selected(request('ads_campana_id') === 'sin_asignar')>Sin asignar</option>
                    @foreach ($campanas as $campana)
                        <option value="{{ $campana->id }}" @selected((string) request('ads_campana_id') === (string) $campana->id)>{{ $campana->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="tipo_id">Tipo de identificador</label>
                <select class="select" name="tipo_id" id="tipo_id">
                    <option value="">— Cualquiera —</option>
                    <option value="gclid" @selected(request('tipo_id') === 'gclid')>GCLID</option>
                    <option value="gbraid" @selected(request('tipo_id') === 'gbraid')>GBRAID</option>
                    <option value="wbraid" @selected(request('tipo_id') === 'wbraid')>WBRAID</option>
                </select>
            </div>
            <div class="field" style="grid-column: span 2;">
                <label class="field__label" for="utm_campaign">Nombre de campaña (UTM)</label>
                <input class="input" type="text" name="utm_campaign" id="utm_campaign" value="{{ request('utm_campaign') }}" placeholder="Buscar por utm_campaign...">
            </div>
            <div style="grid-column: span 2; display:flex; gap: var(--space-2);">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a href="{{ route('admin.clientes.clics', $cliente) }}" class="btn btn--secondary">Limpiar</a>
            </div>
        </form>
    </div>

    @if ($clics->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-arrow-pointer"></i></div>
            <p class="empty-state__text">No hay clics registrados con estos filtros.</p>
        </div>
    @else
        <x-data-table :headers="['Fecha', 'Identificador', 'Campaña', 'UTM Campaign', 'Landing URL', '']">
            @foreach ($clics as $clic)
                <tr>
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $clic->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        @if ($clic->gclid)
                            <span class="badge badge--info" title="{{ $clic->gclid }}">GCLID</span>
                        @elseif ($clic->gbraid)
                            <span class="badge badge--info" title="{{ $clic->gbraid }}">GBRAID</span>
                        @elseif ($clic->wbraid)
                            <span class="badge badge--info" title="{{ $clic->wbraid }}">WBRAID</span>
                        @else
                            <span style="color:var(--color-muted-foreground);">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($clic->adsCampana)
                            {{ $clic->adsCampana->nombre }}
                        @else
                            <form method="POST" action="{{ route('admin.clientes.clics.asignar', ['cliente' => $cliente, 'clic' => $clic]) }}" style="display:flex; gap:4px;">
                                @csrf
                                <select name="ads_campana_id" class="select" style="font-size:var(--text-xs); padding:2px 4px;" onchange="this.form.requestSubmit()">
                                    <option value="">Sin asignar</option>
                                    @foreach ($campanas as $campana)
                                        <option value="{{ $campana->id }}">{{ $campana->nombre }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                    </td>
                    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $clic->utm_campaign ?? '—' }}</span></td>
                    <td><span class="u-mono" style="font-size:var(--text-xs); color:var(--color-primary);" title="{{ $clic->landing_url }}">{{ \Illuminate\Support\Str::limit($clic->landing_url, 45) }}</span></td>
                    <td>{{ $clic->conversiones()->exists() ? '✓' : '' }}</td>
                </tr>
            @endforeach
        </x-data-table>

        @include('admin.integraciones._paginacion', ['paginator' => $clics])
    @endif
@endsection
