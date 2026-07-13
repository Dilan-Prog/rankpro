@php
    $configuracion = $campana->configuracion;
    $checklistCompleto = collect(\App\Models\AdsConfiguracion::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($configuracion->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="configuracion">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 2 · Configuración — Ciclo {{ $campana->ciclo_actual }}</h2>
        <span class="fase-panel__hint">Estructura, píxel y UTMs. Administra grupos de anuncios y creativos más abajo.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.ads.fase.guardar', $campana) }}">
        <div class="field">
            <label class="field__label" for="estructura_campana">Estructura de campaña definida</label>
            <textarea class="textarea" name="estructura_campana" id="estructura_campana" data-autosave>{{ $configuracion->estructura_campana }}</textarea>
        </div>

        <div class="field" style="margin-top: var(--space-4); max-width: 420px;">
            <label class="field__label" for="cuenta_publicitaria">Cuenta publicitaria vinculada</label>
            <input class="input" type="text" name="cuenta_publicitaria" id="cuenta_publicitaria" data-autosave value="{{ $configuracion->cuenta_publicitaria }}" placeholder="ID o nombre de la cuenta">
        </div>

        <div class="checkbox-group" style="margin-top: var(--space-4);">
            <label class="checkbox-item">
                <input type="checkbox" name="pixel_ok" data-autosave-toggle @checked($configuracion->pixel_ok)>
                Píxel / conversiones configuradas
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="utms_ok" data-autosave-toggle @checked($configuracion->utms_ok)>
                UTMs configurados
            </label>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas">Notas de configuración</label>
            <textarea class="textarea" name="notas" id="notas" data-autosave>{{ $configuracion->notas }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de configuración</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\AdsConfiguracion::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($configuracion->checklist[$key] ?? false)>
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
        <form method="POST" action="{{ route('admin.ads.fase.aprobar', $campana) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Lanzamiento
            </button>
        </form>
        <form method="POST" action="{{ route('admin.ads.fase.retroceder', $campana) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Briefing?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
