@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/inicio.css')
@endsection

@section('scripts')
    @vite('resources/js/inicio.js')
@endsection

{{--
    Inicio: resumen del día. Cada bloque se arma con datos reales y se oculta (o
    muestra su estado vacío) según permisos; el controlador ya filtra por módulo.
--}}
@section('content')
    <div class="inicio" data-inicio>
        <div class="page-header">
            <div>
                <h1 class="page-header__title" data-inicio-saludo>{{ $saludo }}</h1>
                <p class="page-header__subtitle">{{ $fechaLarga }}</p>
            </div>
        </div>

        @if (count($kpis))
            <div class="kpi-grid">
                @foreach ($kpis as $kpi)
                    <x-stat-card :label="$kpi['label']" :value="$kpi['value']" :sub="$kpi['sub']"
                        :trend="$kpi['trend']" :icon="$kpi['icon']" :color="$kpi['color']" :href="$kpi['href']" :invertir="$kpi['invertir'] ?? false" />
                @endforeach
            </div>
        @endif

        @if (count($accesos))
            <nav class="inicio__accesos" aria-label="Accesos rápidos">
                @foreach ($accesos as $acceso)
                    <a href="{{ $acceso['href'] }}" class="btn btn--secondary">
                        <i class="fa-solid {{ $acceso['icono'] }}"></i> {{ $acceso['etiqueta'] }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="inicio__grid">
            <section class="card card--padded inicio__bloque" aria-labelledby="inicio-atencion">
                <h2 class="card__header-title" id="inicio-atencion">Requiere tu atención</h2>
                @if (empty($alertas))
                    <div class="empty-state">
                        <div class="empty-state__icon"><i class="fa-solid fa-circle-check"></i></div>
                        <p class="empty-state__text">Nada urgente por ahora.</p>
                    </div>
                @else
                    <ul class="inicio__lista">
                        @foreach ($alertas as $alerta)
                            <li class="inicio__fila">
                                <i class="fa-solid {{ $alerta['icon'] }}" style="color: {{ $alerta['color'] }}"></i>
                                <a href="{{ $alerta['href'] }}" class="inicio__enlace">{{ $alerta['msg'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card card--padded inicio__bloque" aria-labelledby="inicio-agenda">
                <h2 class="card__header-title" id="inicio-agenda">Agenda de hoy</h2>
                @if (empty($agenda))
                    <div class="empty-state">
                        <div class="empty-state__icon"><i class="fa-regular fa-calendar"></i></div>
                        <p class="empty-state__text">No hay eventos para hoy.</p>
                    </div>
                @else
                    <ul class="inicio__lista">
                        @foreach ($agenda as $evento)
                            <li class="inicio__fila">
                                <span class="inicio__hora">{{ $evento['hora'] ?? 'Todo el día' }}</span>
                                <span class="inicio__texto">
                                    @if ($evento['url'])
                                        <a href="{{ $evento['url'] }}" class="inicio__enlace">{{ $evento['titulo'] }}</a>
                                    @else
                                        {{ $evento['titulo'] }}
                                    @endif
                                    @if ($evento['cliente'])
                                        <small class="inicio__detalle">{{ $evento['cliente'] }}</small>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($verTareas)
                <section class="card card--padded inicio__bloque" aria-labelledby="inicio-tareas"
                         data-inicio-tareas data-url-base="{{ url('admin/inicio/tareas') }}">
                    <div class="inicio__encabezado">
                        <h2 class="card__header-title" id="inicio-tareas">Mis tareas</h2>
                        <a href="{{ route('admin.mi-espacio.index') }}" class="inicio__enlace">Abrir Mi espacio</a>
                    </div>

                    <div class="inicio__progreso">
                        <div class="inicio__barra" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                             aria-valuenow="{{ $progreso['porcentaje'] }}" aria-label="Tareas hechas">
                            <span data-inicio-barra style="width: {{ $progreso['porcentaje'] }}%"></span>
                        </div>
                        <small class="inicio__detalle" data-inicio-progreso>{{ $progreso['hechas'] }} de {{ $progreso['total'] }} hechas</small>
                    </div>

                    @if ($tareas->isEmpty())
                        <div class="empty-state" data-inicio-vacio>
                            <div class="empty-state__icon"><i class="fa-solid fa-circle-check"></i></div>
                            <p class="empty-state__text">No tienes tareas pendientes.</p>
                        </div>
                    @else
                        <ul class="inicio__lista" data-inicio-lista>
                            @foreach ($tareas as $tarea)
                                <li class="inicio__fila" data-tarea-id="{{ $tarea->id }}">
                                    <input type="checkbox" class="inicio__check" data-inicio-check
                                           aria-label="Marcar como hecha: {{ $tarea->titulo }}">
                                    <span class="inicio__texto">
                                        {{ $tarea->titulo }}
                                        @if ($tarea->fecha_limite || $tarea->cliente)
                                            <small class="inicio__detalle">
                                                {{ collect([$tarea->fecha_limite?->format('d/m/Y'), $tarea->cliente?->nombre])->filter()->implode(' · ') }}
                                            </small>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            <section class="card card--padded inicio__bloque" aria-labelledby="inicio-actividad">
                <h2 class="card__header-title" id="inicio-actividad">Actividad reciente</h2>
                @if ($actividad->isEmpty())
                    <div class="empty-state">
                        <div class="empty-state__icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <p class="empty-state__text">Aún no hay actividad registrada.</p>
                    </div>
                @else
                    <ul class="inicio__lista">
                        @foreach ($actividad as $item)
                            <li class="inicio__fila">
                                <i class="fa-solid {{ $item['icono'] }} inicio__icono"></i>
                                <span class="inicio__texto">
                                    @if ($item['href'])
                                        <a href="{{ $item['href'] }}" class="inicio__enlace">{{ $item['texto'] }}</a>
                                    @else
                                        {{ $item['texto'] }}
                                    @endif
                                    <small class="inicio__detalle">
                                        {{ collect([$item['detalle'], $item['cuando']->diffForHumans()])->filter()->implode(' · ') }}
                                    </small>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
