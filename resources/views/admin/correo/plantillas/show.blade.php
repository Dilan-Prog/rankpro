@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/correo-plantillas.css')
@endsection

@php
    $marca = $editorData['plantilla']['marca'];
    $htmlLibre = $plantilla->esHtmlLibre();
    $sinNombre = trim((string) $plantilla->nombre) === '';
@endphp

@section('content')
    {{-- Pantalla completa: el editor tapa el shell del panel (sidebar y cabecera) para dar todo el alto a la vista previa. --}}
    <div data-correo-plantilla-editor class="cpe-root" role="region" aria-label="Editor de plantilla de correo">

        {{-- ================= Barra superior ================= --}}
        <header class="cpe-bar">
            <div class="cpe-bar__izq">
                <span class="cpe-bar__icono" aria-hidden="true"><i class="fa-solid fa-table-cells-large"></i></span>
                <div class="cpe-bar__titulos">
                    <div class="cpe-bar__titulo" data-titulo>{{ $sinNombre ? 'Plantilla sin nombre' : $plantilla->nombre }}</div>
                    <div class="cpe-bar__meta">
                        <span>Editor en vivo · <span data-resumen-bloques>{{ count($plantilla->bloques ?? []) }} bloques</span></span>
                        <span data-resumen-html {{ $htmlLibre ? '' : 'hidden' }}>· HTML propio</span>
                    </div>
                </div>
            </div>

            <div class="cpe-bar__der">
                <span class="cpe-guardado" data-autosave-note role="status" aria-live="polite">Guardado</span>

                <select class="cpe-select cpe-select--compacto" data-campo="estado" aria-label="Estado de la plantilla">
                    @foreach ($estados as $estado)
                        <option value="{{ $estado }}" @selected($plantilla->estado === $estado)>{{ ucfirst($estado) }}</option>
                    @endforeach
                </select>

                <button type="button" class="cpe-btn cpe-btn--icono" data-duplicar title="Duplicar plantilla" aria-label="Duplicar plantilla">
                    <i class="fa-solid fa-copy" aria-hidden="true"></i>
                </button>

                {{-- Vista sencilla = bloques; vista avanzada = HTML propio (mismo campo html_personalizado de siempre). --}}
                <div class="cpe-modos" role="group" aria-label="Modo del editor">
                    <button type="button" class="cpe-modos__btn" data-modo="sencilla" aria-pressed="{{ $htmlLibre ? 'false' : 'true' }}">
                        <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i><span>Vista sencilla</span>
                    </button>
                    <button type="button" class="cpe-modos__btn" data-modo="avanzada" aria-pressed="{{ $htmlLibre ? 'true' : 'false' }}">
                        <i class="fa-solid fa-code" aria-hidden="true"></i><span>Vista avanzada</span>
                    </button>
                </div>

                <a href="{{ route('admin.correo.plantillas.index') }}" class="cpe-btn cpe-btn--borde cp-bar__volver" data-cancelar title="Salir al listado (Esc). Lo que ya se guardó solo se conserva.">Cancelar</a>
                <button type="button" class="cpe-btn cpe-btn--primario" data-guardar>
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span data-guardar-texto>Guardar plantilla</span>
                </button>
            </div>
        </header>

        <div class="cpe-cuerpo">
            {{-- ================= Columna izquierda: formulario ================= --}}
            <div class="cpe-panel">

                <section class="cpe-seccion" aria-label="Datos de la plantilla">
                    <div class="cpe-fila2">
                        <div class="cpe-campo">
                            <label class="cpe-etiqueta" for="cp_nombre">Nombre de la plantilla</label>
                            <input class="cpe-input" type="text" id="cp_nombre" data-campo="nombre" value="{{ $plantilla->nombre }}" maxlength="255" autocomplete="off" aria-describedby="cp_nombre_error">
                            <span class="cpe-error" id="cp_nombre_error" data-error-for="nombre" role="alert"></span>
                        </div>
                        <div class="cpe-campo">
                            <label class="cpe-etiqueta" for="cp_categoria">Categoría</label>
                            <select class="cpe-select" id="cp_categoria" data-campo="categoria" aria-describedby="cp_categoria_error">
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->value }}" @selected($plantilla->categoria === $categoria)>{{ $categoria->label() }}</option>
                                @endforeach
                            </select>
                            <span class="cpe-error" id="cp_categoria_error" data-error-for="categoria" role="alert"></span>
                        </div>
                    </div>

                    <div class="cpe-campo">
                        <label class="cpe-etiqueta" for="cp_asunto">Asunto del correo</label>
                        <input class="cpe-input" type="text" id="cp_asunto" data-campo="asunto" data-var-target value="{{ $plantilla->asunto }}" maxlength="255" autocomplete="off" aria-describedby="cp_asunto_error">
                        <span class="cpe-error" id="cp_asunto_error" data-error-for="asunto" role="alert"></span>
                    </div>

                    <div class="cpe-campo">
                        <span class="cpe-etiqueta" id="cp_variables_lbl">Variables · clic para insertarlas en el campo activo</span>
                        <div class="cpe-chips" role="group" aria-labelledby="cp_variables_lbl" data-variables></div>
                        <p class="cpe-ayuda">Se sustituyen al enviar con los datos del cliente y del envío. En la vista previa se ven con valores de ejemplo.</p>
                    </div>
                </section>

                {{-- ---------- Vista avanzada: HTML propio ---------- --}}
                <section class="cpe-seccion" data-html-panel aria-label="HTML propio" {{ $htmlLibre ? '' : 'hidden' }}>
                    <h3 class="cpe-seccion__titulo">HTML propio</h3>
                    <div class="cpe-tarjeta">
                        <div class="cp-aviso" data-html-aviso {{ $htmlLibre ? '' : 'hidden' }}>
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            <div>
                                <strong>Este correo usa HTML propio.</strong> Mientras el código tenga contenido, los bloques y la marca no se usan: la vista previa y los envíos salen de aquí. Puedes seguir usando <code>@{{variables}}</code>.
                            </div>
                        </div>
                        <label class="cpe-etiqueta" for="cp_html">Código del correo</label>
                        <textarea class="cpe-textarea cpe-codigo" id="cp_html" data-campo="html_personalizado" data-var-target rows="14" spellcheck="false" placeholder="<!doctype html>&#10;<html>…</html>&#10;&#10;Vacío = se usan los bloques.">{{ $plantilla->html_personalizado }}</textarea>
                        <span class="cpe-error" data-error-for="html_personalizado" role="alert"></span>
                        <div class="cpe-codigo__pie">
                            <span class="u-mono" data-html-longitud></span>
                            <button type="button" class="cp-link" data-html-descartar>Descartar el HTML y volver a los bloques</button>
                        </div>
                    </div>
                </section>

                {{-- ---------- Marca ---------- --}}
                <section class="cpe-seccion" data-seccion-bloques aria-labelledby="cp_marca_titulo">
                    <h3 class="cpe-seccion__titulo" id="cp_marca_titulo">Marca del correo</h3>
                    <div class="cpe-tarjeta">
                        <div class="cpe-campo">
                            <label class="cpe-etiqueta" for="cp_logo">Logotipo en la cabecera</label>
                            <select class="cpe-select" id="cp_logo" data-marca="logo">
                                @foreach ($editorData['logos'] as $logo)
                                    <option value="{{ $logo }}" @selected($marca['logo'] === $logo)>{{ ['wordmark' => 'Wordmark (RankPro)', 'monograma' => 'Monograma (RP)', 'apilado' => 'Apilado con bajada', 'imagen' => 'Imagen propia', 'ninguno' => 'Sin logotipo'][$logo] ?? ucfirst($logo) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="cpe-campo" data-logo-url-campo {{ $marca['logo'] === 'imagen' ? '' : 'hidden' }}>
                            <label class="cpe-etiqueta" for="cp_logo_url">URL del logotipo (PNG o SVG, alto 28px)</label>
                            <input class="cpe-input u-mono" type="url" id="cp_logo_url" data-marca="logo_url" value="{{ $marca['logo_url'] }}" placeholder="https://rankprosolutions.com.mx/logo.png" autocomplete="off">
                        </div>

                        <div class="cpe-campo">
                            <span class="cpe-etiqueta" id="cp_variante_lbl">Variante del logotipo</span>
                            <div class="cpe-logos" role="radiogroup" aria-labelledby="cp_variante_lbl" data-logos></div>
                        </div>

                        <div class="cpe-campo">
                            <label class="cpe-etiqueta" for="cp_tagline">Bajada bajo el nombre</label>
                            <input class="cpe-input" type="text" id="cp_tagline" data-marca="tagline" value="{{ $marca['tagline'] }}" maxlength="120" autocomplete="off">
                        </div>

                        <div class="cpe-campo">
                            <span class="cpe-etiqueta" id="cp_color_lbl">Color de marca · cabecera, botones y cifras</span>
                            <div class="cpe-chips" role="group" aria-labelledby="cp_color_lbl" data-colores>
                                @foreach ($editorData['colores'] as $hex => $label)
                                    <button type="button" class="cpe-color {{ strtoupper($marca['color']) === strtoupper($hex) ? 'is-active' : '' }}" data-color="{{ $hex }}" aria-pressed="{{ strtoupper($marca['color']) === strtoupper($hex) ? 'true' : 'false' }}" title="{{ $label }} {{ $hex }}">
                                        <span class="cpe-color__muestra" style="background: {{ $hex }}"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                        {{ $label }}
                                    </button>
                                @endforeach
                                <label class="cpe-color cpe-color--libre" title="Otro color">
                                    <input type="color" data-color-libre value="{{ $marca['color'] }}" aria-label="Otro color de marca">
                                    <span class="u-mono" data-color-hex>{{ strtoupper($marca['color']) }}</span>
                                </label>
                            </div>
                            <span class="cpe-error" data-error-for="marca" role="alert"></span>
                        </div>

                        <div class="cpe-campo">
                            <div class="cpe-fila-titulo">
                                <span class="cpe-etiqueta" id="cp_redes_lbl">Redes sociales en el pie · <span class="u-mono" data-redes-activas></span></span>
                                <button type="button" class="cp-link" data-red-agregar>+ Añadir enlace</button>
                            </div>
                            <div class="cpe-chips" role="group" aria-labelledby="cp_redes_lbl" data-redes-chips></div>
                            <div class="cpe-redes" data-redes></div>
                        </div>
                    </div>
                </section>

                {{-- ---------- Bloques ---------- --}}
                <section class="cpe-seccion" data-seccion-bloques aria-labelledby="cp_bloques_titulo">
                    <h3 class="cpe-seccion__titulo" id="cp_bloques_titulo">Bloques del correo</h3>
                    <span class="cpe-error" data-error-for="bloques" role="alert"></span>
                    <div class="cpe-bloques" data-bloques></div>
                    <p class="cpe-bloques__vacio" data-bloques-vacio hidden>Sin bloques. Añade uno abajo para empezar.</p>

                    <div class="cpe-paleta">
                        <div class="cpe-seccion__titulo">Añadir bloque</div>
                        <div class="cpe-paleta__grid" data-paleta></div>
                    </div>
                </section>
            </div>

            {{-- ================= Columna derecha: vista previa ================= --}}
            <div class="cpe-preview">
                <div class="cpe-preview__barra">
                    <div class="cpe-preview__asunto">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        <span>Asunto: <strong data-preview-asunto>{{ trim((string) $plantilla->asunto) !== '' ? $plantilla->asunto : '(sin asunto)' }}</strong></span>
                    </div>
                    <div class="cpe-dispositivos" role="group" aria-label="Dispositivo de la vista previa">
                        <button type="button" class="cpe-dispositivos__btn is-active" data-dispositivo="escritorio" aria-pressed="true" title="Escritorio (600px)" aria-label="Escritorio"><i class="fa-solid fa-desktop" aria-hidden="true"></i></button>
                        <button type="button" class="cpe-dispositivos__btn" data-dispositivo="movil" aria-pressed="false" title="Móvil (375px)" aria-label="Móvil"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="cpe-preview__lienzo">
                    <div class="cpe-preview__tarjeta">
                        <div class="cpe-preview__marco" data-preview-marco style="width:600px">
                            <iframe class="cpe-preview__iframe" data-preview-iframe title="Vista previa del correo" sandbox="allow-same-origin" referrerpolicy="no-referrer"></iframe>
                            <div class="cpe-preview__estado" data-preview-estado role="status" hidden></div>
                        </div>
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
