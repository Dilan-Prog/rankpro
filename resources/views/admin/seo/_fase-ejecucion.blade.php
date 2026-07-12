@php
    $ejecucion = $campana->faseEjecucion;
    $checklistCompleto = collect(\App\Models\SeoFaseEjecucion::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($ejecucion->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="ejecucion">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 3 · Ejecución — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">On-Page, Off-Page, técnico y contenido. Da seguimiento a posiciones, backlinks y contenido más abajo.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <div class="field" style="max-width: 320px;">
            <label class="field__label" for="porcentaje_avance">Porcentaje de avance general</label>
            <div style="display:flex; align-items:center; gap: var(--space-3);">
                <input class="input" type="range" min="0" max="100" name="porcentaje_avance" id="porcentaje_avance" data-autosave data-progreso-range value="{{ $ejecucion->porcentaje_avance }}" style="flex:1;">
                <span class="u-mono" data-progreso-value style="min-width:3ch;">{{ $ejecucion->porcentaje_avance }}%</span>
            </div>
            <div class="progress-bar" style="margin-top: var(--space-2);">
                <div class="progress-bar__fill" data-progreso-fill style="width:{{ $ejecucion->porcentaje_avance }}%;"></div>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">On-Page</h3>
        <div class="field" style="max-width: 240px;">
            <label class="field__label" for="paginas_optimizadas">Páginas optimizadas</label>
            <input class="input" type="number" min="0" name="paginas_optimizadas" id="paginas_optimizadas" data-autosave value="{{ $ejecucion->paginas_optimizadas }}">
        </div>
        <div class="checkbox-group" style="margin-top: var(--space-3);">
            <label class="checkbox-item">
                <input type="checkbox" name="titles_meta_ok" data-autosave-toggle @checked($ejecucion->titles_meta_ok)>
                Títulos y meta descriptions actualizados
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="headings_ok" data-autosave-toggle @checked($ejecucion->headings_ok)>
                Estructura H1/H2/H3 corregida
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="imagenes_ok" data-autosave-toggle @checked($ejecucion->imagenes_ok)>
                Imágenes optimizadas con alt text
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="links_internos_ok" data-autosave-toggle @checked($ejecucion->links_internos_ok)>
                Links internos trabajados
            </label>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Off-Page / Link Building</h3>
        <div class="field" style="max-width: 240px;">
            <label class="field__label" for="backlinks_mes">Backlinks conseguidos este mes</label>
            <input class="input" type="number" min="0" name="backlinks_mes" id="backlinks_mes" data-autosave value="{{ $ejecucion->backlinks_mes }}">
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Técnico</h3>
        <div class="checkbox-group">
            <label class="checkbox-item">
                <input type="checkbox" name="errores_404_ok" data-autosave-toggle @checked($ejecucion->errores_404_ok)>
                Errores 404 corregidos
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="redirecciones_ok" data-autosave-toggle @checked($ejecucion->redirecciones_ok)>
                Redirecciones implementadas
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="schema_ok" data-autosave-toggle @checked($ejecucion->schema_ok)>
                Schema markup aplicado
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="velocidad_ok" data-autosave-toggle @checked($ejecucion->velocidad_ok)>
                Velocidad mejorada
            </label>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Contenido</h3>
        <div class="field" style="max-width: 240px;">
            <label class="field__label" for="articulos_publicados">Artículos publicados este mes</label>
            <input class="input" type="number" min="0" name="articulos_publicados" id="articulos_publicados" data-autosave value="{{ $ejecucion->articulos_publicados }}">
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
