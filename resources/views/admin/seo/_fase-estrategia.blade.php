@php
    $estrategia = $campana->faseEstrategia;
    $checklistCompleto = collect(\App\Models\SeoFaseEstrategia::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($estrategia->checklist[$key] ?? false));
    $keywordsBanco = \App\Models\Keyword::where('cliente_id', $campana->cliente_id)->orderBy('keyword')->get();
    $keywordsSeleccionadas = collect($estrategia->keywords_ids ?? []);
@endphp
<div class="card card--padded fase-panel" data-fase-panel="estrategia">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 2 · Estrategia — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Define competencia, contenido, link building y metas antes de pasar a Ejecución.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <h3 class="fase-form__section-title">Keywords objetivo</h3>
        @if ($keywordsBanco->isEmpty())
            <p class="field__hint" style="margin-bottom: var(--space-4);">
                Este cliente no tiene keywords en el <a href="{{ route('admin.keywords.create') }}" style="text-decoration:underline;">banco de keywords</a> todavía.
            </p>
        @else
            <div class="field" style="margin-bottom: var(--space-5);">
                <label class="field__label" for="keywords_ids">Selecciona las keywords objetivo (principales, secundarias y long tail)</label>
                <select class="select" name="keywords_ids[]" id="keywords_ids" data-autosave multiple size="6">
                    @foreach ($keywordsBanco as $kw)
                        <option value="{{ $kw->id }}" @selected($keywordsSeleccionadas->contains($kw->id))>
                            {{ $kw->keyword }} — {{ \App\Support\Labels::tipoKeyword($kw->tipo) }}
                        </option>
                    @endforeach
                </select>
                <span class="field__hint">Ctrl/Cmd + clic para seleccionar varias.</span>
            </div>
        @endif

        <div class="field">
            <label class="field__label" for="analisis_competencia">Análisis de competencia</label>
            <textarea class="textarea" name="analisis_competencia" id="analisis_competencia" data-autosave>{{ $estrategia->analisis_competencia }}</textarea>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="plan_contenido">Plan de contenido</label>
            <textarea class="textarea" name="plan_contenido" id="plan_contenido" data-autosave>{{ $estrategia->plan_contenido }}</textarea>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="estrategia_link_building">Estrategia de link building</label>
            <textarea class="textarea" name="estrategia_link_building" id="estrategia_link_building" data-autosave>{{ $estrategia->estrategia_link_building }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Metas mensuales</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="meta_trafico_mensual">Meta de tráfico orgánico</label>
                <input class="input" type="number" min="0" name="meta_trafico_mensual" id="meta_trafico_mensual" data-autosave value="{{ $estrategia->meta_trafico_mensual }}">
            </div>
            <div class="field">
                <label class="field__label" for="meta_leads_mensual">Meta de leads mensuales</label>
                <input class="input" type="number" min="0" name="meta_leads_mensual" id="meta_leads_mensual" data-autosave value="{{ $estrategia->meta_leads_mensual }}">
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="meta_top3">Meta de posiciones en Top 3</label>
                <input class="input" type="number" min="0" name="meta_top3" id="meta_top3" data-autosave value="{{ $estrategia->meta_top3 }}">
            </div>
            <div class="field">
                <label class="field__label" for="meta_top10">Meta de posiciones en Top 10</label>
                <input class="input" type="number" min="0" name="meta_top10" id="meta_top10" data-autosave value="{{ $estrategia->meta_top10 }}">
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Herramientas y cronograma</h3>
        <div class="field">
            <label class="field__label" for="herramientas">Herramientas a usar</label>
            <input class="input" type="text" name="herramientas" id="herramientas" data-autosave value="{{ $estrategia->herramientas }}" placeholder="Semrush, Ahrefs, Google Search Console...">
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="cronograma">Cronograma de ejecución</label>
            <textarea class="textarea" name="cronograma" id="cronograma" data-autosave>{{ $estrategia->cronograma }}</textarea>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas">Notas de estrategia</label>
            <textarea class="textarea" name="notas" id="notas" data-autosave>{{ $estrategia->notas }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de estrategia</h3>
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
