@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/clientes.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">CRM — Clientes</h1>
            <p class="page-header__subtitle">{{ $clientes->count() }} clientes · {{ $activos }} activos</p>
        </div>
        <a href="{{ route('admin.clientes.create') }}" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i> Nuevo Cliente
        </a>
    </div>

    <div class="filters-bar">
        <input type="search" class="input input--search" id="clientSearch" placeholder="Buscar empresa o contacto...">
        <select class="select" id="clientEstadoFilter">
            <option value="all">Todos los estados</option>
            <option value="activo">Activo</option>
            <option value="pausado">Pausado</option>
            <option value="cancelado">Cancelado</option>
        </select>
    </div>

    @if ($clientes->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-users"></i></div>
            <p class="empty-state__text">Aún no hay clientes registrados.</p>
            <a href="{{ route('admin.clientes.create') }}" class="btn btn--primary">Agregar el primer cliente</a>
        </div>
    @else
        <x-data-table :headers="['Empresa', 'Contacto', 'Servicios', 'MRR', 'Vencimiento', 'Estado', '']">
            @foreach ($clientes as $cliente)
                <tr class="is-clickable" data-client-row
                    data-search="{{ mb_strtolower($cliente['empresa'].' '.$cliente['contacto_nombre']) }}"
                    data-estado="{{ $cliente['estado'] }}"
                    data-client="{{ json_encode($cliente) }}">
                    <td><div style="font-weight:500">{{ $cliente['empresa'] ?? $cliente['nombre'] }}</div></td>
                    <td>
                        <div>{{ $cliente['contacto_nombre'] ?? '—' }}</div>
                        <div style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $cliente['email'] ?? '—' }}</div>
                    </td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                            @forelse ($cliente['servicios'] as $tipo)
                                <span class="badge badge--primary">{{ \App\Support\Labels::servicioTipo($tipo) }}</span>
                            @empty
                                <span style="color:var(--color-muted-foreground);font-size:var(--text-xs)">Sin servicios</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="u-mono">{{ $cliente['mrr'] > 0 ? '$'.number_format($cliente['mrr']) : '—' }}</td>
                    <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $cliente['fecha_renovacion_contrato'] ?? '—' }}</span></td>
                    <td><x-badge :status="$cliente['estado']" /></td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <a href="{{ route('admin.clientes.show', $cliente['id']) }}" class="btn--icon" title="Ver ficha completa">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                            <a href="{{ route('admin.clientes.edit', $cliente['id']) }}" class="btn--icon" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.clientes.destroy', $cliente['id']) }}"
                                data-confirm="¿Eliminar al cliente &quot;{{ $cliente['empresa'] ?? $cliente['nombre'] }}&quot;? Esta acción no se puede deshacer.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
        <p class="table__empty" id="clientNoResults" hidden>No se encontraron clientes con esos filtros.</p>
    @endif

    <x-modal id="clientModal">
        <x-slot:header>
            <h2 id="clientModalName" style="margin-bottom:6px;"></h2>
            <span id="clientModalBadge"></span>
        </x-slot:header>

        <div class="client-modal__grid">
            <div class="client-modal__field"><i class="fa-solid fa-user"></i><div><div class="client-modal__label">Contacto</div><div id="clientModalContact"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-envelope"></i><div><div class="client-modal__label">Email</div><div id="clientModalEmail"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-phone"></i><div><div class="client-modal__label">Teléfono</div><div id="clientModalPhone"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-credit-card"></i><div><div class="client-modal__label">Forma de pago</div><div id="clientModalPayment"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-calendar"></i><div><div class="client-modal__label">Inicio</div><div id="clientModalStart"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-calendar-check"></i><div><div class="client-modal__label">Vencimiento</div><div id="clientModalEnd"></div></div></div>
        </div>

        <div class="client-modal__section">
            <div class="client-modal__label" style="margin-bottom:8px;">Servicios</div>
            <div id="clientModalServices" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
        </div>

        <div class="client-modal__section">
            <div class="client-modal__label">MRR</div>
            <div class="kpi__value" id="clientModalMrr"></div>
        </div>

        <div class="client-modal__section" id="clientModalNotesWrap">
            <div class="client-modal__label" style="margin-bottom:6px;">Notas internas</div>
            <div class="client-modal__notes" id="clientModalNotes"></div>
        </div>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/clientes.js')
@endsection
