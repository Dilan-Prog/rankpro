@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/audiencia.css')
@endsection

@section('scripts')
    @vite('resources/js/audiencia.js')
@endsection

{{--
    Audiencia: buyer personas del cliente activo. Captura manual con tarjetas y
    un modal único para crear/editar; reordenar con flechas (formularios POST).
--}}
@section('content')
    <div class="audiencia" data-audiencia>
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Audiencia</h1>
                <p class="page-header__subtitle">
                    @if ($cliente)
                        {{ $cliente->nombre_comercial }} · {{ $personas->count() }} {{ $personas->count() === 1 ? 'buyer persona' : 'buyer personas' }}
                    @else
                        Buyer personas de cada cliente.
                    @endif
                </p>
            </div>
            @if ($cliente)
                <div class="audiencia__acciones-cabecera">
                    <button type="button" class="btn" data-generador-abrir data-url="{{ route('admin.audiencia.generar') }}"><i class="fa-solid fa-list-check"></i> Armar con 4 preguntas</button>
                    <button type="button" class="btn btn--primary" data-persona-nueva><i class="fa-solid fa-plus"></i> Agregar</button>
                </div>
            @endif
        </div>

        @if (session('status'))
            <div class="form-status"><span>{{ session('status') }}</span></div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status--error"><span>{{ $errors->first() }}</span></div>
        @endif

        @if (! $cliente)
            <div class="card card--padded empty-state" data-audiencia-vacio>
                <i class="fa-solid fa-users empty-state__icon"></i>
                <p class="empty-state__text">Elige un cliente en el selector de la sección «Marca» del menú para ver o capturar sus buyer personas.</p>
            </div>
        @elseif ($personas->isEmpty())
            <div class="card card--padded empty-state" data-audiencia-sin-personas>
                <i class="fa-solid fa-users empty-state__icon"></i>
                <p class="empty-state__text">Este cliente todavía no tiene buyer personas. Describe a quién le vende: quién es, qué le duele y qué decirle.</p>
                <div class="audiencia__acciones-cabecera">
                    <button type="button" class="btn" data-generador-abrir data-url="{{ route('admin.audiencia.generar') }}"><i class="fa-solid fa-list-check"></i> Armar con 4 preguntas</button>
                    <button type="button" class="btn btn--primary" data-persona-nueva>Agregar la primera</button>
                </div>
            </div>
        @else
            <div class="audiencia__grid">
                @foreach ($personas as $persona)
                    <article class="card card--padded audiencia__tarjeta" data-persona="{{ $persona->id }}">
                        <header class="audiencia__cabecera">
                            <span class="audiencia__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($persona->nombre, 0, 1)) }}</span>
                            <h2 class="audiencia__nombre">{{ $persona->nombre }}</h2>
                            <div class="audiencia__acciones">
                                @if (! $loop->first)
                                    <form method="POST" action="{{ route('admin.audiencia.mover', $persona) }}">
                                        @csrf
                                        <input type="hidden" name="direccion" value="arriba">
                                        <button type="submit" class="btn btn--icon" title="Mover antes" aria-label="Mover antes"><i class="fa-solid fa-arrow-up"></i></button>
                                    </form>
                                @endif
                                @if (! $loop->last)
                                    <form method="POST" action="{{ route('admin.audiencia.mover', $persona) }}">
                                        @csrf
                                        <input type="hidden" name="direccion" value="abajo">
                                        <button type="submit" class="btn btn--icon" title="Mover después" aria-label="Mover después"><i class="fa-solid fa-arrow-down"></i></button>
                                    </form>
                                @endif
                                <button type="button" class="btn btn--icon" title="Editar" aria-label="Editar" data-persona-editar
                                        data-url="{{ route('admin.audiencia.update', $persona) }}"
                                        data-nombre="{{ $persona->nombre }}"
                                        data-perfil="{{ $persona->perfil }}"
                                        data-dolores="{{ $persona->dolores }}"
                                        data-mensaje="{{ $persona->mensaje }}"
                                        data-canales="{{ $persona->canales }}"><i class="fa-solid fa-pen"></i></button>
                            </div>
                        </header>

                        @if ($persona->perfil)
                            <p class="audiencia__texto audiencia__texto--suave">{{ $persona->perfil }}</p>
                        @endif

                        @if ($persona->doloresLista())
                            <div>
                                <div class="audiencia__rotulo">Dolores</div>
                                <ul class="audiencia__dolores">
                                    @foreach ($persona->doloresLista() as $dolor)
                                        <li>{{ $dolor }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($persona->mensaje)
                            <div>
                                <div class="audiencia__rotulo">Qué decirles</div>
                                <p class="audiencia__texto">{{ $persona->mensaje }}</p>
                            </div>
                        @endif

                        @if ($persona->canales)
                            <footer class="audiencia__pie"><strong>Dónde:</strong> {{ $persona->canales }}</footer>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if ($cliente)
            <x-modal id="personaModal">
                <x-slot:header><h2 data-persona-modal-titulo>Nueva buyer persona</h2></x-slot:header>
                <p class="audiencia__aviso" data-persona-aviso-borrador hidden>Borrador armado desde una plantilla: revísalo y edítalo antes de guardar. No se guarda hasta que pulses Guardar.</p>
                <form method="POST" action="{{ route('admin.audiencia.store') }}" data-persona-form
                      data-url-crear="{{ route('admin.audiencia.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="PUT" data-persona-method disabled>

                    <div class="field">
                        <label class="field__label" for="persona_nombre">Nombre</label>
                        <input type="text" class="input" id="persona_nombre" name="nombre" maxlength="120" required placeholder="Ej. Laura, dueña de negocio">
                    </div>
                    <div class="field">
                        <label class="field__label" for="persona_perfil">Perfil</label>
                        <textarea class="input textarea" id="persona_perfil" name="perfil" rows="2" maxlength="2000" placeholder="Quién es, qué hace, qué busca"></textarea>
                    </div>
                    <div class="field">
                        <label class="field__label" for="persona_dolores">Dolores (uno por línea)</label>
                        <textarea class="input textarea" id="persona_dolores" name="dolores" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="field">
                        <label class="field__label" for="persona_mensaje">Qué decirle</label>
                        <textarea class="input textarea" id="persona_mensaje" name="mensaje" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div class="field">
                        <label class="field__label" for="persona_canales">Dónde encontrarla</label>
                        <input type="text" class="input" id="persona_canales" name="canales" maxlength="255" placeholder="Ej. Instagram, Google, referidos">
                    </div>

                    <div class="audiencia__modal-pie">
                        <button type="button" class="btn btn--danger audiencia__eliminar" data-persona-eliminar hidden>Eliminar</button>
                        <button type="button" class="btn" data-modal-close="personaModal">Cancelar</button>
                        <button type="submit" class="btn btn--primary">Guardar</button>
                    </div>
                </form>
                {{-- Formulario aparte para eliminar: un <form> no puede anidarse dentro del de edición. --}}
                <form method="POST" action="" data-persona-form-eliminar hidden>
                    @csrf
                    @method('DELETE')
                </form>
            </x-modal>

            <x-modal id="generadorModal">
                <x-slot:header><h2>Armar buyer personas con 4 preguntas</h2></x-slot:header>

                <p class="audiencia__aviso">
                    Son <strong>borradores a partir de una plantilla, no un análisis de mercado</strong>: reordenan lo que
                    escribas dentro de frases fijas, sin IA ni datos externos. Revísalos y edítalos; nada se guarda solo.
                </p>

                <form data-generador-form novalidate>
                    @csrf
                    <div class="audiencia__progreso" aria-hidden="true"><span data-generador-barra></span></div>
                    <p class="audiencia__paso-num" data-generador-num>Pregunta 1 de 4</p>

                    <div class="audiencia__paso is-activo" data-generador-paso>
                        <label class="field__label" for="gen_producto">¿Qué vende o hace la marca?</label>
                        <input type="text" class="input" id="gen_producto" name="producto" maxlength="120" placeholder="Ej. clínica dental, ropa deportiva, software de facturación">
                    </div>
                    <div class="audiencia__paso" data-generador-paso>
                        <label class="field__label" for="gen_publico">¿A quién le vende?</label>
                        <textarea class="input textarea" id="gen_publico" name="publico" rows="3" maxlength="300" placeholder="Lo que sepas: ocupación, ciudad, tipo de negocio…"></textarea>
                    </div>
                    <div class="audiencia__paso" data-generador-paso>
                        <label class="field__label" for="gen_dolor">¿Qué problemas o dolores resuelve?</label>
                        <textarea class="input textarea" id="gen_dolor" name="dolor" rows="3" maxlength="500" placeholder="Sepáralos con comas o un renglón por dolor"></textarea>
                        <span class="field__hint">Se usan hasta 3 de ellos, uno por cada buyer persona.</span>
                    </div>
                    <div class="audiencia__paso" data-generador-paso>
                        <label class="field__label" for="gen_donde">¿Dónde se le encuentra?</label>
                        <input type="text" class="input" id="gen_donde" name="donde" maxlength="200" placeholder="Ej. Instagram, Google, referidos, WhatsApp">
                    </div>

                    <p class="audiencia__error" data-generador-error role="alert" hidden></p>

                    <div class="audiencia__modal-pie">
                        <button type="button" class="btn" data-generador-atras>Cancelar</button>
                        <button type="button" class="btn btn--primary" data-generador-siguiente>Siguiente</button>
                    </div>
                </form>

                <div class="audiencia__resultado" data-generador-resultado hidden>
                    <p class="audiencia__paso-num">Tres borradores. Elige uno para cargarlo en el formulario y editarlo.</p>
                    <div class="audiencia__borradores" data-generador-lista></div>
                    <div class="audiencia__modal-pie">
                        <button type="button" class="btn" data-generador-reiniciar>Volver a las preguntas</button>
                        <button type="button" class="btn" data-modal-close="generadorModal">Cerrar</button>
                    </div>
                </div>
            </x-modal>
        @endif
    </div>
@endsection
