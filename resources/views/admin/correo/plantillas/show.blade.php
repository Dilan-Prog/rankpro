@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/correo-plantillas.css')
@endsection

@php
    $marca = $editorData['plantilla']['marca'];
    $htmlLibre = $plantilla->esHtmlLibre();
@endphp

@section('content')
    <div data-correo-plantilla-editor class="cp-editor-root">

        {{-- ================= Barra superior ================= --}}
        <div class="cp-bar">
            <div class="cp-bar__izq">
                <a href="{{ route('admin.correo.plantillas.index') }}" class="btn--icon cp-bar__volver" title="Volver al listado">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <span class="cp-bar__icono"><i class="fa-solid fa-envelope-open-text"></i></span>
                <div class="cp-bar__nombre">
                    <input class="cp-bar__nombre-input" type="text" data-campo="nombre" value="{{ $plantilla->nombre }}" placeholder="Nombre de la plantilla" aria-label="Nombre de la plantilla" maxlength="255">
                    <div class="cp-bar__meta">
                        <span data-resumen-bloques>{{ count($plantilla->bloques ?? []) }} bloques</span>
                        <span data-resumen-html {{ $htmlLibre ? '' : 'hidden' }}>· HTML propio</span>
                    </div>
                </div>
            </div>
            <div class="cp-bar__der">
                <span class="cp-guardado" data-autosave-note aria-live="polite">Guardado</span>
                <select class="select cp-bar__select" data-campo="categoria" aria-label="Categoría">
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->value }}" @selected($plantilla->categoria === $categoria)>{{ $categoria->label() }}</option>
                    @endforeach
                </select>
                <select class="select cp-bar__select" data-campo="estado" aria-label="Estado">
                    @foreach ($estados as $estado)
                        <option value="{{ $estado }}" @selected($plantilla->estado === $estado)>{{ ucfirst($estado) }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn--secondary" data-duplicar>
                    <i class="fa-solid fa-copy"></i> Duplicar
                </button>
            </div>
        </div>

        <div class="cp-editor">
            {{-- ================= Columna izquierda: formulario ================= --}}
            <div class="cp-panel">

                <section class="cp-seccion">
                    <div class="field">
                        <label class="field__label" for="cp_asunto">Asunto del correo</label>
                        <input class="input" type="text" id="cp_asunto" data-campo="asunto" data-var-target value="{{ $plantilla->asunto }}" maxlength="255">
                    </div>
                    <div class="cp-variables">
                        <div class="cp-variables__titulo">Variables · clic para insertarlas en el campo activo</div>
                        <div class="cp-chips" data-variables></div>
                        <p class="field__hint">Se sustituyen al enviar con los datos del cliente y del envío. En la vista previa se ven con valores de ejemplo.</p>
                    </div>
                </section>

                {{-- ---------- HTML propio ---------- --}}
                <section class="cp-seccion">
                    <label class="cp-switch">
                        <input type="checkbox" data-html-toggle @checked($htmlLibre)>
                        <span class="cp-switch__pista"></span>
                        <span class="cp-switch__texto">HTML propio</span>
                        <span class="field__hint">Pega tu propio código en lugar de usar los bloques.</span>
                    </label>
                    <div class="cp-html" data-html-panel {{ $htmlLibre ? '' : 'hidden' }}>
                        <div class="cp-aviso" data-html-aviso {{ $htmlLibre ? '' : 'hidden' }}>
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <strong>Este correo usa HTML propio.</strong> Mientras el código tenga contenido, los bloques y la marca no se usan: la vista previa y los envíos salen de aquí. Puedes seguir usando <code>@{{variables}}</code>.
                            </div>
                        </div>
                        <textarea class="textarea cp-html__codigo" data-campo="html_personalizado" data-var-target rows="14" spellcheck="false" placeholder="<!doctype html>&#10;<html>…</html>&#10;&#10;Vacío = se usan los bloques.">{{ $plantilla->html_personalizado }}</textarea>
                        <div class="cp-html__pie">
                            <span class="u-mono" data-html-longitud></span>
                            <button type="button" class="cp-link" data-html-descartar>Descartar el HTML y volver a los bloques</button>
                        </div>
                    </div>
                </section>

                {{-- ---------- Marca ---------- --}}
                <section class="cp-seccion" data-seccion-bloques>
                    <h3 class="cp-seccion__titulo">Marca del correo</h3>
                    <div class="card cp-tarjeta">
                        <div class="field">
                            <span class="field__label">Color de marca · cabecera, botones y cifras</span>
                            <div class="cp-colores" data-colores>
                                @foreach ($editorData['colores'] as $hex => $label)
                                    <button type="button" class="cp-color {{ strtoupper($marca['color']) === strtoupper($hex) ? 'is-active' : '' }}" data-color="{{ $hex }}" title="{{ $label }} {{ $hex }}">
                                        <span class="cp-color__muestra" style="background: {{ $hex }}"><i class="fa-solid fa-check"></i></span>
                                        {{ $label }}
                                    </button>
                                @endforeach
                                <label class="cp-color cp-color--libre" title="Otro color">
                                    <input type="color" data-color-libre value="{{ $marca['color'] }}" aria-label="Color libre">
                                    <span class="u-mono" data-color-hex>{{ strtoupper($marca['color']) }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-grid form-grid--2">
                            <div class="field">
                                <label class="field__label" for="cp_logo">Logotipo en la cabecera</label>
                                <select class="select" id="cp_logo" data-marca="logo">
                                    @foreach ($editorData['logos'] as $logo)
                                        <option value="{{ $logo }}" @selected($marca['logo'] === $logo)>{{ ['wordmark' => 'Wordmark (RankPro)', 'monograma' => 'Monograma (RP)', 'apilado' => 'Apilado con bajada', 'imagen' => 'Imagen propia', 'ninguno' => 'Sin logotipo'][$logo] ?? ucfirst($logo) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field__label" for="cp_tagline">Bajada bajo el nombre</label>
                                <input class="input" type="text" id="cp_tagline" data-marca="tagline" value="{{ $marca['tagline'] }}" maxlength="120">
                            </div>
                        </div>
                        <div class="field" data-logo-url-campo {{ $marca['logo'] === 'imagen' ? '' : 'hidden' }}>
                            <label class="field__label" for="cp_logo_url">URL del logotipo (PNG o SVG, alto 28px)</label>
                            <input class="input u-mono" type="url" id="cp_logo_url" data-marca="logo_url" value="{{ $marca['logo_url'] }}" placeholder="https://rankprosolutions.com.mx/logo.png">
                        </div>
                        <div class="field">
                            <div class="cp-fila-titulo">
                                <span class="field__label">Redes y enlaces en el pie</span>
                                <button type="button" class="cp-link" data-red-agregar>+ Añadir enlace</button>
                            </div>
                            <div class="cp-redes" data-redes></div>
                        </div>
                    </div>
                </section>

                {{-- ---------- Bloques ---------- --}}
                <section class="cp-seccion" data-seccion-bloques>
                    <h3 class="cp-seccion__titulo">Bloques del correo</h3>
                    <div class="cp-bloques" data-bloques></div>
                    <p class="cp-bloques__vacio" data-bloques-vacio hidden>Sin bloques. Añade uno abajo para empezar.</p>

                    <div class="cp-paleta">
                        <div class="cp-seccion__titulo">Añadir bloque</div>
                        <div class="cp-paleta__grid" data-paleta></div>
                    </div>
                </section>
            </div>

            {{-- ================= Columna derecha: vista previa ================= --}}
            <div class="cp-preview">
                <div class="cp-preview__barra">
                    <div class="cp-preview__asunto">
                        <i class="fa-solid fa-eye"></i>
                        <span>Asunto: <strong data-preview-asunto>{{ $plantilla->asunto }}</strong></span>
                    </div>
                    <div class="cp-segmento" role="group" aria-label="Dispositivo">
                        <button type="button" class="cp-segmento__btn is-active" data-dispositivo="escritorio" title="Escritorio (600px)"><i class="fa-solid fa-desktop"></i></button>
                        <button type="button" class="cp-segmento__btn" data-dispositivo="movil" title="Móvil (375px)"><i class="fa-solid fa-mobile-screen"></i></button>
                    </div>
                </div>
                <div class="cp-preview__lienzo">
                    <div class="cp-preview__marco" data-preview-marco style="width:600px">
                        <iframe class="cp-preview__iframe" data-preview-iframe title="Vista previa del correo" sandbox="allow-same-origin" referrerpolicy="no-referrer"></iframe>
                        <div class="cp-preview__estado" data-preview-estado hidden></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="plantilla-data">@json($editorData)</script>
@endsection

@section('scripts')
    @vite('resources/js/correo-plantillas.js')
@endsection
