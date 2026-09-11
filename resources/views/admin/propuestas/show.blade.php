@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/propuestas.css')
@endsection

@php
    $resumen = $propuesta->resumen ?? [];
    $situacion = $propuesta->situacion_actual ?? [];
    $contexto = $propuesta->contexto_continuidad ?? [];
    $plan = $propuesta->plan_detalle ?? [];
    $condiciones = $propuesta->condiciones_proyeccion ?? [];

    // Interruptores de visibilidad en el PDF. Los de sección usan Propuesta::visible().
    // Los de bloque leen su propio valor guardado (no el derivado): si la sección está
    // apagada, el bloque conserva su marca y el JS lo atenúa; al reactivar la sección
    // vuelve a imprimirse exactamente lo que estaba marcado.
    $mapaVisibilidad = $propuesta->visibilidad ?? [];
    $bloqueVisible = fn (string $clave) => array_key_exists($clave, $mapaVisibilidad) ? (bool) $mapaVisibilidad[$clave] : true;
@endphp

@section('content')
    <div class="page-header">
        <div>
            <p class="page-header__subtitle u-mono" style="margin-bottom:2px;">{{ $propuesta->folio ?? 'Sin folio — se asigna al generar el PDF' }}</p>
            <h1 class="page-header__title">{{ $propuesta->cliente->empresa ?: $propuesta->cliente->nombre }} · {{ $propuesta->titulo }}</h1>
            <p class="page-header__subtitle">Campaña SEO: {{ $propuesta->seoCampana->nombre ?? 'Sin campaña vinculada' }}</p>
        </div>
        <div class="propuesta-header-actions">
            <span class="propuesta-saved-note" data-autosave-note></span>
            <x-badge :status="$propuesta->estado" data-estado-badge />
            <select class="select" id="propuestaEstado" data-estado-select>
                @foreach (\App\Enums\EstadoPropuesta::cases() as $estado)
                    <option value="{{ $estado->value }}" @selected($propuesta->estado === $estado)>{{ $estado->label() }}</option>
                @endforeach
            </select>
            <a href="{{ route('admin.propuestas.preview', $propuesta) }}" class="btn btn--secondary" data-nav-flush>
                <i class="fa-solid fa-eye"></i> Vista previa
            </a>
            <form method="POST" action="{{ route('admin.propuestas.pdf', $propuesta) }}" data-nav-flush>
                @csrf
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-download"></i> Generar PDF</button>
            </form>
            <a href="{{ route('admin.propuestas.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div data-propuesta-root
         data-estado-url="{{ route('admin.propuestas.update', $propuesta) }}"
         data-sugerir-url="{{ route('admin.propuestas.sugerir-consultas', $propuesta) }}"
         data-visibilidad-url="{{ route('admin.propuestas.secciones.actualizar', ['propuesta' => $propuesta, 'seccion' => 'visibilidad']) }}"
         data-tiene-campana="{{ $tieneCampana ? '1' : '0' }}"
         data-precio-mensual="{{ $propuesta->precio_mensual }}"
         data-horas-mensuales="{{ $propuesta->horas_mensuales }}"
         data-tarifa-hora="{{ $propuesta->tarifa_hora }}">

        <div class="propuesta-tabs">
            <div class="tabs" id="propuestaTabs">
                <button type="button" class="tabs__item is-active" data-tab="resumen">1 · Resumen/Portada</button>
                <button type="button" class="tabs__item" data-tab="situacion">2 · Situación Actual</button>
                <button type="button" class="tabs__item" data-tab="contexto">3 · Por qué Continuar</button>
                <button type="button" class="tabs__item" data-tab="plan">4 · Plan en Detalle</button>
                <button type="button" class="tabs__item" data-tab="condiciones">5 · Condiciones y Proyección</button>
            </div>
            <span class="propuesta-tabs__hint">Los cambios se guardan automáticamente</span>
        </div>

        {{-- ================= Pestaña 1 · Resumen/Portada ================= --}}
        <div data-tab-panel="resumen" data-autosave-url="{{ route('admin.propuestas.secciones.actualizar', ['propuesta' => $propuesta, 'seccion' => 'resumen']) }}">
            <div class="card card--padded">
                <h3 class="card__header-title">Portada del documento</h3>
                <p class="field__hint">Encabezado, vigencia y precio que aparecen en la página 1 del PDF. Los interruptores «Visible en el PDF» solo afectan al documento: el dato se conserva y sigue editable aquí.</p>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                    <div class="field">
                        <label class="field__label">Cliente (vinculado)</label>
                        <div class="propuesta-readonly">{{ $propuesta->cliente->empresa ?: $propuesta->cliente->nombre }}</div>
                    </div>
                    <div class="field">
                        <label class="field__label">Campaña SEO vinculada</label>
                        <div class="propuesta-readonly">{{ $propuesta->seoCampana->nombre ?? 'Sin campaña vinculada' }}</div>
                    </div>
                    <div class="field">
                        <div class="prop-field-head">
                            <label class="field__label" for="rf_sitio_web">Sitio web</label>
                            <label class="prop-switch" title="Incluir en el PDF">
                                <input type="checkbox" data-visible="portada.sitio_web" @checked($bloqueVisible('portada.sitio_web'))>
                                <span>Visible en el PDF</span>
                            </label>
                        </div>
                        <input class="input" type="text" name="sitio_web" id="rf_sitio_web" data-autosave value="{{ $resumen['sitio_web'] ?? '' }}" placeholder="hotelfratelli.com.mx">
                    </div>
                    <div class="field">
                        <label class="field__label" for="rf_titulo">Título del documento</label>
                        <input class="input" type="text" name="titulo" id="rf_titulo" data-autosave value="{{ $propuesta->titulo }}">
                    </div>
                    <div class="field">
                        <div class="prop-field-head">
                            <label class="field__label" for="rf_subtitulo_plan">Subtítulo del plan</label>
                            <label class="prop-switch" title="Incluir en el PDF">
                                <input type="checkbox" data-visible="portada.subtitulo" @checked($bloqueVisible('portada.subtitulo'))>
                                <span>Visible en el PDF</span>
                            </label>
                        </div>
                        <input class="input" type="text" name="subtitulo_plan" id="rf_subtitulo_plan" data-autosave value="{{ $resumen['subtitulo_plan'] ?? '' }}" placeholder="Plan Continuidad (Puente 3 meses)">
                    </div>
                    <div class="field">
                        <div class="prop-field-head">
                            <label class="field__label" for="rf_vigencia_label">Vigencia</label>
                            <label class="prop-switch" title="Incluir en el PDF">
                                <input type="checkbox" data-visible="portada.vigencia" @checked($bloqueVisible('portada.vigencia'))>
                                <span>Visible en el PDF</span>
                            </label>
                        </div>
                        <input class="input" type="text" name="vigencia_label" id="rf_vigencia_label" data-autosave value="{{ $resumen['vigencia_label'] ?? '' }}" placeholder="Septiembre – Noviembre 2026">
                    </div>
                    <div class="field">
                        <div class="prop-field-head">
                            <label class="field__label" for="rf_precio_mensual">Precio mensual (MXN)</label>
                            <label class="prop-switch" title="Incluir en el PDF">
                                <input type="checkbox" data-visible="portada.precio" @checked($bloqueVisible('portada.precio'))>
                                <span>Visible en el PDF</span>
                            </label>
                        </div>
                        <input class="input" type="number" min="0" step="0.01" name="precio_mensual" id="rf_precio_mensual" data-autosave data-precio-input value="{{ $propuesta->precio_mensual }}">
                    </div>
                    <div class="field">
                        <div class="prop-field-head">
                            <label class="field__label">Desglose</label>
                            <label class="prop-switch" title="Incluir el desglose horas × tarifa en la portada del PDF">
                                <input type="checkbox" data-visible="portada.desglose" @checked($bloqueVisible('portada.desglose'))>
                                <span>Visible en el PDF</span>
                            </label>
                        </div>
                        <div class="propuesta-readonly propuesta-readonly--accent" data-desglose-output>—</div>
                        <span class="field__hint">Ocultarlo no borra horas ni tarifa; solo deja de imprimirse en la portada.</span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="rf_horas_mensuales">Horas mensuales</label>
                        <input class="input" type="number" min="0" step="1" name="horas_mensuales" id="rf_horas_mensuales" data-autosave data-horas-input value="{{ $propuesta->horas_mensuales }}">
                    </div>
                    <div class="field">
                        <label class="field__label" for="rf_tarifa_hora">Tarifa por hora (MXN)</label>
                        <input class="input" type="number" min="0" step="0.01" name="tarifa_hora" id="rf_tarifa_hora" data-autosave data-tarifa-input value="{{ $propuesta->tarifa_hora }}">
                    </div>
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <div>
                        <h3 class="card__header-title">Estadísticas destacadas de portada</h3>
                        <p class="field__hint">Entre 2 y 4 bloques. Aparecen en la franja inferior de la portada.</p>
                    </div>
                    <div class="prop-card-actions">
                        <label class="prop-switch" title="Incluir en el PDF">
                            <input type="checkbox" data-visible="portada.estadisticas" @checked($bloqueVisible('portada.estadisticas'))>
                            <span>Visible en el PDF</span>
                        </label>
                        <button type="button" class="btn btn--sm btn--ghost" data-row-add="estadisticas_destacadas">+ Agregar</button>
                    </div>
                </div>
                <div class="row-grid row-grid--4" data-row-list="estadisticas_destacadas" data-row-list-min="2" data-row-list-max="4" style="margin-top: var(--space-4);">
                    @foreach (($resumen['estadisticas_destacadas'] ?? []) as $i => $stat)
                        <div class="row-card" data-row>
                            <div class="row-card__head">
                                <span class="row-card__badge" data-row-badge>BLOQUE {{ $i + 1 }}</span>
                                <button type="button" class="row-card__remove" data-row-remove title="Quitar"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <input class="input row-card__value" type="text" data-field="valor" value="{{ $stat['valor'] ?? '' }}" placeholder="+134%">
                            <input class="input" type="text" data-field="etiqueta" value="{{ $stat['etiqueta'] ?? '' }}" placeholder="Impresiones">
                            <input class="input" type="text" data-field="nota" value="{{ $stat['nota'] ?? '' }}" placeholder="Agosto 2026">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ================= Pestaña 2 · Situación Actual ================= --}}
        <div data-tab-panel="situacion" data-autosave-url="{{ route('admin.propuestas.secciones.actualizar', ['propuesta' => $propuesta, 'seccion' => 'situacion']) }}" hidden>
            <div class="prop-seccion-bar">
                <label class="prop-switch prop-switch--seccion" title="Imprimir o no toda la sección 1 en el PDF">
                    <input type="checkbox" data-visible="situacion" @checked($propuesta->visible('situacion'))>
                    <span>Incluir esta sección en el PDF</span>
                </label>
                <span class="prop-seccion-bar__hint">Si se apaga, ningún bloque de esta pestaña se imprime, aunque esté marcado visible. Los datos se conservan.</span>
            </div>

            <div class="card card--padded">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">KPI de situación actual</h3>
                    <label class="prop-switch" title="Incluir en el PDF">
                        <input type="checkbox" data-visible="situacion.kpis" @checked($bloqueVisible('situacion.kpis'))>
                        <span>Visible en el PDF</span>
                    </label>
                </div>
                <div class="row-grid row-grid--5" data-row-list="kpis" style="margin-top: var(--space-4);">
                    @foreach (($situacion['kpis'] ?? []) as $kpi)
                        <div class="row-card" data-row>
                            <input class="input" type="text" data-field="etiqueta" value="{{ $kpi['etiqueta'] ?? '' }}" placeholder="Impresiones totales">
                            <input class="input row-card__value" type="text" data-field="valor" value="{{ $kpi['valor'] ?? '' }}" placeholder="1,068">
                            <input class="input" type="text" data-field="color" value="{{ $kpi['color'] ?? '' }}" placeholder="#0FA37F">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Comparación de periodos</h3>
                    <label class="prop-switch" title="Incluir en el PDF">
                        <input type="checkbox" data-visible="situacion.comparacion" @checked($bloqueVisible('situacion.comparacion'))>
                        <span>Visible en el PDF</span>
                    </label>
                    <div class="propuesta-period-picker">
                        <input class="input" type="text" name="periodo_comparacion[label_1]" data-autosave value="{{ $situacion['periodo_comparacion']['label_1'] ?? '' }}" placeholder="Jul 2026">
                        <span>vs</span>
                        <input class="input" type="text" name="periodo_comparacion[label_2]" data-autosave value="{{ $situacion['periodo_comparacion']['label_2'] ?? '' }}" placeholder="Ago 2026">
                    </div>
                </div>
                <div class="table-wrap" style="margin-top: var(--space-4);">
                    <table class="table">
                        <thead><tr><th>Métrica</th><th>Periodo 1</th><th>Periodo 2</th><th style="width:40px;"></th></tr></thead>
                        <tbody data-row-list="tabla_comparacion" data-row-list-min="0">
                            @foreach (($situacion['tabla_comparacion'] ?? []) as $fila)
                                <tr data-row>
                                    <td><input class="input" type="text" data-field="metrica" value="{{ $fila['metrica'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="valor_1" value="{{ $fila['valor_1'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="valor_2" value="{{ $fila['valor_2'] ?? '' }}"></td>
                                    <td><button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn--sm btn--ghost" data-row-add="tabla_comparacion" style="margin-top: var(--space-3);">+ Fila</button>
                <p class="field__hint">La variación % se calcula al generar la vista previa o el PDF — no se captura aquí.</p>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <div>
                        <h3 class="card__header-title">Posiciones destacadas y oportunidades</h3>
                        <p class="field__hint">La oportunidad se muestra como texto libre (ej. "ALTA — top 5 alcanzable").</p>
                    </div>
                    <div class="prop-card-actions">
                        <label class="prop-switch" title="Incluir en el PDF">
                            <input type="checkbox" data-visible="situacion.consultas" @checked($bloqueVisible('situacion.consultas'))>
                            <span>Visible en el PDF</span>
                        </label>
                        <button type="button" class="btn btn--sm btn--secondary" data-sugerir-keywords
                                @unless ($tieneCampana) disabled title="Esta propuesta no tiene una campaña SEO vinculada." @endunless>
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Sugerir desde banco de keywords
                        </button>
                        <button type="button" class="btn btn--sm btn--ghost" data-row-add="tabla_consultas">+ Fila</button>
                    </div>
                </div>
                <div class="table-wrap" style="margin-top: var(--space-4);">
                    <table class="table">
                        <thead><tr><th>Consulta</th><th>Posición</th><th>Impresiones</th><th>Clics</th><th>Oportunidad</th><th style="width:40px;"></th></tr></thead>
                        <tbody data-row-list="tabla_consultas" data-row-list-min="1">
                            @foreach (($situacion['tabla_consultas'] ?? []) as $fila)
                                <tr data-row>
                                    <td><input class="input" type="text" data-field="consulta" value="{{ $fila['consulta'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="posicion" value="{{ $fila['posicion'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="impresiones" value="{{ $fila['impresiones'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="clics" value="{{ $fila['clics'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="oportunidad" value="{{ $fila['oportunidad'] ?? '' }}"></td>
                                    <td><button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Resumen de la situación</h3>
                    <label class="prop-switch" title="Incluir en el PDF">
                        <input type="checkbox" data-visible="situacion.intro" @checked($bloqueVisible('situacion.intro'))>
                        <span>Visible en el PDF</span>
                    </label>
                </div>
                <p class="field__hint">Párrafo introductorio de la página 2 del PDF ("1. Situación actual del sitio").</p>
                <textarea class="textarea" name="resumen_texto" data-autosave rows="4" style="margin-top: var(--space-3);">{{ $situacion['resumen_texto'] ?? '' }}</textarea>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Insight crítico</h3>
                    <label class="prop-switch" title="Incluir en el PDF">
                        <input type="checkbox" data-visible="situacion.insight" @checked($bloqueVisible('situacion.insight'))>
                        <span>Visible en el PDF</span>
                    </label>
                </div>
                <textarea class="textarea" name="insight_texto" data-autosave rows="4" style="margin-top: var(--space-3);">{{ $situacion['insight_texto'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- ================= Pestaña 3 · Por qué Continuar ================= --}}
        <div data-tab-panel="contexto" data-autosave-url="{{ route('admin.propuestas.secciones.actualizar', ['propuesta' => $propuesta, 'seccion' => 'contexto']) }}" hidden>
            <div class="prop-seccion-bar">
                <label class="prop-switch prop-switch--seccion" title="Imprimir o no toda la sección 2 en el PDF">
                    <input type="checkbox" data-visible="contexto" @checked($propuesta->visible('contexto'))>
                    <span>Incluir esta sección en el PDF</span>
                </label>
                <span class="prop-seccion-bar__hint">Si se apaga, ningún bloque de esta pestaña se imprime, aunque esté marcado visible. Los datos se conservan.</span>
            </div>

            <div class="card card--padded">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Introducción</h3>
                    <label class="prop-switch" title="Incluir en el PDF">
                        <input type="checkbox" data-visible="contexto.intro" @checked($bloqueVisible('contexto.intro'))>
                        <span>Visible en el PDF</span>
                    </label>
                </div>
                <p class="field__hint">Párrafo introductorio de la página 3 del PDF ("2. ¿Por qué un Plan de Continuidad ahora?").</p>
                <textarea class="textarea" name="intro_texto" data-autosave rows="4" style="margin-top: var(--space-3);">{{ $contexto['intro_texto'] ?? '' }}</textarea>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Riesgos si no se actúa</h3>
                    <div class="prop-card-actions">
                        <label class="prop-switch" title="Incluir en el PDF">
                            <input type="checkbox" data-visible="contexto.riesgos" @checked($bloqueVisible('contexto.riesgos'))>
                            <span>Visible en el PDF</span>
                        </label>
                        <button type="button" class="btn btn--sm btn--ghost" data-row-add="tabla_riesgos">+ Riesgo</button>
                    </div>
                </div>
                <div class="row-list" data-row-list="tabla_riesgos" data-row-list-min="1" style="margin-top: var(--space-4);">
                    @foreach (($contexto['tabla_riesgos'] ?? []) as $fila)
                        <div class="row-list__row row-list__row--2" data-row>
                            <input class="input" type="text" data-field="riesgo" value="{{ $fila['riesgo'] ?? '' }}" placeholder="Riesgo">
                            <input class="input" type="text" data-field="impacto" value="{{ $fila['impacto'] ?? '' }}" placeholder="Impacto si no se actúa">
                            <button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Lo que el Plan Continuidad sí protege</h3>
                    <div class="prop-card-actions">
                        <label class="prop-switch" title="Incluir en el PDF">
                            <input type="checkbox" data-visible="contexto.protege" @checked($bloqueVisible('contexto.protege'))>
                            <span>Visible en el PDF</span>
                        </label>
                        <button type="button" class="btn btn--sm btn--ghost" data-row-add="checklist_protege">+ Punto</button>
                    </div>
                </div>
                <div class="row-list" data-row-list="checklist_protege" data-row-list-min="1" style="margin-top: var(--space-4);">
                    @foreach (($contexto['checklist_protege'] ?? []) as $fila)
                        <div class="row-list__row row-list__row--2" data-row>
                            <input class="input" type="text" data-field="titulo" value="{{ $fila['titulo'] ?? '' }}" placeholder="Título">
                            <input class="input" type="text" data-field="descripcion" value="{{ $fila['descripcion'] ?? '' }}" placeholder="Descripción">
                            <button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Lógica de negocio</h3>
                    <label class="prop-switch" title="Incluir en el PDF">
                        <input type="checkbox" data-visible="contexto.logica" @checked($bloqueVisible('contexto.logica'))>
                        <span>Visible en el PDF</span>
                    </label>
                </div>
                <textarea class="textarea" name="logica_negocio_texto" data-autosave rows="4" style="margin-top: var(--space-3);">{{ $contexto['logica_negocio_texto'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- ================= Pestaña 4 · Plan en Detalle ================= --}}
        <div data-tab-panel="plan" data-autosave-url="{{ route('admin.propuestas.secciones.actualizar', ['propuesta' => $propuesta, 'seccion' => 'plan']) }}" hidden>
            <div class="prop-seccion-bar">
                <label class="prop-switch prop-switch--seccion" title="Imprimir o no toda la sección 3 en el PDF">
                    <input type="checkbox" data-visible="plan" @checked($propuesta->visible('plan'))>
                    <span>Incluir esta sección en el PDF</span>
                </label>
                <span class="prop-seccion-bar__hint">Si se apaga, ningún bloque de esta pestaña se imprime, aunque esté marcado visible. Los datos se conservan.</span>
            </div>

            <div class="card card--padded">
                <h3 class="card__header-title">Definición del plan</h3>
                <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                    <div class="field" style="grid-column: span 2;">
                        <label class="field__label" for="pl_nombre">Nombre del plan</label>
                        <input class="input" type="text" name="nombre" id="pl_nombre" data-autosave value="{{ $plan['nombre'] ?? '' }}">
                    </div>
                    <div class="field">
                        <div class="prop-field-head">
                            <label class="field__label">Precio mensual</label>
                            <label class="prop-switch" title="Incluir la barra de precio del plan en el PDF">
                                <input type="checkbox" data-visible="plan.barra_precio" @checked($bloqueVisible('plan.barra_precio'))>
                                <span>Visible en el PDF</span>
                            </label>
                        </div>
                        <div class="propuesta-readonly" data-precio-echo>—</div>
                        <span class="field__hint">Se edita en la pestaña 1 · Resumen/Portada.</span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="pl_duracion_meses">Duración (meses)</label>
                        <input class="input" type="text" name="duracion_meses" id="pl_duracion_meses" data-autosave value="{{ $plan['duracion_meses'] ?? '' }}" placeholder="3">
                    </div>
                    <div class="field">
                        <label class="field__label">Horas mensuales</label>
                        <div class="propuesta-readonly" data-horas-echo>—</div>
                    </div>
                    <div class="field">
                        <label class="field__label">Tarifa por hora</label>
                        <div class="propuesta-readonly" data-tarifa-echo>—</div>
                    </div>
                    <div class="field">
                        <div class="prop-field-head">
                            <label class="field__label">Subtotal horas × tarifa</label>
                            <label class="prop-switch" title="Incluir horas × tarifa en la barra de precio del PDF">
                                <input type="checkbox" data-visible="plan.barra_desglose" @checked($bloqueVisible('plan.barra_desglose'))>
                                <span>Visible en el PDF</span>
                            </label>
                        </div>
                        <div class="propuesta-readonly propuesta-readonly--accent" data-subtotal-output>—</div>
                        <span class="field__hint">Ocultarlo no borra horas ni tarifa; solo deja de imprimirse en la barra del plan.</span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="pl_inicio">Inicio</label>
                        <input class="input" type="text" name="vigencia_inicio_texto" id="pl_inicio" data-autosave value="{{ $plan['vigencia_inicio_texto'] ?? '' }}" placeholder="1 de septiembre de 2026">
                    </div>
                    <div class="field">
                        <label class="field__label" for="pl_fin">Fin</label>
                        <input class="input" type="text" name="vigencia_fin_texto" id="pl_fin" data-autosave value="{{ $plan['vigencia_fin_texto'] ?? '' }}" placeholder="30 de noviembre de 2026">
                    </div>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-5);">
                <div class="card card--padded">
                    <div class="propuesta-card-head">
                        <h3 class="card__header-title">Alcance incluido</h3>
                        <div class="prop-card-actions">
                            <label class="prop-switch" title="Incluir en el PDF">
                                <input type="checkbox" data-visible="plan.alcance_incluido" @checked($bloqueVisible('plan.alcance_incluido'))>
                                <span>Visible en el PDF</span>
                            </label>
                            <button type="button" class="btn btn--sm btn--ghost" data-row-add="alcance_incluido">+ Viñeta</button>
                        </div>
                    </div>
                    <div class="row-list" data-row-list="alcance_incluido" data-row-list-min="1" data-row-list-type="strings" style="margin-top: var(--space-3);">
                        @foreach (($plan['alcance_incluido'] ?? ['']) as $texto)
                            <div class="row-list__row row-list__row--1" data-row>
                                <input class="input" type="text" data-field="value" value="{{ $texto }}">
                                <button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card card--padded">
                    <div class="propuesta-card-head">
                        <h3 class="card__header-title" style="color:var(--color-muted-foreground);">Alcance NO incluido</h3>
                        <div class="prop-card-actions">
                            <label class="prop-switch" title="Incluir en el PDF">
                                <input type="checkbox" data-visible="plan.alcance_no_incluido" @checked($bloqueVisible('plan.alcance_no_incluido'))>
                                <span>Visible en el PDF</span>
                            </label>
                            <button type="button" class="btn btn--sm btn--ghost" data-row-add="alcance_no_incluido">+ Viñeta</button>
                        </div>
                    </div>
                    <p class="field__hint">Reservado para Sprint 2.</p>
                    <div class="row-list" data-row-list="alcance_no_incluido" data-row-list-min="1" data-row-list-type="strings" style="margin-top: var(--space-3);">
                        @foreach (($plan['alcance_no_incluido'] ?? ['']) as $texto)
                            <div class="row-list__row row-list__row--1" data-row>
                                <input class="input" type="text" data-field="value" value="{{ $texto }}">
                                <button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Plan de ejecución mes a mes</h3>
                    <div class="prop-card-actions">
                        <label class="prop-switch" title="Incluir la tabla mes a mes en el PDF">
                            <input type="checkbox" data-visible="plan.meses" @checked($bloqueVisible('plan.meses'))>
                            <span>Visible en el PDF</span>
                        </label>
                        <button type="button" class="btn btn--sm btn--ghost" data-row-add="meses">+ Mes</button>
                    </div>
                </div>
                <div class="table-wrap" style="margin-top: var(--space-4);">
                    <table class="table">
                        <thead><tr><th style="width:70px;">Mes</th><th style="width:140px;">Calendario</th><th>Actividades clave</th><th>Entregable</th><th style="width:110px;">
                            <span class="prop-th-switch">
                                Hrs
                                <label class="prop-switch prop-switch--compact" title="Incluir la columna de horas en el PDF (las horas se conservan aunque se oculte)">
                                    <input type="checkbox" data-visible="plan.meses_horas" @checked($bloqueVisible('plan.meses_horas'))>
                                    <span>PDF</span>
                                </label>
                            </span>
                        </th><th style="width:40px;"></th></tr></thead>
                        <tbody data-row-list="meses" data-row-list-min="1">
                            @foreach (($plan['meses'] ?? []) as $mes)
                                <tr data-row>
                                    <td><input class="input" type="text" data-field="mes_label" value="{{ $mes['mes_label'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="calendario" value="{{ $mes['calendario'] ?? '' }}"></td>
                                    <td><textarea class="textarea" rows="2" data-field="actividades">{{ $mes['actividades'] ?? '' }}</textarea></td>
                                    <td><input class="input" type="text" data-field="entregable" value="{{ $mes['entregable'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="horas" value="{{ $mes['horas'] ?? '' }}"></td>
                                    <td><button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ================= Pestaña 5 · Condiciones y Proyección ================= --}}
        <div data-tab-panel="condiciones" data-autosave-url="{{ route('admin.propuestas.secciones.actualizar', ['propuesta' => $propuesta, 'seccion' => 'condiciones']) }}" hidden>
            <div class="prop-seccion-bar">
                <label class="prop-switch prop-switch--seccion" title="Imprimir o no toda la sección 4 en el PDF">
                    <input type="checkbox" data-visible="condiciones" @checked($propuesta->visible('condiciones'))>
                    <span>Incluir esta sección en el PDF</span>
                </label>
                <span class="prop-seccion-bar__hint">Si se apaga, ningún bloque de esta pestaña se imprime, aunque esté marcado visible. Los datos se conservan.</span>
            </div>

            <div class="card card--padded">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Condiciones comerciales</h3>
                    <div class="prop-card-actions">
                        <label class="prop-switch" title="Incluir en el PDF">
                            <input type="checkbox" data-visible="condiciones.tabla" @checked($bloqueVisible('condiciones.tabla'))>
                            <span>Visible en el PDF</span>
                        </label>
                        <button type="button" class="btn btn--sm btn--ghost" data-row-add="condiciones">+ Condición</button>
                    </div>
                </div>
                <div class="row-list" data-row-list="condiciones" data-row-list-min="1" style="margin-top: var(--space-4);">
                    @foreach (($condiciones['condiciones'] ?? []) as $fila)
                        <div class="row-list__row row-list__row--label" data-row>
                            <input class="input" type="text" data-field="etiqueta" value="{{ $fila['etiqueta'] ?? '' }}" placeholder="Duración">
                            <input class="input" type="text" data-field="valor" value="{{ $fila['valor'] ?? '' }}" placeholder="3 meses fijos…">
                            <button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Renegociación al mes 3</h3>
                    <label class="prop-switch" title="Incluir en el PDF">
                        <input type="checkbox" data-visible="condiciones.renegociacion" @checked($bloqueVisible('condiciones.renegociacion'))>
                        <span>Visible en el PDF</span>
                    </label>
                </div>
                <p class="field__hint">Hasta tres caminos que el cliente podrá elegir al cierre del puente.</p>
                <div class="row-grid row-grid--3" data-row-list="opciones_renegociacion" style="margin-top: var(--space-4);">
                    @foreach (($condiciones['opciones_renegociacion'] ?? []) as $i => $opcion)
                        <div class="row-card row-card--reneg" data-row>
                            <span class="row-card__letter">{{ chr(65 + $i) }}</span>
                            <input class="input" type="text" data-field="nombre" value="{{ $opcion['nombre'] ?? '' }}" placeholder="Nombre de la opción">
                            <textarea class="textarea" rows="3" data-field="descripcion" placeholder="Descripción">{{ $opcion['descripcion'] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <h3 class="card__header-title">Proyección de resultados esperados</h3>
                    <div class="prop-card-actions">
                        <label class="prop-switch" title="Incluir en el PDF">
                            <input type="checkbox" data-visible="condiciones.proyeccion" @checked($bloqueVisible('condiciones.proyeccion'))>
                            <span>Visible en el PDF</span>
                        </label>
                        <button type="button" class="btn btn--sm btn--ghost" data-row-add="tabla_proyeccion">+ Métrica</button>
                    </div>
                </div>
                <div class="field" style="max-width:280px; margin-top: var(--space-3);">
                    <label class="field__label" for="cp_periodo_label">Etiqueta del periodo de proyección</label>
                    <input class="input" type="text" name="proyeccion_periodo_label" id="cp_periodo_label" data-autosave value="{{ $condiciones['proyeccion_periodo_label'] ?? '' }}" placeholder="Proyección Nov 2026">
                </div>
                <div class="table-wrap" style="margin-top: var(--space-4);">
                    <table class="table">
                        <thead><tr><th>Métrica</th><th>Periodo base</th><th>Proyección</th><th>Escenario</th><th style="width:40px;"></th></tr></thead>
                        <tbody data-row-list="tabla_proyeccion" data-row-list-min="1">
                            @foreach (($condiciones['tabla_proyeccion'] ?? []) as $fila)
                                <tr data-row>
                                    <td><input class="input" type="text" data-field="metrica" value="{{ $fila['metrica'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="base" value="{{ $fila['base'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="proyeccion" value="{{ $fila['proyeccion'] ?? '' }}"></td>
                                    <td><input class="input" type="text" data-field="escenario" value="{{ $fila['escenario'] ?? '' }}"></td>
                                    <td><button type="button" class="btn--icon" data-row-remove title="Eliminar"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card--padded" style="margin-top: var(--space-5);">
                <div class="propuesta-card-head">
                    <div>
                        <h3 class="card__header-title">Bloque de contacto</h3>
                        <p class="field__hint">Texto fijo de cierre del PDF (datos de contacto de la agencia). No se edita aquí; solo se decide si se imprime.</p>
                    </div>
                    <label class="prop-switch" title="Incluir el bloque de contacto en el PDF">
                        <input type="checkbox" data-visible="condiciones.contacto" @checked($bloqueVisible('condiciones.contacto'))>
                        <span>Visible en el PDF</span>
                    </label>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    @vite('resources/js/propuestas.js')
@endsection
