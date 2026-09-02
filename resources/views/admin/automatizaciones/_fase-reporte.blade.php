@php
    $reporte = $proyecto->reporteActual;
    $checklistCompleto = collect(\App\Models\AutomatizacionReporte::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($reporte->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="reporte">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 4 · Reporte y Análisis — Ciclo {{ $proyecto->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Impacto de este ciclo: flujos activos, horas ahorradas y satisfacción del cliente.</span>
    </div>

    @if ($reporte->aprobado)
        <div class="form-status" style="margin-bottom: var(--space-4);">
            <i class="fa-solid fa-circle-check" style="margin-top:2px"></i>
            <span>Reporte del ciclo {{ $proyecto->ciclo_actual }} aprobado el {{ $reporte->fecha_aprobacion?->format('Y-m-d') }}. Elige cómo continuar el proyecto.</span>
        </div>
    @endif

    <form id="faseForm" data-fase-action="{{ route('admin.automatizaciones.fase.guardar', $proyecto) }}">
        <h3 class="fase-form__section-title">Impacto del ciclo</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="flujos_activos_total">Flujos activos totales</label>
                <input class="input" type="number" min="0" name="flujos_activos_total" id="flujos_activos_total" data-autosave value="{{ $reporte->flujos_activos_total }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="horas_ahorradas_mes">Horas ahorradas al mes</label>
                <input class="input" type="number" step="0.5" min="0" name="horas_ahorradas_mes" id="horas_ahorradas_mes" data-autosave value="{{ $reporte->horas_ahorradas_mes }}" @disabled($reporte->aprobado)>
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="mensajes_gestionados_mes">Mensajes gestionados al mes</label>
                <input class="input" type="number" min="0" name="mensajes_gestionados_mes" id="mensajes_gestionados_mes" data-autosave value="{{ $reporte->mensajes_gestionados_mes }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="tareas_automatizadas_mes">Tareas automatizadas al mes</label>
                <input class="input" type="number" min="0" name="tareas_automatizadas_mes" id="tareas_automatizadas_mes" data-autosave value="{{ $reporte->tareas_automatizadas_mes }}" @disabled($reporte->aprobado)>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Cierre del ciclo</h3>
        <div class="field">
            <label class="field__label" for="incidencias">Incidencias del periodo</label>
            <textarea class="textarea" name="incidencias" id="incidencias" data-autosave @disabled($reporte->aprobado)>{{ $reporte->incidencias }}</textarea>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="conclusiones">Conclusiones del periodo</label>
            <textarea class="textarea" name="conclusiones" id="conclusiones" data-autosave @disabled($reporte->aprobado)>{{ $reporte->conclusiones }}</textarea>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="recomendaciones">Recomendaciones para el siguiente ciclo</label>
            <textarea class="textarea" name="recomendaciones" id="recomendaciones" data-autosave @disabled($reporte->aprobado)>{{ $reporte->recomendaciones }}</textarea>
        </div>

        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="satisfaccion_cliente">Satisfacción del cliente (1-5)</label>
                <select class="select" name="satisfaccion_cliente" id="satisfaccion_cliente" data-autosave @disabled($reporte->aprobado)>
                    <option value="">— Sin calificar —</option>
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected($reporte->satisfaccion_cliente === $i)>{{ $i }} {{ $i === 1 ? 'estrella' : 'estrellas' }}</option>
                    @endfor
                </select>
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <label class="checkbox-item">
                    <input type="checkbox" name="continua_proyecto" data-autosave-toggle @checked($reporte->continua_proyecto) @disabled($reporte->aprobado)>
                    ¿Continúa el proyecto?
                </label>
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas_cierre">Notas de cierre</label>
            <textarea class="textarea" name="notas_cierre" id="notas_cierre" data-autosave @disabled($reporte->aprobado)>{{ $reporte->notas_cierre }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de reporte</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AutomatizacionReporte::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($reporte->checklist[$key] ?? false) @disabled($reporte->aprobado)>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--secondary" @disabled($reporte->aprobado)><i class="fa-solid fa-floppy-disk"></i> Guardar avance</button>
            <span class="fase-panel__autosave-note" data-autosave-note></span>
        </div>
    </form>

    <div class="fase-panel__approve">
        @unless ($reporte->aprobado)
            <form method="POST" action="{{ route('admin.automatizaciones.fase.aprobar', $proyecto) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                    <i class="fa-solid fa-check-double"></i> Aprobar Reporte
                </button>
            </form>
            <form method="POST" action="{{ route('admin.automatizaciones.fase.retroceder', $proyecto) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Implementación?">
                @csrf
                <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
            </form>
            @unless ($checklistCompleto)
                <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
            @endunless
        @else
            <form method="POST" action="{{ route('admin.automatizaciones.fase.nuevo-ciclo', $proyecto) }}" style="display:inline;" data-confirm="¿Iniciar el ciclo {{ $proyecto->ciclo_actual + 1 }}? El proyecto volverá a fase de Diagnóstico con historial completo preservado.">
                @csrf
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-arrows-rotate"></i> Nuevo Ciclo
                </button>
            </form>
            <form method="POST" action="{{ route('admin.automatizaciones.fase.cerrar', $proyecto) }}" style="display:inline;" data-confirm="¿Cerrar este proyecto definitivamente?">
                @csrf
                <button type="submit" class="btn btn--secondary">
                    <i class="fa-solid fa-flag-checkered"></i> Cerrar Proyecto
                </button>
            </form>
            <form method="POST" action="{{ route('admin.automatizaciones.fase.pausar', $proyecto) }}" style="display:inline;" data-confirm="¿Pausar este proyecto?">
                @csrf
                <button type="submit" class="btn btn--ghost">
                    <i class="fa-solid fa-pause"></i> Pausar
                </button>
            </form>
        @endunless
    </div>
</div>
