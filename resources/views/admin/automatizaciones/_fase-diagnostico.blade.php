@php
    $diagnostico = $proyecto->faseDiagnostico;
    $checklistCompleto = collect(\App\Models\AutomatizacionFaseDiagnostico::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($diagnostico->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="diagnostico">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 1 · Diagnóstico — Ciclo {{ $proyecto->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Entender el proceso actual del cliente antes de diseñar el flujo.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.automatizaciones.fase.guardar', $proyecto) }}">
        <div class="field">
            <label class="field__label" for="objetivo_cliente">Objetivo del cliente</label>
            <textarea class="textarea" name="objetivo_cliente" id="objetivo_cliente" data-autosave>{{ $diagnostico->objetivo_cliente }}</textarea>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="procesos_actuales">Procesos actuales (manuales)</label>
            <textarea class="textarea" name="procesos_actuales" id="procesos_actuales" data-autosave>{{ $diagnostico->procesos_actuales }}</textarea>
        </div>

        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="herramientas_actuales">Herramientas actuales</label>
                <input class="input" type="text" name="herramientas_actuales" id="herramientas_actuales" data-autosave value="{{ $diagnostico->herramientas_actuales }}" placeholder="Ej. WhatsApp Business, HubSpot">
            </div>
            <div class="field">
                <label class="field__label" for="volumen_mensual_estimado">Volumen mensual estimado</label>
                <input class="input" type="number" min="0" name="volumen_mensual_estimado" id="volumen_mensual_estimado" data-autosave value="{{ $diagnostico->volumen_mensual_estimado }}" placeholder="Mensajes o tareas al mes">
            </div>
        </div>

        <div class="checkbox-group" style="margin-top: var(--space-4);">
            <label class="checkbox-item">
                <input type="checkbox" name="viable" data-autosave-toggle @checked($diagnostico->viable)>
                Viabilidad de automatización confirmada
            </label>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas">Notas</label>
            <textarea class="textarea" name="notas" id="notas" data-autosave>{{ $diagnostico->notas }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de diagnóstico</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AutomatizacionFaseDiagnostico::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($diagnostico->checklist[$key] ?? false)>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-floppy-disk"></i> Guardar avance</button>
            <span class="fase-panel__autosave-note" data-autosave-note></span>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.automatizaciones.fase.aprobar', $proyecto) }}" class="fase-panel__approve">
        @csrf
        <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
            <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Diseño de Flujo
        </button>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar este botón.</span>
        @endunless
    </form>
</div>
