@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/clientes.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">CRM — Clientes</h1>
            <p class="page-header__subtitle" id="clientesCountSubtitle">{{ $clientes->count() }} clientes · {{ $activos }} activos</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-client-modal>
            <i class="fa-solid fa-plus"></i> Nuevo Cliente
        </button>
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
            <button type="button" class="btn btn--primary" data-open-client-modal>Agregar el primer cliente</button>
        </div>
    @else
        <x-data-table :headers="['Empresa', 'Contacto', 'Servicios', 'MRR', 'Vencimiento', 'Estado', '']" data-paginate="15">
            @foreach ($clientes as $cliente)
                <tr class="is-clickable" data-client-row data-client-row-id="{{ $cliente['id'] }}"
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
                            <a href="{{ route('admin.clientes.integraciones', $cliente['id']) }}" class="btn--icon" title="Integraciones y tracking">
                                <i class="fa-solid fa-plug"></i>
                            </a>
                            <button type="button" class="btn--icon" title="Editar" data-edit-client="{{ $cliente['id'] }}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-client="{{ $cliente['id'] }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
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

        <div class="record-modal__stats">
            <div class="client-modal__field"><i class="fa-solid fa-user"></i><div><div class="client-modal__label">Contacto</div><div id="clientModalContact"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-envelope"></i><div><div class="client-modal__label">Email</div><div id="clientModalEmail"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-phone"></i><div><div class="client-modal__label">Teléfono</div><div id="clientModalPhone"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-credit-card"></i><div><div class="client-modal__label">Forma de pago</div><div id="clientModalPayment"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-calendar"></i><div><div class="client-modal__label">Inicio</div><div id="clientModalStart"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-calendar-check"></i><div><div class="client-modal__label">Vencimiento</div><div id="clientModalEnd"></div></div></div>
        </div>

        <div class="record-modal__section">
            <div class="record-modal__section-label">Servicios</div>
            <div class="record-modal__tags" id="clientModalServices"></div>
        </div>

        <div class="record-modal__section">
            <div class="record-modal__section-label">MRR</div>
            <div class="kpi__value" id="clientModalMrr"></div>
        </div>

        <div class="record-modal__section" id="clientModalNotesWrap">
            <div class="record-modal__section-label">Notas internas</div>
            <div class="client-modal__notes" id="clientModalNotes"></div>
        </div>
    </x-modal>

    <x-modal id="clientFormModal">
        <x-slot:header>
            <h2 id="clientFormModalTitle" style="margin-bottom:0;">Nuevo Cliente</h2>
        </x-slot:header>
        <form id="clientForm" novalidate
              data-store-action="{{ route('admin.clientes.store') }}"
              data-update-action-template="{{ route('admin.clientes.update', ['cliente' => '__ID__']) }}">
            @csrf
            @include('admin.clientes._form', ['cliente' => null])
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="clientFormSubmit"><i class="fa-solid fa-check"></i> Crear Cliente</button>
                <button type="button" class="btn btn--secondary" data-modal-close="clientFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="clientDeleteModal">
        <x-slot:header><h2 style="margin-bottom:0;">Eliminar cliente</h2></x-slot:header>
        <p style="margin-bottom:var(--space-4);">¿Eliminar a <strong id="clientDeleteName"></strong>? Esta acción no se puede deshacer.</p>
        <div class="record-modal__stats">
            <div class="client-modal__field"><i class="fa-solid fa-dollar-sign"></i><div><div class="client-modal__label">MRR</div><div id="clientDeleteMrr"></div></div></div>
            <div class="client-modal__field"><i class="fa-solid fa-calendar-check"></i><div><div class="client-modal__label">Vencimiento</div><div id="clientDeleteEnd"></div></div></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn--danger" id="clientDeleteConfirm" data-destroy-action-template="{{ url('/admin/clientes') }}/__ID__">Eliminar</button>
            <button type="button" class="btn btn--secondary" data-modal-close="clientDeleteModal">Cancelar</button>
        </div>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/clientes.js')
@endsection
