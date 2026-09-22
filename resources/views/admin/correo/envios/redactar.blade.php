@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/correo-envios.css')
    {{-- El editor de "Personalizar contenido" reutiliza las clases cp-* del
         editor de Plantillas (bloques, marca, HTML propio): mismo motor, misma
         apariencia, sin duplicar CSS. --}}
    @vite('resources/css/admin/correo-plantillas.css')
@endsection

{{--
    Redactar (create) y editar (edit) comparten esta pantalla. Todo el estado
    inicial viaja en #envio-data (plantillas con sus bloques/marca, clientes
    activos, catálogo de variables y, si es edición, el envío cargado); el JS
    pinta las partes dinámicas (tarjetas de plantilla, chips de destinatarios,
    campos de variables, previa).
--}}
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $envio ? 'Editar envío' : 'Redactar correo' }}</h1>
            <p class="page-header__subtitle">
                @if ($envio)
                    <x-badge :status="$envio->estado" /> · Al guardar vuelve a borrador hasta que lo envíes o lo programes de nuevo
                @else
                    Elige una plantilla, escribe a quién va y envíalo ahora o prográmalo
                @endif
            </p>
        </div>
        <div class="correo-header-actions">
            <a href="{{ route('admin.correo.envios.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver al historial
            </a>
        </div>
    </div>

    {{-- JSON_HEX_TAG: el HTML libre de una plantilla puede contener "</script>". --}}
    <script type="application/json" id="envio-data">@json($datos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>

    <div class="correo-redactar" data-correo-envio-redactar>
        <form class="correo-redactar__form" id="envioForm" novalidate>
            @csrf

            {{-- 1 · Plantilla --}}
            <section class="card card--padded correo-paso">
                <div class="correo-paso__head">
                    <span class="correo-paso__num">1</span>
                    <div>
                        <h3 class="card__header-title">Plantilla</h3>
                        <p class="field__hint">Solo se listan plantillas activas. Al elegir una se prellena el asunto.</p>
                    </div>
                </div>
                <div class="correo-plantillas" data-plantillas-lista></div>
                <p class="correo-vacio" data-plantillas-vacio hidden>
                    No hay plantillas activas. <a href="{{ route('admin.correo.plantillas.index') }}">Crea una primero</a>.
                </p>
                <span class="field__error" data-error-for="plantilla_id"></span>

                {{-- ---------- Personalizar contenido de este envío ---------- --}}
                <div class="correo-personalizar">
                    <label class="cp-switch">
                        <input type="checkbox" data-personalizar-toggle>
                        <span class="cp-switch__pista"></span>
                        <span class="cp-switch__texto">Personalizar el contenido de este correo</span>
                        <span class="field__hint">Edita el cuerpo, botones, enlaces y pie solo para este envío, sin tocar la plantilla.</span>
                    </label>

                    <div class="correo-personalizar__panel" data-personalizar-panel hidden>
                        <div class="cp-aviso">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>
                                Estos cambios son <strong>solo de este envío</strong>. La plantilla original no se modifica.
                                <button type="button" class="cp-link" data-personalizar-reiniciar>Reiniciar al contenido de la plantilla</button>
                            </div>
                        </div>

                        <p class="cp-ayuda-edicion" data-cp-ayuda-bloques>
                            <i class="fa-solid fa-arrow-pointer"></i>
                            Haz clic en el texto del correo (en la vista previa) para editarlo directamente. Pasa el mouse sobre un bloque para moverlo, duplicarlo o borrarlo.
                        </p>
                        <p class="cp-ayuda-edicion" data-cp-ayuda-html-libre hidden>
                            <i class="fa-solid fa-arrow-pointer"></i>
                            Este envío usa HTML propio: haz clic en cualquier texto de la vista previa para editarlo directamente, sin tocar el código.
                        </p>

                        <div class="cp-variables">
                            <div class="cp-variables__titulo">Variables · clic para insertarlas en el campo activo</div>
                            <div class="cp-chips" data-personalizar-variables></div>
                        </div>

                        {{-- Marca --}}
                        <section class="cp-seccion" data-personalizar-seccion-bloques>
                            <h3 class="cp-seccion__titulo">Marca de este correo</h3>
                            <div class="card cp-tarjeta">
                                <div class="field">
                                    <span class="field__label">Color de marca</span>
                                    <div class="cp-colores" data-personalizar-colores>
                                        @foreach ($datos['colores'] as $hex => $label)
                                            <button type="button" class="cp-color" data-color="{{ $hex }}" title="{{ $label }} {{ $hex }}">
                                                <span class="cp-color__muestra" style="background: {{ $hex }}"><i class="fa-solid fa-check"></i></span>
                                                {{ $label }}
                                            </button>
                                        @endforeach
                                        <label class="cp-color cp-color--libre" title="Otro color">
                                            <input type="color" data-personalizar-color-libre aria-label="Color libre">
                                            <span class="u-mono" data-personalizar-color-hex></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-grid form-grid--2">
                                    <div class="field">
                                        <label class="field__label" for="ev_personalizar_logo">Logotipo en la cabecera</label>
                                        <select class="select" id="ev_personalizar_logo" data-personalizar-marca="logo">
                                            @foreach ($datos['logos'] as $logo)
                                                <option value="{{ $logo }}">{{ ['wordmark' => 'Wordmark (RankPro)', 'monograma' => 'Monograma (RP)', 'apilado' => 'Apilado con bajada', 'imagen' => 'Imagen propia', 'ninguno' => 'Sin logotipo'][$logo] ?? ucfirst($logo) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field__label" for="ev_personalizar_tagline">Bajada bajo el nombre</label>
                                        <input class="input" type="text" id="ev_personalizar_tagline" data-personalizar-marca="tagline" maxlength="120">
                                    </div>
                                </div>
                                <div class="field" data-personalizar-logo-url-campo hidden>
                                    <label class="field__label" for="ev_personalizar_logo_url">URL del logotipo (PNG o SVG, alto 28px)</label>
                                    <input class="input u-mono" type="url" id="ev_personalizar_logo_url" data-personalizar-marca="logo_url" placeholder="https://rankprosolutions.com.mx/logo.png">
                                </div>
                                <div class="field">
                                    <div class="cp-fila-titulo">
                                        <span class="field__label">Redes y enlaces en el pie</span>
                                        <button type="button" class="cp-link" data-personalizar-red-agregar>+ Añadir enlace</button>
                                    </div>
                                    <div class="cp-redes" data-personalizar-redes></div>
                                </div>
                            </div>
                        </section>

                        {{-- HTML propio (avanzado) --}}
                        <details class="cp-avanzado" data-personalizar-html-panel-detalles>
                            <summary>Avanzado</summary>
                            <div class="cp-seccion" style="margin-top: var(--space-3);">
                                <label class="cp-switch">
                                    <input type="checkbox" data-personalizar-html-toggle>
                                    <span class="cp-switch__pista"></span>
                                    <span class="cp-switch__texto">HTML propio</span>
                                    <span class="field__hint">Pega tu propio código en lugar de usar los bloques.</span>
                                </label>
                                <div class="cp-html" data-personalizar-html-panel hidden>
                                    <textarea class="textarea cp-html__codigo" data-personalizar-html-codigo data-var-target rows="12" spellcheck="false" placeholder="<!doctype html>&#10;<html>…</html>"></textarea>
                                    <div class="cp-html__pie">
                                        <span class="u-mono" data-personalizar-html-longitud></span>
                                        <button type="button" class="cp-link" data-personalizar-html-descartar>Descartar el HTML y volver a los bloques</button>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </section>

            {{-- 2 · Asunto y remitente --}}
            <section class="card card--padded correo-paso">
                <div class="correo-paso__head">
                    <span class="correo-paso__num">2</span>
                    <div>
                        <h3 class="card__header-title">Asunto y remitente</h3>
                        <p class="field__hint">El asunto admite variables como <code>@{{contacto}}</code>.</p>
                    </div>
                </div>
                <div class="field">
                    <label class="field__label" for="ef_asunto">Asunto <span style="color:var(--text-danger)">*</span></label>
                    <input class="input" type="text" name="asunto" id="ef_asunto" maxlength="255" data-campo="asunto">
                    <span class="field__error" data-error-for="asunto"></span>
                </div>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                    <div class="field">
                        <label class="field__label" for="ef_remitente_nombre">Nombre del remitente</label>
                        <input class="input" type="text" name="remitente_nombre" id="ef_remitente_nombre" maxlength="255" data-campo="remitente_nombre">
                        <span class="field__error" data-error-for="remitente_nombre"></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="ef_remitente_email">Correo del remitente</label>
                        <input class="input u-mono" type="email" name="remitente_email" id="ef_remitente_email" maxlength="255" data-campo="remitente_email">
                        <span class="field__error" data-error-for="remitente_email"></span>
                    </div>
                </div>
                <p class="field__hint" style="margin-top: var(--space-2);">
                    <i class="fa-solid fa-shield-halved"></i>
                    Usa una dirección del dominio que autentica el SMTP; con otro dominio el correo puede caer en spam o ser rechazado.
                </p>
            </section>

            {{-- 3 · Destinatarios --}}
            <section class="card card--padded correo-paso">
                <div class="correo-paso__head">
                    <span class="correo-paso__num">3</span>
                    <div>
                        <h3 class="card__header-title">Destinatarios <span class="correo-contador" data-destinatarios-contador>0</span></h3>
                        <p class="field__hint">Clientes del CRM o correos sueltos. Un mismo correo solo se manda una vez por envío.</p>
                    </div>
                    <div class="correo-paso__acciones">
                        <button type="button" class="btn btn--ghost btn--sm" data-todos-activos>
                            <i class="fa-solid fa-users"></i> Todos los clientes activos
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" data-limpiar-destinatarios hidden>Limpiar</button>
                    </div>
                </div>

                <div class="correo-chips" data-chips>
                    <span class="correo-chips__vacio" data-chips-vacio>Sin destinatarios todavía</span>
                </div>

                <div class="correo-buscador" data-buscador>
                    <input type="search" class="input input--search" placeholder="Buscar cliente activo por empresa, contacto o correo…" data-buscador-input autocomplete="off">
                    <div class="correo-buscador__lista" data-buscador-lista hidden></div>
                </div>

                <div class="field" style="margin-top: var(--space-4);">
                    <label class="field__label" for="ef_sueltos">Correos sueltos</label>
                    <textarea class="textarea u-mono" id="ef_sueltos" rows="3" data-sueltos placeholder="uno por línea o separados por coma&#10;Nombre &lt;correo@empresa.com&gt; también vale"></textarea>
                    <div class="correo-sueltos__pie">
                        <span class="field__hint">Con formato <code>Nombre &lt;correo@empresa.com&gt;</code> el nombre se usa como <code>@{{contacto}}</code>.</span>
                        <button type="button" class="btn btn--secondary btn--sm" data-agregar-sueltos><i class="fa-solid fa-plus"></i> Añadir</button>
                    </div>
                </div>
                <span class="field__error" data-error-for="destinatarios"></span>
            </section>

            {{-- 4 · Variables del envío --}}
            <section class="card card--padded correo-paso">
                <div class="correo-paso__head">
                    <span class="correo-paso__num">4</span>
                    <div>
                        <h3 class="card__header-title">Variables del envío</h3>
                        <p class="field__hint">Valen para todos los destinatarios. Se llenan solo las que usa la plantilla elegida.</p>
                    </div>
                </div>
                <div class="form-grid form-grid--2" data-variables-campos></div>
                <p class="correo-vacio" data-variables-vacio hidden>La plantilla elegida no usa variables de envío.</p>
                <p class="field__hint correo-variables-persona" data-variables-persona hidden>
                    <i class="fa-solid fa-user"></i>
                    <span data-variables-persona-texto></span> se resuelven por destinatario: desde el cliente del CRM o desde el nombre del correo suelto.
                </p>

                <div class="correo-variables-personalizadas">
                    <div class="form-grid form-grid--2" data-variables-personalizadas-lista></div>
                    <div class="correo-variables-personalizadas__alta">
                        <input type="text" class="input u-mono" data-variable-nueva-clave placeholder="nombre_de_variable" maxlength="60">
                        <input type="text" class="input" data-variable-nueva-valor placeholder="Valor">
                        <button type="button" class="btn btn--secondary btn--sm" data-variable-agregar><i class="fa-solid fa-plus"></i> Añadir variable</button>
                    </div>
                    <p class="field__hint">Solo minúsculas y guiones bajos, p. ej. <code class="u-mono">numero_de_pedido</code>. Se usa en el correo como <code>@{{numero_de_pedido}}</code>.</p>
                </div>
                <span class="field__error" data-error-for="variables"></span>
            </section>

            {{-- 5 · Cuándo --}}
            <section class="card card--padded correo-paso">
                <div class="correo-paso__head">
                    <span class="correo-paso__num">5</span>
                    <div>
                        <h3 class="card__header-title">Cuándo se envía</h3>
                    </div>
                </div>
                <div class="correo-cuando">
                    <label class="correo-cuando__opcion">
                        <input type="radio" name="cuando" value="ahora" checked data-cuando>
                        <span><i class="fa-solid fa-paper-plane"></i> Enviar ahora</span>
                    </label>
                    <label class="correo-cuando__opcion">
                        <input type="radio" name="cuando" value="programar" data-cuando>
                        <span><i class="fa-solid fa-clock"></i> Programar</span>
                    </label>
                </div>
                <div class="field" data-programar-campo hidden style="margin-top: var(--space-3);">
                    <label class="field__label" for="ef_programado_para">Fecha y hora (hora de México)</label>
                    <input class="input" type="datetime-local" id="ef_programado_para" data-campo="programado_para">
                    <span class="field__hint">El envío sale en el minuto indicado; hasta entonces se puede editar o cancelar.</span>
                    <span class="field__error" data-error-for="programado_para"></span>
                </div>
            </section>

            <div class="form-status form-status--error" data-form-error hidden></div>

            <div class="correo-acciones">
                <button type="button" class="btn btn--secondary" data-accion-prueba title="Manda el correo solo a tu usuario, con datos de ejemplo">
                    <i class="fa-solid fa-flask"></i> Enviarme una prueba
                </button>
                <div style="flex:1"></div>
                <button type="button" class="btn btn--secondary" data-accion="borrador">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar borrador
                </button>
                <button type="button" class="btn btn--primary" data-accion="enviar" data-accion-principal>
                    <i class="fa-solid fa-paper-plane"></i> <span data-accion-principal-texto>Enviar ahora</span>
                </button>
            </div>
        </form>

        {{-- Vista previa --}}
        <aside class="correo-redactar__previa">
            <div class="card correo-previa">
                <div class="correo-previa__head">
                    <span class="correo-previa__titulo"><i class="fa-solid fa-eye"></i> <span data-previa-plantilla>Sin plantilla</span></span>
                    <div class="correo-previa__dispositivos">
                        <button type="button" class="is-active" data-previa-ancho="600" title="Escritorio"><i class="fa-solid fa-desktop"></i></button>
                        <button type="button" data-previa-ancho="390" title="Móvil"><i class="fa-solid fa-mobile-screen"></i></button>
                    </div>
                </div>
                <dl class="correo-previa__meta">
                    <dt>De</dt><dd class="u-mono" data-previa-de>—</dd>
                    <dt>Para</dt><dd data-previa-para>—</dd>
                    <dt>Asunto</dt><dd data-previa-asunto>(sin asunto)</dd>
                    <dt>Salida</dt><dd data-previa-salida>Inmediata al confirmar</dd>
                </dl>
                <div class="correo-previa__lienzo">
                    <iframe class="correo-previa__frame" data-previa-frame title="Vista previa del correo" sandbox="allow-same-origin" style="width:600px"></iframe>
                    <p class="correo-previa__estado" data-previa-estado hidden></p>
                    {{-- Controles de edición en vivo sobre la previa (solo con "Personalizar" activo): los pinta correo-envios.js. --}}
                    <div class="cp-flot" data-cp-flot hidden></div>
                    <div class="cp-popover" data-cp-popover hidden></div>
                </div>
                <p class="correo-previa__nota">
                    La previa usa valores de ejemplo para las variables de persona. Cada destinatario recibe las suyas.
                </p>
            </div>
        </aside>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/correo-envios.js')
@endsection
