@php
    $ejecucion = $campana->faseEjecucion;
    $checklistCompleto = collect(\App\Models\SeoFaseEjecucion::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($ejecucion->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="ejecucion">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 3 · Ejecución</h2>
        <span class="fase-panel__hint">On-Page, Off-Page, técnico y contenido. Da seguimiento a posiciones y backlinks más abajo.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <div class="checkbox-group">
            <label class="checkbox-item">
                <input type="checkbox" name="on_page_completado" data-autosave-toggle @checked($ejecucion->on_page_completado)>
                On-Page completado
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="off_page_completado" data-autosave-toggle @checked($ejecucion->off_page_completado)>
                Off-Page completado
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="tecnico_completado" data-autosave-toggle @checked($ejecucion->tecnico_completado)>
                Técnico completado
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="contenido_completado" data-autosave-toggle @checked($ejecucion->contenido_completado)>
                Contenido completado
            </label>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de ejecución</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\SeoFaseEjecucion::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($ejecucion->checklist[$key] ?? false)>
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
        <form method="POST" action="{{ route('admin.seo.fase.aprobar', $campana) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Reporte
            </button>
        </form>
        <form method="POST" action="{{ route('admin.seo.fase.retroceder', $campana) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Estrategia?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
