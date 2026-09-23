@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/agenda.css')
@endsection

@section('scripts')
    @vite('resources/js/agenda.js')
@endsection

{{--
    Módulo "Agendar reunión" — panel con 3 pestañas (Agenda semanal, Citas,
    Disponibilidad) y un cajón de detalle (modal) para cada reunión.

    IMPORTANTE — límites reales del backend (ver AgendaController, que esta
    vista NO puede tocar):
    - $reuniones SOLO trae futuras + estado 'confirmada' (máx. 50). No hay
      forma de mostrar canceladas ni histórico completo aquí sin tocar el
      controlador, así que no se ofrece un filtro de estado en "Citas" (no
      tendría nada que filtrar: todo lo que llega ya es "confirmada").
    - Los únicos estados reales son 'confirmada' y 'cancelada' (EstadoReunion).
      "Completada" que se ve aquí es 100% visual: $reunion->inicia_en->isPast(),
      calculado en esta vista, sin guardar nada nuevo. "Pendiente" y "no-show"
      del mockup no existen en los datos reales y no se inventan.
    - No hay "crear cita manual" ni "reagendar": el backend no los soporta.
      El botón "+ Nueva cita" se deja deshabilitado como recordatorio visual
      de que esa función está pendiente de construir, no simulada.
    - El campo "source" (de dónde vino la cita) no existe en la BD: se omite.

    $horarios SIEMPRE trae 7 elementos (uno por dia_semana 0-6, sin huecos),
    así que iteramos con @foreach directo, sin @forelse.
--}}
@php
    $nombresDias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $mesesEs = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    $fechaLarga = function ($carbon) use ($nombresDias, $mesesEs) {
        return $nombresDias[$carbon->dayOfWeek] . ' ' . $carbon->day . ' de ' . $mesesEs[$carbon->month - 1];
    };

    // Mismos colores que resources/views/components/badge.blade.php para
    // los valores de App\Enums\EstadoReunion — el cajón de detalle y la
    // vista semanal se pintan en JS, sin pasar por <x-badge>, así que
    // necesitan la clase ya resuelta en el payload.
    $estadoClaseDe = fn ($estado) => match ($estado) {
        \App\Enums\EstadoReunion::Confirmada, \App\Enums\EstadoReunion::Completada => 'badge--success',
        \App\Enums\EstadoReunion::Pendiente => 'badge--warning',
        \App\Enums\EstadoReunion::NoShow, \App\Enums\EstadoReunion::Cancelada => 'badge--danger',
    };

    // $reuniones trae los últimos 90 días + futuras, cualquier estado (ver
    // AgendaController::index) — las tarjetas de arriba solo cuentan lo que
    // sigue vigente (futuro y no cancelado), no todo el historial.
    $reunionesVigentes = $reuniones->filter(fn ($r) => $r->inicia_en->isFuture() && $r->estado !== \App\Enums\EstadoReunion::Cancelada);
    $inicioSemana = now()->startOfWeek(\Carbon\Carbon::MONDAY);
    $finSemana = now()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $reunionesEstaSemana = $reunionesVigentes->filter(fn ($r) => $r->inicia_en->between($inicioSemana, $finSemana))->count();
    $proximaReunion = $reunionesVigentes->sortBy('inicia_en')->first();
    $totalProximas = $reunionesVigentes->count();

    // Estados posibles a los que se puede pasar desde el cajón de detalle
    // (cancelar tiene su propio botón/ruta de siempre, no va aquí).
    $estadosDisponibles = collect(\App\Enums\EstadoReunion::cases())->reject(fn ($e) => $e === \App\Enums\EstadoReunion::Cancelada);

    // Payload para agenda.js: una fila por reunión con todo lo que la vista
    // semanal y el cajón de detalle necesitan pintar sin volver a pedirle
    // nada al servidor.
    $reunionesJs = $reuniones->map(function ($r) use ($fechaLarga, $estadoClaseDe) {
        return [
            'id' => $r->id,
            'nombre' => $r->nombre,
            'email' => $r->email,
            'telefono' => $r->telefono ?: null,
            'notas' => $r->notas ?: null,
            'cliente' => $r->cliente?->nombre,
            'fecha' => $r->inicia_en->format('Y-m-d'),
            'fechaLarga' => $fechaLarga($r->inicia_en),
            'horaInicio' => $r->inicia_en->format('g:i A'),
            'horaFin' => $r->termina_en->format('g:i A'),
            'horaCorta' => $r->inicia_en->format('g:i A'),
            'estado' => $r->estado->value,
            'estadoLabel' => $r->estado->label(),
            'estadoClase' => $estadoClaseDe($r->estado),
            'cancelada' => $r->estado === \App\Enums\EstadoReunion::Cancelada,
        ];
    })->values();
@endphp

@section('content')
    <div class="agenda-panel" data-agenda-panel data-reuniones='@json($reunionesJs)'>
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Agendar reunión</h1>
                <p class="page-header__subtitle">Configura la disponibilidad de la página pública de agendar y revisa las reuniones que se han reservado.</p>
            </div>
            <button type="button" class="btn btn--primary" data-agenda-nueva-cita>
                <i class="fa-solid fa-plus"></i> Nueva cita
            </button>
        </div>

        @if (session('status'))
            <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status--error">
                <i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{-- Tarjetas de estadísticas --}}
        <div class="agenda-stats">
            <div class="agenda-stats__item">
                <div class="agenda-stats__icono"><i class="fa-solid fa-calendar-week"></i></div>
                <div>
                    <div class="agenda-stats__valor">{{ $reunionesEstaSemana }}</div>
                    <div class="agenda-stats__label">Reuniones esta semana</div>
                </div>
            </div>
            <div class="agenda-stats__item">
                <div class="agenda-stats__icono"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <div class="agenda-stats__valor">{{ $proximaReunion ? $proximaReunion->inicia_en->format('d/m g:i A') : '—' }}</div>
                    <div class="agenda-stats__label">Próxima reunión</div>
                </div>
            </div>
            <div class="agenda-stats__item">
                <div class="agenda-stats__icono"><i class="fa-solid fa-list-check"></i></div>
                <div>
                    <div class="agenda-stats__valor">{{ $totalProximas }}</div>
                    <div class="agenda-stats__label">Total próximas</div>
                </div>
            </div>
            <div class="agenda-stats__item">
                <div class="agenda-stats__icono"><i class="fa-solid fa-ban"></i></div>
                <div>
                    <div class="agenda-stats__valor">{{ $bloqueos->count() }}</div>
                    <div class="agenda-stats__label">Días bloqueados</div>
                </div>
            </div>
        </div>

        {{-- Pestañas --}}
        <div class="tabs" data-agenda-tabs>
            <button type="button" class="tabs__item is-active" data-agenda-tab="agenda">Agenda</button>
            <button type="button" class="tabs__item" data-agenda-tab="citas">Citas</button>
            <button type="button" class="tabs__item" data-agenda-tab="disponibilidad">Disponibilidad</button>
        </div>

        {{-- a) Pestaña Agenda: vista semanal --}}
        <div class="agenda-tabpanel" data-agenda-panel-tab="agenda">
            <div class="card card--padded">
                <div class="agenda-semana__nav">
                    <button type="button" class="btn btn--secondary btn--sm" data-agenda-semana-hoy>Hoy</button>
                    <button type="button" class="btn--icon" data-agenda-semana-prev aria-label="Semana anterior"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="btn--icon" data-agenda-semana-next aria-label="Semana siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                    <span class="agenda-semana__label" data-agenda-semana-label></span>
                </div>
                <div class="agenda-semana__grid" data-agenda-semana-grid>
                    {{-- agenda.js pinta aquí las 7 columnas (lunes a domingo) --}}
                </div>
                <p class="field__hint">Muestra los últimos 90 días y las próximas reuniones, de cualquier estado.</p>
            </div>
        </div>

        {{-- b) Pestaña Citas: la tabla de siempre + buscador --}}
        <div class="agenda-tabpanel" data-agenda-panel-tab="citas" hidden>
            <div class="card card--padded">
                <div class="agenda-citas__toolbar">
                    <div class="agenda-citas__buscador">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" class="input" id="agendaCitasBuscar" placeholder="Buscar por nombre o correo…">
                    </div>
                    <select class="select" id="agendaCitasEstado">
                        <option value="">Todos los estados</option>
                        @foreach (\App\Enums\EstadoReunion::cases() as $estadoOpcion)
                            <option value="{{ $estadoOpcion->value }}">{{ $estadoOpcion->label() }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($reuniones->count())
                    <x-data-table :headers="['Nombre', 'Correo', 'Cliente', 'Fecha y hora', 'Estado', '']">
                        @foreach ($reuniones as $reunion)
                            <tr class="is-clickable" data-agenda-cita-row
                                data-agenda-buscar="{{ mb_strtolower($reunion->nombre . ' ' . $reunion->email) }}"
                                data-agenda-estado="{{ $reunion->estado->value }}"
                                data-agenda-ver="{{ $reunion->id }}">
                                <td>{{ $reunion->nombre }}</td>
                                <td>{{ $reunion->email }}</td>
                                <td>{{ $reunion->cliente?->nombre ?? '—' }}</td>
                                <td>{{ $reunion->inicia_en->format('d/m/Y g:i A') }}</td>
                                <td><x-badge :status="$reunion->estado" /></td>
                                <td><button type="button" class="btn btn--secondary btn--sm" data-agenda-ver="{{ $reunion->id }}">Ver</button></td>
                            </tr>
                        @endforeach
                    </x-data-table>
                    <p class="field__hint" id="agendaCitasSinResultados" hidden>No hay citas que coincidan con la búsqueda.</p>
                @else
                    <p class="field__hint">Todavía no hay reuniones agendadas.</p>
                @endif
            </div>

            {{-- Forms reales (uno por reunión y por acción), ocultos aquí y
                 movidos por agenda.js al cajón de detalle cuando se abre —
                 nada de esto se recrea en JS, son los mismos POST/PUT de
                 siempre solo reubicados dentro del modal. --}}
            <div hidden data-agenda-forms-cancelar>
                @foreach ($reuniones as $reunion)
                    <form method="POST" action="{{ route('admin.agenda.reuniones.cancelar', $reunion->id) }}" data-confirm="¿Cancelar esta reunión?" data-agenda-form-cancelar="{{ $reunion->id }}">
                        @csrf
                        <button type="submit" class="btn btn--secondary">
                            <i class="fa-solid fa-ban"></i> Cancelar reunión
                        </button>
                    </form>
                @endforeach
            </div>

            <div hidden data-agenda-forms-estado>
                @foreach ($reuniones as $reunion)
                    <div class="agenda-detalle__estados" data-agenda-form-estado="{{ $reunion->id }}">
                        @foreach ($estadosDisponibles as $estadoOpcion)
                            @continue($estadoOpcion === $reunion->estado)
                            <form method="POST" action="{{ route('admin.agenda.reuniones.estado', $reunion->id) }}">
                                @csrf
                                <input type="hidden" name="estado" value="{{ $estadoOpcion->value }}">
                                <button type="submit" class="btn btn--secondary btn--sm">Marcar {{ mb_strtolower($estadoOpcion->label()) }}</button>
                            </form>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div hidden data-agenda-forms-reagendar>
                @foreach ($reuniones as $reunion)
                    <form method="POST" action="{{ route('admin.agenda.reuniones.reagendar', $reunion->id) }}" class="agenda-detalle__reagendar" data-agenda-form-reagendar="{{ $reunion->id }}">
                        @csrf
                        @method('PUT')
                        <input type="date" class="input" name="fecha" value="{{ $reunion->inicia_en->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}" required>
                        <input type="time" class="input" name="hora" value="{{ $reunion->inicia_en->format('H:i') }}" required>
                        <button type="submit" class="btn btn--secondary btn--sm"><i class="fa-solid fa-clock-rotate-left"></i> Reagendar</button>
                    </form>
                @endforeach
            </div>
        </div>

        {{-- c) Pestaña Disponibilidad: las mismas 3 tarjetas, solo reordenadas --}}
        <div class="agenda-tabpanel" data-agenda-panel-tab="disponibilidad" hidden>
            <div class="card card--padded" style="margin-bottom: var(--space-6);">
                <h2 class="card__header-title">Configuración</h2>

                <form method="POST" action="{{ route('admin.agenda.configuracion.update') }}">
                    @csrf
                    @method('PUT')

                    <label class="cfg-switch" style="margin-bottom: var(--space-4);">
                        <input type="checkbox" name="activa" value="1" @checked(old('activa', $config?->activa))>
                        <span class="cfg-switch__pista"></span>
                        <span class="cfg-switch__texto">Activar la página pública de agendar</span>
                        <span class="field__hint">Con esto apagado, la página pública muestra un aviso de "no disponible" en vez del calendario.</span>
                    </label>

                    <div class="form-grid form-grid--2">
                        <div class="field">
                            <label class="field__label" for="agenda_duracion">Duración de cada reunión (minutos) <span style="color:var(--text-danger)">*</span></label>
                            <input class="input" type="number" name="duracion_minutos" id="agenda_duracion" min="5" step="5" value="{{ old('duracion_minutos', $config?->duracion_minutos ?? 30) }}" required>
                            @error('duracion_minutos')<span class="field__error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label class="field__label" for="agenda_anticipacion">Anticipación mínima (horas) <span style="color:var(--text-danger)">*</span></label>
                            <input class="input" type="number" name="anticipacion_minima_horas" id="agenda_anticipacion" min="0" value="{{ old('anticipacion_minima_horas', $config?->anticipacion_minima_horas ?? 2) }}" required>
                            @error('anticipacion_minima_horas')<span class="field__error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                        <div class="field">
                            <label class="field__label" for="agenda_dias_visibles">Días visibles hacia adelante <span style="color:var(--text-danger)">*</span></label>
                            <input class="input" type="number" name="dias_visibles" id="agenda_dias_visibles" min="1" max="60" value="{{ old('dias_visibles', $config?->dias_visibles ?? 14) }}" required>
                            @error('dias_visibles')<span class="field__error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label class="field__label" for="agenda_notificar">Correo para avisar de reuniones nuevas</label>
                            <input class="input" type="email" name="notificar_email" id="agenda_notificar" maxlength="255" value="{{ old('notificar_email', $config?->notificar_email) }}">
                            @error('notificar_email')<span class="field__error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="agenda__acciones">
                        <button type="submit" class="btn btn--primary">
                            <i class="fa-solid fa-floppy-disk"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>

            <div class="card card--padded" style="margin-bottom: var(--space-6);">
                <h2 class="card__header-title">Horarios semanales</h2>

                <form method="POST" action="{{ route('admin.agenda.horarios.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="agenda-horarios">
                        @foreach ($horarios as $i => $dia)
                            <div class="agenda-horarios__fila">
                                <input type="hidden" name="dias[{{ $i }}][dia_semana]" value="{{ $dia['dia_semana'] }}">

                                <label class="cfg-switch agenda-horarios__activo">
                                    <input type="checkbox" name="dias[{{ $i }}][activo]" value="1" @checked(old("dias.$i.activo", $dia['activo']))>
                                    <span class="cfg-switch__pista"></span>
                                    <span class="cfg-switch__texto">{{ $nombresDias[$dia['dia_semana']] }}</span>
                                </label>

                                <div class="field">
                                    <label class="field__label" for="agenda_hi_{{ $i }}">Hora inicio</label>
                                    <input class="input" type="time" name="dias[{{ $i }}][hora_inicio]" id="agenda_hi_{{ $i }}"
                                           value="{{ old("dias.$i.hora_inicio", $dia['hora_inicio'] ? \Illuminate\Support\Str::of($dia['hora_inicio'])->substr(0, 5) : '') }}">
                                    @error("dias.$i.hora_inicio")<span class="field__error">{{ $message }}</span>@enderror
                                </div>

                                <div class="field">
                                    <label class="field__label" for="agenda_hf_{{ $i }}">Hora fin</label>
                                    <input class="input" type="time" name="dias[{{ $i }}][hora_fin]" id="agenda_hf_{{ $i }}"
                                           value="{{ old("dias.$i.hora_fin", $dia['hora_fin'] ? \Illuminate\Support\Str::of($dia['hora_fin'])->substr(0, 5) : '') }}">
                                    @error("dias.$i.hora_fin")<span class="field__error">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="agenda__acciones">
                        <button type="submit" class="btn btn--primary">
                            <i class="fa-solid fa-floppy-disk"></i> Guardar horarios
                        </button>
                    </div>
                </form>
            </div>

            <div class="card card--padded">
                <h2 class="card__header-title">Días bloqueados</h2>

                @if ($bloqueos->count())
                    <ul class="agenda-bloqueos">
                        @foreach ($bloqueos as $bloqueo)
                            <li class="agenda-bloqueos__item">
                                <span class="agenda-bloqueos__fecha">{{ $bloqueo->fecha->format('d/m/Y') }}</span>
                                <span class="agenda-bloqueos__motivo">{{ $bloqueo->motivo ?: '—' }}</span>
                                <form method="POST" action="{{ route('admin.agenda.bloqueos.destroy', $bloqueo->id) }}" data-confirm="¿Quitar este bloqueo?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn--secondary btn--sm">
                                        <i class="fa-solid fa-trash"></i> Quitar
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="field__hint">No hay días bloqueados.</p>
                @endif

                <form method="POST" action="{{ route('admin.agenda.bloqueos.store') }}" class="agenda-bloqueos__form">
                    @csrf
                    <div class="field">
                        <label class="field__label" for="agenda_bloqueo_fecha">Fecha</label>
                        <input class="input" type="date" name="fecha" id="agenda_bloqueo_fecha" min="{{ now()->format('Y-m-d') }}" required>
                        @error('fecha')<span class="field__error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="agenda_bloqueo_motivo">Motivo (opcional)</label>
                        <input class="input" type="text" name="motivo" id="agenda_bloqueo_motivo" maxlength="255">
                        @error('motivo')<span class="field__error">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn--secondary">
                        <i class="fa-solid fa-ban"></i> Bloquear fecha
                    </button>
                </form>
            </div>
        </div>

        {{-- Cajón de detalle de una reunión (solo lectura + Cancelar) --}}
        <x-modal id="agendaDetalleModal">
            <x-slot:header>
                <h2 id="agendaDetalleNombre" style="margin-bottom:6px;"></h2>
                <span id="agendaDetalleBadge"></span>
            </x-slot:header>

            <div class="record-modal__section">
                <div class="record-modal__section-label">Fecha y hora</div>
                <div id="agendaDetalleFecha"></div>
            </div>

            <div class="record-modal__section">
                <div class="record-modal__section-label">Datos de contacto</div>
                <div class="form-grid form-grid--2">
                    <div>
                        <div class="record-modal__section-label">Correo</div>
                        <div id="agendaDetalleCorreo"></div>
                    </div>
                    <div>
                        <div class="record-modal__section-label">Teléfono</div>
                        <div id="agendaDetalleTelefono"></div>
                    </div>
                    <div>
                        <div class="record-modal__section-label">Cliente vinculado</div>
                        <div id="agendaDetalleCliente"></div>
                    </div>
                </div>
            </div>

            <div class="record-modal__section" id="agendaDetalleNotasWrap" hidden>
                <div class="record-modal__section-label">Notas</div>
                <div id="agendaDetalleNotas"></div>
            </div>

            <div class="record-modal__section" id="agendaDetalleReagendarWrap" hidden>
                <div class="record-modal__section-label">Reagendar</div>
                <div id="agendaDetalleReagendar"></div>
            </div>

            <div class="record-modal__section" id="agendaDetalleEstadosWrap" hidden>
                <div class="record-modal__section-label">Cambiar estado</div>
                <div id="agendaDetalleEstados"></div>
            </div>

            <div class="record-modal__actions" id="agendaDetalleAcciones"></div>
        </x-modal>

        {{-- Nueva cita manual: misma validación de disponibilidad que la
             página pública (AgendaController::store), un form clásico. --}}
        <x-modal id="agendaNuevaCitaModal">
            <x-slot:header>
                <h2>Nueva cita</h2>
            </x-slot:header>

            <form method="POST" action="{{ route('admin.agenda.reuniones.store') }}">
                @csrf
                <div class="field">
                    <label class="field__label" for="agenda_nueva_nombre">Nombre <span style="color:var(--text-danger)">*</span></label>
                    <input class="input" type="text" name="nombre" id="agenda_nueva_nombre" maxlength="255" value="{{ old('nombre') }}" required>
                    @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                    <div class="field">
                        <label class="field__label" for="agenda_nueva_email">Correo <span style="color:var(--text-danger)">*</span></label>
                        <input class="input" type="email" name="email" id="agenda_nueva_email" maxlength="255" value="{{ old('email') }}" required>
                        @error('email')<span class="field__error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="agenda_nueva_telefono">Teléfono</label>
                        <input class="input" type="text" name="telefono" id="agenda_nueva_telefono" maxlength="30" value="{{ old('telefono') }}">
                        @error('telefono')<span class="field__error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                    <div class="field">
                        <label class="field__label" for="agenda_nueva_fecha">Fecha <span style="color:var(--text-danger)">*</span></label>
                        <input class="input" type="date" id="agenda_nueva_fecha" min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="agenda_nueva_hora">Hora <span style="color:var(--text-danger)">*</span></label>
                        <input class="input" type="time" id="agenda_nueva_hora" required>
                    </div>
                </div>
                {{-- Combina fecha+hora en el "Y-m-d H:i" único que espera el backend (mismo formato que la página pública). --}}
                <input type="hidden" name="inicia_en" id="agenda_nueva_inicia_en">
                <div class="field" style="margin-top: var(--space-3);">
                    <label class="field__label" for="agenda_nueva_notas">Notas</label>
                    <textarea class="input" name="notas" id="agenda_nueva_notas" rows="2" maxlength="2000">{{ old('notas') }}</textarea>
                    @error('notas')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <p class="field__hint">Se valida contra el mismo horario y las mismas reuniones que la página pública — si el hueco ya no está libre, no se va a poder guardar.</p>
                <div class="record-modal__actions">
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear cita</button>
                </div>
            </form>
        </x-modal>
    </div>
@endsection
