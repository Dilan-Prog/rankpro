{{-- Shared by create.blade.php and edit.blade.php --}}
<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="keyword">Keyword</label>
        <input class="input" type="text" name="keyword" id="keyword" value="{{ old('keyword', $keyword->keyword ?? '') }}" required>
        @error('keyword')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="cliente_id">Cliente</label>
        <select class="select" name="cliente_id" id="cliente_id" required>
            <option value="">— Selecciona un cliente —</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('cliente_id', $keyword->cliente_id ?? '') === $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="tipo">Tipo</label>
        <select class="select" name="tipo" id="tipo" required>
            @foreach (['principal' => 'Principal', 'secundaria' => 'Secundaria', 'long_tail' => 'Long Tail', 'lsi' => 'LSI'] as $value => $label)
                <option value="{{ $value }}" @selected(old('tipo', $keyword->tipo ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach (['en_uso' => 'En Uso', 'seguimiento' => 'Seguimiento', 'descartada' => 'Descartada'] as $value => $label)
                <option value="{{ $value }}" @selected(old('estado', $keyword->estado->value ?? 'seguimiento') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="volumen_busqueda">Volumen de búsqueda</label>
        <input class="input" type="number" min="0" name="volumen_busqueda" id="volumen_busqueda" value="{{ old('volumen_busqueda', $keyword->volumen_busqueda ?? '') }}">
        @error('volumen_busqueda')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="dificultad">Dificultad (0-100)</label>
        <input class="input" type="number" min="0" max="100" name="dificultad" id="dificultad" value="{{ old('dificultad', $keyword->dificultad ?? '') }}">
        @error('dificultad')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="cpc_estimado">CPC estimado (MXN)</label>
        <input class="input" type="number" step="0.01" min="0" name="cpc_estimado" id="cpc_estimado" value="{{ old('cpc_estimado', $keyword->cpc_estimado ?? '') }}">
        @error('cpc_estimado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="intencion">Intención</label>
        <select class="select" name="intencion" id="intencion">
            <option value="">— Sin especificar —</option>
            @foreach (['informacional' => 'Informacional', 'transaccional' => 'Transaccional', 'navegacional' => 'Navegacional'] as $value => $label)
                <option value="{{ $value }}" @selected(old('intencion', $keyword->intencion ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('intencion')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="url_asignada">URL asignada</label>
        <input class="input" type="text" name="url_asignada" id="url_asignada" value="{{ old('url_asignada', $keyword->url_asignada ?? '') }}" placeholder="/pagina-destino">
        @error('url_asignada')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="posicion_actual">Posición actual</label>
        <input class="input" type="number" min="0" name="posicion_actual" id="posicion_actual" value="{{ old('posicion_actual', $keyword->posicion_actual ?? '') }}">
        @error('posicion_actual')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="herramienta_origen">Herramienta de origen</label>
        <select class="select" name="herramienta_origen" id="herramienta_origen">
            <option value="">— Sin especificar —</option>
            @foreach (['semrush' => 'Semrush', 'ahrefs' => 'Ahrefs', 'google_kp' => 'Google Keyword Planner', 'otro' => 'Otro'] as $value => $label)
                <option value="{{ $value }}" @selected(old('herramienta_origen', $keyword->herramienta_origen ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('herramienta_origen')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_incorporacion">Fecha de incorporación</label>
        <input class="input" type="date" name="fecha_incorporacion" id="fecha_incorporacion" value="{{ old('fecha_incorporacion', optional($keyword->fecha_incorporacion ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
        @error('fecha_incorporacion')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="notas">Notas</label>
    <textarea class="textarea" name="notas" id="notas">{{ old('notas', $keyword->notas ?? '') }}</textarea>
    @error('notas')<span class="field__error">{{ $message }}</span>@enderror
</div>
