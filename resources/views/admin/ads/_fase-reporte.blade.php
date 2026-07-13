@php
    $reporte = $campana->reporteActual;
    $checklistCompleto = collect(\App\Models\AdsReporte::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($reporte->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="reporte">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 4 · Reporte y Análisis — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Resultados del periodo contra los objetivos del briefing.</span>
    </div>

    @if ($reporte->aprobado)
        <div class="form-status" style="margin-bottom: var(--space-4);">
            <i class="fa-solid fa-circle-check" style="margin-top:2px"></i>
            <span>Reporte del ciclo {{ $campana->ciclo_actual }} aprobado el {{ $reporte->fecha_aprobacion?->format('Y-m-d') }}. Elige cómo continuar la campaña.</span>
        </div>
    @endif

    <form id="faseForm" data-fase-action="{{ route('admin.ads.fase.guardar', $campana) }}">
        <h3 class="fase-form__section-title">Totales del periodo</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="inversion_total">Inversión total (MXN)</label>
                <input class="input" type="number" step="0.01" min="0" name="inversion_total" id="inversion_total" data-autosave value="{{ $reporte->inversion_total }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="impresiones_total">Impresiones totales</label>
                <input class="input" type="number" min="0" name="impresiones_total" id="impresiones_total" data-autosave value="{{ $reporte->impresiones_total }}" @disabled($reporte->aprobado)>
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="clics_total">Clics totales</label>
                <input class="input" type="number" min="0" name="clics_total" id="clics_total" data-autosave value="{{ $reporte->clics_total }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="ctr_promedio">CTR promedio (%)</label>
                <input class="input" type="number" step="0.001" min="0" name="ctr_promedio" id="ctr_promedio" data-autosave value="{{ $reporte->ctr_promedio }}" @disabled($reporte->aprobado)>
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="conversiones_total">Conversiones totales</label>
                <input class="input" type="number" min="0" name="conversiones_total" id="conversiones_total" data-autosave value="{{ $reporte->conversiones_total }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="roas_promedio">ROAS promedio</label>
                <input class="input" type="number" step="0.01" min="0" name="roas_promedio" id="roas_promedio" data-autosave value="{{ $reporte->roas_promedio }}" @disabled($reporte->aprobado)>
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="cpl_promedio">CPL promedio</label>
                <input class="input" type="number" step="0.01" min="0" name="cpl_promedio" id="cpl_promedio" data-autosave value="{{ $reporte->cpl_promedio }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="cpa_promedio">CPA promedio</label>
                <input class="input" type="number" step="0.01" min="0" name="cpa_promedio" id="cpa_promedio" data-autosave value="{{ $reporte->cpa_promedio }}" @disabled($reporte->aprobado)>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Mejores anuncios</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="mejor_anuncio_ctr">Mejor anuncio por CTR</label>
                <input class="input" type="text" name="mejor_anuncio_ctr" id="mejor_anuncio_ctr" data-autosave value="{{ $reporte->mejor_anuncio_ctr }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="mejor_anuncio_conversiones">Mejor anuncio por conversiones</label>
                <input class="input" type="text" name="mejor_anuncio_conversiones" id="mejor_anuncio_conversiones" data-autosave value="{{ $reporte->mejor_anuncio_conversiones }}" @disabled($reporte->aprobado)>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Cierre del ciclo</h3>
        <div class="field">
            <label class="field__label" for="conclusiones">Conclusiones del periodo</label>
            <textarea class="textarea" name="conclusiones" id="conclusiones" data-autosave @disabled($reporte->aprobado)>{{ $reporte->conclusiones }}</textarea>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="recomendaciones">Recomendaciones para el siguiente ciclo</label>
            <textarea class="textarea" name="recomendaciones" id="recomendaciones" data-autosave @disabled($reporte->aprobado)>{{ $reporte->recomendaciones }}</textarea>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="satisfaccion_cliente">Satisfacción del cliente (1-5)</label>
                <select class="select" name="satisfaccion_cliente" id="satisfaccion_cliente" data-autosave @disabled($reporte->aprobado)>
                    <option value="">— Sin calificar —</option>
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected($reporte->satisfaccion_cliente === $i)>{{ $i }} {{ $i === 1 ? 'estrella' : 'estrellas' }}</option>
                    @endfor
                </select>
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <label class="checkbox-item">
                    <input type="checkbox" name="continua_campana" data-autosave-toggle @checked($reporte->continua_campana) @disabled($reporte->aprobado)>
                    ¿Continúa la campaña?
                </label>
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas_cierre">Notas de cierre</label>
            <textarea class="textarea" name="notas_cierre" id="notas_cierre" data-autosave @disabled($reporte->aprobado)>{{ $reporte->notas_cierre }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de reporte</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AdsReporte::CHECKLIST as $key => $label)
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
            <form method="POST" action="{{ route('admin.ads.fase.aprobar', $campana) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                    <i class="fa-solid fa-check-double"></i> Aprobar Reporte
                </button>
            </form>
            <form method="POST" action="{{ route('admin.ads.fase.retroceder', $campana) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Lanzamiento?">
                @csrf
                <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
            </form>
            @unless ($checklistCompleto)
                <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
            @endunless
        @else
            <form method="POST" action="{{ route('admin.ads.fase.nuevo-ciclo', $campana) }}" style="display:inline;" data-confirm="¿Iniciar el ciclo {{ $campana->ciclo_actual + 1 }}? La campaña volverá a fase de Briefing con historial completo preservado.">
                @csrf
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-arrows-rotate"></i> Nuevo Ciclo
                </button>
            </form>
            <form method="POST" action="{{ route('admin.ads.fase.cerrar', $campana) }}" style="display:inline;" data-confirm="¿Cerrar esta campaña definitivamente?">
                @csrf
                <button type="submit" class="btn btn--secondary">
                    <i class="fa-solid fa-flag-checkered"></i> Cerrar Campaña
                </button>
            </form>
            <form method="POST" action="{{ route('admin.ads.fase.pausar', $campana) }}" style="display:inline;" data-confirm="¿Pausar esta campaña?">
                @csrf
                <button type="submit" class="btn btn--ghost">
                    <i class="fa-solid fa-pause"></i> Pausar
                </button>
            </form>
        @endunless
    </div>
</div>
