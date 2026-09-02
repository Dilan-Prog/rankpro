{{-- Editar Proyecto — datos generales solamente. Los campos por fase (diagnóstico, diseño, implementación, reporte) viven en los paneles de show.blade.php. --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}" @selected((int) old('cliente_id', $proyecto->cliente_id) === $cliente->id)>{{ $cliente->nombre }}</option>
            @endforeach
        </select>
        @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="servicio_id">Servicio de Automatización asociado</label>
        <select class="select" name="servicio_id" id="servicio_id" required>
            <option value="">— Selecciona un servicio —</option>
            @foreach ($serviciosAutomatizacion as $servicio)
                <option value="{{ $servicio->id }}" @selected((int) old('servicio_id', $proyecto->servicio_id) === $servicio->id)>{{ $servicio->nombre }}</option>
            @endforeach
        </select>
        <span class="field__hint">Solo aparecen servicios de tipo Automatización de este cliente.</span>
        @error('servicio_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="nombre">Nombre del proyecto</label>
        <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $proyecto->nombre) }}" required>
        @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $proyecto->estado->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4); max-width: 240px;">
    <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
    <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', optional($proyecto->fecha_inicio)->format('Y-m-d')) }}">
    @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="notas">Notas</label>
    <textarea class="textarea" name="notas" id="notas">{{ old('notas', $proyecto->notas) }}</textarea>
    @error('notas')<span class="field__error">{{ $message }}</span>@enderror
</div>
