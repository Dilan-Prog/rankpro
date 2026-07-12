@php
    $auditoria = $campana->faseAuditoria;
    $checklistCompleto = collect(\App\Models\SeoFaseAuditoria::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($auditoria->checklist[$key] ?? false));
    $herramientas = ['semrush' => 'Semrush', 'ahrefs' => 'Ahrefs', 'screaming_frog' => 'Screaming Frog', 'google_search_console' => 'Google Search Console', 'otro' => 'Otro'];
@endphp
<div class="card card--padded fase-panel" data-fase-panel="auditoria">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 1 · Auditoría — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Análisis técnico del sitio antes de definir la estrategia.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <h3 class="fase-form__section-title">SEO Score y velocidad</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="seo_score">SEO Score (0-100)</label>
                <input class="input" type="number" min="0" max="100" name="seo_score" id="seo_score" data-autosave value="{{ $auditoria->seo_score }}">
            </div>
            <div class="field">
                <label class="field__label" for="herramienta">Herramienta usada</label>
                <select class="select" name="herramienta" id="herramienta" data-autosave>
                    <option value="">— Sin definir —</option>
                    @foreach ($herramientas as $value => $label)
                        <option value="{{ $value }}" @selected($auditoria->herramienta === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="velocidad_mobile">Velocidad Mobile (0-100)</label>
                <input class="input" type="number" step="0.01" min="0" max="100" name="velocidad_mobile" id="velocidad_mobile" data-autosave value="{{ $auditoria->velocidad_mobile }}">
            </div>
            <div class="field">
                <label class="field__label" for="velocidad_desktop">Velocidad Desktop (0-100)</label>
                <input class="input" type="number" step="0.01" min="0" max="100" name="velocidad_desktop" id="velocidad_desktop" data-autosave value="{{ $auditoria->velocidad_desktop }}">
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Core Web Vitals</h3>
        <div class="form-grid form-grid--2">
            <div class="field"><label class="field__label" style="font-weight:600;">Mobile</label></div>
            <div class="field"><label class="field__label" style="font-weight:600;">Desktop</label></div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-2);">
            <div style="display:flex; gap: var(--space-2);">
                <div class="field"><label class="field__label" for="lcp_mobile">LCP</label><input class="input" type="number" step="0.01" min="0" name="lcp_mobile" id="lcp_mobile" data-autosave value="{{ $auditoria->lcp_mobile }}"></div>
                <div class="field"><label class="field__label" for="fid_mobile">FID</label><input class="input" type="number" step="0.01" min="0" name="fid_mobile" id="fid_mobile" data-autosave value="{{ $auditoria->fid_mobile }}"></div>
                <div class="field"><label class="field__label" for="cls_mobile">CLS</label><input class="input" type="number" step="0.001" min="0" name="cls_mobile" id="cls_mobile" data-autosave value="{{ $auditoria->cls_mobile }}"></div>
            </div>
            <div style="display:flex; gap: var(--space-2);">
                <div class="field"><label class="field__label" for="lcp_desktop">LCP</label><input class="input" type="number" step="0.01" min="0" name="lcp_desktop" id="lcp_desktop" data-autosave value="{{ $auditoria->lcp_desktop }}"></div>
                <div class="field"><label class="field__label" for="fid_desktop">FID</label><input class="input" type="number" step="0.01" min="0" name="fid_desktop" id="fid_desktop" data-autosave value="{{ $auditoria->fid_desktop }}"></div>
                <div class="field"><label class="field__label" for="cls_desktop">CLS</label><input class="input" type="number" step="0.001" min="0" name="cls_desktop" id="cls_desktop" data-autosave value="{{ $auditoria->cls_desktop }}"></div>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Errores técnicos</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="errores_tecnicos">Errores técnicos encontrados</label>
                <input class="input" type="number" min="0" name="errores_tecnicos" id="errores_tecnicos" data-autosave value="{{ $auditoria->errores_tecnicos }}">
            </div>
            <div class="field">
                <label class="field__label" for="errores_404">Errores 404 detectados</label>
                <input class="input" type="number" min="0" name="errores_404" id="errores_404" data-autosave value="{{ $auditoria->errores_404 }}">
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4); max-width: 320px;">
            <label class="field__label" for="redirecciones_incorrectas">Redirecciones incorrectas</label>
            <input class="input" type="number" min="0" name="redirecciones_incorrectas" id="redirecciones_incorrectas" data-autosave value="{{ $auditoria->redirecciones_incorrectas }}">
        </div>

        <div class="checkbox-group" style="margin-top: var(--space-4);">
            <label class="checkbox-item">
                <input type="checkbox" name="indexacion_ok" data-autosave-toggle @checked($auditoria->indexacion_ok)>
                Estado de indexación en Google correcto
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="sitemap_ok" data-autosave-toggle @checked($auditoria->sitemap_ok)>
                Sitemap presente
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="robots_ok" data-autosave-toggle @checked($auditoria->robots_ok)>
                Robots.txt correcto
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="duplicidad_contenido" data-autosave-toggle @checked($auditoria->duplicidad_contenido)>
                Duplicidad de contenido detectada
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="canonical_ok" data-autosave-toggle @checked($auditoria->canonical_ok)>
                Etiquetas canonical presentes
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="schema_ok" data-autosave-toggle @checked($auditoria->schema_ok)>
                Schema markup presente
            </label>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas">Notas de auditoría</label>
            <textarea class="textarea" name="notas" id="notas" data-autosave>{{ $auditoria->notas }}</textarea>
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
