@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Conversiones</h1>
            <p class="page-header__subtitle">Todas las conversiones de todos los clientes, en un solo lugar</p>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="form-status form-status--error"><i class="fa-solid fa-triangle-exclamation" style="margin-top:2px"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <form method="GET" class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="cliente_id">Cliente</label>
                <select class="select" name="cliente_id" id="cliente_id" onchange="this.form.requestSubmit()">
                    <option value="">— Todos los clientes —</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected((string) request('cliente_id') === (string) $cliente->id)>{{ $cliente->nombre }}</option>
                    @endforeach
                </select>
                <span class="field__hint">Elige un cliente para poder filtrar también por etapa del embudo.</span>
            </div>

            @if ($clienteSeleccionado)
                <div class="field">
                    <label class="field__label" for="ads_embudo_etapa_id">Etapa del embudo — {{ $clienteSeleccionado->nombre }}</label>
                    <select class="select" name="ads_embudo_etapa_id" id="ads_embudo_etapa_id">
                        <option value="">— Todas —</option>
                        <option value="sin_clasificar" @selected(request('ads_embudo_etapa_id') === 'sin_clasificar')>Sin clasificar</option>
                        @foreach ($etapas as $etapa)
                            <option value="{{ $etapa->id }}" @selected((string) request('ads_embudo_etapa_id') === (string) $etapa->id)>{{ $etapa->nombre }}</option>
                        @endforeach
                    </select>
                    @if ($etapas->isEmpty())
                        <span class="field__hint">Este cliente no tiene etapas todavía — <a href="{{ route('admin.clientes.conversiones', $clienteSeleccionado) }}">agrégalas aquí</a>.</span>
                    @endif
                </div>
            @endif

            <div class="field">
                <label class="field__label" for="fecha_desde">Desde</label>
                <input class="input" type="date" name="fecha_desde" id="fecha_desde" value="{{ request('fecha_desde') }}">
            </div>
            <div class="field">
                <label class="field__label" for="fecha_hasta">Hasta</label>
                <input class="input" type="date" name="fecha_hasta" id="fecha_hasta" value="{{ request('fecha_hasta') }}">
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
                <label class="field__label" for="valor_min">Valor mínimo</label>
                <input class="input" type="number" step="0.01" min="0" name="valor_min" id="valor_min" value="{{ request('valor_min') }}">
            </div>
            <div class="field" style="grid-column: span 2;">
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
            <div style="grid-column: span 2; display:flex; gap: var(--space-2);">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a href="{{ route('admin.conversiones.index') }}" class="btn btn--secondary">Limpiar</a>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.conversiones.exportar-excel') }}" style="margin-top: var(--space-3);">
            @csrf
            @foreach (request()->query() as $key => $value)
                @foreach ((array) $value as $v)
                    <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $v }}">
                @endforeach
            @endforeach
            <button type="submit" class="btn btn--secondary">
                <i class="fa-solid fa-file-excel"></i> Exportar a Excel
            </button>
        </form>
    </div>

    @if ($conversiones->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-filter"></i></div>
            <p class="empty-state__text">No hay conversiones registradas con estos filtros.</p>
        </div>
    @else
        <x-data-table :headers="['Fecha', 'Cliente', 'Tipo', 'Identificador', 'Campaña', 'Valor', 'Estado', 'Etapa del embudo']">
            @foreach ($conversiones as $conversion)
                <tr>
                    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $conversion->created_at->format('Y-m-d H:i') }}</td>
                    <td><a href="{{ route('admin.clientes.conversiones', $conversion->cliente) }}" style="color:var(--color-foreground);">{{ $conversion->cliente->nombre }}</a></td>
                    <td>{{ \App\Support\Labels::tipoConversion($conversion->tipo->value) }}</td>
                    <td>
                        @php $identificador = $conversion->gclid ?? $conversion->gbraid ?? $conversion->wbraid; @endphp
                        <span class="u-mono" style="font-size:var(--text-xs);" title="{{ $identificador }}">{{ $identificador ? \Illuminate\Support\Str::limit($identificador, 16) : '—' }}</span>
                    </td>
                    <td>{{ $conversion->adsClic?->adsCampana?->nombre ?? '—' }}</td>
                    <td class="u-mono">{{ $conversion->valor ? '$'.number_format($conversion->valor, 2).' '.$conversion->moneda : '—' }}</td>
                    <td><x-badge :status="$conversion->estado" /></td>
                    <td>
                        <form method="POST" action="{{ route('admin.conversiones.etapa', $conversion) }}">
                            @csrf
                            <select name="ads_embudo_etapa_id" class="select" style="font-size:var(--text-xs); padding:2px 4px;" onchange="this.form.requestSubmit()">
                                <option value="">Sin clasificar</option>
                                @foreach ($conversion->cliente->embudoEtapas as $etapa)
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
