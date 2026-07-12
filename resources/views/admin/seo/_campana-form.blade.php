{{-- Editar Campaña SEO — general data only. Phase-specific fields (audit metrics, strategy, execution, reports) live in show.blade.php's phase panels. --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}" @selected((int) old('cliente_id', $campana->cliente_id) === $cliente->id)>{{ $cliente->nombre }}</option>
            @endforeach
        </select>
        @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="servicio_id">Servicio SEO asociado</label>
        <select class="select" name="servicio_id" id="servicio_id" required>
            <option value="">— Selecciona un servicio —</option>
            @foreach ($serviciosSeo as $servicio)
                <option value="{{ $servicio->id }}" @selected((int) old('servicio_id', $campana->servicio_id) === $servicio->id)>{{ $servicio->nombre }}</option>
            @endforeach
        </select>
        <span class="field__hint">Solo aparecen servicios de tipo SEO de este cliente.</span>
        @error('servicio_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="nombre">Nombre de la campaña</label>
        <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $campana->nombre) }}" required>
        @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="url_sitio">URL del sitio</label>
        <input class="input" type="text" name="url_sitio" id="url_sitio" value="{{ old('url_sitio', $campana->url_sitio) }}" placeholder="https://ejemplo.com">
        @error('url_sitio')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $campana->estado->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
        <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', optional($campana->fecha_inicio)->format('Y-m-d')) }}">
        @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="notas">Notas</label>
    <textarea class="textarea" name="notas" id="notas">{{ old('notas', $campana->notas) }}</textarea>
    @error('notas')<span class="field__error">{{ $message }}</span>@enderror
</div>
