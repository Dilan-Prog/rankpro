@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/configuracion.css')
@endsection

{{--
    Medición del sitio público (Tag Manager, Analytics 4, Google Ads). Pensada para
    una persona NO técnica: pega el código que le dio Google y el JS detecta los IDs
    (configuracion.js → initMedicion). Formulario clásico (PUT + errores de sesión):
    es una sola pantalla; el JS solo ayuda a rellenar, nunca es necesario para guardar.
    El texto pegado nunca se evalúa ni se inserta como HTML (solo textContent / value).
    La regla GTM vs. etiquetas directas se explica aquí porque es la causa más
    común de contar dos veces cada visita.
--}}
@php
    $estado = $estado ?? [];
    $emite = $estado['emite'] ?? [];
    $activaHoy = (bool) ($estado['activa'] ?? false);
    $motivos = $estado['motivos'] ?? [];
    $formatos = $formatos ?? [];
    $fGtm = $formatos['gtm'] ?? 'GTM-ABC1234';
    $fGa4 = $formatos['ga4'] ?? 'G-ABCDE12345';
    $fAds = $formatos['ads'] ?? 'AW-123456789';
    $fConv = $formatos['conversion'] ?? 'AW-123456789/AbC_dEfGhIj';

    $cargando = [];
    if (! empty($emite['gtm'])) { $cargando[] = 'Google Tag Manager'; }
    if (! empty($emite['ga4'])) { $cargando[] = 'Google Analytics 4'; }
    if (! empty($emite['ads'])) { $cargando[] = 'Google Ads'; }

    $dominiosTexto = trim((string) old('dominios', $config?->dominios));
    $idAdsGuardado = $config?->google_ads_id;
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Medición del sitio</h1>
            <p class="page-header__subtitle">Conecta Google Tag Manager, Google Analytics y Google Ads con tu sitio público. Solo tienes que copiar y pegar.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="cfg-med" data-configuracion-medicion>

        {{-- a) Estado actual --}}
        <section class="card card--padded cfg-med__estado {{ $activaHoy ? 'cfg-med__estado--ok' : 'cfg-med__estado--aviso' }}" aria-labelledby="med_estado_titulo">
            <div class="cfg-med__estado-icono" aria-hidden="true">
                <i class="fa-solid {{ $activaHoy ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
            </div>
            <div class="cfg-med__estado-cuerpo">
                <h2 class="cfg-med__estado-titulo" id="med_estado_titulo">
                    @if ($activaHoy)
                        Activa: el sitio carga {{ $cargando ? implode(' y ', $cargando) : 'la medición' }}
                    @else
                        Todavía no se carga nada
                    @endif
                </h2>

                @if (! $activaHoy && count($motivos))
                    <p class="cfg-med__estado-texto">Esto es lo que lo impide ahora mismo:</p>
                    <ul class="cfg-med__lista">
                        @foreach ($motivos as $motivo)
                            <li>{{ $motivo }}</li>
                        @endforeach
                    </ul>
                @elseif ($activaHoy && ! empty($emite['banner']))
                    <p class="cfg-med__estado-texto">Los visitantes verán el aviso de cookies; la publicidad queda bloqueada hasta que lo acepten.</p>
                @endif

                @if (($estado['fuente'] ?? null) === 'env')
                    <p class="cfg-med__estado-texto cfg-med__estado-texto--nota">
                        <i class="fa-solid fa-server"></i>
                        Hoy se usa la configuración del servidor (<code>.env</code>). Al guardar aquí, este panel manda.
                    </p>
                @endif

                <dl class="cfg-med__datos">
                    <div>
                        <dt>Estás en</dt>
                        <dd class="u-mono">{{ $hostActual ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Se carga en</dt>
                        <dd class="u-mono">{{ $dominiosTexto !== '' ? $dominiosTexto : 'los dominios que indiques abajo' }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.configuracion.medicion.update') }}" id="medicionForm" data-med-form novalidate>
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="form-status form-status--error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i>
                    <span>Revisa los campos marcados en rojo antes de guardar.</span>
                </div>
            @endif

            {{-- b) Pegar código --}}
            <section class="card card--padded cfg-med__pegar" aria-labelledby="med_pegar_titulo">
                <h2 class="cfg-med__titulo" id="med_pegar_titulo"><i class="fa-solid fa-paste"></i> Pega aquí el código de Google</h2>
                <div class="field">
                    <label class="field__label" for="med_codigo">Pega el código completo que te dio Google (Tag Manager, Analytics o Google Ads) o solo el ID</label>
                    <textarea class="textarea u-mono cfg-med__codigo" name="codigo_pegado" id="med_codigo" rows="6" spellcheck="false" autocomplete="off"
                              aria-describedby="med_codigo_ayuda med_detectado"
                              placeholder="Ejemplo: {{ $fAds }}  o el bloque que empieza con &lt;script async src=&quot;https://www.googletagmanager.com/gtag/js?id=...&quot;&gt;"
                              data-med-codigo>{{ old('codigo_pegado') }}</textarea>
                    <p class="field__hint" id="med_codigo_ayuda">No hace falta limpiar nada: nosotros buscamos los IDs y rellenamos los campos de abajo. Lo que pegas aquí no se guarda; solo se guardan los IDs.</p>
                </div>

                <div class="cfg-med__detectado" id="med_detectado" aria-live="polite">
                    <span class="cfg-med__detectado-titulo" data-med-detectado-titulo>Aún no hemos detectado nada.</span>
                    <span class="cfg-med__chips" data-med-chips></span>
                    <button type="button" class="btn btn--secondary btn--sm" data-med-limpiar hidden>
                        <i class="fa-solid fa-eraser"></i> Limpiar
                    </button>
                </div>
            </section>

            {{-- c) Tres etiquetas --}}
            <div class="cfg-med__tarjetas">

                <section class="card card--padded cfg-med__tarjeta" aria-labelledby="med_gtm_titulo">
                    <h2 class="cfg-med__titulo" id="med_gtm_titulo">Google Tag Manager</h2>
                    <p class="cfg-med__para">Un “contenedor” donde se administran todas tus etiquetas desde la web de Google, sin tocar el sitio.</p>
                    <div class="field">
                        <label class="field__label" for="med_gtm">ID del contenedor</label>
                        <input class="input u-mono" type="text" name="gtm_id" id="med_gtm" maxlength="20" autocomplete="off" spellcheck="false"
                               placeholder="{{ $fGtm }}" value="{{ old('gtm_id', $config?->gtm_id) }}" aria-describedby="med_gtm_ayuda" data-med-campo="gtm">
                        <p class="field__hint" id="med_gtm_ayuda">Formato: <code>{{ $fGtm }}</code></p>
                        @error('gtm_id')<span class="field__error">{{ $message }}</span>@enderror
                    </div>

                    <p class="cfg-med__regla">
                        <i class="fa-solid fa-circle-info"></i>
                        <span><strong>Regla importante:</strong> si pegas un Tag Manager, Analytics y Google Ads se configuran <strong>dentro</strong> de Tag Manager y las etiquetas directas de abajo no se cargan (para no contar dos veces). Si <strong>no</strong> tienes Tag Manager, deja este campo vacío y usa solo los de Analytics 4 y Google Ads: es lo más simple.</span>
                    </p>

                    <details class="cfg-med__ayuda">
                        <summary>Cómo lo consigo</summary>
                        <ol>
                            <li>Entra a <strong>tagmanager.google.com</strong> con tu cuenta de Google.</li>
                            <li>Haz clic en tu cuenta y luego en tu contenedor.</li>
                            <li>Arriba a la derecha verás el ID: <code>GTM-XXXXXXX</code>. Cópialo.</li>
                        </ol>
                        <p class="cfg-med__ayuda-sub">No lo tengo, ¿qué hago?</p>
                        <ol>
                            <li>En tagmanager.google.com haz clic en <strong>Crear cuenta</strong>.</li>
                            <li>Como nombre del contenedor escribe tu dominio (por ejemplo <code>rankprosolutions.com.mx</code>).</li>
                            <li>En “Plataforma de destino” elige <strong>Web</strong> y crea el contenedor.</li>
                            <li>Copia el <code>GTM-XXXXXXX</code> que aparece. Si prefieres no usar Tag Manager, sáltate esta tarjeta.</li>
                        </ol>
                    </details>
                </section>

                <section class="card card--padded cfg-med__tarjeta" aria-labelledby="med_ga4_titulo">
                    <h2 class="cfg-med__titulo" id="med_ga4_titulo">Google Analytics 4</h2>
                    <p class="cfg-med__para">Te dice cuánta gente visita el sitio, de dónde llega y qué hace.</p>
                    <div class="field">
                        <label class="field__label" for="med_ga4">ID de medición</label>
                        <input class="input u-mono" type="text" name="ga4_id" id="med_ga4" maxlength="20" autocomplete="off" spellcheck="false"
                               placeholder="{{ $fGa4 }}" value="{{ old('ga4_id', $config?->ga4_id) }}" aria-describedby="med_ga4_ayuda" data-med-campo="ga4">
                        <p class="field__hint" id="med_ga4_ayuda">Formato: <code>{{ $fGa4 }}</code> (empieza con G-)</p>
                        @error('ga4_id')<span class="field__error">{{ $message }}</span>@enderror
                    </div>

                    <details class="cfg-med__ayuda">
                        <summary>Cómo lo consigo</summary>
                        <ol>
                            <li>Entra a <strong>analytics.google.com</strong>.</li>
                            <li>Abajo a la izquierda haz clic en <strong>Administrar</strong> (el engrane).</li>
                            <li>Ve a <strong>Flujos de datos</strong> y abre tu flujo web.</li>
                            <li>Copia el <strong>ID de medición</strong>: empieza con <code>G-</code>.</li>
                        </ol>
                        <p class="cfg-med__ayuda-sub">No lo tengo, ¿qué hago?</p>
                        <ol>
                            <li>En Administrar, haz clic en <strong>Crear</strong> y elige <strong>Propiedad</strong> (Google Analytics 4).</li>
                            <li>Sigue el asistente y, cuando pida un flujo de datos, elige <strong>Web</strong> y escribe la URL de tu sitio.</li>
                            <li>Al terminar aparece el ID de medición <code>G-…</code>. Cópialo.</li>
                        </ol>
                    </details>
                </section>

                <section class="card card--padded cfg-med__tarjeta" aria-labelledby="med_ads_titulo">
                    <h2 class="cfg-med__titulo" id="med_ads_titulo">Google Ads</h2>
                    <p class="cfg-med__para">Permite medir los resultados de tus anuncios y mostrarlos de nuevo a quien ya visitó el sitio.</p>
                    <div class="field">
                        <label class="field__label" for="med_ads">ID de Google Ads</label>
                        <input class="input u-mono" type="text" name="google_ads_id" id="med_ads" maxlength="30" autocomplete="off" spellcheck="false"
                               placeholder="{{ $fAds }}" value="{{ old('google_ads_id', $idAdsGuardado) }}" aria-describedby="med_ads_ayuda" data-med-campo="ads">
                        <p class="field__hint" id="med_ads_ayuda">
                            Formato: <code>{{ $fAds }}</code> (empieza con AW-).
                            @if ($idAdsGuardado)
                                ID guardado ahora: <code>{{ $idAdsGuardado }}</code>.
                            @endif
                        </p>
                        @error('google_ads_id')<span class="field__error">{{ $message }}</span>@enderror
                    </div>

                    <details class="cfg-med__ayuda">
                        <summary>Cómo lo consigo</summary>
                        <ol>
                            <li>Entra a <strong>ads.google.com</strong>.</li>
                            <li>Haz clic en <strong>Herramientas</strong> y luego en <strong>Administrar datos</strong> (o en “Etiqueta de Google”). También sirve el asistente “Mida las conversiones”.</li>
                            <li>Elige <strong>Instalar manualmente</strong>.</li>
                            <li>Copia <strong>solo</strong> el ID que empieza con <code>AW-</code>, o pega todo el bloque en el cuadro de arriba y nosotros lo buscamos.</li>
                        </ol>
                        <p class="cfg-med__ayuda-sub">No lo tengo, ¿qué hago?</p>
                        <ol>
                            <li>Necesitas una cuenta de Google Ads: créala en <strong>ads.google.com</strong> con “Empezar ahora”.</li>
                            <li>Cuando la cuenta exista, repite los pasos de arriba. Sin cuenta no hay ID.</li>
                        </ol>
                    </details>
                </section>
            </div>

            {{-- d) Conversiones --}}
            <section class="card card--padded cfg-med__seccion" aria-labelledby="med_conv_titulo">
                <h2 class="cfg-med__titulo" id="med_conv_titulo">Conversiones de Google Ads <span class="badge badge--neutral">Opcional</span></h2>
                <p class="cfg-med__para">Una conversión es una acción valiosa: que alguien reserve una reunión o te escriba. Con estas etiquetas Google Ads sabe qué anuncios te traen resultados.</p>

                <div class="form-grid form-grid--3">
                    <div class="field">
                        <label class="field__label" for="med_conv_agendar">Reserva en /agendar</label>
                        <input class="input u-mono" type="text" name="conversion_agendar" id="med_conv_agendar" maxlength="60" autocomplete="off" spellcheck="false"
                               placeholder="{{ $fConv }}" value="{{ old('conversion_agendar', $config?->conversion_agendar) }}" aria-describedby="med_conv_ayuda">
                        @error('conversion_agendar')<span class="field__error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="med_conv_wa">Clic a WhatsApp</label>
                        <input class="input u-mono" type="text" name="conversion_whatsapp" id="med_conv_wa" maxlength="60" autocomplete="off" spellcheck="false"
                               placeholder="{{ $fConv }}" value="{{ old('conversion_whatsapp', $config?->conversion_whatsapp) }}" aria-describedby="med_conv_ayuda">
                        @error('conversion_whatsapp')<span class="field__error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="field__label" for="med_conv_tel">Clic al teléfono</label>
                        <input class="input u-mono" type="text" name="conversion_telefono" id="med_conv_tel" maxlength="60" autocomplete="off" spellcheck="false"
                               placeholder="{{ $fConv }}" value="{{ old('conversion_telefono', $config?->conversion_telefono) }}" aria-describedby="med_conv_ayuda">
                        @error('conversion_telefono')<span class="field__error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <p class="field__hint" id="med_conv_ayuda">Formato: <code>{{ $fConv }}</code>. Déjalo vacío si todavía no creas la conversión.</p>

                <details class="cfg-med__ayuda">
                    <summary>Cómo consigo cada etiqueta</summary>
                    <ol>
                        <li>En <strong>ads.google.com</strong> ve a <strong>Objetivos</strong> &gt; <strong>Conversiones</strong> &gt; <strong>Resumen</strong>.</li>
                        <li>Haz clic en <strong>+ Nueva acción de conversión</strong> y elige <strong>Sitio web</strong>.</li>
                        <li>Elige <strong>Agregar manualmente</strong> y crea la conversión (por ejemplo “Reserva”).</li>
                        <li>Al terminar elige <strong>Usar la etiqueta de Google</strong> (o “Instalar la etiqueta”).</li>
                        <li>Busca una línea como <code>send_to: 'AW-123456789/AbC_dEfGhIj'</code> y copia lo que está entre comillas: <code>AW-123456789/AbC_dEfGhIj</code>.</li>
                        <li>Pégalo en el campo que corresponde. Repite para cada acción.</li>
                    </ol>
                </details>

                <p class="cfg-med__regla">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Estas etiquetas solo se disparan cuando se usa Google Ads <strong>directo</strong> (sin Tag Manager). Si usas Tag Manager, crea ahí un disparador por evento con estos nombres:</span>
                </p>
                <ul class="cfg-med__eventos">
                    @foreach (['agendar_completado' => 'Reserva completada en /agendar', 'click_whatsapp' => 'Clic a WhatsApp', 'click_telefono' => 'Clic al teléfono'] as $evento => $descripcion)
                        <li>
                            <code class="u-mono">{{ $evento }}</code>
                            <span class="cfg-med__evento-desc">{{ $descripcion }}</span>
                            <button type="button" class="btn btn--secondary btn--sm" data-med-copiar="{{ $evento }}" aria-label="Copiar el nombre {{ $evento }}">
                                <i class="fa-regular fa-copy"></i> Copiar
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- e) Activar --}}
            <section class="card card--padded cfg-med__seccion" aria-labelledby="med_activar_titulo">
                <h2 class="cfg-med__titulo" id="med_activar_titulo">Activar</h2>

                <label class="cfg-switch" style="margin-bottom: var(--space-4);">
                    <input type="hidden" name="activa" value="0">
                    <input type="checkbox" name="activa" value="1" aria-describedby="med_activa_ayuda" @checked(old('activa', $config?->activa))>
                    <span class="cfg-switch__pista"></span>
                    <span class="cfg-switch__texto">Cargar la medición en el sitio</span>
                </label>
                <p class="field__hint" id="med_activa_ayuda" style="margin-top: calc(var(--space-2) * -1); margin-bottom: var(--space-4);">Apagado, el sitio no carga nada de Google aunque tengas IDs guardados.</p>

                <div class="field">
                    <label class="field__label" for="med_dominios">Dominios donde aplica</label>
                    <input class="input u-mono" type="text" name="dominios" id="med_dominios" maxlength="500" autocomplete="off" spellcheck="false"
                           placeholder="rankprosolutions.com.mx" value="{{ old('dominios', $config?->dominios) }}" aria-describedby="med_dominios_ayuda">
                    <p class="field__hint" id="med_dominios_ayuda">Solo se carga en estos dominios, para que una copia de prueba no contamine tus datos. Normalmente: <code>rankprosolutions.com.mx</code>. Si pones varios, sepáralos con comas.</p>
                    @error('dominios')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </section>

            {{-- g) Guardar --}}
            <div class="cfg-med__guardar">
                <button type="submit" class="btn btn--primary" data-med-guardar>
                    <i class="fa-solid fa-floppy-disk"></i> <span data-med-guardar-texto>Guardar</span>
                </button>
                <span class="cfg-med__sin-guardar" data-med-sin-guardar hidden role="status">
                    <i class="fa-solid fa-circle"></i> Tienes cambios sin guardar
                </span>
            </div>
        </form>

        {{-- f) Comprobar --}}
        <section class="card card--padded cfg-med__seccion" aria-labelledby="med_comprobar_titulo">
            <h2 class="cfg-med__titulo" id="med_comprobar_titulo">Cómo comprobar que funciona</h2>
            <ol class="cfg-med__pasos">
                <li><strong>Guarda</strong> con el botón de arriba y confirma que el cuadro de estado se pone en verde.</li>
                <li>Abre tu sitio, haz <strong>clic derecho &gt; Ver código fuente de la página</strong> y busca (Ctrl + F) tu ID, por ejemplo <code>{{ $idAdsGuardado ?: $fAds }}</code>. Si aparece, el sitio lo está cargando.</li>
                <li>Instala o abre <strong>Tag Assistant</strong> de Google (tagassistant.google.com), escribe la dirección de tu sitio y comprueba que detecta tus etiquetas.</li>
                <li>En <strong>Google Ads</strong> entra a la etiqueta o conversión y haz clic en <strong>Volver a probar</strong>. Puede tardar unas horas en pasar a “activa”.</li>
            </ol>
            <p class="field__hint">
                <i class="fa-solid fa-cookie-bite"></i>
                Es normal: mientras la persona no acepte el banner de cookies, la publicidad queda denegada y no se envían datos de anuncios. Para probar, acepta el banner en tu navegador.
            </p>
        </section>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/configuracion.js')
@endsection
