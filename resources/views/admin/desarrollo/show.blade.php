@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/desarrollo.css')
@endsection

@section('content')
    @php
        $fases = ['planeacion', 'organizacion', 'direccion', 'control'];
        $ordenActual = $proyecto->fase_actual->orden();
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $proyecto->nombre }}</h1>
            <p class="page-header__subtitle">{{ $proyecto->cliente->nombre }} · {{ \App\Support\Labels::tipoProyecto($proyecto->tipo) }}</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.desarrollo.edit', $proyecto) }}" class="btn btn--secondary">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="{{ route('admin.desarrollo.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a Desarrollo
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
        <x-stat-card label="Fase Actual" value="{{ \App\Support\Labels::faseProyecto($proyecto->fase_actual->value) }}" icon="fa-diagram-project" color="primary" />
        <x-stat-card label="Avance" value="{{ $proyecto->porcentaje_avance }}%" icon="fa-chart-line" color="teal" />
        <x-stat-card label="Presupuesto" value="${{ number_format($proyecto->presupuesto) }}" icon="fa-dollar-sign" color="amber" />
        <x-stat-card label="Cobrado" value="${{ number_format($proyecto->pagos_recibidos) }}" icon="fa-circle-check" color="emerald" />
    </div>

    {{-- ---------- Rastreador visual de fases (Proceso Administrativo) ---------- --}}
    <div class="fase-tracker">
        @foreach ($fases as $fase)
            @php
                $ordenFase = \App\Enums\FaseProyecto::from($fase)->orden();
                $estadoPaso = $ordenFase < $ordenActual || $proyecto->fase_actual->value === 'cerrado'
                    ? 'done'
                    : ($ordenFase === $ordenActual ? 'current' : 'locked');
            @endphp
            <div class="fase-tracker__step fase-tracker__step--{{ $estadoPaso }}">
                <div class="fase-tracker__circle">
                    @if ($estadoPaso === 'done')
                        <i class="fa-solid fa-check"></i>
                    @else
                        {{ $ordenFase }}
                    @endif
                </div>
                <div class="fase-tracker__label">{{ \App\Support\Labels::faseProyecto($fase) }}</div>
            </div>
            @if (! $loop->last)
                <div class="fase-tracker__line fase-tracker__line--{{ $ordenFase < $ordenActual || $proyecto->fase_actual->value === 'cerrado' ? 'done' : 'locked' }}"></div>
            @endif
        @endforeach
    </div>

    @if ($proyecto->fase_actual->value === 'cerrado')
        <div class="card card--padded fase-panel">
            <div class="fase-panel__header">
                <h2 class="card__header-title"><i class="fa-solid fa-flag-checkered"></i> Proyecto Cerrado</h2>
            </div>
            <p style="color:var(--color-muted-foreground); font-size:var(--text-sm);">
                Este proyecto completó las 4 fases del proceso administrativo y quedó cerrado
                @if ($proyecto->control?->fecha_aprobacion) el {{ $proyecto->control->fecha_aprobacion->format('Y-m-d') }} @endif.
            </p>
            @if ($proyecto->control?->satisfaccion_cliente)
                <p style="margin-top: var(--space-2); font-size:var(--text-sm);">
                    Satisfacción del cliente:
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="fa-solid fa-star" style="color:{{ $i <= $proyecto->control->satisfaccion_cliente ? 'var(--text-warning)' : 'var(--color-border)' }}"></i>
                    @endfor
                </p>
            @endif
            <form method="POST" action="{{ route('admin.desarrollo.fase.retroceder', $proyecto) }}" style="margin-top: var(--space-4);">
                @csrf
                <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-rotate-left"></i> Reabrir fase de Control</button>
            </form>
        </div>
    @else
        @include('admin.desarrollo._fase-' . $proyecto->fase_actual->value)
    @endif

    {{-- ---------- Tareas: visibles desde Dirección en adelante ---------- --}}
    @if (in_array($proyecto->fase_actual->value, ['direccion', 'control', 'cerrado']))
        <div class="card" style="margin-top: var(--space-6);" id="tareasCard">
            <div class="card__header">
                <h2 class="card__header-title">Tareas</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('tareaModal')">
                    <i class="fa-solid fa-plus"></i> Agregar Tarea
                </button>
            </div>
            <div data-tareas-body>
                @include('admin.desarrollo._tareas-tabla', ['tareas' => $proyecto->tareas])
            </div>
        </div>
    @endif

    {{-- ---------- Bugs + QA: visibles solo en Control ---------- --}}
    @if (in_array($proyecto->fase_actual->value, ['control', 'cerrado']))
        <div class="card" style="margin-top: var(--space-6);" id="bugsCard">
            <div class="card__header">
                <h2 class="card__header-title">Bugs</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('bugModal')">
                    <i class="fa-solid fa-plus"></i> Reportar Bug
                </button>
            </div>
            <div data-bugs-body>
                @include('admin.desarrollo._bugs-tabla', ['bugs' => $proyecto->bugs])
            </div>
        </div>

        <div class="card" style="margin-top: var(--space-6);" id="qaCard">
            <div class="card__header">
                <h2 class="card__header-title">Pruebas QA</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('qaModal')">
                    <i class="fa-solid fa-plus"></i> Agregar Prueba
                </button>
            </div>
            <div data-qa-body>
                @include('admin.desarrollo._qa-tabla', ['registros' => $proyecto->qa])
            </div>
        </div>
    @endif

    {{-- ---------- Comunicaciones: visibles desde Dirección en adelante ---------- --}}
    @if (in_array($proyecto->fase_actual->value, ['direccion', 'control', 'cerrado']))
        <div class="card" style="margin-top: var(--space-6);" id="comunicacionesCard">
            <div class="card__header">
                <h2 class="card__header-title">Comunicación con Cliente</h2>
                <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('comunicacionModal')">
                    <i class="fa-solid fa-plus"></i> Registrar Comunicación
                </button>
            </div>
            <div data-comunicaciones-body>
                @include('admin.desarrollo._comunicaciones-lista', ['comunicaciones' => $proyecto->comunicaciones])
            </div>
        </div>
    @endif

    {{-- ---------- Modales ---------- --}}
    <x-modal id="tareaModal">
        <x-slot:header><h2 data-tarea-modal-title>Agregar Tarea</h2></x-slot:header>
        <form id="tareaForm"
            data-store-action="{{ route('admin.desarrollo.tareas.store', $proyecto) }}"
            data-update-action-template="{{ route('admin.desarrollo.tareas.update', ['tarea' => '__ID__']) }}">
            <div class="field">
                <label class="field__label" for="t_titulo">Título</label>
                <input class="input" type="text" name="titulo" id="t_titulo" required>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="t_descripcion">Descripción</label>
                <textarea class="textarea" name="descripcion" id="t_descripcion"></textarea>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="t_responsable">Responsable</label>
                <input class="input" type="text" name="responsable" id="t_responsable">
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="t_prioridad">Prioridad</label>
                    <select class="select" name="prioridad" id="t_prioridad" required>
                        <option value="alta">Alta</option>
                        <option value="media" selected>Media</option>
                        <option value="baja">Baja</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="t_estado">Estado</label>
                    <select class="select" name="estado" id="t_estado" required>
                        <option value="pendiente">Pendiente</option>
                        <option value="en_progreso">En Progreso</option>
                        <option value="completada">Completada</option>
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="t_fecha">Fecha límite</label>
                <input class="input" type="date" name="fecha_limite" id="t_fecha">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-tarea-submit-label>Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="tareaModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="bugModal">
        <x-slot:header><h2 data-bug-modal-title>Reportar Bug</h2></x-slot:header>
        <form id="bugForm"
            data-store-action="{{ route('admin.desarrollo.bugs.store', $proyecto) }}"
            data-update-action-template="{{ route('admin.desarrollo.bugs.update', ['bug' => '__ID__']) }}">
            <div class="field">
                <label class="field__label" for="b_titulo">Título</label>
                <input class="input" type="text" name="titulo" id="b_titulo" required>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="b_descripcion">Descripción</label>
                <textarea class="textarea" name="descripcion" id="b_descripcion"></textarea>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="b_prioridad">Prioridad</label>
                    <select class="select" name="prioridad" id="b_prioridad" required>
                        <option value="alta">Alta</option>
                        <option value="media" selected>Media</option>
                        <option value="baja">Baja</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="b_estado">Estado</label>
                    <select class="select" name="estado" id="b_estado" required>
                        <option value="abierto">Abierto</option>
                        <option value="en_progreso">En Progreso</option>
                        <option value="resuelto">Resuelto</option>
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="b_fecha">Fecha de resolución (si aplica)</label>
                <input class="input" type="date" name="fecha_resolucion" id="b_fecha">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" data-bug-submit-label>Reportar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="bugModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="qaModal">
        <x-slot:header><h2>Agregar Prueba QA</h2></x-slot:header>
        <form id="qaForm" data-action="{{ route('admin.desarrollo.qa.store', $proyecto) }}">
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="q_tipo">Tipo de prueba</label>
                    <select class="select" name="tipo_prueba" id="q_tipo" required>
                        <option value="funcional">Funcional</option>
                        <option value="visual">Visual</option>
                        <option value="rendimiento">Rendimiento</option>
                        <option value="seguridad">Seguridad</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="q_resultado">Resultado</label>
                    <select class="select" name="resultado" id="q_resultado" required>
                        <option value="aprobado">Aprobado</option>
                        <option value="fallido">Fallido</option>
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="q_notas">Notas</label>
                <textarea class="textarea" name="notas" id="q_notas"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="qaModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="comunicacionModal">
        <x-slot:header><h2>Registrar Comunicación</h2></x-slot:header>
        <form id="comunicacionForm" data-action="{{ route('admin.desarrollo.comunicaciones.store', $proyecto) }}">
            <div class="field">
                <label class="field__label" for="c_fecha">Fecha de reunión/actualización</label>
                <input class="input" type="date" name="fecha" id="c_fecha" value="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="c_resumen">Resumen de lo comunicado</label>
                <textarea class="textarea" name="resumen" id="c_resumen" required></textarea>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="c_aprobaciones">Aprobaciones del cliente</label>
                <textarea class="textarea" name="aprobaciones" id="c_aprobaciones"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Registrar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="comunicacionModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/desarrollo.js')
@endsection
