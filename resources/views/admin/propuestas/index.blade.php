@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/propuestas.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Propuestas de Continuidad</h1>
            <p class="page-header__subtitle">Ventas · Documentos de cliente</p>
        </div>
        <button type="button" class="btn btn--primary" data-open-propuesta-modal>
            <i class="fa-solid fa-plus"></i> Nueva Propuesta
        </button>
    </div>

    {{-- ---------- KPIs ---------- --}}
    <div class="kpi-grid">
        <x-stat-card label="Total de propuestas" value="{{ $kpis['total'] }}" sub="activas" icon="fa-file-signature" color="primary" />
        <x-stat-card label="En borrador" value="{{ $kpis['borrador'] }}" sub="por enviar" icon="fa-pen-ruler" color="teal" />
        <x-stat-card label="Enviadas" value="{{ $kpis['enviada'] }}" sub="esperando respuesta" icon="fa-paper-plane" color="blue" />
        <x-stat-card label="Tasa de aprobación" value="{{ $kpis['tasa_aprobacion'] !== null ? $kpis['tasa_aprobacion'].'%' : '—' }}" sub="{{ $kpis['aprobadas'] }} de {{ $kpis['decididas'] }} resueltas" icon="fa-chart-line" color="emerald" />
    </div>

    <div class="filters-bar">
        <input type="search" class="input input--search" id="propuestaSearch" placeholder="Buscar por folio, cliente o título…">
        <select class="select" id="propuestaClienteFilter">
            <option value="all">Todos los clientes</option>
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}">{{ $cliente->empresa ?: $cliente->nombre }}</option>
            @endforeach
        </select>
        <select class="select" id="propuestaEstadoFilter">
            <option value="all">Todos los estados</option>
            @foreach (\App\Enums\EstadoPropuesta::cases() as $estado)
                <option value="{{ $estado->value }}">{{ $estado->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="card empty-state" data-propuestas-empty {{ $propuestas->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-file-signature"></i></div>
        <p class="empty-state__text">Aún no hay propuestas. Crea una propuesta de continuidad para justificar la renovación con datos reales.</p>
        <button type="button" class="btn btn--primary" data-open-propuesta-modal>Crear la primera propuesta</button>
    </div>

    <x-data-table :headers="['Folio', 'Cliente', 'Título', 'Precio/mes', 'Estado', 'Emisión', '']"
                  data-paginate="15" data-propuestas-tabla :hidden="$propuestas->isEmpty()"
                  data-propuesta-destroy-url="{{ route('admin.propuestas.destroy', ['propuesta' => '__ID__']) }}">
        @foreach ($propuestas as $propuesta)
            <tr data-propuesta-row
                data-search="{{ mb_strtolower(($propuesta['folio'] ?? '').' '.($propuesta['cliente'] ?? '').' '.$propuesta['titulo']) }}"
                data-cliente="{{ $propuesta['cliente_id'] }}"
                data-estado="{{ $propuesta['estado'] }}">
                <td><span class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $propuesta['folio'] ?? '—' }}</span></td>
                <td><div style="font-weight:600">{{ $propuesta['cliente'] ?? '—' }}</div></td>
                <td><span style="color:var(--color-muted-foreground)">{{ $propuesta['titulo'] }}</span></td>
                <td style="font-weight:600">{{ $propuesta['precio_mensual'] !== null ? '$'.number_format($propuesta['precio_mensual'], 0, '.', ',') : '—' }}</td>
                <td><x-badge :status="$propuesta['estado']" /></td>
                <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $propuesta['fecha_emision'] ?? '—' }}</span></td>
                <td>
                    <div style="display:flex; gap:4px; justify-content:flex-end;">
                        <a href="{{ $propuesta['show_url'] }}" class="btn--icon" title="Abrir propuesta"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-propuesta="{{ $propuesta['id'] }}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
    <p class="table__empty" id="propuestaNoResults" hidden>No se encontraron propuestas con esos filtros.</p>

    {{-- ---------- Nueva Propuesta: cliente obligatorio, campaña real del cliente elegido ---------- --}}
    <x-modal id="propuestaModal">
        <x-slot:header><h2 style="margin-bottom:0;">Nueva Propuesta</h2></x-slot:header>
        <form id="propuestaForm" novalidate
              data-store-action="{{ route('admin.propuestas.store') }}"
              data-campanas-por-cliente="{{ json_encode($campanasPorCliente) }}">
            @csrf
            <p style="font-size:var(--text-xs); color:var(--color-muted-foreground); margin-bottom: var(--space-4);">
                Se creará un borrador y se abrirá el editor.
            </p>
            <div class="field">
                <label class="field__label" for="pf_cliente_id">Cliente <span style="color:var(--text-danger)">*</span></label>
                <select class="select" name="cliente_id" id="pf_cliente_id" required>
                    <option value="">— Selecciona cliente —</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}">{{ $cliente->empresa ?: $cliente->nombre }}</option>
                    @endforeach
                </select>
                <span class="field__error" data-error-for="cliente_id"></span>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="pf_seo_campana_id">Campaña SEO <span style="color:var(--color-muted-foreground); font-weight:500;">(opcional)</span></label>
                <select class="select" name="seo_campana_id" id="pf_seo_campana_id" disabled>
                    <option value="">Elige primero un cliente</option>
                </select>
                <span class="field__error" data-error-for="seo_campana_id"></span>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="pf_titulo">Título</label>
                <input class="input" type="text" name="titulo" id="pf_titulo" value="Propuesta de Continuidad SEO">
                <span class="field__error" data-error-for="titulo"></span>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="propuestaModal">Cancelar</button>
                <button type="submit" class="btn btn--primary" data-propuesta-submit>Crear y editar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/propuestas.js')
@endsection
