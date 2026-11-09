@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Conversiones</h1>
            <p class="page-header__subtitle">Todas las conversiones de todos los clientes, en un solo lugar</p>
        </div>
    </div>

    <x-cliente-activo-aviso :cliente="$clienteActivo" />

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="form-status form-status--error"><i class="fa-solid fa-triangle-exclamation" style="margin-top:2px"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <details class="card card--padded" style="margin-bottom: var(--space-6);" open>
        <summary class="filters-toggle">
            <span class="filters-toggle__label"><i class="fa-solid fa-filter"></i> Filtros</span>
            <i class="fa-solid fa-chevron-down filters-toggle__chevron"></i>
        </summary>
        <div class="filters-toggle__body">
        <form method="GET" class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="cliente_id">Cliente</label>
                <select class="select" name="cliente_id" id="cliente_id" onchange="this.form.requestSubmit()">
                    <option value="">— Todos los clientes —</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected((string) request('cliente_id') === (string) $cliente->id)>{{ $cliente->nombre_comercial }}</option>
                    @endforeach
                </select>
                <span class="field__hint">Elige un cliente para poder filtrar también por etapa del embudo.</span>
            </div>

            @if ($clienteSeleccionado)
                <div class="field">
                    <label class="field__label" for="ads_embudo_etapa_id">Etapa del embudo — {{ $clienteSeleccionado->nombre_comercial }}</label>
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

        <form method="POST" action="{{ route('admin.conversiones.exportar-excel') }}" style="margin-top: var(--space-3);" data-descarga>
            @csrf
            @foreach (request()->query() as $key => $value)
                @foreach ((array) $value as $v)
                    <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $v }}">
                @endforeach
            @endforeach
            <button type="submit" class="btn btn--secondary">
                <i class="fa-solid fa-file-csv"></i> Exportar a CSV
            </button>
        </form>
        </div>
    </details>

    {{-- Estado de la lista: qué se puede subir a Google Ads y qué no (las 31 sin click id siguen visibles, marcadas). --}}
    @php
        $consultaSinPagina = request()->except('duplicadas', 'page');
    @endphp
    <div class="conv-resumen" data-conv-resumen>
        @if ($verDuplicadas)
            <span><strong>{{ $resumen['duplicadas'] }}</strong> marcadas como duplicadas (doble clic o repetidas a mano). No cuentan en la lista, el embudo ni los exportes.</span>
            <a href="{{ route('admin.conversiones.index', $consultaSinPagina) }}" class="btn btn--secondary btn--sm"><i class="fa-solid fa-arrow-left"></i> Volver a la lista</a>
        @else
            <span><strong>{{ $resumen['total'] }}</strong> conversiones</span>
            <span class="conv-chip conv-chip--ok" title="Tienen etapa y gclid, gbraid o wbraid, y no son duplicadas"><i class="fa-solid fa-check"></i> {{ $resumen['exportables'] }} listas para Google Ads</span>
            <span class="conv-chip conv-chip--aviso" title="Tienen identificador de clic pero falta clasificarlas en una etapa"><i class="fa-solid fa-tag"></i> {{ $resumen['sin_etapa'] }} sin etapa</span>
            <span class="conv-chip conv-chip--error" title="Sin gclid, gbraid ni wbraid Google no puede atribuirlas: no se exportan"><i class="fa-solid fa-ban"></i> {{ $resumen['sin_identificador'] }} sin identificador de clic (no exportables)</span>
            <a href="{{ route('admin.conversiones.index', $consultaSinPagina + ['duplicadas' => 1]) }}" class="conv-resumen__enlace">Ver duplicadas ({{ $resumen['duplicadas'] }})</a>
        @endif
    </div>

    {{-- Los checkboxes de la tabla se asocian a este formulario con form="conv-lote": enviar no depende de JS. --}}
    <form method="POST" id="conv-lote" class="conv-lote" action="{{ route('admin.conversiones.lote.etapa') }}" data-conv-lote>
        @csrf
        <span class="conv-lote__contador" data-lote-contador aria-live="polite">0 seleccionadas</span>
        @if (! $verDuplicadas)
            @if ($clienteSeleccionado)
                <select name="ads_embudo_etapa_id" class="select" data-lote-etapa aria-label="Etapa para las seleccionadas">
                    <option value="">Elige una etapa…</option>
                    @foreach ($etapas as $etapa)
                        <option value="{{ $etapa->id }}">{{ $etapa->nombre }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn--primary btn--sm" data-lote-clasificar disabled><i class="fa-solid fa-tag"></i> Clasificar seleccionadas</button>
                <button type="submit" name="quitar_etapa" value="1" class="btn btn--secondary btn--sm" data-lote-requiere disabled>Quitar etapa</button>
            @else
                <span class="field__hint">Filtra por un cliente para clasificar en lote (las etapas son de cada cliente).</span>
            @endif
            <button type="submit" formaction="{{ route('admin.conversiones.lote.duplicadas') }}" class="btn btn--secondary btn--sm" data-lote-requiere data-lote-min="2" disabled
                    title="Se conserva la más antigua de la selección y las demás se marcan; se puede revertir"><i class="fa-regular fa-clone"></i> Marcar como duplicadas</button>
        @endif
    </form>

    @if ($conversiones->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-filter"></i></div>
            <p class="empty-state__text">No hay conversiones registradas con estos filtros.</p>
        </div>
    @else
        <div class="table-wrap table-wrap--grid" data-conversiones-table
             data-columnas-store="{{ route('admin.conversiones.columnas.store') }}"
             data-columnas-base="{{ url('admin/conversiones/columnas') }}"
             data-actualizar-base="{{ url('admin/conversiones') }}">
            <table class="table">
                <thead>
                    <tr>
                        <th class="conv-check"><input type="checkbox" data-lote-todas aria-label="Seleccionar todas las de esta página" title="Seleccionar todas las de esta página"></th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Tipo ID</th>
                        <th>Identificador</th>
                        <th>Campaña</th>
                        <th class="u-mono">Valor</th>
                        <th>Estado</th>
                        <th>Etapa del embudo</th>
                        @foreach ($columnas as $columna)
                            <th data-columna-id="{{ $columna->id }}">
                                <span data-columna-nombre-display="{{ $columna->id }}">{{ $columna->nombre }}</span>
                                <button type="button" class="btn--icon" data-delete-columna="{{ $columna->id }}" title="Eliminar columna"><i class="fa-solid fa-xmark"></i></button>
                            </th>
                        @endforeach
                        <th style="white-space:nowrap;">
                            <button type="button" class="btn--icon" data-add-columna title="Agregar columna"><i class="fa-solid fa-plus"></i></button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conversiones as $conversion)
                        @php
                            $tipoId = $conversion->gclid ? 'Gclid' : ($conversion->gbraid ? 'Gbraid' : ($conversion->wbraid ? 'Wbraid' : null));
                            $identificador = $conversion->gclid ?? $conversion->gbraid ?? $conversion->wbraid;
                        @endphp
                        <tr data-conversion-id="{{ $conversion->id }}" @class(['conv-fila--duplicada' => $conversion->esDuplicada()])>
                            <td class="conv-check"><input type="checkbox" name="conversion_ids[]" value="{{ $conversion->id }}" form="conv-lote" data-lote-fila aria-label="Seleccionar la conversión {{ $conversion->id }}"></td>
                            <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ fecha_mx($conversion->created_at, true) }}</td>
                            <td><a href="{{ route('admin.clientes.conversiones', $conversion->cliente) }}" style="color:var(--color-foreground);">{{ $conversion->cliente->nombre_comercial }}</a></td>
                            <td>{{ \App\Support\Labels::tipoConversion($conversion->tipo->value) }}</td>
                            <td>{{ $tipoId ?? '—' }}</td>
                            <td>
                                <span class="u-mono" style="font-size:var(--text-xs);" title="{{ $identificador }}">{{ $identificador ? \Illuminate\Support\Str::limit($identificador, 16) : '—' }}</span>
                            </td>
                            <td>{{ $conversion->campanaNombre() ?? '—' }}</td>
                            <td class="u-mono" data-editable-cell data-field="valor" @if ($conversion->valor !== null) data-valor="{{ $conversion->valor }}" @endif>{{ $conversion->valor !== null ? number_format($conversion->valor, 2) : '—' }}</td>
                            <td>
                                <x-badge :status="$conversion->estado" />
                                @php
                                    $motivo = $conversion->motivoNoExportable();
                                @endphp
                                @if ($motivo === 'duplicada')
                                    <span class="conv-chip conv-chip--dup" title="Se conserva la #{{ $conversion->duplicada_de_id }}">Duplicada de #{{ $conversion->duplicada_de_id }}</span>
                                @elseif ($motivo === 'sin_identificador')
                                    <span class="conv-chip conv-chip--error" title="Sin gclid, gbraid ni wbraid Google no puede atribuirla">No exportable: sin identificador</span>
                                @elseif ($motivo === 'sin_etapa')
                                    <span class="conv-chip conv-chip--aviso">Falta clasificar</span>
                                @else
                                    <span class="conv-chip conv-chip--ok">Lista para Google Ads</span>
                                @endif
                            </td>
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
                            @foreach ($columnas as $columna)
                                <td data-editable-cell data-field="custom" data-columna-id="{{ $columna->id }}">{{ $conversion->datos_personalizados[$columna->id] ?? '—' }}</td>
                            @endforeach
                            <td>
                                @if ($conversion->esDuplicada())
                                    <form method="POST" action="{{ route('admin.conversiones.desmarcar-duplicada', $conversion) }}">
                                        @csrf
                                        <button type="submit" class="btn btn--secondary btn--sm" title="Devuelve la conversión a la lista">Quitar marca</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('admin.integraciones._paginacion', ['paginator' => $conversiones])
    @endif
@endsection

@section('scripts')
    @vite('resources/js/conversiones.js')
@endsection
