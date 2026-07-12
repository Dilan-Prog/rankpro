{{-- Shared by create.blade.php and edit.blade.php --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $cliente)
                <option value="{{ $cliente->id }}" @selected((int) old('cliente_id', $finanza->cliente_id ?? '') === $cliente->id)>{{ $cliente->nombre }}</option>
            @endforeach
        </select>
        @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="servicio_id">Servicio (opcional)</label>
        <select class="select" name="servicio_id" id="servicio_id">
            <option value="">— Sin servicio asociado —</option>
            @foreach ($clientes as $cliente)
                @foreach ($cliente->servicios as $servicio)
                    <option value="{{ $servicio->id }}" data-cliente="{{ $cliente->id }}"
                        @selected((int) old('servicio_id', $finanza->servicio_id ?? '') === $servicio->id)>
                        {{ $servicio->nombre }}
                    </option>
                @endforeach
            @endforeach
        </select>
        @error('servicio_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="concepto">Concepto</label>
    <input class="input" type="text" name="concepto" id="concepto" value="{{ old('concepto', $finanza->concepto ?? '') }}" required placeholder="Ej. SEO + Google Ads — Julio 2025">
    @error('concepto')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="tipo">Tipo</label>
        <select class="select" name="tipo" id="tipo" required>
            <option value="ingreso" @selected(old('tipo', $finanza->tipo ?? 'ingreso') === 'ingreso')>Ingreso</option>
            <option value="gasto" @selected(old('tipo', $finanza->tipo ?? '') === 'gasto')>Gasto</option>
        </select>
        @error('tipo')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="monto">Monto (MXN)</label>
        <input class="input" type="number" step="0.01" min="0" name="monto" id="monto" value="{{ old('monto', $finanza->monto ?? '') }}" required>
        @error('monto')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4); max-width: 240px;">
    <label class="field__label" for="estado">Estado</label>
    <select class="select" name="estado" id="estado" required>
        @foreach (['pagado' => 'Pagado', 'pendiente' => 'Pendiente', 'vencido' => 'Vencido'] as $value => $label)
            <option value="{{ $value }}" @selected(old('estado', $finanza->estado->value ?? 'pendiente') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('estado')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="mes">Mes</label>
        <select class="select" name="mes" id="mes" required>
            @foreach (['1'=>'Enero','2'=>'Febrero','3'=>'Marzo','4'=>'Abril','5'=>'Mayo','6'=>'Junio','7'=>'Julio','8'=>'Agosto','9'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'] as $value => $label)
                <option value="{{ $value }}" @selected((int) old('mes', $finanza->mes ?? now()->month) === (int) $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('mes')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="anio">Año</label>
        <input class="input" type="number" min="2000" max="2100" name="anio" id="anio" value="{{ old('anio', $finanza->anio ?? now()->year) }}" required>
        @error('anio')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_emision">Fecha de emisión</label>
        <input class="input" type="date" name="fecha_emision" id="fecha_emision" value="{{ old('fecha_emision', optional($finanza->fecha_emision ?? null)->format('Y-m-d')) }}">
        @error('fecha_emision')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_vencimiento">Fecha de vencimiento</label>
        <input class="input" type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="{{ old('fecha_vencimiento', optional($finanza->fecha_vencimiento ?? null)->format('Y-m-d')) }}">
        @error('fecha_vencimiento')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4); max-width: 240px;">
    <label class="field__label" for="fecha_pago">Fecha de pago (si ya se pagó)</label>
    <input class="input" type="date" name="fecha_pago" id="fecha_pago" value="{{ old('fecha_pago', optional($finanza->fecha_pago ?? null)->format('Y-m-d')) }}">
    @error('fecha_pago')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="notas">Notas</label>
    <textarea class="textarea" name="notas" id="notas">{{ old('notas', $finanza->notas ?? '') }}</textarea>
    @error('notas')<span class="field__error">{{ $message }}</span>@enderror
</div>
