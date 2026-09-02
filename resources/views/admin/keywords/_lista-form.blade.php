{{-- Shared by index.blade.php's #listaFormModal (create + edit, fields filled client-side by keywords.js). $lista is always null — no full-page create/edit for this module. --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="kl_cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="kl_cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('cliente_id', $lista?->cliente_id ?? '') === $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="cliente_id">@error('cliente_id'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="kl_responsable_id">Responsable</label>
        <select class="select" name="responsable_id" id="kl_responsable_id">
            <option value="">— Sin asignar —</option>
            @foreach ($usuarios as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('responsable_id', $lista?->responsable_id ?? '') === $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="responsable_id">@error('responsable_id'){{ $message }}@enderror</span>
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="kl_nombre">Nombre de la lista</label>
    <input class="input" type="text" name="nombre" id="kl_nombre" value="{{ old('nombre', $lista?->nombre ?? '') }}" required placeholder="Ej. SEO Local — Servicios Dentales">
    <span class="field__error" data-error-for="nombre">@error('nombre'){{ $message }}@enderror</span>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="kl_canal">Canal</label>
        <input class="input" type="text" name="canal" id="kl_canal" value="{{ old('canal', $lista?->canal ?? '') }}" placeholder="Ej. SEO, Google Ads, SEO + Google Ads">
        <span class="field__error" data-error-for="canal">@error('canal'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="kl_estado">Estado</label>
        <select class="select" name="estado" id="kl_estado" required>
            @foreach (['en_uso' => 'En Uso', 'seguimiento' => 'Seguimiento', 'descartada' => 'Descartada'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $lista?->estado?->value ?? 'en_uso') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="estado">@error('estado'){{ $message }}@enderror</span>
    </div>
</div>
