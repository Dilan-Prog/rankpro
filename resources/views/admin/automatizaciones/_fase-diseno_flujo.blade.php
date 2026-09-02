@php
    $diseno = $proyecto->faseDiseno;
    $checklistCompleto = collect(\App\Models\AutomatizacionFaseDiseno::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($diseno->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="diseno_flujo">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 2 · Diseño de Flujo — Ciclo {{ $proyecto->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Define qué flujos se van a construir en n8n antes de pasar a Implementación.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.automatizaciones.fase.guardar', $proyecto) }}">
        <div class="field">
            <label class="field__label" for="flujos_planeados">Flujos planeados</label>
            <textarea class="textarea" name="flujos_planeados" id="flujos_planeados" data-autosave placeholder="Descripción de cada flujo a construir">{{ $diseno->flujos_planeados }}</textarea>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="integraciones_planeadas">Integraciones a usar</label>
            <input class="input" type="text" name="integraciones_planeadas" id="integraciones_planeadas" data-autosave value="{{ $diseno->integraciones_planeadas }}" placeholder="WhatsApp Business, HubSpot, Google Sheets">
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="diagrama_url">Enlace al diagrama de flujo</label>
            <input class="input" type="text" name="diagrama_url" id="diagrama_url" data-autosave value="{{ $diseno->diagrama_url }}" placeholder="https://...">
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="cronograma">Cronograma</label>
            <textarea class="textarea" name="cronograma" id="cronograma" data-autosave>{{ $diseno->cronograma }}</textarea>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas">Notas de diseño</label>
            <textarea class="textarea" name="notas" id="notas" data-autosave>{{ $diseno->notas }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de diseño</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AutomatizacionFaseDiseno::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($diseno->checklist[$key] ?? false)>
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
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Implementación
            </button>
        </form>
        <form method="POST" action="{{ route('admin.automatizaciones.fase.retroceder', $proyecto) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Diagnóstico?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
