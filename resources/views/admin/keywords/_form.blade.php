{{--
    Shared by keywords/index.blade.php's #keywordFormModal (create + edit —
    fields are populated client-side by keywords.js from a row's data-* JSON,
    same pattern as admin.servicios._form). $keyword is always null here since
    this module has no full-page create/edit anymore; old() only fires on a
    stale 422-redirected session, defaults otherwise apply.

    Orden del diseño: palabra clave, lista/tipo, métricas, intención/fuente y URL.
    El cliente sólo se pide cuando la keyword NO tiene lista (con lista el
    cliente es el de la lista, y keywords.js lo sincroniza), y estado, fecha y
    notas viven en "Más datos" para no cargar el formulario con campos que casi
    nunca se tocan. Los `name` de todos los campos siguen siendo los mismos.
--}}
<div class="field">
    <label class="field__label" for="kw_keyword">Palabra clave</label>
    <input class="input" type="text" name="keyword" id="kw_keyword" value="{{ old('keyword', $keyword?->keyword ?? '') }}" required placeholder="Ej. implantes dentales cdmx" autocomplete="off">
    <span class="field__error" data-error-for="keyword">@error('keyword'){{ $message }}@enderror</span>
</div>

<div class="form-grid form-grid--2 kwx-fila">
    <div class="field">
        <label class="field__label" for="kw_lista_id">Lista</label>
        <select class="select" name="lista_id" id="kw_lista_id">
            <option value="">— Sin lista —</option>
            @foreach ($listas as $l)
                <option value="{{ $l['id'] }}" data-cliente="{{ $l['cliente_id'] }}"
                    @selected((int) old('lista_id', $keyword?->lista_id ?? '') === $l['id'])>
                    {{ $l['nombre'] }} ({{ $l['cliente'] }})
                </option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="lista_id">@error('lista_id'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="kw_tipo">Tipo</label>
        <select class="select" name="tipo" id="kw_tipo" required>
            @foreach (['principal' => 'Principal', 'secundaria' => 'Secundaria', 'long_tail' => 'Long Tail', 'lsi' => 'LSI'] as $value => $label)
                <option value="{{ $value }}" @selected(old('tipo', $keyword?->tipo ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="tipo">@error('tipo'){{ $message }}@enderror</span>
    </div>
</div>

<div class="field kwx-fila" data-kw-cliente-field hidden>
    <label class="field__label" for="kw_cliente_id">Cliente</label>
    <select class="select" name="cliente_id" id="kw_cliente_id" required>
        <option value="">— Selecciona un cliente —</option>
        @foreach ($clientes as $id => $nombre)
            <option value="{{ $id }}" @selected((int) old('cliente_id', $keyword?->cliente_id ?? '') === $id)>{{ $nombre }}</option>
        @endforeach
    </select>
    <span class="field__hint">Una keyword sin lista necesita saber de qué cliente es.</span>
    <span class="field__error" data-error-for="cliente_id">@error('cliente_id'){{ $message }}@enderror</span>
</div>

<div class="kwx-grid-4 kwx-fila">
    <div class="field">
        <label class="field__label" for="kw_volumen_busqueda">Volumen</label>
        <input class="input u-mono" type="number" min="0" name="volumen_busqueda" id="kw_volumen_busqueda" value="{{ old('volumen_busqueda', $keyword?->volumen_busqueda ?? '') }}" placeholder="0">
        <span class="field__error" data-error-for="volumen_busqueda">@error('volumen_busqueda'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="kw_dificultad">KD</label>
        <input class="input u-mono" type="number" min="0" max="100" name="dificultad" id="kw_dificultad" value="{{ old('dificultad', $keyword?->dificultad ?? '') }}" placeholder="0-100">
        <span class="field__error" data-error-for="dificultad">@error('dificultad'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="kw_cpc_estimado">CPC est. (MXN)</label>
        <input class="input u-mono" type="number" step="0.01" min="0" name="cpc_estimado" id="kw_cpc_estimado" value="{{ old('cpc_estimado', $keyword?->cpc_estimado ?? '') }}" placeholder="0.00">
        <span class="field__error" data-error-for="cpc_estimado">@error('cpc_estimado'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="kw_posicion_actual">Posición</label>
        <input class="input u-mono" type="number" min="0" name="posicion_actual" id="kw_posicion_actual" value="{{ old('posicion_actual', $keyword?->posicion_actual ?? '') }}" placeholder="—">
        <span class="field__error" data-error-for="posicion_actual">@error('posicion_actual'){{ $message }}@enderror</span>
    </div>
</div>

<div class="form-grid form-grid--2 kwx-fila">
    <div class="field">
        <label class="field__label" for="kw_intencion">Intención</label>
        <select class="select" name="intencion" id="kw_intencion">
            <option value="">— Sin especificar —</option>
            @foreach (['informacional' => 'Informacional', 'transaccional' => 'Transaccional', 'navegacional' => 'Navegacional'] as $value => $label)
                <option value="{{ $value }}" @selected(old('intencion', $keyword?->intencion ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="intencion">@error('intencion'){{ $message }}@enderror</span>
    </div>
    <div class="field">
        <label class="field__label" for="kw_herramienta_origen">Fuente</label>
        <select class="select" name="herramienta_origen" id="kw_herramienta_origen">
            <option value="">— Sin especificar —</option>
            @foreach (['semrush' => 'Semrush', 'ahrefs' => 'Ahrefs', 'google_kp' => 'Google Keyword Planner', 'otro' => 'Otro'] as $value => $label)
                <option value="{{ $value }}" @selected(old('herramienta_origen', $keyword?->herramienta_origen ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="field__error" data-error-for="herramienta_origen">@error('herramienta_origen'){{ $message }}@enderror</span>
    </div>
</div>

<div class="field kwx-fila">
    <label class="field__label" for="kw_url_asignada">URL destino</label>
    <input class="input u-mono" type="text" name="url_asignada" id="kw_url_asignada" value="{{ old('url_asignada', $keyword?->url_asignada ?? '') }}" placeholder="/implantes-dentales" autocomplete="off">
    <span class="field__error" data-error-for="url_asignada">@error('url_asignada'){{ $message }}@enderror</span>
</div>

<details class="kwx-mas kwx-fila" data-kw-mas>
    <summary>Más datos <span class="kwx-tenue">(estado, fecha de incorporación y notas)</span></summary>
    <div class="form-grid form-grid--2 kwx-fila">
        <div class="field">
            <label class="field__label" for="kw_estado">Estado</label>
            <select class="select" name="estado" id="kw_estado" required>
                @foreach (['en_uso' => 'En Uso', 'seguimiento' => 'Seguimiento', 'descartada' => 'Descartada'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('estado', $keyword?->estado?->value ?? 'seguimiento') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="field__error" data-error-for="estado">@error('estado'){{ $message }}@enderror</span>
        </div>
        <div class="field">
            <label class="field__label" for="kw_fecha_incorporacion">Fecha de incorporación</label>
            <input class="input" type="date" name="fecha_incorporacion" id="kw_fecha_incorporacion" value="{{ old('fecha_incorporacion', optional($keyword?->fecha_incorporacion)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
            <span class="field__error" data-error-for="fecha_incorporacion">@error('fecha_incorporacion'){{ $message }}@enderror</span>
        </div>
    </div>
    <div class="field kwx-fila">
        <label class="field__label" for="kw_notas">Notas</label>
        <textarea class="textarea" name="notas" id="kw_notas">{{ old('notas', $keyword?->notas ?? '') }}</textarea>
        <span class="field__error" data-error-for="notas">@error('notas'){{ $message }}@enderror</span>
    </div>
</details>
