{{-- Shared by the create/edit client modal (see index.blade.php's #clientFormModal) --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="nombre">Nombre</label>
        <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $cliente?->nombre ?? '') }}" required>
        <span class="field__error" data-error-for="nombre">@error('nombre'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="empresa">Empresa</label>
        <input class="input" type="text" name="empresa" id="empresa" value="{{ old('empresa', $cliente?->empresa ?? '') }}">
        <span class="field__error" data-error-for="empresa">@error('empresa'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="contacto_nombre">Nombre de contacto</label>
        <input class="input" type="text" name="contacto_nombre" id="contacto_nombre" value="{{ old('contacto_nombre', $cliente?->contacto_nombre ?? '') }}">
        <span class="field__error" data-error-for="contacto_nombre">@error('contacto_nombre'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (\App\Enums\EstadoClienteServicio::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('estado', $cliente?->estado?->value ?? 'activo') === $case->value)>
                    {{ \App\Support\Labels::estadoCliente($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="estado">@error('estado'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="email">Email</label>
        <input class="input" type="email" name="email" id="email" value="{{ old('email', $cliente?->email ?? '') }}">
        <span class="field__error" data-error-for="email">@error('email'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="telefono">Teléfono / WhatsApp</label>
        <input class="input" type="tel" name="telefono" id="telefono" value="{{ old('telefono', $cliente?->telefono ?? '') }}">
        <span class="field__error" data-error-for="telefono">@error('telefono'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_inicio_contrato">Inicio de contrato</label>
        <input class="input" type="date" name="fecha_inicio_contrato" id="fecha_inicio_contrato" value="{{ old('fecha_inicio_contrato', optional($cliente?->fecha_inicio_contrato ?? null)->format('Y-m-d')) }}">
        <span class="field__error" data-error-for="fecha_inicio_contrato">@error('fecha_inicio_contrato'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="fecha_renovacion_contrato">Renovación de contrato</label>
        <input class="input" type="date" name="fecha_renovacion_contrato" id="fecha_renovacion_contrato" value="{{ old('fecha_renovacion_contrato', optional($cliente?->fecha_renovacion_contrato ?? null)->format('Y-m-d')) }}">
        <span class="field__error" data-error-for="fecha_renovacion_contrato">@error('fecha_renovacion_contrato'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="forma_pago">Forma de pago</label>
        <select class="select" name="forma_pago" id="forma_pago">
            <option value="">— Sin especificar —</option>
            @foreach (\App\Enums\FormaPago::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('forma_pago', $cliente?->forma_pago?->value ?? '') === $case->value)>
                    {{ \App\Support\Labels::formaPago($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="forma_pago">@error('forma_pago'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="metodo_pago">Método de pago</label>
        <select class="select" name="metodo_pago" id="metodo_pago">
            <option value="">— Sin especificar —</option>
            @foreach (\App\Enums\MetodoPago::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('metodo_pago', $cliente?->metodo_pago?->value ?? '') === $case->value)>
                    {{ \App\Support\Labels::metodoPago($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="metodo_pago">@error('metodo_pago'){{ $message }}@enderror</span>
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="notas">Notas internas</label>
    <textarea class="textarea" name="notas" id="notas">{{ old('notas', $cliente?->notas ?? '') }}</textarea>
    <span class="field__error" data-error-for="notas">@error('notas'){{ $message }}@enderror</span>
</div>
