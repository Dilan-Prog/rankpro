@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/identidad.css')
    {{-- Las fuentes de Google se cargan solo en esta pantalla, y solo las dos elegidas; el JS suma otra si se cambia el selector. --}}
    @if ($identidad)
        @foreach (collect([$identidad->fuente_titulos, $identidad->fuente_texto])->filter()->unique() as $fuente)
            <link rel="stylesheet" href="{{ $fuente->urlGoogleFonts() }}" data-identidad-fuente="{{ $fuente->value }}">
        @endforeach
    @endif
@endsection

@section('scripts')
    @vite('resources/js/identidad.js')
@endsection

{{--
    Identidad de marca: una ficha por cliente activo (logo, slogan, paleta,
    tipografía, voz y esencia). Un solo formulario con botón Guardar.
--}}
@section('content')
    @php
        $tonosMarcados = old('tonos', $identidad?->tonos ?? []);
        $fuenteTitulos = old('fuente_titulos', $identidad?->fuente_titulos?->value);
        $fuenteTexto = old('fuente_texto', $identidad?->fuente_texto?->value);
    @endphp
    <div class="identidad" data-identidad>
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Identidad de marca</h1>
                <p class="page-header__subtitle">
                    @if ($cliente)
                        {{ $cliente->empresa ?: $cliente->nombre }} · guía visual y de comunicación
                    @else
                        Guía visual y de comunicación de cada cliente.
                    @endif
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="form-status"><span>{{ session('status') }}</span></div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status--error"><span>{{ $errors->first() }}</span></div>
        @endif

        @if (! $cliente)
            <div class="card card--padded empty-state" data-identidad-vacio>
                <i class="fa-solid fa-palette empty-state__icon"></i>
                <p class="empty-state__text">Elige un cliente en el selector de la sección «Marca» del menú para ver o editar su identidad.</p>
            </div>
        @else
            <form method="POST" action="{{ route('admin.identidad.guardar') }}" enctype="multipart/form-data" data-identidad-form>
                @csrf
                @method('PUT')

                <div class="identidad__fila identidad__fila--3">
                    <section class="card card--padded">
                        <h2 class="identidad__titulo">Logo</h2>
                        <div class="identidad__logo">
                            @if ($identidad?->logo_ruta)
                                <img src="{{ route('admin.identidad.logo') }}?v={{ $identidad->updated_at->timestamp }}" alt="Logo de {{ $cliente->empresa ?: $cliente->nombre }}" data-identidad-logo-img>
                            @else
                                <span class="identidad__logo-vacio" data-identidad-logo-vacio><i class="fa-regular fa-image"></i> Sin logo</span>
                            @endif
                        </div>
                        <div class="field">
                            <label class="field__label" for="identidad_logo">{{ $identidad?->logo_ruta ? 'Cambiar logo' : 'Subir logo' }}</label>
                            <input type="file" class="input" id="identidad_logo" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" data-identidad-logo-input>
                            <span class="field__hint">PNG, JPG, WEBP o SVG, hasta 2 MB.</span>
                        </div>
                        @if ($identidad?->logo_ruta)
                            <label class="identidad__check">
                                <input type="checkbox" name="quitar_logo" value="1"> Quitar el logo actual
                            </label>
                        @endif
                        <div class="field">
                            <label class="field__label" for="identidad_slogan">Slogan</label>
                            <input type="text" class="input" id="identidad_slogan" name="slogan" maxlength="200"
                                   value="{{ old('slogan', $identidad?->slogan) }}" placeholder="La frase que define a la marca" data-identidad-slogan>
                        </div>
                    </section>

                    <section class="card card--padded identidad__ancho-2">
                        <div class="identidad__titulo-fila">
                            <h2 class="identidad__titulo">Paleta de colores</h2>
                            <button type="button" class="btn btn--sm" data-color-agregar><i class="fa-solid fa-plus"></i> Agregar color</button>
                        </div>
                        <div class="identidad__paleta" data-paleta>
                            @foreach ($paleta as $color)
                                @include('admin.identidad._color', ['nombre' => $color['nombre'] ?? '', 'hex' => $color['hex'] ?? '#0F9D6E'])
                            @endforeach
                        </div>
                        <p class="field__hint" data-paleta-vacia @if (count($paleta)) hidden @endif>Agrega los colores de la marca.</p>
                        <div class="identidad__barra" data-paleta-barra aria-hidden="true"></div>
                        <template data-color-plantilla>
                            @include('admin.identidad._color', ['nombre' => 'Nuevo color', 'hex' => '#3B82F6'])
                        </template>
                    </section>
                </div>

                <div class="identidad__fila identidad__fila--2">
                    <section class="card card--padded">
                        <h2 class="identidad__titulo">Tipografía</h2>
                        <div class="identidad__dos">
                            <div class="field">
                                <label class="field__label" for="identidad_fuente_titulos">Títulos</label>
                                <select class="select" id="identidad_fuente_titulos" name="fuente_titulos" data-fuente="titulos">
                                    <option value="">Sin definir</option>
                                    @foreach ($fuentes as $f)
                                        <option value="{{ $f->value }}" data-url="{{ $f->urlGoogleFonts() }}" @selected($fuenteTitulos === $f->value)>{{ $f->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field__label" for="identidad_fuente_texto">Texto</label>
                                <select class="select" id="identidad_fuente_texto" name="fuente_texto" data-fuente="texto">
                                    <option value="">Sin definir</option>
                                    @foreach ($fuentes as $f)
                                        <option value="{{ $f->value }}" data-url="{{ $f->urlGoogleFonts() }}" @selected($fuenteTexto === $f->value)>{{ $f->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="identidad__previa" data-previa>
                            <div class="identidad__previa-titulo" data-previa-titulo>Así se ve la marca</div>
                            <p class="identidad__previa-texto" data-previa-texto>Este es un párrafo de ejemplo con la tipografía de texto elegida para la marca.</p>
                            <span class="identidad__previa-boton" data-previa-boton>Llamado a la acción</span>
                        </div>
                    </section>

                    <section class="card card--padded">
                        <h2 class="identidad__titulo">Voz y tono</h2>
                        <div class="identidad__tonos">
                            @foreach ($tonos as $t)
                                <label class="identidad__tono">
                                    <input type="checkbox" name="tonos[]" value="{{ $t->value }}" @checked(in_array($t->value, $tonosMarcados, true))>
                                    <span>{{ $t->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="field">
                            <label class="field__label" for="identidad_voz">Cómo habla la marca</label>
                            <textarea class="input textarea" id="identidad_voz" name="voz" rows="5" maxlength="3000"
                                      placeholder="Ej. Hablamos de tú, con frases cortas y sin tecnicismos.">{{ old('voz', $identidad?->voz) }}</textarea>
                        </div>
                    </section>
                </div>

                <section class="card card--padded">
                    <h2 class="identidad__titulo">Esencia de la marca</h2>
                    <div class="identidad__fila identidad__fila--3 identidad__fila--plana">
                        <div class="field">
                            <label class="field__label" for="identidad_mision">Misión</label>
                            <textarea class="input textarea" id="identidad_mision" name="mision" rows="4" maxlength="3000" placeholder="¿Para qué existe la marca?">{{ old('mision', $identidad?->mision) }}</textarea>
                        </div>
                        <div class="field">
                            <label class="field__label" for="identidad_vision">Visión</label>
                            <textarea class="input textarea" id="identidad_vision" name="vision" rows="4" maxlength="3000" placeholder="¿Hacia dónde va?">{{ old('vision', $identidad?->vision) }}</textarea>
                        </div>
                        <div class="field">
                            <label class="field__label" for="identidad_valores">Valores</label>
                            <textarea class="input textarea" id="identidad_valores" name="valores" rows="4" maxlength="3000" placeholder="Uno por línea">{{ old('valores', $identidad?->valores) }}</textarea>
                        </div>
                    </div>
                </section>

                <div class="identidad__pie">
                    <button type="submit" class="btn btn--primary" data-identidad-guardar>Guardar identidad</button>
                    <span class="field__hint" data-identidad-sucio hidden>Hay cambios sin guardar.</span>
                </div>
            </form>
        @endif
    </div>
@endsection
