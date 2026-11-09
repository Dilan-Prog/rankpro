@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/reportes.css')
@endsection

@section('content')
    @php
        // «Actualizar datos» solo tiene sentido en un borrador nacido de «Generar reporte del mes».
        $puedeActualizar = $reporte->esDelMes() && $reporte->estado === \App\Enums\EstadoReporte::Borrador;
    @endphp
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $reporte->titulo }}</h1>
            <p class="page-header__subtitle">
                {{ $reporte->cliente?->nombre_comercial }}
                · {{ $reporte->area->label() }}
                · {{ fecha_mx($reporte->periodo_inicio) }} — {{ fecha_mx($reporte->periodo_fin) }}
                @if ($reporte->numero) · <span class="u-mono">{{ $reporte->numero }}</span> @endif
            </p>
        </div>
        <div class="rep-header-actions">
            <x-badge :status="$reporte->estado" />

            {{-- data-nav-flush: el editor vacía su autosave antes de navegar o generar. --}}
            <a href="{{ route('admin.reportes.preview', $reporte) }}" class="btn btn--secondary" target="_blank" rel="noopener" data-nav-flush>
                <i class="fa-solid fa-eye"></i> Vista previa
            </a>

            {{-- data-descarga: descarga con progreso y error visible (lib/descargas.js); recarga para ver estado, folio y entregas. --}}
            @if ($puedeActualizar)
                <button type="button" class="btn btn--secondary" data-actualizar-datos data-url="{{ route('admin.reportes.actualizar-datos', $reporte) }}"
                        title="Refresca solo lo precargado; no pisa lo que ya editaste">
                    <i class="fa-solid fa-rotate"></i> Actualizar datos
                </button>
            @endif

            <a href="{{ route('admin.reportes.preview-cliente', $reporte) }}" class="btn btn--secondary" target="_blank" rel="noopener" data-nav-flush>
                <i class="fa-solid fa-user-check"></i> Vista previa para cliente
            </a>

            <form method="POST" action="{{ route('admin.reportes.pdf-cliente', $reporte) }}"
                  data-descarga data-descarga-recargar data-descarga-aviso="#repDescargaAviso" data-nav-flush>
                @csrf
                <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-file-pdf"></i> Descargar versión para cliente</button>
            </form>

            <form method="POST" action="{{ route('admin.reportes.pdf', $reporte) }}"
                  data-descarga data-descarga-recargar data-descarga-aviso="#repDescargaAviso" data-nav-flush>
                @csrf
                <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-file-pdf"></i> Generar PDF</button>
            </form>

            <form method="POST" action="{{ route('admin.reportes.xlsx', $reporte) }}"
                  data-descarga data-descarga-recargar data-descarga-aviso="#repDescargaAviso" data-nav-flush>
                @csrf
                <button type="submit" class="btn btn--secondary"><i class="fa-solid fa-file-excel"></i> Generar Excel</button>
            </form>

            <a href="{{ route('admin.reportes.index') }}" class="btn btn--secondary" data-nav-flush>
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div id="repDescargaAviso" data-descarga-mensajes></div>
    <div class="form-status" data-accion-mensaje hidden role="status"></div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="form-status form-status--error"><i class="fa-solid fa-triangle-exclamation" style="margin-top:2px"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <div class="rep-layout"
         data-reporte-editor
         data-reporte-id="{{ $reporte->id }}"
         data-seccion-store-url="{{ route('admin.reportes.secciones.store', $reporte) }}"
         data-reordenar-url="{{ route('admin.reportes.secciones.reordenar', $reporte) }}"
         data-seccion-update-url="{{ route('admin.reportes.secciones.update', ['reporte' => $reporte, 'seccion' => '__ID__']) }}"
         data-seccion-destroy-url="{{ route('admin.reportes.secciones.destroy', ['reporte' => $reporte, 'seccion' => '__ID__']) }}"
         data-seccion-pegar-url="{{ route('admin.reportes.secciones.pegar', ['reporte' => $reporte, 'seccion' => '__ID__']) }}">

        {{-- ---------- Secciones ---------- --}}
        <div class="rep-main">
            <div class="rep-main__toolbar">
                <h2 class="card__header-title">Secciones del reporte</h2>
                <button type="button" class="btn btn--primary btn--sm" data-open-seccion-modal>
                    <i class="fa-solid fa-plus"></i> Añadir sección
                </button>
            </div>

            <div class="rep-secciones" data-secciones>
                @foreach ($reporte->secciones as $seccion)
                    <section class="rep-seccion"
                             data-seccion
                             data-seccion-id="{{ $seccion->id }}"
                             data-seccion-tipo="{{ $seccion->tipo->value }}"
                             data-contenido="{{ json_encode($seccion->contenido ?? []) }}"
                             data-aviso="{{ json_encode($seccion->aviso) }}"
                             data-interno="{{ $seccion->interno ? '1' : '0' }}">
                        <header class="rep-seccion__head">
                            <button type="button" class="rep-seccion__toggle" data-toggle-seccion aria-expanded="true" title="Plegar / desplegar">
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <span class="rep-seccion__tipo">{{ $seccion->tipo->label() }}</span>
                            <span class="rep-seccion__interno-chip" data-interno-chip title="No sale en ningún entregable" @if (! $seccion->interno) hidden @endif>
                                <i class="fa-solid fa-lock"></i> Interno
                            </span>
                            {{-- Rótulo opcional impreso sobre el título (SECCIÓN 2, ANEXO A). No se siembra desde la plantilla: repetiría el título en el PDF y, al reordenar, la numeración quedaría mentirosa. --}}
                            <input class="input rep-seccion__rotulo u-mono" type="text" value="{{ $seccion->rotulo }}"
                                   data-seccion-rotulo placeholder="Rótulo de la sección (opcional)" aria-label="Rótulo de la sección">
                            <input class="input rep-seccion__titulo" type="text" value="{{ $seccion->titulo }}"
                                   data-seccion-titulo aria-label="Título de la sección">
                            <span class="rep-seccion__saved" data-guardado></span>
                            <label class="rep-switch" title="Incluir en el entregable">
                                <input type="checkbox" data-seccion-visible @checked($seccion->visible)>
                                <span>Visible</span>
                            </label>
                            <div class="rep-seccion__botones">
                                <button type="button" class="btn--icon" title="Aviso de dato desactualizado" data-toggle-aviso aria-expanded="false"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                <button type="button" class="btn--icon" title="Subir" data-mover="arriba"><i class="fa-solid fa-arrow-up"></i></button>
                                <button type="button" class="btn--icon" title="Bajar" data-mover="abajo"><i class="fa-solid fa-arrow-down"></i></button>
                                @if ($seccion->tipo->admitePegado())
                                    <button type="button" class="btn--icon" title="Pegar desde Excel" data-abrir-pegado><i class="fa-solid fa-paste"></i></button>
                                @endif
                                <button type="button" class="btn--icon" title="Eliminar sección" style="color:var(--text-danger);" data-eliminar-seccion><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </header>

                        {{-- Fuera del cuerpo plegable: un 422 o un aviso vigente tienen que
                             seguir viéndose con la sección cerrada. --}}
                        <div class="rep-seccion__interno">
                            <label class="rep-switch" title="Una sección interna se ve aquí pero no sale en el PDF, el Excel ni el envío al cliente.">
                                <input type="checkbox" data-seccion-interno @checked($seccion->interno)>
                                <span>Interno (no sale en el PDF del cliente)</span>
                            </label>
                            <span class="rep-seccion__pago" data-pago-aviso hidden>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Parece contener datos de pago: ¿marcar como interno?
                                <button type="button" class="btn btn--secondary btn--sm" data-marcar-interno>Marcar como interno</button>
                            </span>
                        </div>

                        <p class="rep-seccion__error" data-seccion-error hidden></p>
                        <p class="rep-seccion__aviso-chip" data-aviso-chip hidden></p>

                        <div class="rep-seccion__aviso" data-aviso-panel hidden>
                            <label class="rep-seccion__aviso-campo rep-seccion__aviso-campo--ancho">
                                <span class="rep-row__label">Aviso de dato desactualizado</span>
                                <input class="input" type="text" data-aviso-texto placeholder="Qué dato está desactualizado y por qué">
                            </label>
                            <label class="rep-seccion__aviso-campo">
                                <span class="rep-row__label">Fecha del dato</span>
                                <input class="input" type="date" data-aviso-fecha>
                            </label>
                            <button type="button" class="btn btn--secondary btn--sm" data-limpiar-aviso>Quitar aviso</button>
                        </div>

                        <div class="rep-seccion__body" data-seccion-body>
                            @include('admin.reportes.secciones._'.$seccion->tipo->value, ['seccion' => $seccion])
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="card empty-state" data-secciones-empty {{ $reporte->secciones->isEmpty() ? '' : 'hidden' }}>
                <div class="empty-state__icon"><i class="fa-solid fa-layer-group"></i></div>
                <p class="empty-state__text">Este reporte no tiene secciones. Añade la primera.</p>
            </div>
        </div>

        {{-- ---------- Histórico de entregas ---------- --}}
        <aside class="rep-aside">
            <div class="card card--padded">
                <h2 class="card__header-title">Entregas</h2>
                <p class="rep-aside__hint">Cada generación deja rastro: no se sobrescribe ningún entregable anterior.</p>

                <ul class="rep-entregas" data-entregas>
                    @forelse ($reporte->entregas as $entrega)
                        <li class="rep-entrega">
                            <span class="rep-entrega__formato rep-entrega__formato--{{ $entrega->formato }}">{{ $entrega->formato === 'pdf_cliente' ? 'CLIENTE' : mb_strtoupper($entrega->formato) }}</span>
                            <div class="rep-entrega__cuerpo">
                                @if ($entrega->archivo)
                                    <a href="{{ route('admin.archivos.download', $entrega->archivo) }}" class="rep-entrega__folio u-mono">{{ $entrega->archivo->nombre }}</a>
                                @else
                                    <span class="rep-entrega__folio u-mono">{{ $reporte->numero ?? '—' }}</span>
                                @endif
                                <span class="rep-entrega__meta">
                                    {{ fecha_mx($entrega->created_at, true) }}
                                    · {{ $entrega->usuario?->name ?? 'Sistema' }}
                                </span>
                            </div>
                        </li>
                    @empty
                        <li class="rep-entregas__vacio">Todavía no se ha generado ningún entregable.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Enlace de solo lectura a la versión para cliente. El token en claro solo se ve al crearlo. --}}
            <div class="card card--padded" data-enlaces
                 data-enlace-store-url="{{ route('admin.reportes.enlaces.store', $reporte) }}"
                 data-enlace-destroy-url="{{ route('admin.reportes.enlaces.destroy', ['reporte' => $reporte, 'enlace' => '__ID__']) }}">
                <h2 class="card__header-title">Compartir con el cliente</h2>
                <p class="rep-aside__hint">Enlace de solo lectura a la versión de una página. Vence a los 30 días y se puede revocar.</p>

                <div class="rep-enlace-nuevo" data-enlace-nuevo hidden>
                    <label class="rep-row__label" for="repEnlaceUrl">Copia el enlace ahora; no se vuelve a mostrar</label>
                    <div class="rep-enlace-nuevo__fila">
                        <input class="input u-mono" id="repEnlaceUrl" type="text" readonly data-enlace-url>
                        <button type="button" class="btn btn--secondary btn--sm" data-enlace-copiar>Copiar</button>
                    </div>
                </div>

                <ul class="rep-enlaces" data-enlaces-lista>
                    @forelse ($enlaces as $enlace)
                        <li class="rep-enlace" data-enlace-id="{{ $enlace['id'] }}" data-estado="{{ $enlace['estado'] }}">
                            <span class="rep-enlace__estado rep-enlace__estado--{{ $enlace['estado'] }}">{{ ucfirst($enlace['estado']) }}</span>
                            <span class="rep-enlace__meta">vence {{ fecha_mx($enlace['expira_en']) }} · {{ $enlace['accesos'] }} {{ $enlace['accesos'] === 1 ? 'acceso' : 'accesos' }}</span>
                            @if ($enlace['estado'] === 'vigente')
                                <button type="button" class="btn btn--secondary btn--sm" data-enlace-revocar>Revocar</button>
                            @endif
                        </li>
                    @empty
                        <li class="rep-entregas__vacio" data-enlaces-vacio>Todavía no hay enlaces.</li>
                    @endforelse
                </ul>

                <button type="button" class="btn btn--secondary btn--sm" data-enlace-crear><i class="fa-solid fa-link"></i> Crear enlace</button>
            </div>

            <div class="card card--padded">
                <h2 class="card__header-title">Alcance</h2>
                <p class="rep-aside__hint">{{ $reporte->notas_alcance ?: 'Sin notas de alcance.' }}</p>
            </div>
        </aside>
    </div>

    {{-- ---------- Añadir sección ---------- --}}
    <x-modal id="seccionModal">
        <x-slot:header><h2 style="margin-bottom:0;">Añadir sección</h2></x-slot:header>
        <form id="seccionForm" novalidate>
            <div class="field">
                <label class="field__label" for="sec_tipo">Tipo de sección</label>
                <select class="select" name="tipo" id="sec_tipo" required data-seccion-tipo-select>
                    @foreach ($tiposSeccion as $tipo)
                        <option value="{{ $tipo['value'] }}">{{ $tipo['label'] }}</option>
                    @endforeach
                </select>
                <span class="field__error" data-error-for="tipo"></span>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="sec_titulo">Título</label>
                <input class="input" type="text" name="titulo" id="sec_titulo" required placeholder="Rendimiento por campaña">
                <span class="field__error" data-error-for="titulo"></span>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="seccionModal">Cancelar</button>
                <button type="submit" class="btn btn--primary">Añadir</button>
            </div>
        </form>
    </x-modal>

    {{-- ---------- Pegar desde hoja de cálculo (compartido por serie/tabla/ficha/plan) ---------- --}}
    <x-modal id="pegarModal" size="lg">
        <x-slot:header><h2 style="margin-bottom:0;">Pegar desde Excel</h2></x-slot:header>
        <form id="pegarForm" novalidate>
            <p class="rep-pegar__ayuda">
                Pega las filas tal cual salen de la hoja (separadas por tabulaciones), en este orden:
                <strong data-pegar-columnas>—</strong>
            </p>
            <div class="field">
                <label class="field__label" for="pegar_texto">Filas</label>
                <textarea class="textarea rep-pegar__textarea" name="texto" id="pegar_texto" rows="14"
                          data-pegar-textarea placeholder="Una fila por línea, columnas separadas por tabulador."></textarea>
            </div>

            <div class="rep-pegar__errores" data-pegar-errores hidden></div>

            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="pegarModal">Cerrar</button>
                <button type="submit" class="btn btn--primary" data-pegar-importar>Importar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/reportes.js')
@endsection
