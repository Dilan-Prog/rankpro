@php
    $lanzamiento = $campana->lanzamiento;
    $checklistCompleto = collect(\App\Models\AdsLanzamiento::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($lanzamiento->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="lanzamiento">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 3 · Lanzamiento y Optimización — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Etapa operativa. Registra métricas mensuales y optimizaciones más abajo.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.ads.fase.guardar', $campana) }}">
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="fecha_lanzamiento">Fecha de lanzamiento real</label>
                <input class="input" type="date" name="fecha_lanzamiento" id="fecha_lanzamiento" data-autosave value="{{ optional($lanzamiento->fecha_lanzamiento)->format('Y-m-d') }}">
            </div>
            <div class="field">
                <label class="field__label" for="porcentaje_avance">Porcentaje de avance general</label>
                <div style="display:flex; align-items:center; gap: var(--space-3);">
                    <input class="input" type="range" min="0" max="100" name="porcentaje_avance" id="porcentaje_avance" data-autosave data-progreso-range value="{{ $lanzamiento->porcentaje_avance }}" style="flex:1;">
                    <span class="u-mono" data-progreso-value style="min-width:3ch;">{{ $lanzamiento->porcentaje_avance }}%</span>
                </div>
                <div class="progress-bar" style="margin-top: var(--space-2);">
                    <div class="progress-bar__fill" data-progreso-fill style="width:{{ $lanzamiento->porcentaje_avance }}%;"></div>
                </div>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de lanzamiento y optimización</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AdsLanzamiento::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($lanzamiento->checklist[$key] ?? false)>
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
        <form method="POST" action="{{ route('admin.ads.fase.aprobar', $campana) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Reporte
            </button>
        </form>
        <form method="POST" action="{{ route('admin.ads.fase.retroceder', $campana) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Configuración?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
