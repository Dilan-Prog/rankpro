{{-- Shared by the create/edit servicio modal (see index.blade.php's #servicioFormModal) --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('cliente_id', $servicio?->cliente_id ?? '') === $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="cliente_id">@error('cliente_id'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="responsable_id">Responsable</label>
        <select class="select" name="responsable_id" id="responsable_id">
            <option value="">— Sin asignar —</option>
            @foreach ($usuarios as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('responsable_id', $servicio?->responsable_id ?? '') === $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="responsable_id">@error('responsable_id'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="tipo">Tipo</label>
        <select class="select" name="tipo" id="tipo" required>
            @foreach (\App\Enums\TipoServicio::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('tipo', $servicio?->tipo?->value ?? '') === $case->value)>
                    {{ \App\Support\Labels::servicioTipo($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="tipo">@error('tipo'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (\App\Enums\EstadoClienteServicio::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('estado', $servicio?->estado?->value ?? 'activo') === $case->value)>
                    {{ \App\Support\Labels::estadoCliente($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="estado">@error('estado'){{ $message }}@enderror</span>
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="nombre">Nombre del servicio</label>
    <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $servicio?->nombre ?? '') }}" required placeholder="Ej. SEO Local Odontología">
    <span class="field__error" data-error-for="nombre">@error('nombre'){{ $message }}@enderror</span>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="descripcion">Descripción</label>
    <textarea class="textarea" name="descripcion" id="descripcion">{{ old('descripcion', $servicio?->descripcion ?? '') }}</textarea>
    <span class="field__error" data-error-for="descripcion">@error('descripcion'){{ $message }}@enderror</span>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="precio_mensual">Precio mensual (MXN)</label>
    <input class="input" type="number" step="0.01" min="0" name="precio_mensual" id="precio_mensual" value="{{ old('precio_mensual', $servicio?->precio_mensual ?? '0') }}" required>
    <span class="field__error" data-error-for="precio_mensual">@error('precio_mensual'){{ $message }}@enderror</span>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
        <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', optional($servicio?->fecha_inicio)->format('Y-m-d')) }}">
        <span class="field__error" data-error-for="fecha_inicio">@error('fecha_inicio'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="fecha_fin">Fecha de fin</label>
        <input class="input" type="date" name="fecha_fin" id="fecha_fin" value="{{ old('fecha_fin', optional($servicio?->fecha_fin)->format('Y-m-d')) }}">
        <span class="field__error" data-error-for="fecha_fin">@error('fecha_fin'){{ $message }}@enderror</span>
    </div>
</div>
