{{-- Editar Campaña — general data only. Phase-specific fields (briefing, configuración, lanzamiento, reporte) live in show.blade.php's phase panels. --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}" @selected((int) old('cliente_id', $campana->cliente_id ?? '') === $cliente->id)>{{ $cliente->nombre }}</option>
            @endforeach
        </select>
        @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="servicio_id">Servicio</label>
        <select class="select" name="servicio_id" id="servicio_id" required>
            <option value="">— Selecciona un cliente primero —</option>
            @foreach ($clientes as $cliente)
                @foreach ($cliente->servicios as $servicio)
                    <option value="{{ $servicio->id }}" data-cliente="{{ $cliente->id }}"
                        @selected((int) old('servicio_id', $campana->servicio_id ?? '') === $servicio->id)>
                        {{ $servicio->nombre }}
                    </option>
                @endforeach
            @endforeach
        </select>
        @error('servicio_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="nombre">Nombre de la campaña</label>
    <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $campana->nombre ?? '') }}" required>
    @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="plataforma">Plataforma</label>
        <select class="select" name="plataforma" id="plataforma" required>
            @foreach (['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'tiktok_ads' => 'TikTok Ads'] as $value => $label)
                <option value="{{ $value }}" @selected(old('plataforma', $campana->plataforma ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('plataforma')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="objetivo">Objetivo</label>
        <select class="select" name="objetivo" id="objetivo" required>
            @foreach (['leads' => 'Leads', 'ventas' => 'Ventas', 'trafico' => 'Tráfico', 'branding' => 'Branding'] as $value => $label)
                <option value="{{ $value }}" @selected(old('objetivo', $campana->objetivo ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('objetivo')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="presupuesto_mensual">Presupuesto mensual (MXN)</label>
        <input class="input" type="number" step="0.01" min="0" name="presupuesto_mensual" id="presupuesto_mensual" value="{{ old('presupuesto_mensual', $campana->presupuesto_mensual ?? '0') }}" required>
        @error('presupuesto_mensual')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $campana->estado->value ?? 'activa') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
        <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', optional($campana->fecha_inicio ?? null)->format('Y-m-d')) }}">
        @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_fin">Fecha de fin</label>
        <input class="input" type="date" name="fecha_fin" id="fecha_fin" value="{{ old('fecha_fin', optional($campana->fecha_fin ?? null)->format('Y-m-d')) }}">
        @error('fecha_fin')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="notas">Notas</label>
    <textarea class="textarea" name="notas" id="notas">{{ old('notas', $campana->notas ?? '') }}</textarea>
    @error('notas')<span class="field__error">{{ $message }}</span>@enderror
</div>
