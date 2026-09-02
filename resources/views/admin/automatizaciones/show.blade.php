@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/automatizaciones.css')
@endsection

@section('content')
    @php
        $fases = ['diagnostico', 'diseno_flujo', 'implementacion', 'reporte'];
        $ordenActual = $proyecto->fase_actual->orden();
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $proyecto->nombre }}</h1>
            <p class="page-header__subtitle">{{ $proyecto->cliente->nombre }}</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.automatizaciones.edit', $proyecto) }}" class="btn btn--secondary">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="{{ route('admin.automatizaciones.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a Automatizaciones
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="form-status form-status--error"><i class="fa-solid fa-triangle-exclamation" style="margin-top:2px"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <div class="kpi-grid">
        <x-stat-card label="Fase Actual" value="{{ \App\Support\Labels::faseAutomatizacion($proyecto->fase_actual->value) }}" icon="fa-diagram-project" color="primary" />
        <x-stat-card label="Ciclo" value="{{ $proyecto->ciclo_actual }}" icon="fa-arrows-rotate" color="teal" />
        <x-stat-card label="Flujos Activos" value="{{ $proyecto->flujos->where('estado', 'activo')->count() }}" icon="fa-diagram-project" color="amber" />
        <x-stat-card label="Horas Ahorradas" value="{{ $proyecto->reporteActual->horas_ahorradas_mes ?? '—' }}" sub="al mes" icon="fa-clock" color="emerald" />
    </div>

    {{-- ---------- Rastreador visual de fases ---------- --}}
    <div class="fase-tracker">
        @foreach ($fases as $fase)
            @php
                $ordenFase = \App\Enums\FaseAutomatizacion::from($fase)->orden();
                $estadoPaso = $ordenFase < $ordenActual ? 'done' : ($ordenFase === $ordenActual ? 'current' : 'locked');
            @endphp
            <div class="fase-tracker__step fase-tracker__step--{{ $estadoPaso }}">
                <div class="fase-tracker__circle">
                    @if ($estadoPaso === 'done')
                        <i class="fa-solid fa-check"></i>
                    @else
                        {{ $ordenFase }}
                    @endif
                </div>
                <div class="fase-tracker__label">{{ \App\Support\Labels::faseAutomatizacion($fase) }}</div>
            </div>
            @if (! $loop->last)
                <div class="fase-tracker__line fase-tracker__line--{{ $ordenFase < $ordenActual ? 'done' : 'locked' }}"></div>
            @endif
        @endforeach
    </div>

    @if ($proyecto->fase_actual->value === 'cerrada')
        <div class="card card--padded fase-panel">
            <div class="fase-panel__header">
                <h2 class="card__header-title"><i class="fa-solid fa-flag-checkered"></i> Proyecto Cerrado</h2>
            </div>
            <p style="color:var(--color-muted-foreground); font-size:var(--text-sm);">
                Este proyecto se cerró
                @if ($proyecto->reporteActual?->fecha_aprobacion) el {{ $proyecto->reporteActual->fecha_aprobacion->format('Y-m-d') }} @endif
                después de {{ $proyecto->ciclo_actual }} {{ $proyecto->ciclo_actual === 1 ? 'ciclo' : 'ciclos' }}.
            </p>
            @if ($proyecto->reporteActual?->satisfaccion_cliente)
                <p style="margin-top: var(--space-2); font-size:var(--text-sm);">
                    Satisfacción del cliente:
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="fa-solid fa-star" style="color:{{ $i <= $proyecto->reporteActual->satisfaccion_cliente ? 'var(--text-warning)' : 'var(--color-border)' }}"></i>
                    @endfor
                </p>
            @endif
        </div>
    @else
        @include('admin.automatizaciones._fase-' . $proyecto->fase_actual->value)
    @endif

    {{-- ---------- Flujos automatizados: visibles desde Implementación en adelante ---------- --}}
    @if (in_array($proyecto->fase_actual->value, ['implementacion', 'reporte', 'cerrada']))
        <div class="card" style="margin-top: var(--space-6);" id="flujosCard">
            <div class="card__header">
                <h2 class="card__header-title">Flujos Automatizados</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('flujoModal')">
                    <i class="fa-solid fa-plus"></i> Agregar Flujo
                </button>
            </div>
            <p class="field__hint" style="padding: 0 var(--space-5); margin-top:-4px; margin-bottom: var(--space-3);">
                Registro de los flujos construidos en n8n — este sistema no ejecuta las automatizaciones, solo lleva el control de qué se hizo y su impacto.
            </p>
            <div data-flujos-body>
                @include('admin.automatizaciones._flujos-tabla', ['flujos' => $proyecto->flujos])
            </div>
        </div>
    @endif

    {{-- ---------- Historial de reportes por ciclo ---------- --}}
    @if ($proyecto->reportes->where('aprobado', true)->isNotEmpty())
        <div class="card" style="margin-top: var(--space-6);">
            <div class="card__header">
                <h2 class="card__header-title">Historial de Reportes</h2>
            </div>
            <x-data-table :headers="['Ciclo', 'Flujos Activos', 'Horas Ahorradas/Mes', 'Mensajes Gestionados/Mes', '¿Continúa?', 'Aprobado']">
                @foreach ($proyecto->reportes->where('aprobado', true) as $r)
                    <tr>
                        <td class="u-mono">#{{ $r->ciclo }}</td>
                        <td class="u-mono">{{ $r->flujos_activos_total ?? 0 }}</td>
                        <td class="u-mono">{{ $r->horas_ahorradas_mes ?? '—' }}</td>
                        <td class="u-mono">{{ number_format($r->mensajes_gestionados_mes ?? 0) }}</td>
                        <td>{{ $r->continua_proyecto ? 'Sí' : 'No' }}</td>
                        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $r->fecha_aprobacion?->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    @endif

    {{-- ---------- Modal de flujos ---------- --}}
    <x-modal id="flujoModal">
        <x-slot:header><h2 data-flujo-modal-title>Agregar Flujo</h2></x-slot:header>
        <form id="flujoForm"
            data-store-action="{{ route('admin.automatizaciones.flujos.store', $proyecto) }}"
            data-update-action-template="{{ route('admin.automatizaciones.flujos.update', ['flujo' => '__ID__']) }}">
            <div class="field">
                <label class="field__label" for="fl_nombre">Nombre del flujo</label>
                <input class="input" type="text" name="nombre" id="fl_nombre" required placeholder="Ej. Confirmación de citas por WhatsApp">
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="fl_tipo">Tipo</label>
                    <select class="select" name="tipo" id="fl_tipo" required>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="crm">CRM</option>
                        <option value="email">Email</option>
                        <option value="notificaciones">Notificaciones</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="fl_complejidad">Complejidad</label>
                    <select class="select" name="complejidad" id="fl_complejidad" required>
                        <option value="basico">Básico</option>
                        <option value="intermedio">Intermedio</option>
                        <option value="avanzado">Avanzado</option>
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="fl_integraciones">Integraciones (separadas por coma)</label>
                <input class="input" type="text" name="integraciones_texto" id="fl_integraciones" placeholder="WhatsApp Business, HubSpot, Google Sheets">
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="fl_horas">Horas ahorradas / mes</label>
                    <input class="input" type="number" step="0.5" min="0" name="horas_ahorradas_mes" id="fl_horas">
                </div>
                <div class="field">
                    <label class="field__label" for="fl_mensajes">Mensajes gestionados / mes</label>
                    <input class="input" type="number" min="0" name="mensajes_gestionados_mes" id="fl_mensajes">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="fl_estado">Estado</label>
                    <select class="select" name="estado" id="fl_estado" required>
                        <option value="activo">Activo</option>
                        <option value="pausado">Pausado</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="fl_fecha">Fecha implementado</label>
                    <input class="input" type="date" name="fecha_implementado" id="fl_fecha">
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="fl_notas">Notas</label>
                <textarea class="textarea" name="notas" id="fl_notas" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-flujo-submit-label>Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="flujoModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/automatizaciones.js')
@endsection
