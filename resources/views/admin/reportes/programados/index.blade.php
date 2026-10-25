@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/reportes.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Reportes programados</h1>
            <p class="page-header__subtitle">
                Cada mes se manda por correo el reporte del mes anterior que esté en estado <strong>listo</strong>.
                <a href="{{ route('admin.reportes.index') }}">Volver a Reportes</a>
            </p>
        </div>
        <button type="button" class="btn btn--primary" data-programacion-nueva>
            <i class="fa-solid fa-plus"></i> Nueva programación
        </button>
    </div>

    <div class="card empty-state" data-programaciones-empty {{ $programaciones->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-calendar-check"></i></div>
        <p class="empty-state__text">Aún no hay envíos programados.</p>
    </div>

    <x-data-table :headers="['Cliente', 'Frecuencia', 'Próximo envío', 'Último resultado', '']"
                  data-programaciones-tabla :hidden="$programaciones->isEmpty()"
                  data-url-base="{{ route('admin.reportes.programados.index') }}">
        @foreach ($programaciones as $p)
            <tr data-programacion-row data-id="{{ $p['id'] }}" data-activo="{{ $p['activo'] ? '1' : '0' }}"
                data-programacion="{{ json_encode($p) }}">
                <td>
                    <div style="font-weight:500">{{ $p['cliente'] ?? '—' }}</div>
                    <div style="font-size:var(--text-xs);color:var(--color-muted-foreground)">
                        {{ $p['area_label'] }} · {{ implode(', ', $p['destinatarios']) }}
                    </div>
                </td>
                <td>
                    Día {{ $p['dia_mes'] }} a las {{ $p['hora'] }}
                    @unless ($p['activo'])
                        <div><span class="badge badge--neutral" data-estado-programacion>Pausada</span></div>
                    @endunless
                </td>
                <td><span class="u-mono" style="font-size:var(--text-xs)">{{ $p['activo'] ? ($p['proximo_envio_en'] ?? '—') : '—' }}</span></td>
                <td data-resultado data-ok="{{ $p['ultimo_ok'] === null ? '' : ($p['ultimo_ok'] ? '1' : '0') }}">
                    @if ($p['ultimo_ok'] === null)
                        <span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">Sin envíos todavía</span>
                    @else
                        <span class="badge {{ $p['ultimo_ok'] ? 'badge--success' : 'badge--danger' }}">{{ $p['ultimo_ok'] ? 'Enviado' : 'Falló' }}</span>
                        <span style="font-size:var(--text-xs);color:var(--color-muted-foreground)">{{ $p['ultimo_intento_en'] }}</span>
                        @if ($p['ultimo_envio_url'])
                            <a href="{{ $p['ultimo_envio_url'] }}" style="font-size:var(--text-xs)">ver envío</a>
                        @endif
                        {{-- El mensaje real se queda a la vista: aquí es donde se entera alguien de que algo falló. --}}
                        @unless ($p['ultimo_ok'])
                            <div data-resultado-mensaje style="font-size:var(--text-xs);color:var(--text-danger)">{{ $p['ultimo_resultado'] }}</div>
                        @endunless
                    @endif
                    @if ($p['ultima_prueba'])
                        {{-- Una prueba fallida va en rojo: en gris se confundía con el texto informativo. --}}
                        <div data-ultima-prueba style="font-size:var(--text-xs);color:{{ str_contains($p['ultima_prueba'], '· ERROR: ') ? 'var(--text-danger)' : 'var(--color-muted-foreground)' }}">Última prueba: {{ $p['ultima_prueba'] }}</div>
                    @endif
                </td>
                <td>
                    <div style="display:flex; gap:4px;">
                        <button type="button" class="btn--icon" title="Probar a mi correo" data-accion="probar"><i class="fa-solid fa-vial"></i></button>
                        <button type="button" class="btn--icon" title="Enviar ahora" data-accion="enviar-ahora"><i class="fa-solid fa-paper-plane"></i></button>
                        <button type="button" class="btn--icon" title="{{ $p['activo'] ? 'Pausar' : 'Activar' }}" data-accion="alternar">
                            <i class="fa-solid {{ $p['activo'] ? 'fa-pause' : 'fa-play' }}"></i>
                        </button>
                        <button type="button" class="btn--icon" title="Editar" data-accion="editar"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-accion="eliminar"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-modal id="programacionModal">
        <x-slot:header><h2 style="margin-bottom:0;" data-programacion-titulo>Nueva programación</h2></x-slot:header>
        <form id="programacionForm" novalidate data-store-action="{{ route('admin.reportes.programados.store') }}">
            @csrf
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="pf_cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="pf_cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->empresa ?: $cliente->nombre }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="cliente_id"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="pf_area">Reporte de</label>
                    <select class="select" name="area" id="pf_area" required>
                        @foreach (\App\Enums\AreaReporte::cases() as $area)
                            <option value="{{ $area->value }}">{{ $area->label() }}</option>
                        @endforeach
                    </select>
                    <span class="field__error" data-error-for="area"></span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="pf_dia_mes">Día del mes (1-28)</label>
                    <input class="input" type="number" min="1" max="28" name="dia_mes" id="pf_dia_mes" value="5" required>
                    <span class="field__error" data-error-for="dia_mes"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="pf_hora">Hora</label>
                    <input class="input" type="time" name="hora" id="pf_hora" value="09:00" required>
                    <span class="field__error" data-error-for="hora"></span>
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="pf_plantilla_id">Plantilla de correo</label>
                <select class="select" name="plantilla_id" id="pf_plantilla_id" required>
                    <option value="">— Selecciona una plantilla —</option>
                    @foreach ($plantillas as $plantilla)
                        <option value="{{ $plantilla->id }}">{{ $plantilla->nombre }}</option>
                    @endforeach
                </select>
                <span class="field__error" data-error-for="plantilla_id"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="pf_asunto">Asunto</label>
                <input class="input" type="text" name="asunto" id="pf_asunto" required value="Tu reporte de @{{mes}} — @{{cliente}}">
                <span class="field__error" data-error-for="asunto"></span>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="pf_destinatarios">Destinatarios (separados por coma o salto de línea)</label>
                <textarea class="textarea" name="destinatarios" id="pf_destinatarios" required placeholder="cliente@empresa.com"></textarea>
                <span class="field__error" data-error-for="destinatarios"></span>
                <span class="field__error" data-error-for="destinatarios.0"></span>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="programacionModal">Cancelar</button>
                <button type="submit" class="btn btn--primary">Guardar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/reporte-programaciones.js')
@endsection
