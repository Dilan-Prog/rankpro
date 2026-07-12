{{-- Editar Proyecto — general data only. Phase-specific fields live in show.blade.php's phase panels. --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="nombre">Nombre del proyecto</label>
        <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $proyecto->nombre) }}" required>
        @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('cliente_id', $proyecto->cliente_id) === $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="tipo">Tipo</label>
        <select class="select" name="tipo" id="tipo" required>
            @foreach (['web_nueva' => 'Sitio Web Nuevo', 'rediseno' => 'Rediseño', 'software' => 'Software a Medida', 'landing' => 'Landing Page'] as $value => $label)
                <option value="{{ $value }}" @selected(old('tipo', $proyecto->tipo) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado del proyecto</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (['activo' => 'Activo', 'pausado' => 'Pausado', 'cancelado' => 'Cancelado', 'cerrado' => 'Cerrado'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $proyecto->estado->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="descripcion">Descripción general / briefing</label>
    <textarea class="textarea" name="descripcion" id="descripcion">{{ old('descripcion', $proyecto->descripcion) }}</textarea>
    @error('descripcion')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="responsable">Responsable interno</label>
        <input class="input" type="text" name="responsable" id="responsable" value="{{ old('responsable', $proyecto->responsable) }}">
        @error('responsable')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="forma_pago">Forma de pago</label>
        <select class="select" name="forma_pago" id="forma_pago">
            <option value="">— Sin definir —</option>
            @foreach (['mensual' => 'Mensual', 'etapas' => 'Por Etapas', 'unico' => 'Pago Único'] as $value => $label)
                <option value="{{ $value }}" @selected(old('forma_pago', $proyecto->forma_pago?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('forma_pago')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="presupuesto">Presupuesto acordado (MXN)</label>
        <input class="input" type="number" step="0.01" min="0" name="presupuesto" id="presupuesto" value="{{ old('presupuesto', $proyecto->presupuesto) }}" required>
        @error('presupuesto')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="anticipo">Anticipo recibido (MXN)</label>
        <input class="input" type="number" step="0.01" min="0" name="anticipo" id="anticipo" value="{{ old('anticipo', $proyecto->anticipo) }}">
        @error('anticipo')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4); max-width: 260px;">
    <label class="field__label" for="pagos_recibidos">Pagos recibidos totales (MXN)</label>
    <input class="input" type="number" step="0.01" min="0" name="pagos_recibidos" id="pagos_recibidos" value="{{ old('pagos_recibidos', $proyecto->pagos_recibidos) }}" required>
    @error('pagos_recibidos')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
        <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', optional($proyecto->fecha_inicio)->format('Y-m-d')) }}">
        @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_entrega_estimada">Entrega estimada</label>
        <input class="input" type="date" name="fecha_entrega_estimada" id="fecha_entrega_estimada" value="{{ old('fecha_entrega_estimada', optional($proyecto->fecha_entrega_estimada)->format('Y-m-d')) }}">
        @error('fecha_entrega_estimada')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4); max-width: 240px;">
    <label class="field__label" for="fecha_entrega_real">Entrega real (si ya terminó)</label>
    <input class="input" type="date" name="fecha_entrega_real" id="fecha_entrega_real" value="{{ old('fecha_entrega_real', optional($proyecto->fecha_entrega_real)->format('Y-m-d')) }}">
    @error('fecha_entrega_real')<span class="field__error">{{ $message }}</span>@enderror
</div>
