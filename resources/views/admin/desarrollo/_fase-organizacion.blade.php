@php
    $organizacion = $proyecto->organizacion;
    $checklistCompleto = collect(\App\Models\ProyectoOrganizacion::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($organizacion->checklist[$key] ?? false));
    $equipo = $organizacion->equipo ?: [['nombre' => '', 'rol' => '']];
@endphp
<div class="card card--padded fase-panel" data-fase-panel="organizacion">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 2 · Organización</h2>
        <span class="fase-panel__hint">Define stack, herramientas y equipo antes de pasar a Dirección.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.desarrollo.fase.guardar', $proyecto) }}">
        <div class="form-grid form-grid--2">
            <div class="field">
                <label class="field__label" for="stack_tecnologico">Stack tecnológico</label>
                <input class="input" type="text" name="stack_tecnologico" id="stack_tecnologico" data-autosave value="{{ $organizacion->stack_tecnologico }}" placeholder="Laravel, React, WordPress...">
            </div>
            <div class="field">
                <label class="field__label" for="herramientas">Herramientas a usar</label>
                <input class="input" type="text" name="herramientas" id="herramientas" data-autosave value="{{ $organizacion->herramientas }}" placeholder="Figma, GitHub, Trello...">
            </div>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="arquitectura">Arquitectura del proyecto / estructura de carpetas</label>
            <textarea class="textarea" name="arquitectura" id="arquitectura" data-autosave>{{ $organizacion->arquitectura }}</textarea>
        </div>

        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="url_repositorio">URL del repositorio</label>
                <input class="input" type="text" name="url_repositorio" id="url_repositorio" data-autosave value="{{ $organizacion->url_repositorio }}" placeholder="https://github.com/...">
            </div>
            <div class="field">
                <label class="field__label" for="url_staging">URL de staging</label>
                <input class="input" type="text" name="url_staging" id="url_staging" data-autosave value="{{ $organizacion->url_staging }}">
            </div>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label">Equipo asignado</label>
            <div data-equipo-rows>
                @foreach ($equipo as $miembro)
                    <div class="equipo-row">
                        <input class="input" type="text" placeholder="Nombre" data-equipo-nombre value="{{ $miembro['nombre'] ?? '' }}">
                        <input class="input" type="text" placeholder="Rol" data-equipo-rol value="{{ $miembro['rol'] ?? '' }}">
                        <button type="button" class="btn--icon" data-equipo-remove title="Quitar"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn--ghost" data-equipo-add style="margin-top: var(--space-2);">
                <i class="fa-solid fa-plus"></i> Agregar integrante
            </button>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de organización</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\ProyectoOrganizacion::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($organizacion->checklist[$key] ?? false)>
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
        <form method="POST" action="{{ route('admin.desarrollo.fase.aprobar', $proyecto) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--primary" data-aprobar-btn @disabled(! $checklistCompleto)>
                <i class="fa-solid fa-check-double"></i> Aprobar Fase y Continuar a Dirección
            </button>
        </form>
        <form method="POST" action="{{ route('admin.desarrollo.fase.retroceder', $proyecto) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Planeación?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar la aprobación.</span>
        @endunless
    </div>
</div>
