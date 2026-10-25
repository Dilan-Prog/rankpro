{{-- Campos del modal crear/editar cliente (index.blade.php, #clientFormModal); estilo .form-moderno en clientes.css --}}

<div class="form-moderno__rejilla form-moderno__rejilla--2">
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="nombre">Nombre <span class="form-moderno__requerido" aria-hidden="true">*</span></label>
        <input class="form-moderno__control" type="text" name="nombre" id="nombre" value="{{ old('nombre', $cliente?->nombre ?? '') }}" placeholder="Nombre del cliente" aria-describedby="error-nombre" required>
        <span class="form-moderno__error" id="error-nombre" data-error-for="nombre" role="alert">@error('nombre'){{ $message }}@enderror</span>
    </div>
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="empresa">Empresa</label>
        <input class="form-moderno__control" type="text" name="empresa" id="empresa" value="{{ old('empresa', $cliente?->empresa ?? '') }}" placeholder="Ej. FashionMX" aria-describedby="error-empresa">
        <span class="form-moderno__error" id="error-empresa" data-error-for="empresa" role="alert">@error('empresa'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-moderno__rejilla form-moderno__rejilla--3">
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="contacto_nombre">Contacto</label>
        <input class="form-moderno__control" type="text" name="contacto_nombre" id="contacto_nombre" value="{{ old('contacto_nombre', $cliente?->contacto_nombre ?? '') }}" placeholder="Nombre y apellido" aria-describedby="error-contacto_nombre">
        <span class="form-moderno__error" id="error-contacto_nombre" data-error-for="contacto_nombre" role="alert">@error('contacto_nombre'){{ $message }}@enderror</span>
    </div>
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="email">Email</label>
        <input class="form-moderno__control" type="email" name="email" id="email" value="{{ old('email', $cliente?->email ?? '') }}" placeholder="contacto@empresa.com" aria-describedby="error-email">
        <span class="form-moderno__error" id="error-email" data-error-for="email" role="alert">@error('email'){{ $message }}@enderror</span>
    </div>
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="telefono">WhatsApp</label>
        <input class="form-moderno__control" type="tel" name="telefono" id="telefono" value="{{ old('telefono', $cliente?->telefono ?? '') }}" placeholder="+52 55 0000 0000" aria-describedby="error-telefono">
        <span class="form-moderno__error" id="error-telefono" data-error-for="telefono" role="alert">@error('telefono'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-moderno__rejilla form-moderno__rejilla--3">
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="estado">Estado</label>
        <select class="form-moderno__control form-moderno__control--select" name="estado" id="estado" aria-describedby="error-estado" required>
            @foreach (\App\Enums\EstadoClienteServicio::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('estado', $cliente?->estado?->value ?? 'activo') === $case->value)>
                    {{ \App\Support\Labels::estadoCliente($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="form-moderno__error" id="error-estado" data-error-for="estado" role="alert">@error('estado'){{ $message }}@enderror</span>
    </div>
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="metodo_pago">Forma de pago</label>
        <select class="form-moderno__control form-moderno__control--select" name="metodo_pago" id="metodo_pago" aria-describedby="error-metodo_pago">
            <option value="">Sin especificar</option>
            @foreach (\App\Enums\MetodoPago::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('metodo_pago', $cliente?->metodo_pago?->value ?? '') === $case->value)>
                    {{ \App\Support\Labels::metodoPago($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="form-moderno__error" id="error-metodo_pago" data-error-for="metodo_pago" role="alert">@error('metodo_pago'){{ $message }}@enderror</span>
    </div>
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="forma_pago">Frecuencia</label>
        <select class="form-moderno__control form-moderno__control--select" name="forma_pago" id="forma_pago" aria-describedby="error-forma_pago">
            <option value="">Sin especificar</option>
            @foreach (\App\Enums\FormaPago::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('forma_pago', $cliente?->forma_pago?->value ?? '') === $case->value)>
                    {{ \App\Support\Labels::formaPago($case->value) }}
                </option>
            @endforeach
        </select>
        <span class="form-moderno__error" id="error-forma_pago" data-error-for="forma_pago" role="alert">@error('forma_pago'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-moderno__rejilla form-moderno__rejilla--3">
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="fecha_inicio_contrato">Inicio de contrato</label>
        <input class="form-moderno__control" type="date" name="fecha_inicio_contrato" id="fecha_inicio_contrato" value="{{ old('fecha_inicio_contrato', optional($cliente?->fecha_inicio_contrato ?? null)->format('Y-m-d')) }}" aria-describedby="error-fecha_inicio_contrato">
        <span class="form-moderno__error" id="error-fecha_inicio_contrato" data-error-for="fecha_inicio_contrato" role="alert">@error('fecha_inicio_contrato'){{ $message }}@enderror</span>
    </div>
    <div class="form-moderno__campo">
        <label class="form-moderno__etiqueta" for="fecha_renovacion_contrato">Vencimiento</label>
        <input class="form-moderno__control" type="date" name="fecha_renovacion_contrato" id="fecha_renovacion_contrato" value="{{ old('fecha_renovacion_contrato', optional($cliente?->fecha_renovacion_contrato ?? null)->format('Y-m-d')) }}" aria-describedby="error-fecha_renovacion_contrato">
        <span class="form-moderno__error" id="error-fecha_renovacion_contrato" data-error-for="fecha_renovacion_contrato" role="alert">@error('fecha_renovacion_contrato'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-moderno__campo">
    <label class="form-moderno__etiqueta" for="notas">Notas internas</label>
    <textarea class="form-moderno__control form-moderno__control--area" name="notas" id="notas" rows="3" placeholder="Acuerdos, cadencia de reportes, expectativas…" aria-describedby="error-notas">{{ old('notas', $cliente?->notas ?? '') }}</textarea>
    <span class="form-moderno__error" id="error-notas" data-error-for="notas" role="alert">@error('notas'){{ $message }}@enderror</span>
</div>
