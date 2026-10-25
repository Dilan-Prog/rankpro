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

    {{-- Modal propio (no <x-modal>) para no tocar el componente compartido: mismas clases base, cierre global por [data-modal-close]/Esc. --}}
    <div class="modal-overlay" id="servicioDetailModal" hidden>
        <div class="modal srv-modal" role="dialog" aria-modal="true" aria-labelledby="servicioDetailTitle">
            <header class="srv-modal__cabecera">
                <div class="srv-modal__titulos">
                    <h2 class="srv-modal__titulo" id="servicioDetailTitle"></h2>
                    <p class="srv-modal__subtitulo" id="servicioDetailSubtitulo"></p>
                    <span id="servicioDetailBadge"></span>
                </div>
                <button type="button" class="srv-modal__cerrar" data-modal-close="servicioDetailModal" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>

            <div class="srv-modal__cuerpo">
                <section class="srv-seccion" aria-labelledby="srvSecContratado">
                    <h3 class="srv-seccion__titulo srv-seccion__titulo--acento" id="srvSecContratado">Servicio contratado</h3>
                    <div class="srv-metricas">
                        <div class="srv-metrica">
                            <span class="srv-metrica__etiqueta">MRR</span>
                            <span class="srv-metrica__valor srv-metrica__valor--acento" id="servicioDetailMrr"></span>
                        </div>
                        <div class="srv-metrica">
                            <span class="srv-metrica__etiqueta">Anualizado</span>
                            <span class="srv-metrica__valor" id="servicioDetailAnualizado"></span>
                        </div>
                        <div class="srv-metrica">
                            <span class="srv-metrica__etiqueta">Meses activos</span>
                            <span class="srv-metrica__valor" id="servicioDetailMeses"></span>
                        </div>
                        <div class="srv-metrica">
                            <span class="srv-metrica__etiqueta">Estado</span>
                            <span class="srv-metrica__valor" id="servicioDetailEstadoLabel"></span>
                        </div>
                    </div>
                </section>

                <section class="srv-seccion" aria-labelledby="srvSecFicha">
                    <h3 class="srv-seccion__titulo" id="srvSecFicha">Ficha del registro</h3>
                    <dl class="srv-ficha">
                        <div class="srv-ficha__fila"><dt>Cliente</dt><dd id="servicioDetailCliente"></dd></div>
                        <div class="srv-ficha__fila"><dt>Servicio</dt><dd id="servicioDetailNombre"></dd></div>
                        <div class="srv-ficha__fila"><dt>Inicio</dt><dd class="srv-mono" id="servicioDetailInicio"></dd></div>
                        <div class="srv-ficha__fila"><dt>Responsable</dt><dd id="servicioDetailResponsable"></dd></div>
                        <div class="srv-ficha__fila"><dt>Facturación</dt><dd>Mensual · MXN</dd></div>
                        <div class="srv-ficha__fila"><dt>Ingreso acumulado</dt><dd class="srv-mono srv-acento" id="servicioDetailIngresoAcumulado"></dd></div>
                    </dl>
                </section>

                <section class="srv-seccion" aria-labelledby="srvSecBitacora">
                    <h3 class="srv-seccion__titulo" id="srvSecBitacora">Bitácora</h3>
                    <ul class="srv-bitacora" id="servicioDetailTimeline"></ul>
                </section>
            </div>

            <footer class="srv-modal__pie">
                <button type="button" class="srv-btn srv-btn--secundario" data-modal-close="servicioDetailModal">Cerrar</button>
                <a id="servicioDetailContrato" href="#" class="srv-btn srv-btn--secundario">Ver contrato</a>
                <button type="button" id="servicioDetailEditar" class="srv-btn srv-btn--primario">Editar servicio</button>
            </footer>
        </div>
    </div>

    <div class="modal-overlay" id="servicioFormModal" hidden>
        <div class="modal srv-modal srv-modal--form" role="dialog" aria-modal="true" aria-labelledby="servicioFormModalTitle">
            <form id="servicioForm" class="srv-modal__formulario" novalidate
                  data-store-action="{{ route('admin.servicios.store') }}"
                  data-update-action-template="{{ route('admin.servicios.update', ['servicio' => '__ID__']) }}">
                @csrf
                <header class="srv-modal__cabecera">
                    <div class="srv-modal__titulos">
                        <h2 class="srv-modal__titulo" id="servicioFormModalTitle">Asignar Servicio</h2>
                        <p class="srv-modal__subtitulo" id="servicioFormModalSubtitulo">Alta de contrato con un cliente</p>
                    </div>
                    <button type="button" class="srv-modal__cerrar" data-modal-close="servicioFormModal" aria-label="Cerrar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="srv-modal__cuerpo srv-form">
                    @include('admin.servicios._form', ['servicio' => null, 'clientes' => $clientes, 'usuarios' => $usuarios])
                </div>
                <footer class="srv-modal__pie">
                    <button type="button" class="srv-btn srv-btn--secundario" data-modal-close="servicioFormModal">Cancelar</button>
                    <button type="submit" class="srv-btn srv-btn--primario" id="servicioFormSubmit">Crear servicio</button>
                </footer>
            </form>
        </div>
    </div>

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
