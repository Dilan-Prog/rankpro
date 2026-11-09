{{--
    Asistente de alta de cliente en 4 pasos (clientes-asistente.js). A diferencia del generador de Audiencia, el alta SÍ
    persiste: un solo POST transaccional a admin.clientes.asistente.store. «Alta rápida» guarda solo nombre comercial y contacto.
    Estilo .form-moderno / .modal--moderno de clientes.css.
--}}
@php
    $datosAsistente = [
        'giros' => collect($giros)->map(fn ($g) => [
            'clave' => $g['clave'],
            'etiqueta' => $g['etiqueta'],
            'tema' => $g['tema'],
            'servicios' => $g['servicios'],
            'metricas' => collect($g['metricas'])->map(fn ($clave) => \App\Support\Metricas\Catalogo::buscar($clave)['nombre'] ?? $clave)->all(),
            'keywords' => $g['keywords'],
            'reportes' => $g['reportes'],
        ])->values()->all(),
        'tipos' => collect(\App\Enums\TipoServicio::cases())->map(fn ($t) => ['valor' => $t->value, 'etiqueta' => \App\Support\Labels::servicioTipo($t)])->all(),
        'cobros' => collect(\App\Enums\TipoCobro::cases())->map(fn ($c) => ['valor' => $c->value, 'etiqueta' => $c->label()])->all(),
        'gscDisponible' => $gscDisponible,
    ];
    $pasosAsistente = ['Negocio', 'Contacto y contrato', 'Servicios', 'Marca y conexiones'];
@endphp

<div class="modal-overlay" id="altaClienteModal" hidden>
    <div class="modal modal--moderno modal--asistente" role="dialog" aria-modal="true" aria-labelledby="altaClienteTitulo" aria-describedby="altaClienteSubtitulo">
        <form id="altaClienteForm" class="form-moderno" novalidate enctype="multipart/form-data"
              data-url-store="{{ route('admin.clientes.asistente.store') }}"
              data-url-validar="{{ route('admin.clientes.asistente.validar') }}"
              data-url-servicios="{{ route('admin.clientes.asistente.servicios') }}">
            @csrf
            <div class="modal--moderno__cabecera">
                <div>
                    <h2 id="altaClienteTitulo" class="modal--moderno__titulo">Nuevo cliente</h2>
                    <p id="altaClienteSubtitulo" class="modal--moderno__subtitulo" data-asistente-subtitulo>Paso 1 de 4 · Negocio</p>
                </div>
                <button type="button" class="modal--moderno__cerrar" data-modal-close="altaClienteModal" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="asistente__progreso" aria-hidden="true"><div class="asistente__barra" data-asistente-barra></div></div>
            <ol class="asistente__pasos" data-asistente-lista-pasos>
                @foreach ($pasosAsistente as $i => $nombrePaso)
                    <li data-asistente-etiqueta="{{ $i + 1 }}">{{ $i + 1 }}. {{ $nombrePaso }}</li>
                @endforeach
            </ol>

            <div class="modal--moderno__cuerpo">
                <p class="form-moderno__error asistente__error" data-asistente-error role="alert"></p>

                {{-- Paso 1: Negocio --}}
                <section class="asistente__paso" data-asistente-paso="1">
                    <div class="form-moderno__campo">
                        <label class="form-moderno__etiqueta" for="al_nombre_comercial">Nombre comercial <span class="form-moderno__requerido" aria-hidden="true">*</span></label>
                        <input class="form-moderno__control" type="text" name="nombre_comercial" id="al_nombre_comercial" placeholder="Ej. Hotel Fratelli" required>
                        <span class="form-moderno__error" data-error-for="nombre_comercial" role="alert"></span>
                    </div>
                    <div class="form-moderno__rejilla form-moderno__rejilla--2">
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_giro">Giro</label>
                            <select class="form-moderno__control form-moderno__control--select" name="giro" id="al_giro">
                                <option value="">Otro</option>
                                @foreach (\App\Support\Plantillas\Giros::etiquetas() as $clave => $etiqueta)
                                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            <span class="form-moderno__error" data-error-for="giro" role="alert"></span>
                        </div>
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_plantilla">Plantilla</label>
                            <select class="form-moderno__control form-moderno__control--select" name="plantilla" id="al_plantilla">
                                <option value="">Sin plantilla</option>
                                @foreach (\App\Support\Plantillas\Giros::etiquetas() as $clave => $etiqueta)
                                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            <span class="form-moderno__error" data-error-for="plantilla" role="alert"></span>
                        </div>
                    </div>
                    <div class="form-moderno__rejilla form-moderno__rejilla--2">
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_sitio_web">Sitio web</label>
                            <input class="form-moderno__control" type="text" name="sitio_web" id="al_sitio_web" placeholder="hotelejemplo.com">
                            <span class="form-moderno__error" data-error-for="sitio_web" role="alert"></span>
                        </div>
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_ciudad">Ciudad</label>
                            <input class="form-moderno__control" type="text" name="ciudad" id="al_ciudad" placeholder="Ej. Aguascalientes">
                            <span class="form-moderno__error" data-error-for="ciudad" role="alert"></span>
                        </div>
                    </div>
                    <div class="form-moderno__campo" data-asistente-tema-campo hidden>
                        <label class="form-moderno__etiqueta" for="al_tema" data-asistente-tema-etiqueta>Tema para las keywords (opcional)</label>
                        <input class="form-moderno__control" type="text" name="tema" id="al_tema" maxlength="120">
                        <span class="form-moderno__ayuda">Rellena las keywords semilla de la plantilla; si lo dejas vacío, esas keywords no se crean.</span>
                        <span class="form-moderno__error" data-error-for="tema" role="alert"></span>
                    </div>
                    <div class="asistente__plantilla" data-asistente-vista-plantilla hidden></div>

                    {{-- Alta rápida: reutiliza el nombre comercial del paso 1 y pide solo la persona de contacto. --}}
                    <div class="asistente__rapido" data-asistente-rapido hidden>
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_rapido_contacto">Persona de contacto <span class="form-moderno__requerido" aria-hidden="true">*</span></label>
                            <input class="form-moderno__control" type="text" id="al_rapido_contacto" placeholder="Nombre y apellido">
                            <span class="form-moderno__error" data-error-for="contacto_nombre_rapido" role="alert"></span>
                        </div>
                        <p class="form-moderno__ayuda">Se guarda con el perfil incompleto (sin correo, WhatsApp ni renovación) y aparecerá en Pendientes de captura hasta completarlo.</p>
                    </div>
                </section>

                {{-- Paso 2: Contacto y contrato --}}
                <section class="asistente__paso" data-asistente-paso="2" hidden>
                    <div class="form-moderno__campo">
                        <label class="form-moderno__etiqueta" for="al_contacto_nombre">Persona de contacto <span class="form-moderno__requerido" aria-hidden="true">*</span></label>
                        <input class="form-moderno__control" type="text" name="contacto_nombre" id="al_contacto_nombre" placeholder="Nombre y apellido">
                        <span class="form-moderno__error" data-error-for="contacto_nombre" role="alert"></span>
                    </div>
                    <div class="form-moderno__rejilla form-moderno__rejilla--2">
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_telefono">WhatsApp</label>
                            <input class="form-moderno__control" type="tel" name="telefono" id="al_telefono" placeholder="+52 55 0000 0000">
                            <span class="form-moderno__error" data-error-for="telefono" role="alert"></span>
                        </div>
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_email">Correo</label>
                            <input class="form-moderno__control" type="email" name="email" id="al_email" placeholder="contacto@empresa.com">
                            <span class="form-moderno__error" data-error-for="email" role="alert"></span>
                        </div>
                    </div>
                    <div class="form-moderno__rejilla form-moderno__rejilla--2">
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_inicio">Inicio de contrato</label>
                            <input class="form-moderno__control" type="date" name="fecha_inicio_contrato" id="al_inicio">
                            <span class="form-moderno__error" data-error-for="fecha_inicio_contrato" role="alert"></span>
                        </div>
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_renovacion">Vencimiento</label>
                            <input class="form-moderno__control" type="date" name="fecha_renovacion_contrato" id="al_renovacion">
                            <span class="form-moderno__error" data-error-for="fecha_renovacion_contrato" role="alert"></span>
                        </div>
                    </div>
                    <div class="form-moderno__rejilla form-moderno__rejilla--2">
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_forma_pago">Frecuencia</label>
                            <select class="form-moderno__control form-moderno__control--select" name="forma_pago" id="al_forma_pago">
                                <option value="">Sin especificar</option>
                                @foreach (\App\Enums\FormaPago::cases() as $case)
                                    <option value="{{ $case->value }}">{{ \App\Support\Labels::formaPago($case->value) }}</option>
                                @endforeach
                            </select>
                            <span class="form-moderno__error" data-error-for="forma_pago" role="alert"></span>
                        </div>
                        <div class="form-moderno__campo">
                            <label class="form-moderno__etiqueta" for="al_metodo_pago">Forma de pago</label>
                            <select class="form-moderno__control form-moderno__control--select" name="metodo_pago" id="al_metodo_pago">
                                <option value="">Sin especificar</option>
                                @foreach (\App\Enums\MetodoPago::cases() as $case)
                                    <option value="{{ $case->value }}">{{ \App\Support\Labels::metodoPago($case->value) }}</option>
                                @endforeach
                            </select>
                            <span class="form-moderno__error" data-error-for="metodo_pago" role="alert"></span>
                        </div>
                    </div>
                </section>

                {{-- Paso 3: Servicios --}}
                <section class="asistente__paso" data-asistente-paso="3" hidden>
                    <p class="form-moderno__ayuda" data-asistente-servicios-nota></p>
                    <div class="asistente__servicios" data-asistente-servicios></div>
                    <button type="button" class="form-moderno__boton form-moderno__boton--secundario" data-asistente-agregar-servicio>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Añadir servicio
                    </button>
                    <div class="asistente__mrr" aria-live="polite">
                        <div><span class="asistente__mrr-etiqueta">MRR (solo cobros mensuales)</span><strong data-asistente-mrr>$0</strong></div>
                        <div><span class="asistente__mrr-etiqueta">Pago único (aparte)</span><strong data-asistente-unico>$0</strong></div>
                        <div><span class="asistente__mrr-etiqueta">Gratuitos</span><strong data-asistente-gratuitos>0</strong></div>
                    </div>
                    <p class="form-moderno__ayuda" data-asistente-sin-precio hidden></p>
                </section>

                {{-- Paso 4: Marca y conexiones --}}
                <section class="asistente__paso" data-asistente-paso="4" hidden>
                    <div class="form-moderno__campo">
                        <label class="form-moderno__etiqueta" for="al_logo">Logo (opcional)</label>
                        <input class="form-moderno__control" type="file" name="logo" id="al_logo" accept=".png,.jpg,.jpeg,.webp,.svg">
                        <span class="form-moderno__error" data-error-for="logo" role="alert"></span>
                    </div>
                    <div class="form-moderno__campo">
                        <span class="form-moderno__etiqueta">Colores de la marca (opcional)</span>
                        <div class="asistente__colores" data-asistente-colores>
                            @foreach (range(0, 3) as $i)
                                <label class="asistente__color">
                                    <input type="color" value="#0F9D6E" data-asistente-color="{{ $i }}" data-vacio="1" aria-label="Color {{ $i + 1 }}">
                                    <span>Color {{ $i + 1 }}</span>
                                </label>
                            @endforeach
                        </div>
                        <span class="form-moderno__error" data-error-for="colores" role="alert"></span>
                    </div>
                    <fieldset class="form-moderno__campo">
                        <legend class="form-moderno__etiqueta">Tono de voz (opcional)</legend>
                        <div class="form-moderno__chips">
                            @foreach (\App\Enums\TonoMarca::cases() as $tono)
                                <label class="chips__opcion">
                                    <input type="checkbox" name="tonos[]" value="{{ $tono->value }}">
                                    <span class="chips__chip">{{ $tono->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="asistente__conexiones">
                        <h3 class="asistente__subtitulo">Conexiones</h3>
                        <div class="asistente__conexion" data-asistente-gsc>
                            @if ($gscDisponible)
                                <label class="asistente__conexion-check">
                                    <input type="checkbox" name="conexiones_gsc" value="1" data-asistente-gsc-check>
                                    <span>Search Console: vincular la propiedad del sitio web del cliente</span>
                                </label>
                                <span class="form-moderno__ayuda" data-asistente-gsc-nota>Se crea con el sitio web del paso 1; el cliente debe haber dado acceso a la cuenta de servicio de Google.</span>
                            @else
                                <span>Search Console: aún no hay una conexión de Google configurada.</span>
                                <a href="{{ route('admin.integraciones.search-console.index') }}">Configurar en Integraciones</a>
                            @endif
                        </div>
                        <div class="asistente__conexion">
                            <span>Tracking de conversiones: el snippet con el token del cliente está disponible al terminar el alta.</span>
                        </div>
                        <div class="asistente__conexion asistente__conexion--apagada">
                            <span>Meta Ads</span>
                            <span class="badge badge--neutral">No disponible</span>
                            <span class="form-moderno__ayuda">Todavía no existe una integración con Meta.</span>
                        </div>
                    </div>
                </section>

                {{-- Resultado --}}
                <section class="asistente__resultado" data-asistente-resultado hidden tabindex="-1">
                    <h3 class="asistente__subtitulo" data-asistente-resultado-titulo>Cliente creado</h3>
                    <ul class="asistente__lista" data-asistente-resultado-lista></ul>
                    <p class="asistente__aviso" data-asistente-resultado-perfil hidden></p>
                    <div class="asistente__enlaces">
                        <a class="form-moderno__boton form-moderno__boton--primario" data-asistente-enlace-ficha href="#">Ir a la ficha</a>
                        <a class="form-moderno__boton form-moderno__boton--secundario" data-asistente-enlace-tracking href="#">Snippet de tracking</a>
                    </div>
                </section>
            </div>

            <div class="modal--moderno__pie asistente__pie">
                <span class="asistente__atajos">Enter: siguiente · Alt+←: atrás</span>
                <button type="button" class="form-moderno__boton form-moderno__boton--secundario asistente__rapida-boton" data-asistente-alta-rapida>Alta rápida</button>
                <button type="button" class="form-moderno__boton form-moderno__boton--secundario" data-asistente-atras>Cancelar</button>
                <button type="button" class="form-moderno__boton form-moderno__boton--primario" data-asistente-siguiente>Siguiente</button>
            </div>
        </form>
        <script type="application/json" id="asistenteDatos">@json($datosAsistente)</script>
    </div>
</div>
