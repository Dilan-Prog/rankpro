@php
    $planeacion = $proyecto->planeacion;
    $checklistCompleto = collect(\App\Models\ProyectoPlaneacion::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($planeacion->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="planeacion">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 1 · Planeación</h2>
        <span class="fase-panel__hint">Define el alcance antes de pasar a Organización.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.desarrollo.fase.guardar', $proyecto) }}">
        <div class="field">
            <label class="field__label" for="objetivos">Objetivos del proyecto</label>
            <textarea class="textarea" name="objetivos" id="objetivos" data-autosave>{{ $planeacion->objetivos }}</textarea>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="requerimientos_funcionales">Requerimientos funcionales</label>
                <textarea class="textarea" name="requerimientos_funcionales" id="requerimientos_funcionales" data-autosave>{{ $planeacion->requerimientos_funcionales }}</textarea>
            </div>
            <div class="field">
                <label class="field__label" for="requerimientos_tecnicos">Requerimientos técnicos</label>
                <textarea class="textarea" name="requerimientos_tecnicos" id="requerimientos_tecnicos" data-autosave>{{ $planeacion->requerimientos_tecnicos }}</textarea>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de planeación</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\ProyectoPlaneacion::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($planeacion->checklist[$key] ?? false)>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-floppy-disk"></i> Guardar avance</button>
            <span class="fase-panel__autosave-note" data-autosave-note></span>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.desarrollo.fase.aprobar', $proyecto) }}" class="fase-panel__approve">
        @csrf
        <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
            <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Organización
        </button>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar este botón.</span>
        @endunless
    </form>
</div>
