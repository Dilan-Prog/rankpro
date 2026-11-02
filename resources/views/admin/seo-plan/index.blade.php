@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/seo-plan.css')
@endsection

@section('scripts')
    @vite('resources/js/seo-plan.js')
@endsection

{{--
    SEO Plan: una pantalla por cliente activo (el del menú) con tres pestañas.
    Todo es lectura de módulos existentes (Métricas, Keywords, SEO) salvo la
    meta general, los clústeres y las notas. Sin cliente activo no hay nada que
    mostrar: se invita a elegirlo en el menú en vez de listar todos los clientes.
--}}
@section('content')
    <div class="spl" data-seo-plan>
        <div class="page-header">
            <div>
                <h1 class="page-header__title">SEO Plan</h1>
                <p class="page-header__subtitle">
                    @if ($cliente)
                        {{ $cliente->empresa ?: $cliente->nombre }}: metas, actividades, clústeres de keywords y resultados.
                    @else
                        Plan SEO por cliente.
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
            <div class="card">
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="fa-solid fa-user-check"></i></div>
                    <p class="empty-state__text">Elige un cliente en el selector del menú para ver y armar su plan SEO.</p>
                </div>
            </div>
        @else
            @php
                $semaforoClase = ['verde' => 'badge--success', 'amarillo' => 'badge--warning', 'rojo' => 'badge--danger', 'sin_meta' => 'badge--neutral', 'sin_dato' => 'badge--neutral'];
                $semaforoTexto = ['verde' => 'Verde', 'amarillo' => 'Amarillo', 'rojo' => 'Rojo', 'sin_meta' => 'Sin meta', 'sin_dato' => 'Sin dato'];
                $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
                $clasePorEstado = ['pendiente' => 'neutral', 'en_progreso' => 'info', 'en_revision' => 'warning', 'completada' => 'success', 'pausada' => 'orange'];
                $porClaveCluster = $keywords->groupBy(fn ($k) => $k->cluster_id ?? 0);
            @endphp

            <div class="tabs" role="tablist" aria-label="Secciones del SEO Plan" data-spl-tabs>
                <button type="button" class="tabs__item is-active" role="tab" id="splTab-plan" aria-controls="splPanel-plan" aria-selected="true" data-spl-tab="plan">Plan</button>
                <button type="button" class="tabs__item" role="tab" id="splTab-keywords" aria-controls="splPanel-keywords" aria-selected="false" tabindex="-1" data-spl-tab="keywords">Keywords</button>
                <button type="button" class="tabs__item" role="tab" id="splTab-resultados" aria-controls="splPanel-resultados" aria-selected="false" tabindex="-1" data-spl-tab="resultados">Resultados</button>
            </div>

            {{-- ===================== PLAN ===================== --}}
            <section id="splPanel-plan" role="tabpanel" aria-labelledby="splTab-plan" data-spl-panel="plan">
                <div class="spl-grid">
                    <div class="card card--padded">
                        <h2 class="card__header-title">Meta general</h2>
                        <form method="POST" action="{{ route('admin.seo-plan.plan') }}" class="spl-form-meta">
                            @csrf
                            @method('PUT')
                            <div class="field">
                                <label class="field__label" for="splMetaGeneral">Qué queremos lograr con el SEO de este cliente</label>
                                <textarea class="textarea" id="splMetaGeneral" name="meta_general" maxlength="2000" placeholder="Escribe la meta en tus palabras…">{{ old('meta_general', $plan?->meta_general) }}</textarea>
                            </div>
                            <div><button type="submit" class="btn btn--primary btn--sm">Guardar meta</button></div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card__header">
                            <h2 class="card__header-title">Metas con semáforo</h2>
                            <a class="btn btn--ghost btn--sm" href="{{ route('admin.metricas.index', ['cliente' => $cliente->id]) }}">Definir en Métricas</a>
                        </div>
                        @if ($metas->isEmpty())
                            <div class="empty-state">
                                <p class="empty-state__text">
                                    @if ($metricasSinMeta > 0)
                                        Este cliente tiene {{ $metricasSinMeta }} {{ $metricasSinMeta === 1 ? 'métrica' : 'métricas' }} sin meta. Ponles una meta en Métricas para ver su semáforo aquí.
                                    @else
                                        Este cliente aún no tiene métricas con meta. Defínelas en el módulo Métricas.
                                    @endif
                                </p>
                            </div>
                        @else
                            <x-data-table :headers="['Métrica', 'Meta', 'Último valor', 'Semáforo']">
                                @foreach ($metas as $meta)
                                    <tr>
                                        <td>{{ $meta['metrica']->nombre }}</td>
                                        <td class="u-mono">{{ $fmt($meta['metrica']->meta) }} {{ $meta['metrica']->unidad }}</td>
                                        <td class="u-mono">
                                            {{ $fmt($meta['valor']) }} {{ $meta['valor'] !== null ? $meta['metrica']->unidad : '' }}
                                            @if ($meta['periodo'])
                                                <span class="field__hint">· {{ $meta['periodo']->format('m/Y') }}</span>
                                            @endif
                                        </td>
                                        <td><span class="badge {{ $semaforoClase[$meta['semaforo']] }}">{{ $semaforoTexto[$meta['semaforo']] }}</span></td>
                                    </tr>
                                @endforeach
                            </x-data-table>
                        @endif
                    </div>
                </div>

                {{-- Actividades --}}
                <div class="spl-toolbar">
                    <h2 class="spl-toolbar__title">Actividades</h2>
                    <div class="spl-toolbar__acciones">
                        <div class="spl-vistas" role="group" aria-label="Vista">
                            <button type="button" class="btn btn--sm is-active" data-spl-vista="kanban" aria-pressed="true"><i class="fa-solid fa-table-columns"></i> Kanban</button>
                            <button type="button" class="btn btn--sm" data-spl-vista="lista" aria-pressed="false"><i class="fa-solid fa-list"></i> Lista</button>
                        </div>
                        @if ($campanas->isEmpty())
                            <a class="btn btn--primary btn--sm" href="{{ route('admin.seo.index') }}">Crear campaña SEO</a>
                        @else
                            <button type="button" class="btn btn--primary btn--sm" data-spl-actividad-nueva>Nueva actividad</button>
                        @endif
                    </div>
                </div>

                @if ($campanas->isEmpty())
                    <p class="field__hint spl-aviso">Las actividades cuelgan de una campaña SEO del cliente. Este cliente no tiene una campaña vigente: créala en el módulo SEO para poder agregar actividades.</p>
                @endif

                <div class="filters-bar" data-spl-filtros>
                    <select class="select" data-spl-filtro="tipo" aria-label="Tipo">
                        <option value="">Tipo: todos</option>
                        @foreach ($tipos as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    <select class="select" data-spl-filtro="responsable" aria-label="Responsable">
                        <option value="">Responsable: todos</option>
                        @foreach ($usuarios as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    <select class="select" data-spl-filtro="cluster" aria-label="Clúster">
                        <option value="">Clúster: todos</option>
                        @foreach ($clusters as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    <select class="select" data-spl-filtro="estado" aria-label="Estado">
                        <option value="">Estado: todos</option>
                        @foreach ($columnas as $e)
                            <option value="{{ $e->value }}">{{ $e->label() }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn--ghost btn--sm" data-spl-limpiar hidden>Limpiar</button>
                </div>

                @if ($actividades->isEmpty())
                    <div class="card">
                        <div class="empty-state">
                            <div class="empty-state__icon"><i class="fa-solid fa-seedling"></i></div>
                            <p class="empty-state__text">Aún no hay actividades para este cliente.</p>
                        </div>
                    </div>
                @else
                    {{-- Kanban: una columna por estado. El orden de las tarjetas persiste con PATCH al soltar. --}}
                    <div class="spl-kanban" data-spl-kanban data-url-mover="{{ route('admin.seo-plan.actividades.mover', ['accion' => '__ID__']) }}">
                        @foreach ($columnas as $col)
                            @php
 $enColumna = $actividades->filter(fn ($a) => $a->estado === $col)->values();
 @endphp
                            <div class="spl-col" data-spl-col="{{ $col->value }}">
                                <div class="spl-col__head">
                                    <span class="spl-dot spl-dot--{{ $clasePorEstado[$col->value] }}"></span>
                                    {{ $col->label() }}
                                    <span class="spl-col__count" data-spl-count>{{ $enColumna->count() }}</span>
                                </div>
                                <div class="spl-col__body" data-spl-col-body>
                                    @foreach ($enColumna as $a)
                                        @include('admin.seo-plan._actividad-tarjeta', ['a' => $a])
                                    @endforeach
                                    <div class="spl-col__vacia" data-spl-vacia @if ($enColumna->isNotEmpty()) hidden @endif>Sin actividades</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div data-spl-lista hidden>
                        <x-data-table :headers="['Actividad', 'Tipo', 'Clúster', 'Responsable', 'Fecha límite', 'Estado']">
                            @foreach ($actividades as $a)
                                @include('admin.seo-plan._actividad-fila', ['a' => $a])
                            @endforeach
                            <tr data-spl-lista-vacia hidden><td colspan="6" class="table__empty">Ninguna actividad coincide con los filtros.</td></tr>
                        </x-data-table>
                    </div>
                @endif
            </section>

            {{-- ===================== KEYWORDS ===================== --}}
            <section id="splPanel-keywords" role="tabpanel" aria-labelledby="splTab-keywords" data-spl-panel="keywords" hidden>
                <div class="spl-toolbar">
                    <h2 class="spl-toolbar__title">Clústeres de keywords</h2>
                    <div class="spl-toolbar__acciones">
                        <a class="btn btn--ghost btn--sm" href="{{ route('admin.keywords.index') }}">Banco de keywords</a>
                        <button type="button" class="btn btn--primary btn--sm" data-spl-cluster-nuevo>Nuevo clúster</button>
                    </div>
                </div>

                @if ($clusters->isEmpty() && $keywords->isEmpty())
                    <div class="card">
                        <div class="empty-state">
                            <div class="empty-state__icon"><i class="fa-solid fa-key"></i></div>
                            <p class="empty-state__text">Este cliente aún no tiene keywords. Captúralas en el banco de keywords y agrúpalas aquí en clústeres.</p>
                        </div>
                    </div>
                @endif

                @foreach ($clusters as $c)
                    @php
 $kws = $porClaveCluster->get($c->id, collect());
 @endphp
                    <div class="card spl-cluster">
                        <div class="card__header">
                            <div>
                                <h3 class="card__header-title">{{ $c->nombre }} <span class="field__hint">· {{ $kws->count() }} {{ $kws->count() === 1 ? 'keyword' : 'keywords' }}</span></h3>
                                @if ($c->url)
                                    <div class="field__hint">{{ $c->url }}</div>
                                @endif
                            </div>
                            <div class="spl-cluster__acciones">
                                <button type="button" class="btn--icon" title="Editar" aria-label="Editar clúster" data-spl-cluster-editar data-cluster="{{ json_encode(['id' => $c->id, 'nombre' => $c->nombre, 'url' => $c->url]) }}"><i class="fa-solid fa-pen"></i></button>
                                <form method="POST" action="{{ route('admin.seo-plan.clusters.destroy', $c) }}" data-confirmar="¿Eliminar el clúster «{{ $c->nombre }}»? Sus keywords y actividades quedan sin clúster.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn--icon" style="color:var(--text-danger);" title="Eliminar" aria-label="Eliminar clúster"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        @include('admin.seo-plan._keywords-tabla', ['kws' => $kws, 'clusters' => $clusters, 'posiciones' => $posiciones])
                    </div>
                @endforeach

                @php
 $sueltas = $porClaveCluster->get(0, collect());
 @endphp
                @if ($sueltas->isNotEmpty())
                    <div class="card spl-cluster">
                        <div class="card__header">
                            <h3 class="card__header-title">Sin clúster <span class="field__hint">· {{ $sueltas->count() }}</span></h3>
                        </div>
                        @include('admin.seo-plan._keywords-tabla', ['kws' => $sueltas, 'clusters' => $clusters, 'posiciones' => $posiciones])
                    </div>
                @endif
            </section>

            {{-- ===================== RESULTADOS ===================== --}}
            <section id="splPanel-resultados" role="tabpanel" aria-labelledby="splTab-resultados" data-spl-panel="resultados" hidden>
                @php
                    $hayResultados = $rangos['con_posicion'] > 0 || $cierres['total'] > 0;
                    $enTop10 = $rangos['top3'] + $rangos['top10'];
                    $conFechaCerradas = $cierres['a_tiempo'] + $cierres['tarde'];
                @endphp

                @if (! $hayResultados)
                    <div class="card">
                        <div class="empty-state">
                            <div class="empty-state__icon"><i class="fa-solid fa-chart-simple"></i></div>
                            <p class="empty-state__text">Todavía no hay posiciones registradas ni actividades. Los resultados se arman solos con lo que captures.</p>
                        </div>
                    </div>
                @else
                    <div class="spl-kpis">
                        <x-stat-card label="Keywords con posición" :value="$rangos['con_posicion']" :sub="$rangos['sin_posicion'].' sin posición'" icon="fa-ranking-star" color="primary" />
                        <x-stat-card label="En el top 10" :value="$enTop10" :sub="$rangos['top3'].' en el top 3'" icon="fa-trophy" color="emerald" />
                        <x-stat-card label="Actividades cerradas" :value="$cierres['cerradas'].' de '.$cierres['total']" :sub="$cierres['vencidas'].' abiertas vencidas'" icon="fa-circle-check" color="teal" />
                        <x-stat-card label="Cerradas a tiempo" :value="$conFechaCerradas > 0 ? $cierres['a_tiempo'].' de '.$conFechaCerradas : '—'" :sub="$cierres['sin_fecha_limite'] > 0 ? $cierres['sin_fecha_limite'].' sin fecha límite' : null" icon="fa-clock" color="amber" />
                    </div>

                    <div class="spl-grid">
                        <div class="card card--padded">
                            <h2 class="card__header-title">Keywords por rango de posición</h2>
                            @if ($rangos['con_posicion'] === 0)
                                <p class="field__hint">Sin posiciones registradas todavía.</p>
                            @else
                                @php
                                    $filasRango = [['Top 3', $rangos['top3']], ['4 a 10', $rangos['top10']], ['11 a 20', $rangos['top20']], ['21 o más', $rangos['resto']]];
                                @endphp
                                <ul class="spl-barras">
                                    @foreach ($filasRango as [$etiqueta, $cantidad])
                                        <li>
                                            <span class="spl-barras__etiqueta">{{ $etiqueta }}</span>
                                            <span class="spl-barras__pista"><span class="spl-barras__relleno" style="width: {{ round($cantidad / $rangos['con_posicion'] * 100) }}%"></span></span>
                                            <span class="spl-barras__valor u-mono">{{ $cantidad }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="card card--padded">
                            <h2 class="card__header-title">Cierre de actividades</h2>
                            @if ($cierres['cerradas'] === 0)
                                <p class="field__hint">Ninguna actividad cerrada todavía.</p>
                            @else
                                <ul class="spl-barras">
                                    <li><span class="spl-barras__etiqueta">A tiempo</span><span class="spl-barras__valor u-mono">{{ $cierres['a_tiempo'] }}</span></li>
                                    <li><span class="spl-barras__etiqueta">Tarde</span><span class="spl-barras__valor u-mono">{{ $cierres['tarde'] }}</span></li>
                                    <li><span class="spl-barras__etiqueta">Sin fecha límite</span><span class="spl-barras__valor u-mono">{{ $cierres['sin_fecha_limite'] }}</span></li>
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><h2 class="card__header-title">Medición antes y después</h2></div>
                        @if ($conMedicion->isEmpty())
                            <div class="empty-state"><p class="empty-state__text">Ninguna actividad cerrada tiene medición capturada. Anótala al editar la actividad.</p></div>
                        @else
                            <x-data-table :headers="['Actividad', 'Tipo', 'Cerrada', 'Antes', 'Después']">
                                @foreach ($conMedicion as $a)
                                    <tr>
                                        <td>{{ $a->accion }}</td>
                                        <td>{{ $a->tipo?->label() ?? '—' }}</td>
                                        <td class="u-mono">{{ $a->cerrada_en?->format('d/m/Y') ?? '—' }}</td>
                                        <td>{{ $a->medicion_antes ?: '—' }}</td>
                                        <td>{{ $a->medicion_despues ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </x-data-table>
                        @endif
                    </div>
                @endif
            </section>

            {{-- ===================== MODALES ===================== --}}
            <x-modal id="splActividadModal">
                <x-slot:header><h2 data-spl-modal-titulo>Nueva actividad</h2></x-slot:header>
                <form method="POST" action="{{ route('admin.seo-plan.actividades.store') }}" data-spl-form-actividad data-url-crear="{{ route('admin.seo-plan.actividades.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="PUT" data-spl-metodo disabled>
                    <div class="field">
                        <label class="field__label" for="splAccion">Actividad</label>
                        <textarea class="textarea" id="splAccion" name="accion" required maxlength="2000"></textarea>
                    </div>
                    <div class="form-grid form-grid--2 spl-gap">
                        <div class="field">
                            <label class="field__label" for="splTipo">Tipo</label>
                            <select class="select" id="splTipo" name="tipo" required>
                                @foreach ($tipos as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="splEstado">Estado</label>
                            <select class="select" id="splEstado" name="estado">
                                @foreach ($columnas as $e)
                                    <option value="{{ $e->value }}">{{ $e->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="splCluster">Clúster</label>
                            <select class="select" id="splCluster" name="cluster_id">
                                <option value="">— Sin clúster —</option>
                                @foreach ($clusters as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="splResponsable">Responsable</label>
                            <select class="select" id="splResponsable" name="responsable_id">
                                <option value="">— Sin asignar —</option>
                                @foreach ($usuarios as $id => $nombre)
                                    <option value="{{ $id }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="splLimite">Fecha límite</label>
                            <input class="input" type="date" id="splLimite" name="fecha_limite">
                        </div>
                        <div class="field">
                            <label class="field__label" for="splUrl">URL de la página</label>
                            <input class="input" type="text" id="splUrl" name="url_pagina" maxlength="255" placeholder="/pagina">
                        </div>
                        <div class="field">
                            <label class="field__label" for="splAntes">Medición antes</label>
                            <input class="input" type="text" id="splAntes" name="medicion_antes" maxlength="120" placeholder="Ej. posición 14">
                        </div>
                        <div class="field">
                            <label class="field__label" for="splDespues">Medición después</label>
                            <input class="input" type="text" id="splDespues" name="medicion_despues" maxlength="120" placeholder="Ej. posición 6">
                        </div>
                    </div>
                    <div class="field spl-gap" data-spl-campana-campo>
                        <label class="field__label" for="splCampana">Campaña SEO</label>
                        <select class="select" id="splCampana" name="seo_campana_id">
                            @foreach ($campanas as $camp)
                                <option value="{{ $camp->id }}">{{ $camp->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary" data-spl-guardar>Crear actividad</button>
                        <button type="button" class="btn btn--secondary" data-modal-close="splActividadModal">Cancelar</button>
                    </div>
                </form>

                {{-- Solo al editar: bitácora y eliminar. --}}
                <div class="spl-detalle" data-spl-detalle hidden>
                    <h3 class="spl-detalle__titulo">Bitácora</h3>
                    <div class="spl-notas" data-spl-notas aria-live="polite"></div>
                    <div class="spl-nota-nueva">
                        <input class="input" type="text" maxlength="2000" placeholder="Agregar una nota…" aria-label="Nueva nota" data-spl-nota-texto>
                        <button type="button" class="btn btn--sm" data-spl-nota-agregar>Agregar</button>
                    </div>
                    <form method="POST" action="#" data-spl-form-eliminar data-confirmar="¿Eliminar esta actividad y su bitácora?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--sm">Eliminar actividad</button>
                    </form>
                </div>
            </x-modal>

            <x-modal id="splClusterModal">
                <x-slot:header><h2 data-spl-cluster-titulo>Nuevo clúster</h2></x-slot:header>
                <form method="POST" action="{{ route('admin.seo-plan.clusters.store') }}" data-spl-form-cluster data-url-crear="{{ route('admin.seo-plan.clusters.store') }}" data-url-actualizar="{{ route('admin.seo-plan.clusters.update', ['cluster' => '__ID__']) }}">
                    @csrf
                    <input type="hidden" name="_method" value="PUT" data-spl-cluster-metodo disabled>
                    <div class="field">
                        <label class="field__label" for="splClusterNombre">Nombre</label>
                        <input class="input" type="text" id="splClusterNombre" name="nombre" required maxlength="120">
                    </div>
                    <div class="field spl-gap">
                        <label class="field__label" for="splClusterUrl">URL que atiende el clúster</label>
                        <input class="input" type="text" id="splClusterUrl" name="url" maxlength="255" placeholder="/servicios/seo">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary">Guardar</button>
                        <button type="button" class="btn btn--secondary" data-modal-close="splClusterModal">Cancelar</button>
                    </div>
                </form>
            </x-modal>

            @php
                $configJs = [
                'urlActualizar' => route('admin.seo-plan.actividades.update', ['accion' => '__ID__']),
                'urlEliminar' => route('admin.seo-plan.actividades.destroy', ['accion' => '__ID__']),
                'urlNotas' => route('admin.seo-plan.notas.index', ['accion' => '__ID__']),
                'urlNotaEliminar' => route('admin.seo-plan.notas.destroy', ['accion' => '__ID__', 'nota' => '__NOTA__']),
                'urlAsignar' => route('admin.seo-plan.keywords.cluster', ['keyword' => '__ID__']),
                ];
            @endphp
            <script type="application/json" data-spl-config>{!! json_encode($configJs) !!}</script>
        @endif
    </div>
@endsection
