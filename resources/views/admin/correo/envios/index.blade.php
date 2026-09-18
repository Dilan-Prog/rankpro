@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/correo-envios.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Enviar correo</h1>
            <p class="page-header__subtitle">Historial y programados · un correo puede ir a varias personas a la vez</p>
        </div>
        <div class="correo-header-actions">
            <a href="{{ route('admin.correo.plantillas.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-layer-group"></i> Plantillas
            </a>
            <a href="{{ route('admin.correo.envios.create') }}" class="btn btn--primary">
                <i class="fa-solid fa-pen-to-square"></i> Redactar correo
            </a>
        </div>
    </div>

    {{-- ---------- KPIs del periodo elegido ---------- --}}
    {{-- Pills GET (sin JS): conservan estado/search del listado y solo cambian `periodo`. --}}
    <nav class="correo-periodos" aria-label="Periodo de las métricas">
        @foreach ($periodos as $clave => $label)
            <a href="{{ route('admin.correo.envios.index', array_merge(request()->query(), ['periodo' => $clave])) }}"
               class="correo-periodos__chip {{ $kpis['periodo'] === $clave ? 'is-active' : '' }}"
               @if ($kpis['periodo'] === $clave) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="kpi-grid">
        <x-stat-card label="Tasa de apertura" value="{{ $kpis['apertura'] !== null ? $kpis['apertura'].'%' : '—' }}" sub="Estimada por píxel de seguimiento" icon="fa-envelope-open" color="emerald" />
        <x-stat-card label="Abiertos" value="{{ number_format($kpis['abiertos']) }}" sub="Con al menos una apertura" icon="fa-envelope-open-text" color="blue" />
        <x-stat-card label="No abiertos" value="{{ number_format($kpis['no_abiertos']) }}" sub="Enviados sin apertura registrada" icon="fa-envelope" color="amber" />
        <x-stat-card label="Clics" value="{{ number_format($kpis['clics']) }}" sub="En enlaces del correo" icon="fa-mouse-pointer" color="teal" />
        <x-stat-card label="Enviados en el periodo" value="{{ number_format($kpis['enviados']) }}" sub="{{ $kpis['programados'] }} programados en cola" icon="fa-paper-plane" color="primary" />
    </div>

    <form method="GET" action="{{ route('admin.correo.envios.index') }}" class="filters-bar" data-correo-envios-index>
        <input type="search" class="input input--search" name="search" value="{{ $filtros['search'] }}" placeholder="Buscar por asunto, plantilla o destinatario…" data-filtro-search>
        <select class="select" name="estado" data-filtro-estado>
            <option value="">Todos los estados</option>
            @foreach (\App\Enums\EstadoEnvioCorreo::cases() as $estado)
                <option value="{{ $estado->value }}" @selected($filtros['estado'] === $estado->value)>{{ $estado->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
        @if ($filtros['search'] !== '' || $filtros['estado'] !== '')
            <a href="{{ route('admin.correo.envios.index') }}" class="btn btn--ghost btn--sm">Limpiar</a>
        @endif
    </form>

    <div class="card empty-state" {{ $envios->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-paper-plane"></i></div>
        <p class="empty-state__text">
            @if ($filtros['search'] !== '' || $filtros['estado'] !== '')
                No hay envíos con esos filtros.
            @else
                Aún no hay envíos. Redacta el primero a partir de una plantilla.
            @endif
        </p>
        <a href="{{ route('admin.correo.envios.create') }}" class="btn btn--primary">Redactar correo</a>
    </div>

    <x-data-table :headers="['Asunto', 'Plantilla', 'Destinatarios', 'Estado', 'Fecha', 'Apertura', 'Clics', 'Responsable', '']"
                  data-paginate="15" data-envios-tabla :hidden="$envios->isEmpty()"
                  data-destroy-url="{{ route('admin.correo.envios.destroy', ['envio' => '__ID__']) }}"
                  data-cancelar-url="{{ route('admin.correo.envios.cancelar', ['envio' => '__ID__']) }}">
        @foreach ($envios as $envio)
            <tr data-envio-row="{{ $envio['id'] }}" data-estado="{{ $envio['estado'] }}">
                <td>
                    <a href="{{ $envio['show_url'] }}" class="correo-asunto">
                        <span class="correo-asunto__icon correo-asunto__icon--{{ $envio['estado'] }}">
                            @if ($envio['estado'] === 'programado')
                                <i class="fa-solid fa-clock"></i>
                            @elseif ($envio['estado'] === 'fallido')
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            @elseif ($envio['estado'] === 'borrador')
                                <i class="fa-solid fa-pen"></i>
                            @else
                                <i class="fa-solid fa-envelope"></i>
                            @endif
                        </span>
                        <span class="correo-asunto__texto">{{ $envio['asunto'] }}</span>
                    </a>
                </td>
                <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $envio['plantilla'] ?? '—' }}</span></td>
                <td>
                    <span class="u-mono">{{ $envio['destinatarios'] }}</span>
                    @if ($envio['fallidos'] > 0)
                        <span class="correo-fallidos" title="{{ $envio['fallidos'] }} con error">· {{ $envio['fallidos'] }} fallidos</span>
                    @endif
                </td>
                <td><x-badge :status="$envio['estado']" data-estado-badge /></td>
                <td><span class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $envio['fecha'] ?? '—' }}</span></td>
                <td>
                    @if ($envio['estado'] === 'enviado' && $envio['apertura'] !== null)
                        <span class="u-mono">{{ $envio['apertura'] }}%</span>
                        <span class="correo-est" title="Estimación: algunos clientes de correo bloquean o precargan imágenes">est.</span>
                    @else
                        <span style="color:var(--color-muted-foreground)">—</span>
                    @endif
                </td>
                <td><span class="u-mono">{{ $envio['estado'] === 'enviado' ? $envio['clics'] : '—' }}</span></td>
                <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $envio['responsable'] ?? '—' }}</span></td>
                <td>
                    <div style="display:flex; gap:4px; justify-content:flex-end;">
                        <a href="{{ $envio['show_url'] }}" class="btn--icon" title="Ver detalle"><i class="fa-solid fa-eye"></i></a>
                        @if ($envio['editable'])
                            <a href="{{ route('admin.correo.envios.edit', $envio['id']) }}" class="btn--icon" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        @endif
                        @if ($envio['estado'] === 'programado')
                            <button type="button" class="btn--icon" title="Cancelar programación" style="color:var(--text-warning);" data-cancelar-envio="{{ $envio['id'] }}">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                        @endif
                        @if (in_array($envio['estado'], ['borrador', 'cancelado', 'fallido'], true))
                            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-envio="{{ $envio['id'] }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <p class="correo-nota">
        <i class="fa-solid fa-circle-info"></i>
        La apertura es una estimación: algunos clientes de correo bloquean o precargan imágenes. Los clics sí son exactos.
    </p>
@endsection

@section('scripts')
    @vite('resources/js/correo-envios.js')
@endsection
