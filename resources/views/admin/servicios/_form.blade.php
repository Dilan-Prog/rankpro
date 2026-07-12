{{-- Shared by create.blade.php and edit.blade.php --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('cliente_id', $servicio->cliente_id ?? '') === $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="tipo">Tipo</label>
        <select class="select" name="tipo" id="tipo" required>
            @foreach (['seo' => 'SEO', 'google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'tiktok_ads' => 'TikTok Ads', 'rediseno' => 'Rediseño', 'software' => 'Software'] as $value => $label)
                <option value="{{ $value }}" @selected(old('tipo', $servicio->tipo ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="nombre">Nombre del servicio</label>
    <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $servicio->nombre ?? '') }}" required placeholder="Ej. SEO Local Odontología">
    @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="descripcion">Descripción</label>
    <textarea class="textarea" name="descripcion" id="descripcion">{{ old('descripcion', $servicio->descripcion ?? '') }}</textarea>
    @error('descripcion')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="precio_mensual">Precio mensual (MXN)</label>
        <input class="input" type="number" step="0.01" min="0" name="precio_mensual" id="precio_mensual" value="{{ old('precio_mensual', $servicio->precio_mensual ?? '0') }}" required>
        @error('precio_mensual')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (['activo' => 'Activo', 'pausado' => 'Pausado', 'cancelado' => 'Cancelado'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $servicio->estado->value ?? 'activo') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
        <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', optional($servicio->fecha_inicio ?? null)->format('Y-m-d')) }}">
        @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_fin">Fecha de fin</label>
        <input class="input" type="date" name="fecha_fin" id="fecha_fin" value="{{ old('fecha_fin', optional($servicio->fecha_fin ?? null)->format('Y-m-d')) }}">
        @error('fecha_fin')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>
