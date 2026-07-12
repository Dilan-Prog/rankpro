@php
    $control = $proyecto->control;
    $checklistCompleto = collect(\App\Models\ProyectoControl::CHECKLIST)
        ->keys()
        ->every(fn ($key) => (bool) ($control->checklist[$key] ?? false));
@endphp
<div class="card card--padded fase-panel" data-fase-panel="control">
    <div class="fase-panel__header">
        <h2 class="card__header-title">Fase 4 · Control / Cierre</h2>
        <span class="fase-panel__hint">Última fase antes de cerrar el proyecto. Registra bugs y QA más abajo.</span>
    </div>

    <form id="faseForm" data-fase-action="{{ route('admin.desarrollo.fase.guardar', $proyecto) }}">
        <div class="field">
            <label class="field__label" for="url_produccion">URL de producción</label>
            <input class="input" type="text" name="url_produccion" id="url_produccion" data-autosave value="{{ $control->url_produccion }}" placeholder="https://...">
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Entregables finales</h3>
        <div class="checkbox-group">
            <label class="checkbox-item">
                <input type="checkbox" name="credenciales_entregadas" data-autosave-toggle @checked($control->credenciales_entregadas)>
                Credenciales entregadas
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="manual_entregado" data-autosave-toggle @checked($control->manual_entregado)>
                Manual de usuario entregado
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="capacitacion_realizada" data-autosave-toggle @checked($control->capacitacion_realizada)>
                Capacitación realizada
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="pago_final_recibido" data-autosave-toggle @checked($control->pago_final_recibido)>
                Pago final recibido
            </label>
        </div>

        <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
            <div class="field">
                <label class="field__label" for="monto_pago_final">Monto del pago final (MXN)</label>
                <input class="input" type="number" step="0.01" min="0" name="monto_pago_final" id="monto_pago_final" data-autosave value="{{ $control->monto_pago_final }}">
            </div>
            <div class="field">
                <label class="field__label" for="fecha_entrega_real">Fecha de entrega real</label>
                <input class="input" type="date" name="fecha_entrega_real" id="fecha_entrega_real" data-autosave value="{{ optional($control->fecha_entrega_real)->format('Y-m-d') }}">
            </div>
        </div>

        <div class="field" style="margin-top: var(--space-4); max-width: 320px;">
            <label class="field__label" for="satisfaccion_cliente">Satisfacción del cliente (1-5)</label>
            <select class="select" name="satisfaccion_cliente" id="satisfaccion_cliente" data-autosave>
                <option value="">— Sin calificar —</option>
                @for ($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}" @selected($control->satisfaccion_cliente === $i)>{{ $i }} {{ $i === 1 ? 'estrella' : 'estrellas' }}</option>
                @endfor
            </select>
        </div>

        <div class="field" style="margin-top: var(--space-4);">
            <label class="field__label" for="notas_cierre">Notas de cierre</label>
            <textarea class="textarea" name="notas_cierre" id="notas_cierre" data-autosave>{{ $control->notas_cierre }}</textarea>
        </div>

        <h3 class="fase-form__section-title" style="margin-top: var(--space-5);">Checklist de cierre</h3>
        <div class="checkbox-group">
            @foreach (\App\Models\ProyectoControl::CHECKLIST as $key => $label)
                <label class="checkbox-item">
                    <input type="checkbox" data-checklist-item="{{ $key }}" @checked($control->checklist[$key] ?? false)>
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
                <i class="fa-solid fa-flag-checkered"></i> Aprobar y Cerrar Proyecto
            </button>
        </form>
        <form method="POST" action="{{ route('admin.desarrollo.fase.retroceder', $proyecto) }}" style="display:inline;" data-confirm="¿Retroceder a la fase de Dirección?">
            @csrf
            <button type="submit" class="btn btn--ghost"><i class="fa-solid fa-rotate-left"></i> Retroceder</button>
        </form>
        @unless ($checklistCompleto)
            <span class="field__hint">Completa el checklist para habilitar el cierre del proyecto.</span>
        @endunless
    </div>
</div>
