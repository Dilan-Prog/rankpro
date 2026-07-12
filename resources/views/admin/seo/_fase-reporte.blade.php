@php
    $reporte = $campana->reporteActual;
    $checklistCompleto = collect(\App\Models\SeoReporte::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($reporte->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="reporte">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 4 · Reporte y Análisis — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Resultados de este ciclo contra las metas definidas en Estrategia.</span>
    </div>

    @if ($reporte->aprobado)
        <div class="form-status" style="margin-bottom: var(--space-4);">
            <i class="fa-solid fa-circle-check" style="margin-top:2px"></i>
            <span>Reporte del ciclo {{ $campana->ciclo_actual }} aprobado el {{ $reporte->fecha_aprobacion?->format('Y-m-d') }}.</span>
        </div>
    @endif

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <div class="field">
            <label class="field__label" for="resultados_vs_metas">Resultados vs metas</label>
            <textarea class="textarea" name="resultados_vs_metas" id="resultados_vs_metas" data-autosave>{{ $reporte->resultados_vs_metas }}</textarea>
        </div>

        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="trafico_organico_final">Tráfico orgánico final</label>
                <input class="input" type="number" min="0" name="trafico_organico_final" id="trafico_organico_final" data-autosave value="{{ $reporte->trafico_organico_final }}">
            </div>
            <div class="field">
                <label class="field__label" for="posiciones_ganadas">Posiciones ganadas</label>
                <input class="input" type="number" name="posiciones_ganadas" id="posiciones_ganadas" data-autosave value="{{ $reporte->posiciones_ganadas }}">
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4); max-width: 240px;">
            <label class="field__label" for="roas_organico">ROAS orgánico</label>
            <input class="input" type="number" step="0.01" min="0" name="roas_organico" id="roas_organico" data-autosave value="{{ $reporte->roas_organico }}">
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de reporte</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\SeoReporte::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($reporte->checklist[$key] ?? false) @disabled($reporte->aprobado)>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--secondary" @disabled($reporte->aprobado)><i class="fa-solid fa-floppy-disk"></i> Guardar avance</button>
            <span class="fase-panel__autosave-note" data-autosave-note></span>
        </div>
    </form>

    <div class="fase-panel__approve">
        @unless ($reporte->aprobado)
            <form method="POST" action="{{ route('admin.seo.fase.aprobar', $campana) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                    <i class="fa-solid fa-check-double"></i> Aprobar Reporte
                </button>
            </form>
            <form method="POST" action="{{ route('admin.seo.fase.retroceder', $campana) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Ejecución?">
                @csrf
                <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
            </form>
            @unless ($checklistCompleto)
                <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
            @endunless
        @else
            <form method="POST" action="{{ route('admin.seo.fase.siguiente-ciclo', $campana) }}" style="display:inline;" data-confirm="¿Iniciar el ciclo {{ $campana->ciclo_actual + 1 }}? La campaña volverá a la fase de Ejecución.">
                @csrf
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-arrows-rotate"></i> Iniciar Siguiente Ciclo
                </button>
            </form>
        @endunless
    </div>
</div>
