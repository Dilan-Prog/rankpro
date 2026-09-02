@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/servicios.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Gestión de Servicios</h1>
            <p class="page-header__subtitle" id="serviciosCountSubtitle">{{ $activos }} servicios activos</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-servicio-modal>
            <i class="fa-solid fa-plus"></i> Asignar Servicio
        </button>
    </div>

    <div class="tabs" id="servicioTabs">
        <button type="button" class="tabs__item is-active" data-tipo="all">Todos</button>
        @foreach (\App\Enums\TipoServicio::cases() as $case)
            <button type="button" class="tabs__item" data-tipo="{{ $case->value }}">{{ \App\Support\Labels::servicioTipo($case->value) }}</button>
        @endforeach
    </div>

    @if ($servicios->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-briefcase"></i></div>
            <p class="empty-state__text">Aún no hay servicios asignados.</p>
            <button type="button" class="btn btn--primary" data-open-servicio-modal>Asignar el primer servicio</button>
        </div>
    @else
        <x-data-table :headers="['Cliente', 'Servicio', 'Tipo', 'Estado', 'Inicio', 'Precio Mensual', '']" data-paginate="15">
            @foreach ($servicios as $servicio)
                <tr class="is-clickable" data-servicio-row data-servicio-row-id="{{ $servicio['id'] }}"
                    data-tipo="{{ $servicio['tipo'] }}" data-estado="{{ $servicio['estado'] }}"
                    data-servicio="{{ json_encode($servicio) }}">
                    <td><div style="font-weight:500">{{ $servicio['cliente'] }}</div></td>
                    <td>{{ $servicio['nombre'] }}</td>
                    <td>{{ \App\Support\Labels::servicioTipo($servicio['tipo']) }}</td>
                    <td><x-badge :status="$servicio['estado']" /></td>
                    <td><span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $servicio['fecha_inicio'] ?? '—' }}</span></td>
                    <td class="u-mono">{{ $servicio['precio_mensual'] > 0 ? '$'.number_format($servicio['precio_mensual']) : '—' }}</td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <button type="button" class="btn--icon" title="Editar" data-edit-servicio="{{ $servicio['id'] }}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-servicio="{{ $servicio['id'] }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
        <p class="table__empty" id="servicioNoResults" hidden>No hay servicios de este tipo.</p>
    @endif

    <x-modal id="servicioDetailModal">
        <x-slot:header>
            <h2 id="servicioDetailTitle" style="margin-bottom:6px;"></h2>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:var(--text-xs);color:var(--color-muted-foreground);">Servicio contratado</span>
                <span id="servicioDetailBadge"></span>
            </div>
        </x-slot:header>

        <div class="record-modal__stats">
            <div>
                <div class="record-modal__section-label">MRR</div>
                <div id="servicioDetailMrr"></div>
            </div>
            <div>
                <div class="record-modal__section-label">Anualizado</div>
                <div id="servicioDetailAnualizado"></div>
            </div>
            <div>
                <div class="record-modal__section-label">Meses activos</div>
                <div id="servicioDetailMeses"></div>
            </div>
            <div>
                <div class="record-modal__section-label">Estado</div>
                <div id="servicioDetailEstadoLabel"></div>
            </div>
        </div>

        <div class="record-modal__section">
            <div class="record-modal__section-label">Datos</div>
            <div class="form-grid form-grid--2">
                <div>
                    <div class="record-modal__section-label">Cliente</div>
                    <div id="servicioDetailCliente"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Servicio</div>
                    <div id="servicioDetailNombre"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Inicio</div>
                    <div id="servicioDetailInicio"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Responsable</div>
                    <div id="servicioDetailResponsable"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Facturación</div>
                    <div>Mensual · MXN</div>
                </div>
                <div>
                    <div class="record-modal__section-label">Ingreso acumulado</div>
                    <div id="servicioDetailIngresoAcumulado"></div>
                </div>
            </div>
        </div>

        <div class="record-modal__section">
            <div class="record-modal__section-label">Historial</div>
            <div class="record-modal__timeline" id="servicioDetailTimeline"></div>
        </div>

        <div class="record-modal__actions">
            <a id="servicioDetailContrato" href="#" class="btn btn--secondary">Ver contrato</a>
            <button type="button" id="servicioDetailEditar" class="btn btn--primary">Editar servicio</button>
        </div>
    </x-modal>

    <x-modal id="servicioFormModal">
        <x-slot:header>
            <h2 id="servicioFormModalTitle" style="margin-bottom:0;">Asignar Servicio</h2>
        </x-slot:header>
        <form id="servicioForm" novalidate
              data-store-action="{{ route('admin.servicios.store') }}"
              data-update-action-template="{{ route('admin.servicios.update', ['servicio' => '__ID__']) }}">
            @csrf
            @include('admin.servicios._form', ['servicio' => null, 'clientes' => $clientes, 'usuarios' => $usuarios])
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="servicioFormSubmit"><i class="fa-solid fa-check"></i> Crear Servicio</button>
                <button type="button" class="btn btn--secondary" data-modal-close="servicioFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="servicioDeleteModal">
        <x-slot:header><h2 style="margin-bottom:0;">Eliminar servicio</h2></x-slot:header>
        <p style="margin-bottom:var(--space-4);">¿Eliminar el servicio <strong id="servicioDeleteName"></strong>? Esta acción no se puede deshacer.</p>
        <div class="record-modal__stats">
            <div>
                <div class="record-modal__section-label">MRR que se libera</div>
                <div id="servicioDeleteMrr"></div>
            </div>
            <div>
                <div class="record-modal__section-label">Responsable</div>
                <div id="servicioDeleteResponsable"></div>
            </div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn--danger" id="servicioDeleteConfirm" data-destroy-action-template="{{ url('/admin/servicios') }}/__ID__">Eliminar</button>
            <button type="button" class="btn btn--secondary" data-modal-close="servicioDeleteModal">Cancelar</button>
        </div>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/servicios.js')
@endsection
