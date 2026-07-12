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
            <span>Reporte del ciclo {{ $campana->ciclo_actual }} aprobado el {{ $reporte->fecha_aprobacion?->format('Y-m-d') }}. Elige cómo continuar la campaña.</span>
        </div>
    @endif

    <form id="faseForm" data-fase-action="{{ route('admin.seo.fase.guardar', $campana) }}">
        <h3 class="fase-form__section-title">Tráfico orgánico</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="trafico_inicio">Tráfico orgánico al inicio del ciclo</label>
                <input class="input" type="number" min="0" name="trafico_inicio" id="trafico_inicio" data-autosave value="{{ $reporte->trafico_inicio }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="trafico_actual">Tráfico orgánico actual</label>
                <input class="input" type="number" min="0" name="trafico_actual" id="trafico_actual" data-autosave value="{{ $reporte->trafico_actual }}" @disabled($reporte->aprobado)>
            </div>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Keywords por posición</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="keywords_top3">Keywords en Top 3</label>
                <input class="input" type="number" min="0" name="keywords_top3" id="keywords_top3" data-autosave value="{{ $reporte->keywords_top3 }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="keywords_top10">Keywords en Top 10</label>
                <input class="input" type="number" min="0" name="keywords_top10" id="keywords_top10" data-autosave value="{{ $reporte->keywords_top10 }}" @disabled($reporte->aprobado)>
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4); max-width: 240px;">
            <label class="field__label" for="keywords_top100">Keywords en Top 100</label>
            <input class="input" type="number" min="0" name="keywords_top100" id="keywords_top100" data-autosave value="{{ $reporte->keywords_top100 }}" @disabled($reporte->aprobado)>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Totales acumulados</h3>
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="backlinks_total">Backlinks totales conseguidos</label>
                <input class="input" type="number" min="0" name="backlinks_total" id="backlinks_total" data-autosave value="{{ $reporte->backlinks_total }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="articulos_total">Artículos publicados en total</label>
                <input class="input" type="number" min="0" name="articulos_total" id="articulos_total" data-autosave value="{{ $reporte->articulos_total }}" @disabled($reporte->aprobado)>
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="errores_resueltos">Errores técnicos resueltos</label>
                <input class="input" type="number" min="0" name="errores_resueltos" id="errores_resueltos" data-autosave value="{{ $reporte->errores_resueltos }}" @disabled($reporte->aprobado)>
            </div>
            <div class="field">
                <label class="field__label" for="errores_pendientes">Errores técnicos pendientes</label>
                <input class="input" type="number" min="0" name="errores_pendientes" id="errores_pendientes" data-autosave value="{{ $reporte->errores_pendientes }}" @disabled($reporte->aprobado)>
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
            <form method="POST" action="{{ route('admin.seo.fase.nuevo-ciclo', $campana) }}" style="display:inline;" data-confirm="¿Iniciar el ciclo {{ $campana->ciclo_actual + 1 }}? La campaña volverá a fase de Auditoría con historial completo preservado.">
                @csrf
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-arrows-rotate"></i> Nuevo Ciclo
                </button>
            </form>
            <form method="POST" action="{{ route('admin.seo.fase.cerrar', $campana) }}" style="display:inline;" data-confirm="¿Cerrar esta campaña definitivamente?">
                @csrf
                <button type="submit" class="btn btn--secondary">
                    <i class="fa-solid fa-flag-checkered"></i> Cerrar Campaña
                </button>
            </form>
            <form method="POST" action="{{ route('admin.seo.fase.pausar', $campana) }}" style="display:inline;" data-confirm="¿Pausar esta campaña?">
                @csrf
                <button type="submit" class="btn btn--ghost">
                    <i class="fa-solid fa-pause"></i> Pausar
                </button>
            </form>
        @endunless
    </div>
</div>
