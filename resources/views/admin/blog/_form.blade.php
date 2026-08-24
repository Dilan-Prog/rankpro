{{-- Compartido por create.blade.php y edit.blade.php --}}
@php
    /** @var \App\Models\Articulo|null $articulo */
    $articulo = $articulo ?? null;
@endphp

<div class="form-grid form-grid--2">
    <div class="field">
        <label class="field__label" for="titulo">Título</label>
        <input class="input" type="text" name="titulo" id="titulo" maxlength="255"
               value="{{ old('titulo', $articulo->titulo ?? '') }}" required>
        @error('titulo')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="slug">Slug</label>
        <input class="input u-mono" type="text" name="slug" id="slug" maxlength="120"
               value="{{ old('slug', $articulo->slug ?? '') }}" placeholder="mi-articulo" required>
        <span class="field__hint">URL pública: /blog/<span data-blog-slug-preview>{{ old('slug', $articulo->slug ?? 'mi-articulo') }}</span></span>
        @error('slug')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="meta_title">
            Meta title
            <span class="blog-contador" data-blog-contador="meta_title" data-min="1" data-max="60">0/60</span>
        </label>
        <input class="input" type="text" name="meta_title" id="meta_title" maxlength="60"
               data-blog-contado="meta_title"
               value="{{ old('meta_title', $articulo->meta_title ?? '') }}" required>
        <span class="field__hint">Máximo 60 caracteres: a partir de ahí Google lo trunca en la SERP.</span>
        @error('meta_title')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="cluster">Clúster</label>
        <select class="select" name="cluster" id="cluster" required>
            <option value="">— Selecciona un clúster —</option>
            @foreach ($clusters as $slug => $c)
                <option value="{{ $slug }}" @selected(old('cluster', $articulo->cluster ?? '') === $slug)>{{ $c['nombre'] }}</option>
            @endforeach
        </select>
        @error('cluster')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="meta_description">
        Meta description
        <span class="blog-contador" data-blog-contador="meta_description" data-min="70" data-max="160">0/160</span>
    </label>
    <textarea class="textarea" name="meta_description" id="meta_description" rows="3" maxlength="160"
              data-blog-contado="meta_description" required>{{ old('meta_description', $articulo->meta_description ?? '') }}</textarea>
    <span class="field__hint">Entre 70 y 160 caracteres. Fuera de ese rango Google la recorta o la reescribe.</span>
    @error('meta_description')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="resumen">Resumen</label>
    <textarea class="textarea" name="resumen" id="resumen" rows="3" maxlength="500" required>{{ old('resumen', $articulo->resumen ?? '') }}</textarea>
    <span class="field__hint">Se muestra en el índice del blog y en las tarjetas de artículos relacionados.</span>
    @error('resumen')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="estado">Estado</label>
        <select class="select" name="estado" id="estado" required>
            @foreach ($estados as $estado)
                <option value="{{ $estado->value }}"
                    @selected(old('estado', $articulo->estado->value ?? 'borrador') === $estado->value)>{{ $estado->etiqueta() }}</option>
            @endforeach
        </select>
        <span class="field__hint">Si publicas sin fecha de publicación, se asigna la de hoy.</span>
        @error('estado')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="autor_id">Autor</label>
        <select class="select" name="autor_id" id="autor_id" required>
            <option value="">— Selecciona un autor —</option>
            @foreach ($autores as $id => $nombre)
                <option value="{{ $id }}" @selected((int) old('autor_id', $articulo->autor_id ?? auth()->id()) === (int) $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('autor_id')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
    <div class="field">
        <label class="field__label" for="fecha_publicacion">Fecha de publicación</label>
        <input class="input" type="date" name="fecha_publicacion" id="fecha_publicacion"
               value="{{ old('fecha_publicacion', optional($articulo->fecha_publicacion ?? null)->format('Y-m-d')) }}"
               @if ($articulo?->fecha_publicacion) readonly @endif>
        <span class="field__hint">
            @if ($articulo?->fecha_publicacion)
                Ya publicado: la fecha original no se reescribe al editar.
            @else
                Vacío + estado «Publicado» = se publica hoy. Con fecha futura queda programado.
            @endif
        </span>
        @error('fecha_publicacion')<span class="field__error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label class="field__label" for="fecha_actualizacion">Fecha de actualización</label>
        <input class="input" type="date" name="fecha_actualizacion" id="fecha_actualizacion"
               value="{{ old('fecha_actualizacion', optional($articulo->fecha_actualizacion ?? null)->format('Y-m-d')) }}">
        <span class="field__hint">Cámbiala solo cuando el contenido se revise de verdad: va a dateModified y al sitemap.</span>
        @error('fecha_actualizacion')<span class="field__error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="imagen_alt">Texto alternativo de la imagen destacada</label>
    <input class="input" type="text" name="imagen_alt" id="imagen_alt" maxlength="255"
           value="{{ old('imagen_alt', $articulo->imagen_alt ?? '') }}">
    @error('imagen_alt')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="field" style="margin-top: var(--space-4);">
    <span class="field__label">Servicios que apoya este artículo</span>
    <div class="blog-checks">
        @foreach ($servicios as $servicio)
            @php $marcados = old('servicios', $serviciosSeleccionados); @endphp
            <label class="blog-check">
                <input type="checkbox" name="servicios[]" value="{{ $servicio['slug'] }}"
                       @checked(in_array($servicio['slug'], (array) $marcados, true))>
                <span>{{ $servicio['nombre'] }}</span>
            </label>
        @endforeach
    </div>
    <span class="field__hint">El enlace de ida y vuelta artículo ↔ página de servicio es lo que hace funcionar el modelo hub-and-spoke.</span>
    @error('servicios')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="relacionados">Artículos relacionados</label>
    @php $relSeleccionados = array_map('intval', (array) old('relacionados', $relacionadosSeleccionados)); @endphp
    <select class="select blog-relacionados" name="relacionados[]" id="relacionados" multiple size="8">
        @foreach ($candidatosRelacionados as $candidato)
            <option value="{{ $candidato->id }}" @selected(in_array((int) $candidato->id, $relSeleccionados, true))>
                {{ $candidato->titulo }}
            </option>
        @endforeach
    </select>
    <span class="field__hint">Opcional. Si se deja vacío, el blog deriva los relacionados por clúster.</span>
    @error('relacionados')<span class="field__error">{{ $message }}</span>@enderror
</div>

<div class="field" style="margin-top: var(--space-4);">
    <label class="field__label" for="contenido">
        Contenido (Markdown)
        <span class="blog-contador" data-blog-palabras>0 palabras</span>
    </label>
    <div class="blog-editor">
        <textarea class="textarea blog-editor__input" name="contenido" id="contenido" rows="24"
                  data-blog-markdown required>{{ old('contenido', $articulo->contenido ?? '') }}</textarea>
        <div class="blog-editor__preview">
            <div class="blog-editor__preview-head">
                <span>Vista previa</span>
                <button type="button" class="btn btn--secondary btn--sm" data-blog-preview-refresh>
                    <i class="fa-solid fa-rotate"></i> Actualizar
                </button>
            </div>
            <div class="blog-editor__preview-body prosa" data-blog-preview-body
                 data-preview-url="{{ route('admin.blog.preview') }}">
                <p class="blog-editor__placeholder">Escribe Markdown a la izquierda para ver el resultado aquí.</p>
            </div>
        </div>
    </div>
    <span class="field__hint">El HTML se genera al guardar y se persiste: la vista pública no vuelve a renderizar Markdown.</span>
    @error('contenido')<span class="field__error">{{ $message }}</span>@enderror
</div>
