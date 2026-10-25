@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/metricas.css')
@endsection

@section('scripts')
    @vite('resources/js/metricas.js')
@endsection

{{--
    Módulo Métricas: captura manual mensual por cliente. Una sola pantalla:
    selector cliente/mes, tabla editable con "Guardar mes" en bloque y modal
    para crear/editar definiciones. El diseño visual se rehace aparte; aquí
    solo hay estructura semántica y data-* para el JS.
--}}
@php
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    $semaforoClase = ['verde' => 'badge--success', 'amarillo' => 'badge--warning', 'rojo' => 'badge--danger', 'sin_meta' => 'badge--neutral', 'sin_dato' => 'badge--neutral'];
    $semaforoTexto = ['verde' => 'Verde', 'amarillo' => 'Amarillo', 'rojo' => 'Rojo', 'sin_meta' => 'Sin meta', 'sin_dato' => 'Sin dato'];
    $mesValor = $mes->format('Y-m');
@endphp

@section('content')
    <div class="metricas" data-metricas>
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Métricas</h1>
                <p class="page-header__subtitle">Captura a mano los números mensuales de cada cliente y compáralos contra su meta.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="form-status"><span>{{ session('status') }}</span></div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status--error"><span>{{ $errors->first() }}</span></div>
        @endif

        <form method="GET" action="{{ route('admin.metricas.index') }}" class="filters-bar" data-metricas-filtro>
            <select class="select" name="cliente" aria-label="Cliente" data-metricas-cliente>
                <option value="">Selecciona un cliente…</option>
                @foreach ($clientes as $c)
                    <option value="{{ $c->id }}" @selected($cliente && $cliente->id === $c->id)>{{ $c->nombre }}</option>
                @endforeach
            </select>
            <input type="month" class="input" name="mes" value="{{ $mesValor }}" aria-label="Mes" data-metricas-mes>
            <button type="submit" class="btn">Ver</button>
        </form>

        @if (! $cliente)
            <p class="field__hint">Elige un cliente para capturar sus métricas.</p>
        @else
            <div class="metricas__acciones">
                <button type="button" class="btn btn--primary" data-metrica-nueva data-cliente-id="{{ $cliente->id }}">Nueva métrica</button>
                <a class="btn" href="{{ route('admin.metricas.index', ['cliente' => $cliente->id, 'mes' => $mesValor, 'copiar' => 1]) }}" data-metricas-copiar>Copiar mes anterior</a>
            </div>

            @if ($copiado)
                <p class="field__hint" data-metricas-aviso-copia>Valores de {{ $mesAnterior->format('Y-m') }} precargados como referencia; no se guardan hasta pulsar "Guardar mes".</p>
            @endif

            <form method="POST" action="{{ route('admin.metricas.valores.store') }}" data-metricas-form>
                @csrf
                <input type="hidden" name="cliente_id" value="{{ $cliente->id }}">
                <input type="hidden" name="mes" value="{{ $mesValor }}">

                <x-data-table :headers="['Métrica', 'Meta', 'Valor de '.$mesValor, 'Semáforo', 'Vs. mes anterior', 'Nota', '']">
                    @forelse ($filas as $fila)
                        @php
                            $m = $fila['metrica'];
                            $unidad = $m->unidad ? ' '.$m->unidad : '';
                        @endphp
                        <tr data-metrica-fila="{{ $m->id }}" data-semaforo="{{ $fila['semaforo'] }}">
                            <td>{{ $m->nombre }} <small>({{ $m->sentido->label() }})</small></td>
                            <td data-col="meta">{{ $m->meta === null ? '—' : $fmt($m->meta).$unidad }}</td>
                            <td>
                                <input type="text" inputmode="decimal" class="input" name="valores[{{ $m->id }}]"
                                       value="{{ old('valores.'.$m->id, $fila['valor_input']) }}" placeholder="—" autocomplete="off"
                                       aria-label="Valor de {{ $m->nombre }}" data-metrica-valor
                                       @if ($fila['precargado']) data-precargado="1" @endif>
                                {{ $m->unidad }}
                            </td>
                            <td data-col="semaforo">
                                <span class="badge {{ $semaforoClase[$fila['semaforo']] }}" data-semaforo-badge>{{ $semaforoTexto[$fila['semaforo']] }}</span>
                            </td>
                            <td data-col="variacion">
                                @if ($fila['variacion'])
                                    {{ $fila['variacion']['abs'] > 0 ? '+' : '' }}{{ $fmt($fila['variacion']['abs']) }}
                                    @if ($fila['variacion']['pct'] !== null)
                                        ({{ $fila['variacion']['pct'] > 0 ? '+' : '' }}{{ $fila['variacion']['pct'] }} %)
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <input type="text" class="input" name="notas[{{ $m->id }}]" value="{{ old('notas.'.$m->id, $fila['nota']) }}"
                                       maxlength="2000" placeholder="Nota" aria-label="Nota de {{ $m->nombre }}">
                            </td>
                            <td><a href="{{ route('admin.metricas.show', ['metrica' => $m->id, 'mes' => $mesValor]) }}">Histórico</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table__empty">Este cliente no tiene métricas activas. Crea una con "Nueva métrica".</td></tr>
                    @endforelse
                </x-data-table>

                @if (count($filas))
                    <button type="submit" class="btn btn--primary" data-metricas-guardar>Guardar mes</button>
                @endif
            </form>

            <h2>Definiciones</h2>
            <x-data-table :headers="['Métrica', 'Unidad', 'Meta', 'Sentido', 'Amarillo desde', 'Estado', 'Acciones']" data-metricas-definiciones>
                @forelse ($definiciones as $d)
                    <tr data-definicion="{{ $d->id }}">
                        <td>{{ $d->nombre }}</td>
                        <td>{{ $d->unidad ?: '—' }}</td>
                        <td>{{ $fmt($d->meta) }}</td>
                        <td>{{ $d->sentido->label() }}</td>
                        <td>{{ $d->umbral_amarillo_pct }} %</td>
                        <td><x-badge :status="$d->activa ? 'activa' : 'pausada'" /></td>
                        <td>
                            <button type="button" class="btn btn--sm" data-metrica-editar
                                    data-id="{{ $d->id }}"
                                    data-url="{{ route('admin.metricas.definiciones.update', $d) }}"
                                    data-nombre="{{ $d->nombre }}"
                                    data-unidad="{{ $d->unidad }}"
                                    data-meta="{{ $d->meta }}"
                                    data-sentido="{{ $d->sentido->value }}"
                                    data-umbral="{{ $d->umbral_amarillo_pct }}"
                                    data-orden="{{ $d->orden }}">Editar</button>
                            <form method="POST" action="{{ route('admin.metricas.definiciones.alternar', $d) }}" class="metricas__inline">
                                @csrf
                                <input type="hidden" name="mes" value="{{ $mesValor }}">
                                <button type="submit" class="btn btn--sm">{{ $d->activa ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.metricas.definiciones.destroy', $d) }}" class="metricas__inline"
                                  data-confirmar="Se eliminará la métrica y todo su histórico. ¿Continuar?">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="mes" value="{{ $mesValor }}">
                                <button type="submit" class="btn btn--sm btn--danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table__empty">Sin definiciones todavía.</td></tr>
                @endforelse
            </x-data-table>

            <x-modal id="metricaModal">
                <x-slot:header><h2 data-metrica-modal-titulo>Nueva métrica</h2></x-slot:header>
                <form method="POST" action="{{ route('admin.metricas.definiciones.store') }}" data-metrica-form
                      data-url-crear="{{ route('admin.metricas.definiciones.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" data-metrica-method disabled>
                    <input type="hidden" name="cliente_id" value="{{ $cliente->id }}">
                    <input type="hidden" name="mes" value="{{ $mesValor }}">

                    <div class="field">
                        <label class="field__label" for="metrica_nombre">Nombre</label>
                        <input type="text" class="input" id="metrica_nombre" name="nombre" maxlength="120" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="metrica_unidad">Unidad</label>
                        <input type="text" class="input" id="metrica_unidad" name="unidad" maxlength="20" placeholder="%, MXN, llamadas…">
                    </div>
                    <div class="field">
                        <label class="field__label" for="metrica_meta">Meta mensual</label>
                        <input type="text" inputmode="decimal" class="input" id="metrica_meta" name="meta" placeholder="Sin meta">
                    </div>
                    <div class="field">
                        <label class="field__label" for="metrica_sentido">Sentido</label>
                        <select class="select" id="metrica_sentido" name="sentido">
                            @foreach (\App\Enums\SentidoMetrica::cases() as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="metrica_umbral">Amarillo desde (% de la meta)</label>
                        <input type="number" class="input" id="metrica_umbral" name="umbral_amarillo_pct" min="1" max="100" value="80" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="metrica_orden">Orden</label>
                        <input type="number" class="input" id="metrica_orden" name="orden" min="0" max="65535" placeholder="Automático">
                    </div>
                    <button type="submit" class="btn btn--primary">Guardar métrica</button>
                </form>
            </x-modal>
        @endif
    </div>
@endsection
