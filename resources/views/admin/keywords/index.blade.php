@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/keywords.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Banco de Keywords</h1>
            <p class="page-header__subtitle" id="keywordsCountSubtitle">{{ $totalKeywords }} keywords en {{ $listas->count() }} listas</p>
        </div>
        <div style="display:flex; gap:var(--space-2);">
            <button type="button" class="btn btn--ghost" data-open-import-modal>
                <i class="fa-solid fa-file-import"></i> Importar
            </button>
            <button type="button" class="btn btn--primary" data-open-lista-modal>
                <i class="fa-solid fa-plus"></i> Nueva Lista
            </button>
        </div>
    </div>

    <div class="filters-bar">
        <input type="search" class="input input--search" id="listasSearch" placeholder="Buscar lista o keyword...">
        <select class="select" id="listasClienteFilter">
            <option value="all">Todos los clientes</option>
            @foreach ($clientes as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
        <select class="select" id="listasEstadoFilter">
            <option value="all">Todos los estados</option>
            <option value="en_uso">En Uso</option>
            <option value="seguimiento">Seguimiento</option>
            <option value="descartada">Descartada</option>
        </select>
    </div>

    <div class="bulk-toolbar" id="listasBulkToolbar" hidden
        data-bulk-descartar-action="{{ route('admin.keywords.listas.bulk-descartar') }}">
        <span id="listasBulkCount"></span>
        <div class="bulk-toolbar__actions">
            <button type="button" class="btn btn--danger btn--sm" id="listasBulkDescartar">
                <i class="fa-solid fa-ban"></i> Descartar seleccionadas
            </button>
            <button type="button" class="btn btn--secondary btn--sm" id="listasBulkCancelar">Cancelar</button>
        </div>
    </div>

    @php
        $listaHeaders = [
            new \Illuminate\Support\HtmlString('<input type="checkbox" id="listasCheckAll" aria-label="Seleccionar todas las listas" style="width:15px;height:15px;accent-color:var(--color-primary);">'),
            'Lista', 'Cliente', 'Estado', 'Keywords', 'Volumen', 'KD prom.', 'CPC prom.', 'Posición prom.', 'Fuentes', '',
        ];
    @endphp
    <x-data-table :headers="$listaHeaders" data-paginate="15" data-listas-table>
        @forelse ($listas as $l)
            @include('admin.keywords._lista-row', ['l' => $l])
        @empty
            <tr class="table__empty"><td colspan="11">Aún no hay listas de keywords. Crea la primera con "Nueva Lista".</td></tr>
        @endforelse
    </x-data-table>

    <div style="margin-top:var(--space-8); margin-bottom:var(--space-4);">
        <h2 style="font-size:var(--text-lg); font-weight:var(--font-weight-semibold);">Sin lista asignada</h2>
        <p style="font-size:var(--text-sm); color:var(--color-muted-foreground);">Keywords existentes que aún no pertenecen a ninguna lista.</p>
    </div>
    <div class="empty-state" data-sinlista-empty {{ $keywordsSinLista->isEmpty() ? '' : 'hidden' }}>
        <div class="empty-state__icon"><i class="fa-solid fa-key"></i></div>
        <p class="empty-state__text">No hay keywords sin lista asignada.</p>
    </div>
    <div class="table-wrap" data-sinlista-table data-paginate="15" {{ $keywordsSinLista->isEmpty() ? 'hidden' : '' }}>
        <table class="table">
            <thead>
                <tr>
                    <th>Palabra clave</th>
                    <th>Tipo</th>
                    <th>Volumen</th>
                    <th>KD</th>
                    <th>CPC Est.</th>
                    <th>Intención</th>
                    <th>URL</th>
                    <th>Posición</th>
                    <th>Fuente</th>
                    <th></th>
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
            <h2 id="listaDetailTitle" style="margin-bottom:6px;"></h2>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:var(--text-xs);color:var(--color-muted-foreground);">Lista de keywords</span>
                <span id="listaDetailBadge"></span>
            </div>
        </x-slot:header>

        <div class="record-modal__stats">
            <div>
                <div class="record-modal__section-label">Keywords</div>
                <div id="listaDetailCount"></div>
            </div>
            <div>
                <div class="record-modal__section-label">Volumen total</div>
                <div id="listaDetailVolumen"></div>
            </div>
            <div>
                <div class="record-modal__section-label">KD promedio</div>
                <div id="listaDetailKd"></div>
            </div>
            <div>
                <div class="record-modal__section-label">CPC promedio</div>
                <div id="listaDetailCpc"></div>
            </div>
        </div>

        <div class="record-modal__section">
            <div class="record-modal__section-label">Datos</div>
            <div class="form-grid form-grid--2">
                <div>
                    <div class="record-modal__section-label">Cliente</div>
                    <div id="listaDetailCliente"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Canal</div>
                    <div id="listaDetailCanal"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Responsable</div>
                    <div id="listaDetailResponsable"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Posición promedio</div>
                    <div id="listaDetailPosicion"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Fuentes</div>
                    <div id="listaDetailFuentes"></div>
                </div>
                <div>
                    <div class="record-modal__section-label">Última actualización</div>
                    <div id="listaDetailUpdated"></div>
                </div>
            </div>
        </div>

        <div class="record-modal__actions">
            <a id="listaDetailIrCliente" href="#" class="btn btn--secondary"
                data-href-template="{{ route('admin.clientes.show', ['cliente' => '__ID__']) }}">Ir al cliente</a>
            <button type="button" id="listaDetailImportar" class="btn btn--secondary">Importar keywords</button>
            <button type="button" id="listaDetailAddKeyword" class="btn btn--secondary">Añadir palabra clave</button>
            <button type="button" id="listaDetailEditar" class="btn btn--primary">Editar lista</button>
        </div>
    </x-modal>

    <x-modal id="listaFormModal">
        <x-slot:header><h2 id="listaFormModalTitle" style="margin-bottom:0;">Nueva Lista de Keywords</h2></x-slot:header>
        <form id="listaForm" novalidate
              data-store-action="{{ route('admin.keywords.listas.store') }}"
              data-update-action-template="{{ route('admin.keywords.listas.update', ['lista' => '__ID__']) }}">
            @csrf
            @include('admin.keywords._lista-form', ['lista' => null, 'clientes' => $clientes, 'usuarios' => $usuarios])
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="listaFormSubmit"><i class="fa-solid fa-check"></i> Crear Lista</button>
                <button type="button" class="btn btn--secondary" data-modal-close="listaFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="listaDeleteModal">
        <x-slot:header><h2 style="margin-bottom:0;">Eliminar lista</h2></x-slot:header>
        <p style="margin-bottom:var(--space-4);">
            ¿Eliminar la lista <strong id="listaDeleteName"></strong>?
            Sus <strong id="listaDeleteCount"></strong> keywords <u>no</u> se eliminarán — quedarán sin lista asignada, disponibles en la sección "Sin lista asignada".
        </p>
        <div class="form-actions">
            <button type="button" class="btn btn--danger" id="listaDeleteConfirm"
                data-destroy-action-template="{{ route('admin.keywords.listas.destroy', ['lista' => '__ID__']) }}">Eliminar</button>
            <button type="button" class="btn btn--secondary" data-modal-close="listaDeleteModal">Cancelar</button>
        </div>
    </x-modal>

    <x-modal id="keywordFormModal">
        <x-slot:header><h2 id="keywordFormModalTitle" style="margin-bottom:0;">Añadir Keyword</h2></x-slot:header>
        <form id="keywordForm" novalidate
              data-store-action="{{ route('admin.keywords.store') }}"
              data-update-action-template="{{ route('admin.keywords.update', ['keyword' => '__ID__']) }}">
            @csrf
            @include('admin.keywords._form', ['keyword' => null, 'clientes' => $clientes, 'listas' => $listas])
            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="keywordFormSubmit"><i class="fa-solid fa-check"></i> Guardar Keyword</button>
                <button type="button" class="btn btn--secondary" data-modal-close="keywordFormModal">Cancelar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="keywordDeleteModal">
        <x-slot:header><h2 style="margin-bottom:0;">Eliminar keyword</h2></x-slot:header>
        <p style="margin-bottom:var(--space-4);">
            ¿Eliminar la keyword <strong id="keywordDeleteName"></strong>?
            Se eliminará por completo del banco de keywords, incluyendo de su lista. Esta acción no se puede deshacer.
        </p>
        <div class="form-actions">
            <button type="button" class="btn btn--danger" id="keywordDeleteConfirm"
                data-destroy-action-template="{{ route('admin.keywords.destroy', ['keyword' => '__ID__']) }}">Eliminar</button>
            <button type="button" class="btn btn--secondary" data-modal-close="keywordDeleteModal">Cancelar</button>
        </div>
    </x-modal>

    <x-modal id="keywordImportModal">
        <x-slot:header><h2 style="margin-bottom:0;">Importar Keywords</h2></x-slot:header>
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
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="ki_texto">Pegar keywords (una por línea)</label>
                <textarea class="textarea" name="texto" id="ki_texto" rows="6" placeholder="keyword, volumen, KD, CPC, intención, URL"></textarea>
                <span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">Formato por línea: keyword, volumen, KD, CPC, intención, URL — las columnas después de la keyword son opcionales.</span>
                <span class="field__error" data-error-for="texto"></span>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="ki_archivo">O sube un archivo CSV / TXT</label>
                <input class="input" type="file" name="archivo" id="ki_archivo" accept=".csv,.txt">
                <span class="field__error" data-error-for="archivo"></span>
            </div>

            <div id="keywordImportResults"></div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary" id="keywordImportSubmit"><i class="fa-solid fa-file-import"></i> Importar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="keywordImportModal">Cerrar</button>
            </div>
        </form>
    </x-modal>
@endsection

@section('scripts')
    @vite('resources/js/keywords.js')
@endsection
