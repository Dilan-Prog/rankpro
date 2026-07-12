@php
    $estrategia = $campana->faseEstrategia;
    $checklistCompleto = collect(\App\Models\SeoFaseEstrategia::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($estrategia->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="estrategia">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 2 · Estrategia</h2>
        <span class="fase-panel__hint">Define competencia, contenido, link building y metas antes de pasar a Ejecución.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <div class="field">
            <label class="field__label" for="analisis_competencia">Análisis de competencia</label>
            <textarea class="textarea" name="analisis_competencia" id="analisis_competencia" data-autosave>{{ $estrategia->analisis_competencia }}</textarea>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="plan_contenido">Plan de contenido</label>
            <textarea class="textarea" name="plan_contenido" id="plan_contenido" data-autosave>{{ $estrategia->plan_contenido }}</textarea>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="link_building_strategy">Estrategia de link building</label>
            <textarea class="textarea" name="link_building_strategy" id="link_building_strategy" data-autosave>{{ $estrategia->link_building_strategy }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Metas mensuales</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="meta_trafico_mensual">Meta de tráfico orgánico</label>
                <input class="input" type="number" min="0" name="meta_trafico_mensual" id="meta_trafico_mensual" data-autosave value="{{ $estrategia->meta_trafico_mensual }}">
            </div>
            <div class="field">
                <label class="field__label" for="meta_posiciones_top10">Meta de posiciones en Top 10</label>
                <input class="input" type="number" min="0" name="meta_posiciones_top10" id="meta_posiciones_top10" data-autosave value="{{ $estrategia->meta_posiciones_top10 }}">
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4); max-width: 320px;">
            <label class="field__label" for="meta_leads_mensual">Meta de leads mensuales</label>
            <input class="input" type="number" min="0" name="meta_leads_mensual" id="meta_leads_mensual" data-autosave value="{{ $estrategia->meta_leads_mensual }}">
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de estrategia</h3>
        <p class="field__hint" style="margin-bottom: var(--space-3);">Las keywords objetivo se administran desde el <a href="{{ route('admin.keywords.create') }}" style="text-decoration:underline;">banco de keywords</a> — asígnalas a esta campaña para que aparezcan abajo.</p>
        <div class="checkbox-group">
            @foreach (\App\Models\SeoFaseEstrategia::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($estrategia->checklist[$key] ?? false)>
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
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Ejecución
            </button>
        </form>
        <form method="POST" action="{{ route('admin.seo.fase.retroceder', $campana) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Auditoría?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
