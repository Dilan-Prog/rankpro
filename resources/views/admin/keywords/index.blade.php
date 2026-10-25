@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/keywords.css')
@endsection

@section('content')
    @php
        $nListas = $listas->count();
        $subtituloBanco = $nListas.' '.($nListas === 1 ? 'lista' : 'listas').' de palabras clave · '.$totalKeywords.' '.($totalKeywords === 1 ? 'keyword' : 'keywords').' en total';
    @endphp
    {{-- .kwx acota el rediseño de este módulo (tarjetas, modales y formularios) sin tocar los componentes compartidos. --}}
    <div class="kwx">
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Banco de Keywords</h1>
            <p class="page-header__subtitle" id="keywordsCountSubtitle">{{ $subtituloBanco }}</p>
        </div>
        <div class="kwx-cabecera__botones">
            <button type="button" class="kwx-btn" data-open-import-modal>
                <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i> Importar
            </button>
            <button type="button" class="kwx-btn kwx-btn--primario" data-open-lista-modal>
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva lista
            </button>
        </div>
    </div>

    <div class="filters-bar kwx-filtros">
        <div class="kwx-buscador">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="input input--search" id="listasSearch" placeholder="Buscar lista o palabra clave…" aria-label="Buscar lista o palabra clave">
        </div>
        <select class="select" id="listasClienteFilter" aria-label="Filtrar por cliente">
            <option value="all">Todos los clientes</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
        <select class="select" id="listasEstadoFilter" aria-label="Filtrar por estado">
            <option value="all">Todos los estados</option>
            <option value="en_uso">En Uso</option>
            <option value="seguimiento">Seguimiento</option>
            <option value="descartada">Descartada</option>
        </select>
    </div>

    <div class="bulk-toolbar" id="listasBulkToolbar" hidden
        data-bulk-descartar-action="{{ route('admin.keywords.listas.bulk-descartar') }}">
        <span id="listasBulkCount" aria-live="polite"></span>
        <div class="bulk-toolbar__actions">
            <button type="button" class="kwx-btn kwx-btn--peligro kwx-btn--sm" id="listasBulkDescartar">
                <i class="fa-solid fa-ban"></i> Descartar seleccionadas
            </button>
            <button type="button" class="kwx-btn kwx-btn--sm" id="listasBulkCancelar">Cancelar</button>
        </div>
    </div>

    @php
        $listaHeaders = [
            new \Illuminate\Support\HtmlString('<input type="checkbox" class="kwx-check" id="listasCheckAll" aria-label="Seleccionar todas las listas">'),
            '#', 'Nombre de lista de palabras clave', 'Cliente', 'Estado', 'Keywords', 'Volumen', 'KD prom.', 'CPC prom.', 'Pos. prom.', 'Fuente',
            new \Illuminate\Support\HtmlString('<span class="u-visually-hidden">Acciones</span>'),
        ];
    @endphp
    <x-data-table :headers="$listaHeaders" class="kwx-tabla" data-paginate="15" data-listas-table
        data-mediciones-index-template="{{ route('admin.keywords.listas.mediciones.index', ['lista' => '__ID__']) }}"
        data-mediciones-store-template="{{ route('admin.keywords.listas.mediciones.store', ['lista' => '__ID__']) }}"
        data-medicion-update-template="{{ route('admin.keywords.listas.mediciones.update', ['medicion' => '__ID__']) }}"
        data-keyword-mediciones-template="{{ route('admin.keywords.mediciones', ['keyword' => '__ID__']) }}">
        @forelse ($listas as $l)
            @include('admin.keywords._lista-row', ['l' => $l, 'n' => $loop->iteration])
        @empty
            <tr class="table__empty"><td colspan="12">Aún no hay listas de keywords. Crea la primera con "Nueva lista".</td></tr>
        @endforelse
    </x-data-table>

    <div class="kwx-seccion">
        <h2 class="kwx-seccion__titulo">Sin lista asignada</h2>
        <p class="kwx-seccion__sub">Keywords existentes que aún no pertenecen a ninguna lista.</p>
    </div>
    <div class="kwx-vacio kwx-vacio--tarjeta" data-sinlista-empty {{ $keywordsSinLista->isEmpty() ? '' : 'hidden' }}>
        <i class="fa-solid fa-key" aria-hidden="true"></i>
        <p>No hay keywords sin lista asignada.</p>
    </div>
    <div class="table-wrap kwx-tabla kwx-tabla--simple" data-sinlista-table data-paginate="15" {{ $keywordsSinLista->isEmpty() ? 'hidden' : '' }}>
        <table class="table">
            <thead>
                <tr>
                    <th>Palabra clave</th>
                    <th>Tipo</th>
                    <th>Volumen</th>
                    <th>KD</th>
                    <th>CPC est.</th>
                    <th>Intención</th>
                    <th>URL</th>
                    <th>Posición</th>
                    <th>Fuente</th>
                    <th><span class="u-visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody data-sinlista-rows>
                @foreach ($keywordsSinLista as $k)
                    @include('admin.keywords._keyword-row', ['k' => $k])
                @endforeach
            </tbody>
        </table>
    </div>

    <x-modal id="listaDetailModal">
        <x-slot:header>
            <div class="kwx-modal__titulo">
                <h2 id="listaDetailTitle"></h2>
                <span id="listaDetailBadge"></span>
            </div>
            <p class="kwx-modal__sub">Lista de keywords</p>
        </x-slot:header>

        <div class="kwx-metricas">
            <div class="kwx-metrica">
                <span class="kwx-metrica__etq">Keywords</span>
                <span class="kwx-metrica__val u-mono" id="listaDetailCount"></span>
            </div>
            <div class="kwx-metrica">
                <span class="kwx-metrica__etq">Volumen total</span>
                <span class="kwx-metrica__val u-mono" id="listaDetailVolumen"></span>
            </div>
            <div class="kwx-metrica">
                <span class="kwx-metrica__etq">KD promedio</span>
                <span class="kwx-metrica__val u-mono" id="listaDetailKd"></span>
            </div>
            <div class="kwx-metrica">
                <span class="kwx-metrica__etq">CPC promedio</span>
                <span class="kwx-metrica__val u-mono kwx-verde" id="listaDetailCpc"></span>
            </div>
        </div>

        <div class="kwx-bloque">
            <div class="kwx-etiqueta">Datos</div>
            <dl class="kwx-ficha">
                <div><dt>Cliente</dt><dd id="listaDetailCliente"></dd></div>
                <div><dt>Canal</dt><dd id="listaDetailCanal"></dd></div>
                <div><dt>Responsable</dt><dd id="listaDetailResponsable"></dd></div>
                <div><dt>Posición promedio</dt><dd class="u-mono" id="listaDetailPosicion"></dd></div>
                <div><dt>Fuentes</dt><dd id="listaDetailFuentes"></dd></div>
                <div><dt>Última actualización</dt><dd class="u-mono" id="listaDetailUpdated"></dd></div>
            </dl>
        </div>

        <div class="kwx-pie">
            <a id="listaDetailIrCliente" href="#" class="kwx-btn"
                data-href-template="{{ route('admin.clientes.show', ['cliente' => '__ID__']) }}">Ir al cliente</a>
            <button type="button" id="listaDetailImportar" class="kwx-btn">Importar keywords</button>
            <button type="button" id="listaDetailAddKeyword" class="kwx-btn">Añadir palabra clave</button>
            <button type="button" id="listaDetailEditar" class="kwx-btn kwx-btn--primario">Editar lista</button>
        </div>
    </x-modal>

    <x-modal id="listaFormModal">
        <x-slot:header>
            <h2 id="listaFormModalTitle">Nueva lista de keywords</h2>
            <p class="kwx-modal__sub" id="listaFormModalSub">Agrupa las keywords de un cliente por canal.</p>
        </x-slot:header>
        <form id="listaForm" novalidate
              data-store-action="{{ route('admin.keywords.listas.store') }}"
              data-update-action-template="{{ route('admin.keywords.listas.update', ['lista' => '__ID__']) }}">
            @csrf
            @include('admin.keywords._lista-form', ['lista' => null, 'clientes' => $clientes, 'usuarios' => $usuarios])
            <div class="kwx-pie">
                <button type="button" class="kwx-btn" data-modal-close="listaFormModal">Cancelar</button>
                <button type="submit" class="kwx-btn kwx-btn--primario" id="listaFormSubmit">Crear lista</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="listaDeleteModal">
        <x-slot:header><h2>Eliminar lista</h2></x-slot:header>
        <p class="kwx-texto">
            ¿Eliminar la lista <strong id="listaDeleteName"></strong>?
            Sus <strong id="listaDeleteCount"></strong> keywords <u>no</u> se eliminarán — quedarán sin lista asignada, disponibles en la sección "Sin lista asignada".
        </p>
        <div class="kwx-pie">
            <button type="button" class="kwx-btn" data-modal-close="listaDeleteModal">Cancelar</button>
            <button type="button" class="kwx-btn kwx-btn--peligro" id="listaDeleteConfirm"
                data-destroy-action-template="{{ route('admin.keywords.listas.destroy', ['lista' => '__ID__']) }}">Eliminar</button>
        </div>
    </x-modal>

    {{-- Añadir y editar comparten este modal: keywords.js cambia título, subtítulo y botón (openKeywordFormModal). --}}
    <x-modal id="keywordFormModal">
        <x-slot:header>
            <h2 id="keywordFormModalTitle">Añadir palabra clave</h2>
            <p class="kwx-modal__sub" id="keywordFormModalSub"></p>
        </x-slot:header>
        <form id="keywordForm" novalidate
              data-store-action="{{ route('admin.keywords.store') }}"
              data-update-action-template="{{ route('admin.keywords.update', ['keyword' => '__ID__']) }}">
            @csrf
            @include('admin.keywords._form', ['keyword' => null, 'clientes' => $clientes, 'listas' => $listas])
            <div class="kwx-pie">
                <button type="button" class="kwx-btn" data-modal-close="keywordFormModal">Cancelar</button>
                <button type="submit" class="kwx-btn kwx-btn--primario" id="keywordFormSubmit">Guardar keyword</button>
            </div>
        </form>
    </x-modal>

    {{--
        Ficha de una keyword (sólo lectura): la llena keywords.js (abrirKeywordDetalle) con la
        fila y pide la bitácora a keywords.mediciones. "Tráfico estimado" y "Valor equivalente
        en Ads" del diseño no están: necesitan una curva de CTR orgánico que el proyecto no tiene.
    --}}
    <x-modal id="keywordDetailModal">
        <x-slot:header>
            <div class="kwx-modal__titulo">
                <h2 id="kwDetailTitle"></h2>
                <span id="kwDetailBadge"></span>
            </div>
            <p class="kwx-modal__sub" id="kwDetailSub"></p>
        </x-slot:header>
        <div class="kwx-det">
            <div class="kwx-det__scroll">
                <span class="kwx-tipo" id="kwDetailTipo"></span>

                <div class="kwx-metricas">
                    <div class="kwx-metrica">
                        <span class="kwx-metrica__etq">Posición</span>
                        <span class="kwx-metrica__val u-mono kwx-verde" id="kwDetailPosicion"></span>
                    </div>
                    <div class="kwx-metrica">
                        <span class="kwx-metrica__etq">Volumen</span>
                        <span class="kwx-metrica__val u-mono" id="kwDetailVolumen"></span>
                    </div>
                    <div class="kwx-metrica">
                        <span class="kwx-metrica__etq">KD</span>
                        <span class="kwx-metrica__val u-mono" id="kwDetailKd"></span>
                    </div>
                    <div class="kwx-metrica">
                        <span class="kwx-metrica__etq">CPC est.</span>
                        <span class="kwx-metrica__val u-mono kwx-verde" id="kwDetailCpc"></span>
                    </div>
                </div>

                <div class="kwx-bloque">
                    <div class="kwx-etiqueta">Ficha del registro</div>
                    <dl class="kwx-ficha">
                        <div><dt>Intención</dt><dd id="kwDetailIntencion"></dd></div>
                        <div><dt>URL destino</dt><dd class="u-mono kwx-url" id="kwDetailUrl"></dd></div>
                        <div><dt>Posición anterior</dt><dd class="u-mono" id="kwDetailAnterior"></dd></div>
                        <div><dt>Cambio</dt><dd id="kwDetailCambio"></dd></div>
                        <div><dt>Lista</dt><dd id="kwDetailLista"></dd></div>
                        <div><dt>Incorporada</dt><dd class="u-mono" id="kwDetailIncorporada"></dd></div>
                    </dl>
                    <p class="kwx-nota-ficha" id="kwDetailNotas" hidden></p>
                </div>

                <div class="kwx-bloque">
                    <div class="kwx-etiqueta">Avance</div>
                    <div class="kwx-avance">
                        <span class="kwx-avance__etq">Dificultad (KD)</span>
                        <span class="kwx-avance__barra" aria-hidden="true"><span class="kwx-avance__relleno" id="kwDetailKdBarra"></span></span>
                        <span class="kwx-avance__num u-mono" id="kwDetailKdPct"></span>
                    </div>
                </div>

                <div class="kwx-bloque">
                    <div class="kwx-etiqueta">Bitácora</div>
                    <ol class="kwx-bitacora" id="kwDetailBitacora" aria-live="polite"></ol>
                </div>
            </div>
            <div class="kwx-pie kwx-pie--fijo">
                <button type="button" class="kwx-btn" data-modal-close="keywordDetailModal">Cerrar</button>
                <a class="kwx-btn" id="kwDetailSerp" href="#" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Ver en SERP</a>
                <button type="button" class="kwx-btn kwx-btn--primario" id="kwDetailEditar">Editar keyword</button>
            </div>
        </div>
    </x-modal>

    <x-modal id="keywordDeleteModal">
        <x-slot:header><h2>Eliminar keyword</h2></x-slot:header>
        <p class="kwx-texto">
            ¿Eliminar la keyword <strong id="keywordDeleteName"></strong>?
            Se eliminará por completo del banco de keywords, incluyendo de su lista. Esta acción no se puede deshacer.
        </p>
        <div class="kwx-pie">
            <button type="button" class="kwx-btn" data-modal-close="keywordDeleteModal">Cancelar</button>
            <button type="button" class="kwx-btn kwx-btn--peligro" id="keywordDeleteConfirm"
                data-destroy-action-template="{{ route('admin.keywords.destroy', ['keyword' => '__ID__']) }}">Eliminar</button>
        </div>
    </x-modal>

    <x-modal id="keywordImportModal">
        <x-slot:header>
            <h2>Importar keywords</h2>
            <p class="kwx-modal__sub">Pega las filas o sube un CSV a una lista.</p>
        </x-slot:header>
        <form id="keywordImportForm" novalidate
              data-import-action-template="{{ route('admin.keywords.listas.importar', ['lista' => '__ID__']) }}">
            <div class="field">
                <label class="field__label" for="ki_lista_id">Lista destino</label>
                <select class="select" name="lista_id" id="ki_lista_id" required>
                    <option value="">— Selecciona una lista —</option>
                    @foreach ($listas as $l)
                        <option value="{{ $l['id'] }}">{{ $l['nombre'] }} ({{ $l['cliente'] }})</option>
                    @endforeach
                </select>
                <span class="field__error" data-error-for="lista_id"></span>
            </div>
            <div class="field kwx-fila">
                <label class="field__label" for="ki_texto">Pegar keywords (una por línea)</label>
                <textarea class="textarea u-mono" name="texto" id="ki_texto" rows="6" placeholder="keyword, volumen, KD, CPC, intención, URL"></textarea>
                <span class="field__hint">Formato por línea: keyword, volumen, KD, CPC, intención, URL — las columnas después de la keyword son opcionales.</span>
                <span class="field__error" data-error-for="texto"></span>
            </div>
            <div class="field kwx-fila">
                <label class="field__label" for="ki_archivo">O sube un archivo CSV / TXT</label>
                <input class="input" type="file" name="archivo" id="ki_archivo" accept=".csv,.txt">
                <span class="field__error" data-error-for="archivo"></span>
            </div>

            <div id="keywordImportResults" aria-live="polite"></div>

            <div class="kwx-pie">
                <button type="button" class="kwx-btn" data-modal-close="keywordImportModal">Cerrar</button>
                <button type="submit" class="kwx-btn kwx-btn--primario" id="keywordImportSubmit"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Importar</button>
            </div>
        </form>
    </x-modal>

    {{--
        Captura de una RONDA completa: una lista + una fecha, todas sus keywords
        de una sentada. Las filas se construyen en cliente desde el data-lista
        JSON de la fila de la lista (keywords.js → renderMedicionRows), así que
        aquí sólo va el armazón. Reenviar una fecha ya registrada corrige esa
        ronda en vez de duplicarla, por eso al cambiar la fecha se precargan sus
        valores.
    --}}
    <x-modal id="medicionFormModal" size="lg">
        <x-slot:header>
            <h2 id="medicionFormTitle">Nueva medición</h2>
            <p class="kwx-modal__sub">
                Anota la posición de cada keyword y, sobre todo, <strong>qué se hizo</strong> para moverla.
                Deja la posición vacía si la keyword no rankea.
            </p>
        </x-slot:header>
        <form id="medicionForm" novalidate data-medicion-form>
            @csrf
            <div class="kw-medicion__head">
                <div class="field kw-medicion__fecha-field">
                    <label class="field__label" for="km_fecha">Fecha de la ronda</label>
                    <input class="input" type="date" name="fecha" id="km_fecha" required data-medicion-fecha>
                    <span class="field__error" data-error-for="fecha"></span>
                </div>
                <p class="kw-medicion__estado" data-medicion-estado></p>
            </div>

            {{-- Captura rápida: pegar desde Excel/Search Console o partir de la ronda anterior. Enter en una posición baja al siguiente renglón. --}}
            <div class="kw-medicion__tools" data-medicion-tools>
                <button type="button" class="kwx-btn kwx-btn--sm" data-medicion-pegar-toggle>
                    <i class="fa-solid fa-paste" aria-hidden="true"></i> Pegar desde Excel / Search Console
                </button>
                <button type="button" class="kwx-btn kwx-btn--sm" data-medicion-copiar-anterior>
                    <i class="fa-solid fa-copy" aria-hidden="true"></i> Copiar ronda anterior
                </button>
            </div>
            <div class="field kw-medicion__pegar" data-medicion-pegar-panel hidden>
                <label class="field__label" for="km_pegar">Pega las filas: keyword, posición y (opcional) URL</label>
                <textarea class="textarea u-mono" id="km_pegar" rows="6" data-medicion-pegar-texto
                          placeholder="hotel centro&#9;4&#9;/hoteles&#10;hotel boutique&#9;n/d"></textarea>
                <div>
                    <button type="button" class="kwx-btn kwx-btn--primario kwx-btn--sm" data-medicion-pegar-aplicar>
                        <i class="fa-solid fa-check" aria-hidden="true"></i> Aplicar a la ronda
                    </button>
                </div>
            </div>
            <div class="kw-medicion__captura-resumen" data-medicion-captura-resumen aria-live="polite"></div>

            <div class="kwx-vacio" data-medicion-empty hidden>
                <p>Esta lista aún no tiene keywords que medir.</p>
            </div>

            <div class="table-wrap kw-medicion__table" data-medicion-table>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Palabra clave</th>
                            <th class="kw-medicion__col-pos">Posición</th>
                            <th>¿Qué se hizo este mes?</th>
                        </tr>
                    </thead>
                    <tbody data-medicion-rows></tbody>
                </table>
            </div>

            <div class="kwx-pie">
                <button type="button" class="kwx-btn" data-modal-close="medicionFormModal">Cancelar</button>
                <button type="submit" class="kwx-btn kwx-btn--primario" id="medicionFormSubmit">Guardar medición</button>
            </div>
        </form>
    </x-modal>

    {{-- Corrección de una medición suelta (PUT mediciones.update), abierta al pulsar una celda del historial. --}}
    <x-modal id="medicionNotaModal">
        <x-slot:header>
            <h2 id="medicionNotaTitle">Medición</h2>
            <p class="kwx-modal__sub" id="medicionNotaSubtitle"></p>
        </x-slot:header>
        <form id="medicionNotaForm" novalidate data-medicion-nota-form>
            @csrf
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="kmn_posicion">Posición</label>
                    <input class="input u-mono" type="number" min="1" max="1000" name="posicion" id="kmn_posicion" placeholder="Vacío = no rankea">
                    <span class="field__error" data-error-for="posicion"></span>
                </div>
                <div class="field">
                    <label class="field__label" for="kmn_url">URL medida</label>
                    <input class="input u-mono" type="text" name="url" id="kmn_url" placeholder="/pagina-destino">
                    <span class="field__error" data-error-for="url"></span>
                </div>
            </div>
            <div class="field kwx-fila">
                <label class="field__label" for="kmn_nota">¿Qué se hizo este mes?</label>
                <textarea class="textarea kw-hist__nota-input" name="nota" id="kmn_nota" rows="7"
                    placeholder="Ej. Reescritura del title y del H1, tres enlaces internos desde el blog."></textarea>
                <span class="field__error" data-error-for="nota"></span>
            </div>
            <p id="medicionNotaAutor" class="kw-hist__autor"></p>
            <div class="kwx-pie">
                <button type="button" class="kwx-btn" data-modal-close="medicionNotaModal">Cancelar</button>
                <button type="submit" class="kwx-btn kwx-btn--primario" id="medicionNotaSubmit">Guardar cambios</button>
            </div>
        </form>
    </x-modal>
    </div>{{-- /.kwx --}}
@endsection

@section('scripts')
    @vite('resources/js/keywords.js')
@endsection
