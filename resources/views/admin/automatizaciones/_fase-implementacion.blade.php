@php
    $implementacion = $proyecto->faseImplementacion;
    $checklistCompleto = collect(\App\Models\AutomatizacionFaseImplementacion::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($implementacion->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="implementacion">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 3 · Implementación — Ciclo {{ $proyecto->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Construcción de los flujos en n8n y puesta en marcha.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.automatizaciones.fase.guardar', $proyecto) }}">
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="porcentaje_avance">Porcentaje de avance</label>
                <input class="input" type="number" min="0" max="100" name="porcentaje_avance" id="porcentaje_avance" data-autosave value="{{ $implementacion->porcentaje_avance }}">
            </div>
            <div class="field">
                <label class="field__label" for="flujos_construidos">Flujos construidos</label>
                <input class="input" type="number" min="0" name="flujos_construidos" id="flujos_construidos" data-autosave value="{{ $implementacion->flujos_construidos }}">
            </div>
        </div>

        <div class="checkbox-group" style="margin-top: var(--space-4);">
            <label class="checkbox-item">
                <input type="checkbox" name="pruebas_realizadas" data-autosave-toggle @checked($implementacion->pruebas_realizadas)>
                Pruebas realizadas
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="cliente_capacitado" data-autosave-toggle @checked($implementacion->cliente_capacitado)>
                Cliente capacitado en el uso del flujo
            </label>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas">Notas de implementación</label>
            <textarea class="textarea" name="notas" id="notas" data-autosave>{{ $implementacion->notas }}</textarea>
        </div>

        <p class="field__hint" style="margin-top: var(--space-4);">
            Registra cada flujo individual (tipo, complejidad, integraciones) en la tarjeta "Flujos Automatizados" más abajo.
        </p>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de implementación</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AutomatizacionFaseImplementacion::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($implementacion->checklist[$key] ?? false)>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-floppy-disk"></i> Guardar avance</button>
            <span class="fase-panel__autosave-note" data-autosave-note></span>
        </div>
    </form>

    <div class="fase-panel__approve">
        <form method="POST" action="{{ route('admin.automatizaciones.fase.aprobar', $proyecto) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Reporte
            </button>
        </form>
        <form method="POST" action="{{ route('admin.automatizaciones.fase.retroceder', $proyecto) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Diseño de Flujo?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
