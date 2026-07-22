@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Conversiones — {{ $cliente->nombre }}</h1>
            <p class="page-header__subtitle">Formularios, WhatsApp, llamadas y compras vinculadas a clics de Google Ads</p>
        </div>
        <a href="{{ route('admin.clientes.integraciones', $cliente) }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Integraciones
        </a>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <h3 class="fase-form__section-title">Etapas del embudo de ventas</h3>
        <p class="field__hint" style="margin-bottom: var(--space-3);">Personalizadas para este cliente — clasifica cada conversión con el menú de la tabla de abajo.</p>

        <div style="display:flex; flex-wrap:wrap; gap: var(--space-2); margin-bottom: var(--space-4);">
            @forelse ($etapas as $etapa)
                <div style="display:flex; align-items:center; gap:2px; padding:2px 2px 2px 10px; border-radius:var(--radius-full); background:var(--color-secondary); border:1px solid var(--color-border);">
                    <form method="POST" action="{{ route('admin.clientes.embudo.etapas.update', $etapa) }}">
                        @csrf
                        @method('PUT')
                        <input type="text" name="nombre" value="{{ $etapa->nombre }}"
                            style="border:none; background:transparent; font-size:var(--text-sm); color:var(--color-foreground); width:{{ max(6, strlen($etapa->nombre)) }}ch;"
                            onchange="this.form.requestSubmit()">
                    </form>
                    <form method="POST" action="{{ route('admin.clientes.embudo.etapas.destroy', $etapa) }}" data-confirm="¿Eliminar la etapa &quot;{{ $etapa->nombre }}&quot;? Las conversiones que estaban ahí quedarán sin clasificar.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn--icon" style="padding:4px; color:var(--text-danger);" title="Eliminar etapa">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </form>
                </div>
            @empty
                <span style="font-size:var(--text-sm); color:var(--color-muted-foreground);">Sin etapas todavía — agrega la primera aquí abajo.</span>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.clientes.embudo.etapas.store', $cliente) }}" style="display:flex; gap: var(--space-2); max-width: 420px;">
            @csrf
            <input type="text" name="nombre" class="input" placeholder="Nueva etapa (ej. Lead Calificado)" required>
            <button type="submit" class="btn btn--secondary" style="white-space:nowrap;"><i class="fa-solid fa-plus"></i> Agregar etapa</button>
        </form>
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
                <label class="field__label" for="ads_embudo_etapa_id">Etapa del embudo</label>
                <select class="select" name="ads_embudo_etapa_id" id="ads_embudo_etapa_id">
                    <option value="">— Todas —</option>
                    <option value="sin_clasificar" @selected(request('ads_embudo_etapa_id') === 'sin_clasificar')>Sin clasificar</option>
                    @foreach ($etapas as $etapa)
                        <option value="{{ $etapa->id }}" @selected((string) request('ads_embudo_etapa_id') === (string) $etapa->id)>{{ $etapa->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="estado">Estado</label>
                <select class="select" name="estado" id="estado">
                    <option value="">— Todos —</option>
                    <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option>
                    <option value="exportada" @selected(request('estado') === 'exportada')>Exportada</option>
                </select>
            </div>
            <div class="field">
                <label class="field__label">Tipo</label>
                <div class="checkbox-group">
                    @foreach ($tiposConversion as $tipo)
                        <label class="checkbox-item">
                            <input type="checkbox" name="tipo[]" value="{{ $tipo->value }}" @checked(in_array($tipo->value, (array) request('tipo', [])))>
                            {{ \App\Support\Labels::tipoConversion($tipo->value) }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="field">
                <label class="field__label" for="valor_min">Valor mínimo</label>
                <input class="input" type="number" step="0.01" min="0" name="valor_min" id="valor_min" value="{{ request('valor_min') }}">
            </div>
            <div style="grid-column: span 2; display:flex; gap: var(--space-2);">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a href="{{ route('admin.clientes.conversiones', $cliente) }}" class="btn btn--secondary">Limpiar</a>
            </div>
        </form>

        {{-- Forms separados (no anidados dentro del de filtros — HTML no permite <form> anidados) que reenvían los mismos filtros vía inputs ocultos. --}}
        <div style="display:flex; gap: var(--space-2); margin-top: var(--space-3);">
            <form method="POST" action="{{ route('admin.clientes.conversiones.exportar', $cliente) }}">
                @csrf
                @foreach (request()->query() as $key => $value)
                    @foreach ((array) $value as $v)
                        <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $v }}">
                    @endforeach
                @endforeach
                <button type="submit" class="btn" style="background:var(--color-primary); color:#fff;" data-confirm="¿Exportar las conversiones pendientes que coinciden con estos filtros? Pasarán a estado &quot;Exportada&quot;.">
                    <i class="fa-solid fa-file-csv"></i> Exportar CSV (pendientes, formato Google Ads)
                </button>
            </form>

            <form method="POST" action="{{ route('admin.clientes.conversiones.exportar-excel', $cliente) }}">
                @csrf
                @foreach (request()->query() as $key => $value)
                    @foreach ((array) $value as $v)
                        <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $v }}">
                    @endforeach
                @endforeach
                <button type="submit" class="btn btn--secondary">
                    <i class="fa-solid fa-file-excel"></i> Exportar a Excel (con etapa)
                </button>
            </form>
        </div>
    </div>

    @if ($conversiones->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-bullseye"></i></div>
            <p class="empty-state__text">No hay conversiones registradas con estos filtros.</p>
        </div>
    @else
        <x-data-table :headers="['Fecha', 'Tipo', 'Identificador', 'Campaña', 'Valor', 'Estado', 'Etapa del embudo']">
            @foreach ($conversiones as $conversion)
                <tr>
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $conversion->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ \App\Support\Labels::tipoConversion($conversion->tipo->value) }}</td>
                    <td>
                        @php $identificador = $conversion->gclid ?? $conversion->gbraid ?? $conversion->wbraid; @endphp
                        <span class="u-mono" style="font-size:var(--text-xs);" title="{{ $identificador }}">{{ $identificador ? \Illuminate\Support\Str::limit($identificador, 16) : '—' }}</span>
                    </td>
                    <td>{{ $conversion->adsClic?->adsCampana?->nombre ?? '—' }}</td>
                    <td class="u-mono">{{ $conversion->valor ? '$'.number_format($conversion->valor, 2).' '.$conversion->moneda : '—' }}</td>
                    <td><x-badge :status="$conversion->estado" /></td>
                    <td>
                        <form method="POST" action="{{ route('admin.clientes.conversiones.etapa', ['cliente' => $cliente, 'conversion' => $conversion]) }}">
                            @csrf
                            <select name="ads_embudo_etapa_id" class="select" style="font-size:var(--text-xs); padding:2px 4px;" onchange="this.form.requestSubmit()">
                                <option value="">Sin clasificar</option>
                                @foreach ($etapas as $etapa)
                                    <option value="{{ $etapa->id }}" @selected($conversion->ads_embudo_etapa_id === $etapa->id)>{{ $etapa->nombre }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        @include('admin.integraciones._paginacion', ['paginator' => $conversiones])
    @endif
@endsection
