@php
    $direccion = $proyecto->direccion;
    $checklistCompleto = collect(\App\Models\ProyectoDireccion::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($direccion->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="direccion">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 3 · Dirección / Ejecución</h2>
        <span class="fase-panel__hint">Etapa principal de desarrollo. Da seguimiento con tareas y comunicación con el cliente más abajo.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.desarrollo.fase.guardar', $proyecto) }}">
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="porcentaje_avance">Porcentaje de avance general</label>
                <div style="display:flex; align-items:center; gap: var(--space-3);">
                    <input class="input" type="range" min="0" max="100" name="porcentaje_avance" id="porcentaje_avance" data-autosave data-progreso-range value="{{ $direccion->porcentaje_avance }}" style="flex:1;">
                    <span class="u-mono" data-progreso-value style="min-width:3ch;">{{ $direccion->porcentaje_avance }}%</span>
                </div>
                <div class="progress-bar" style="margin-top: var(--space-2);">
                    <div class="progress-bar__fill" data-progreso-fill style="width:{{ $direccion->porcentaje_avance }}%;"></div>
                </div>
            </div>
            <div class="field">
                <label class="field__label" for="pagos_recibidos_fase">Pagos recibidos durante ejecución (MXN)</label>
                <input class="input" type="number" step="0.01" min="0" name="pagos_recibidos_fase" id="pagos_recibidos_fase" data-autosave value="{{ $direccion->pagos_recibidos_fase }}">
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de ejecución</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\ProyectoDireccion::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($direccion->checklist[$key] ?? false)>
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
        <form method="POST" action="{{ route('admin.desarrollo.fase.aprobar', $proyecto) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Control
            </button>
        </form>
        <form method="POST" action="{{ route('admin.desarrollo.fase.retroceder', $proyecto) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Organización?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
