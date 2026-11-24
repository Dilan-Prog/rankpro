{{-- Una red. Espera $i (índice o __I__ en la plantilla), $fila y $catalogo. --}}
<li class="cfg-red" data-red-fila>
    <div class="cfg-red__cabecera">
        <fieldset class="cfg-red__iconos">
            <legend class="field__label">Icono</legend>
            <div class="cfg-red__rejilla">
                @foreach ($catalogo as $clave => $red)
                    <label class="cfg-red__icono" title="{{ $red['nombre'] }}">
                        <input type="radio" name="redes[{{ $i }}][red]" value="{{ $clave }}" data-ejemplo="{{ $red['ejemplo'] }}" @checked(($fila['red'] ?? null) === $clave)>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $red['icon'] !!}</svg>
                            <span class="cfg-red__icono-nombre">{{ $red['nombre'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error("redes.$i.red")<span class="field__error">{{ $message }}</span>@enderror
        </fieldset>
    </div>

    <div class="form-grid form-grid--2">
        <div class="field">
            <label class="field__label" for="red_url_{{ $i }}">Enlace</label>
            <input class="input" type="text" inputmode="url" name="redes[{{ $i }}][url]" id="red_url_{{ $i }}" maxlength="255"
                   placeholder="{{ $catalogo[$fila['red'] ?? '']['ejemplo'] ?? 'https://…' }}" value="{{ $fila['url'] ?? '' }}" data-red-url>
            @error("redes.$i.url")<span class="field__error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="field__label" for="red_nombre_{{ $i }}">Nombre (opcional)</label>
            <input class="input" type="text" name="redes[{{ $i }}][nombre]" id="red_nombre_{{ $i }}" maxlength="60" value="{{ $fila['nombre'] ?? '' }}">
            <p class="field__hint">Lo leen los lectores de pantalla. Obligatorio con «Otro enlace».</p>
            @error("redes.$i.nombre")<span class="field__error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="cfg-red__pie">
        <label class="cfg-switch">
            <input type="checkbox" name="redes[{{ $i }}][activa]" value="1" @checked($fila['activa'] ?? true)>
            <span class="cfg-switch__pista" aria-hidden="true"></span>
            <span>Mostrar en el pie</span>
        </label>
        <div class="cfg-red__acciones">
            <button type="button" class="btn btn--secondary btn--sm" data-red-subir aria-label="Subir"><i class="fa-solid fa-arrow-up"></i></button>
            <button type="button" class="btn btn--secondary btn--sm" data-red-bajar aria-label="Bajar"><i class="fa-solid fa-arrow-down"></i></button>
            <button type="button" class="btn btn--secondary btn--sm" data-red-quitar><i class="fa-solid fa-trash"></i> Quitar</button>
        </div>
    </div>
</li>
