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
    pinta las partes dinámicas (select de plantillas, chips de destinatarios,
    campos de variables, adjuntos, previa).
--}}
@section('content')
    <div class="cenv">
    @php
        $titulo = $envio ? 'Editar envío' : 'Enviar correo';
        $subtitulo = $envio
            ? 'Al guardar vuelve a borrador hasta que lo envíes o lo programes de nuevo · estado actual: '.e($envio->estado->label())
            : 'Envía ahora o programa · un correo puede ir a varias personas a la vez';
    @endphp
    @include('admin.correo.envios._cabecera', ['tab' => 'nuevo'])

    {{-- JSON_HEX_TAG: el HTML libre de una plantilla puede contener "</script>". --}}
    <script type="application/json" id="envio-data">@json($datos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>

    <div class="cenv-redactar" data-correo-envio-redactar>
        <form class="cenv-tarjeta cenv-form" id="envioForm" novalidate>
            @csrf
            <h2 class="cenv-tarjeta__titulo">Redactar el envío</h2>

            {{-- Remitente y plantilla --}}
            <div class="cenv-fila cenv-fila--2" data-seccion>
                <div class="field">
                    <label class="field__label" for="ef_remitente_email">Remitente</label>
                    <input class="input" type="email" name="remitente_email" id="ef_remitente_email" maxlength="255" data-campo="remitente_email" aria-describedby="ef_remitente_ayuda">
                    <span class="field__error" data-error-for="remitente_email" role="alert"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ef_plantilla">Plantilla</label>
                    <select class="select" id="ef_plantilla" data-plantilla-select aria-describedby="ef_plantilla_vacio"></select>
                    <span class="field__error" data-error-for="plantilla_id" role="alert"></span>
                    <p class="correo-vacio" id="ef_plantilla_vacio" data-plantillas-vacio hidden>
                        No hay plantillas activas. <a href="{{ route('admin.correo.plantillas.index') }}">Crea una primero</a>.
                    </p>
                </div>
            </div>
            <p class="field__hint cenv-ayuda" id="ef_remitente_ayuda">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Usa una dirección del dominio que autentica el SMTP; con otro dominio el correo puede caer en spam o ser rechazado.
            </p>

            {{-- Nombre del remitente y asunto --}}
            <div class="cenv-fila cenv-fila--asunto" data-seccion>
                <div class="field">
                    <label class="field__label" for="ef_remitente_nombre">Nombre del remitente</label>
                    <input class="input" type="text" name="remitente_nombre" id="ef_remitente_nombre" maxlength="255" data-campo="remitente_nombre">
                    <span class="field__error" data-error-for="remitente_nombre" role="alert"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ef_asunto">Asunto <span class="cenv-req" aria-hidden="true">*</span></label>
                    <input class="input" type="text" name="asunto" id="ef_asunto" maxlength="255" data-campo="asunto" required aria-describedby="ef_asunto_ayuda">
                    <span class="field__hint" id="ef_asunto_ayuda">Admite variables como <code>@{{contacto}}</code>.</span>
                    <span class="field__error" data-error-for="asunto" role="alert"></span>
                </div>
            </div>

            {{-- Personalizar el contenido de este envío --}}
            <div class="cenv-personalizar" data-seccion>
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
            </div>

            {{-- Destinatarios --}}
            <div class="cenv-destinatarios" data-seccion>
                <div class="cenv-destinatarios__cabecera">
                    <span class="field__label" id="ef_dest_label">Destinatarios · <span data-destinatarios-contador>0</span> <span data-destinatarios-sufijo>seleccionados</span></span>
                    <span class="cenv-destinatarios__enlaces">
                        <button type="button" class="cenv-enlace" data-todos-activos>Todos los clientes activos</button>
                        <button type="button" class="cenv-enlace cenv-enlace--suave" data-limpiar-destinatarios hidden>Limpiar</button>
                    </span>
                </div>

                <div class="cenv-chips" data-chips role="list" aria-labelledby="ef_dest_label">
                    <span class="cenv-chips__vacio" data-chips-vacio>Sin destinatarios todavía</span>
                </div>

                <div class="cenv-agregar">
                    <input type="text" class="input u-mono cenv-agregar__input" id="ef_sueltos" data-sueltos
                           placeholder="correo@empresa.com y Enter" autocomplete="off"
                           aria-label="Añadir correo de destinatario" aria-describedby="ef_sueltos_ayuda">
                    <button type="button" class="btn btn--secondary cenv-btn" data-agregar-sueltos>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Añadir
                    </button>
                    <div class="cenv-contactos" data-buscador>
                        <button type="button" class="btn btn--secondary cenv-btn" data-contactos-toggle aria-haspopup="true" aria-expanded="false" aria-controls="cenv_contactos_panel">
                            <i class="fa-solid fa-user-group" aria-hidden="true"></i> Contactos
                            <i class="fa-solid fa-chevron-down cenv-contactos__flecha" aria-hidden="true"></i>
                        </button>
                        <div class="cenv-contactos__panel" id="cenv_contactos_panel" data-contactos-panel hidden>
                            <input type="search" class="input" placeholder="Buscar cliente por empresa, contacto o correo…" data-buscador-input autocomplete="off" aria-label="Buscar cliente activo">
                            <div class="cenv-contactos__lista" data-buscador-lista></div>
                        </div>
                    </div>
                </div>
                <p class="field__hint" id="ef_sueltos_ayuda">
                    Pega varios separados por coma o salto de línea. Con <code>Nombre &lt;correo@empresa.com&gt;</code> el nombre se usa como <code>@{{contacto}}</code>. Un mismo correo solo sale una vez.
                </p>
                <span class="field__error" data-error-for="destinatarios" role="alert"></span>
            </div>

            {{-- Variables del envío --}}
            <details class="cenv-plegable" data-seccion data-variables-detalles>
                <summary>Variables del envío</summary>
                <div class="cenv-plegable__cuerpo">
                    <p class="field__hint">Valen para todos los destinatarios. Se llenan solo las que usa la plantilla elegida.</p>
                    <div class="form-grid form-grid--2" data-variables-campos></div>
                    <p class="correo-vacio" data-variables-vacio hidden>La plantilla elegida no usa variables de envío.</p>
                    <p class="field__hint correo-variables-persona" data-variables-persona hidden>
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        <span data-variables-persona-texto></span> se resuelven por destinatario: desde el cliente del CRM o desde el nombre del correo suelto.
                    </p>

                    <div class="correo-variables-personalizadas">
                        <div class="form-grid form-grid--2" data-variables-personalizadas-lista></div>
                        <div class="correo-variables-personalizadas__alta">
                            <input type="text" class="input u-mono" data-variable-nueva-clave placeholder="nombre_de_variable" maxlength="60" aria-label="Nombre de la variable nueva">
                            <input type="text" class="input" data-variable-nueva-valor placeholder="Valor" aria-label="Valor de la variable nueva">
                            <button type="button" class="btn btn--secondary btn--sm" data-variable-agregar><i class="fa-solid fa-plus" aria-hidden="true"></i> Añadir variable</button>
                        </div>
                        <p class="field__hint">Solo minúsculas y guiones bajos, p. ej. <code class="u-mono">numero_de_pedido</code>. Se usa en el correo como <code>@{{numero_de_pedido}}</code>.</p>
                    </div>
                    <span class="field__error" data-error-for="variables" role="alert"></span>
                </div>
            </details>

            {{-- Cuándo se envía --}}
            <div class="field" data-seccion>
                <span class="field__label" id="ef_cuando_label">Cuándo se envía</span>
                <div class="cenv-segmento" role="radiogroup" aria-labelledby="ef_cuando_label">
                    <label class="cenv-segmento__op">
                        <input type="radio" name="cuando" value="ahora" checked data-cuando>
                        <span><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar ahora</span>
                    </label>
                    <label class="cenv-segmento__op">
                        <input type="radio" name="cuando" value="programar" data-cuando>
                        <span><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Programar</span>
                    </label>
                </div>
                <div class="field" data-programar-campo hidden>
                    <label class="field__label" for="ef_programado_para">Fecha y hora (hora de México)</label>
                    <input class="input" type="datetime-local" id="ef_programado_para" data-campo="programado_para">
                    <span class="field__hint">El envío sale en el minuto indicado; hasta entonces se puede editar o cancelar.</span>
                    <span class="field__error" data-error-for="programado_para" role="alert"></span>
                </div>
            </div>

            {{-- Adjuntos: cuelgan del envío, así que solo se agregan una vez guardado (ver Correo.md). --}}
            <div class="field" data-seccion>
                <span class="field__label" id="ef_adj_label">Adjuntos (PDF)</span>
                <div class="cenv-adjuntos" role="group" aria-labelledby="ef_adj_label">
                    <p class="cenv-adjuntos__estado" data-adjuntos-estado>Sin PDF adjuntos</p>
                    <ul class="cenv-adjuntos__lista" data-adjuntos-lista hidden></ul>
                    <p class="field__hint" data-adjuntos-ayuda>Guarda el borrador para adjuntar PDF: los adjuntos se agregan en el detalle del envío.</p>
                </div>
                <div>
                    <button type="button" class="btn btn--secondary cenv-btn" data-accion-adjuntar>
                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i> <span data-adjuntar-texto>Guardar y adjuntar PDF</span>
                    </button>
                </div>
            </div>

            <div class="form-status form-status--error" data-form-error role="alert" hidden></div>

            {{-- Pie de acciones --}}
            <div class="cenv-pie">
                <div class="cenv-pie__izq">
                    <button type="button" class="btn btn--secondary cenv-btn" data-accion="borrador">
                        <i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Guardar borrador
                    </button>
                    <button type="button" class="btn btn--secondary cenv-btn" data-accion-prueba title="Manda el correo solo a tu usuario, con datos de ejemplo">
                        <i class="fa-solid fa-flask" aria-hidden="true"></i> Enviar prueba
                    </button>
                </div>
                <div class="cenv-pie__der">
                    <button type="button" class="btn btn--primary cenv-btn" data-accion="enviar" data-accion-principal>
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span data-accion-principal-texto>Enviar a 0</span>
                    </button>
                </div>
            </div>
            <p class="cenv-pie__aviso" data-accion-aviso aria-live="polite"></p>
        </form>

        {{-- Vista previa --}}
        <aside class="cenv-tarjeta cenv-previa" aria-label="Vista previa del correo">
            <div class="correo-previa__head">
                <span class="correo-previa__titulo"><i class="fa-regular fa-eye" aria-hidden="true"></i> <span data-previa-plantilla>Sin plantilla</span></span>
                <div class="correo-previa__dispositivos" role="group" aria-label="Ancho de la vista previa">
                    <button type="button" class="is-active" data-previa-ancho="600" title="Escritorio" aria-label="Escritorio" aria-pressed="true"><i class="fa-solid fa-desktop" aria-hidden="true"></i></button>
                    <button type="button" data-previa-ancho="390" title="Móvil" aria-label="Móvil" aria-pressed="false"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></button>
                </div>
            </div>
            <dl class="correo-previa__meta">
                <dt>De</dt><dd class="u-mono" data-previa-de>—</dd>
                <dt>Para</dt><dd class="u-mono" data-previa-para>—</dd>
                <dt>Asunto</dt><dd class="u-mono" data-previa-asunto>(sin asunto)</dd>
                <dt>Salida</dt><dd data-previa-salida>Inmediata al confirmar</dd>
            </dl>
            <div class="correo-previa__lienzo">
                <iframe class="correo-previa__frame" data-previa-frame title="Vista previa del correo" sandbox="allow-same-origin" style="width:600px"></iframe>
                <p class="correo-previa__estado" data-previa-estado role="status" hidden></p>
                {{-- Controles de edición en vivo sobre la previa (solo con "Personalizar" activo): los pinta correo-envios.js. --}}
                <div class="cp-flot" data-cp-flot hidden></div>
                <div class="cp-popover" data-cp-popover hidden></div>
            </div>
            <p class="correo-previa__nota">
                La previa usa valores de ejemplo para las variables de persona. Cada destinatario recibe las suyas.
            </p>
        </aside>
    </div>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/correo-envios.js')
@endsection
