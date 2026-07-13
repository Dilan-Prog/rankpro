@php
    $briefing = $campana->briefing;
    $checklistCompleto = collect(\App\Models\AdsBriefing::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($briefing->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="briefing">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 1 · Briefing y Estrategia — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Define audiencia, propuesta de valor y estrategia antes de pasar a Configuración.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.ads.fase.guardar', $campana) }}">
        <h3 class="fase-form__section-title">Audiencia objetivo</h3>
        <div class="field">
            <label class="field__label" for="publico_objetivo">Público objetivo (descripción)</label>
            <textarea class="textarea" name="publico_objetivo" id="publico_objetivo" data-autosave>{{ $briefing->publico_objetivo }}</textarea>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="rango_edad">Rango de edad</label>
                <input class="input" type="text" name="rango_edad" id="rango_edad" data-autosave value="{{ $briefing->rango_edad }}" placeholder="25-45">
            </div>
            <div class="field">
                <label class="field__label" for="genero">Género</label>
                <input class="input" type="text" name="genero" id="genero" data-autosave value="{{ $briefing->genero }}" placeholder="Todos / Hombres / Mujeres">
            </div>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="ubicacion_geografica">Ubicación geográfica objetivo</label>
                <input class="input" type="text" name="ubicacion_geografica" id="ubicacion_geografica" data-autosave value="{{ $briefing->ubicacion_geografica }}">
            </div>
            <div class="field">
                <label class="field__label" for="producto_servicio">Producto o servicio a promocionar</label>
                <input class="input" type="text" name="producto_servicio" id="producto_servicio" data-autosave value="{{ $briefing->producto_servicio }}">
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="intereses">Intereses y comportamientos</label>
            <textarea class="textarea" name="intereses" id="intereses" data-autosave>{{ $briefing->intereses }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Estrategia</h3>
        <div class="field">
            <label class="field__label" for="propuesta_valor">Propuesta de valor del anuncio</label>
            <textarea class="textarea" name="propuesta_valor" id="propuesta_valor" data-autosave>{{ $briefing->propuesta_valor }}</textarea>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="analisis_competencia">Análisis de competencia en ads</label>
            <textarea class="textarea" name="analisis_competencia" id="analisis_competencia" data-autosave>{{ $briefing->analisis_competencia }}</textarea>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="url_destino">URL de destino (landing page)</label>
                <input class="input" type="text" name="url_destino" id="url_destino" data-autosave value="{{ $briefing->url_destino }}" placeholder="https://...">
            </div>
            <div class="field">
                <label class="field__label" for="fecha_inicio_estimada">Fecha de inicio estimada</label>
                <input class="input" type="date" name="fecha_inicio_estimada" id="fecha_inicio_estimada" data-autosave value="{{ optional($briefing->fecha_inicio_estimada)->format('Y-m-d') }}">
            </div>
        </div>
        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas">Notas de estrategia</label>
            <textarea class="textarea" name="notas" id="notas" data-autosave>{{ $briefing->notas }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de briefing</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AdsBriefing::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($briefing->checklist[$key] ?? false)>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-floppy-disk"></i> Guardar avance</button>
            <span class="fase-panel__autosave-note" data-autosave-note></span>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.ads.fase.aprobar', $campana) }}" class="fase-panel__approve">
        @csrf
        <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
            <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Configuración
        </button>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar este botón.</span>
        @endunless
    </form>
</div>
