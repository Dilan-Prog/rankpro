@php
    $auditoria = $campana->faseAuditoria;
    $checklistCompleto = collect(\App\Models\SeoFaseAuditoria::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($auditoria->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="auditoria">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 1 · Auditoría</h2>
        <span class="fase-panel__hint">Análisis técnico del sitio antes de definir la estrategia.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="seo_score">SEO Score (0-100)</label>
                <input class="input" type="number" min="0" max="100" name="seo_score" id="seo_score" data-autosave value="{{ $campana->seo_score }}">
            </div>
            <div class="field">
                <label class="field__label" for="errores_tecnicos">Errores técnicos detectados</label>
                <input class="input" type="number" min="0" name="errores_tecnicos" id="errores_tecnicos" data-autosave value="{{ $campana->errores_tecnicos }}">
            </div>
        </div>

        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="velocidad_mobile">Velocidad Mobile (Core Web Vitals)</label>
                <input class="input" type="number" step="0.01" min="0" max="100" name="velocidad_mobile" id="velocidad_mobile" data-autosave value="{{ $campana->velocidad_mobile }}">
            </div>
            <div class="field">
                <label class="field__label" for="velocidad_desktop">Velocidad Desktop (Core Web Vitals)</label>
                <input class="input" type="number" step="0.01" min="0" max="100" name="velocidad_desktop" id="velocidad_desktop" data-autosave value="{{ $campana->velocidad_desktop }}">
            </div>
        </div>

        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="trafico_organico_mensual">Tráfico orgánico mensual (baseline)</label>
                <input class="input" type="number" min="0" name="trafico_organico_mensual" id="trafico_organico_mensual" data-autosave value="{{ $campana->trafico_organico_mensual }}">
            </div>
            <div class="field">
                <label class="field__label" for="backlinks_total">Backlinks totales (baseline)</label>
                <input class="input" type="number" min="0" name="backlinks_total" id="backlinks_total" data-autosave value="{{ $campana->backlinks_total }}">
            </div>
        </div>

        <div class="checkbox-group" style="margin-top: var(--space-4);">
            <label class="checkbox-item">
                <input type="checkbox" name="sitemap_ok" data-autosave-toggle @checked($campana->sitemap_ok)>
                Sitemap XML enviado a Search Console
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="robots_ok" data-autosave-toggle @checked($campana->robots_ok)>
                robots.txt configurado correctamente
            </label>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de auditoría</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\SeoFaseAuditoria::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($auditoria->checklist[$key] ?? false)>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-floppy-disk"></i> Guardar avance</button>
            <span class="fase-panel__autosave-note" data-autosave-note></span>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.seo.fase.aprobar', $campana) }}" class="fase-panel__approve">
        @csrf
        <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
            <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Estrategia
        </button>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar este botón.</span>
        @endunless
    </form>
</div>
