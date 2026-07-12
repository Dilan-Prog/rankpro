{{-- Shared by create.blade.php and edit.blade.php --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="nombre">Nombre</label>
        <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}" required>
        @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="empresa">Empresa</label>
        <input class="input" type="text" name="empresa" id="empresa" value="{{ old('empresa', $cliente->empresa ?? '') }}">
        @error('empresa')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="contacto_nombre">Nombre de contacto</label>
        <input class="input" type="text" name="contacto_nombre" id="contacto_nombre" value="{{ old('contacto_nombre', $cliente->contacto_nombre ?? '') }}">
        @error('contacto_nombre')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (['activo' => 'Activo', 'pausado' => 'Pausado', 'cancelado' => 'Cancelado'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $cliente->estado->value ?? 'activo') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="email">Email</label>
        <input class="input" type="email" name="email" id="email" value="{{ old('email', $cliente->email ?? '') }}">
        @error('email')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="telefono">Teléfono / WhatsApp</label>
        <input class="input" type="tel" name="telefono" id="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}">
        @error('telefono')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_inicio_contrato">Inicio de contrato</label>
        <input class="input" type="date" name="fecha_inicio_contrato" id="fecha_inicio_contrato" value="{{ old('fecha_inicio_contrato', optional($cliente->fecha_inicio_contrato ?? null)->format('Y-m-d')) }}">
        @error('fecha_inicio_contrato')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_renovacion_contrato">Renovación de contrato</label>
        <input class="input" type="date" name="fecha_renovacion_contrato" id="fecha_renovacion_contrato" value="{{ old('fecha_renovacion_contrato', optional($cliente->fecha_renovacion_contrato ?? null)->format('Y-m-d')) }}">
        @error('fecha_renovacion_contrato')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="forma_pago">Forma de pago</label>
        <select class="select" name="forma_pago" id="forma_pago">
            <option value="">— Sin especificar —</option>
            @foreach (['mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'anual' => 'Anual'] as $value => $label)
                <option value="{{ $value }}" @selected(old('forma_pago', $cliente->forma_pago ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('forma_pago')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="metodo_pago">Método de pago</label>
        <select class="select" name="metodo_pago" id="metodo_pago">
            <option value="">— Sin especificar —</option>
            @foreach (['transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo', 'paypal' => 'PayPal'] as $value => $label)
                <option value="{{ $value }}" @selected(old('metodo_pago', $cliente->metodo_pago ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('metodo_pago')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="notas">Notas internas</label>
    <textarea class="textarea" name="notas" id="notas">{{ old('notas', $cliente->notas ?? '') }}</textarea>
    @error('notas')<span class="field__error">{{ $message }}</span>@enderror
</div>
