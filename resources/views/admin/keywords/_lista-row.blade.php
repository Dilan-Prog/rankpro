{{--
    One lista <tr> + its paired keyword sub-table <tr>, used both for the
    server-rendered rows in index.blade.php and reconstructed by keywords.js
    (outerHTML upsert) after AJAX create/update — mirrors admin.servicios's
    data-servicio-row JSON-on-row convention (data-lista carries the full
    KeywordLista::toRow() shape, the single source of truth the JS reads for
    the detail modal, the edit form, and row reconstruction).
    $l is a KeywordLista::toRow() array. Column count here (11, including the
    leading checkbox and trailing actions column) must match the colspan on
    the paired sub-row below and the headers passed to <x-data-table> in
    index.blade.php.
--}}
<tr class="is-clickable" data-lista-row data-lista-id="{{ $l['id'] }}"
    data-search="{{ Str::lower($l['nombre'].' '.collect($l['keywords'])->pluck('keyword')->implode(' ')) }}"
    data-estado="{{ $l['estado'] }}"
    data-cliente="{{ $l['cliente_id'] }}"
    data-lista="{{ json_encode($l) }}">
    <td>
        <input type="checkbox" data-lista-checkbox value="{{ $l['id'] }}" aria-label="Seleccionar {{ $l['nombre'] }}" style="width:15px;height:15px;accent-color:var(--color-primary);">
    </td>
    <td>
        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" class="btn--icon" data-toggle-lista title="Expandir / colapsar">
                <i class="fa-solid fa-chevron-right lista-toggle__chevron"></i>
            </button>
            <span style="font-weight:500">{{ $l['nombre'] }}</span>
        </div>
    </td>
    <td>{{ $l['cliente'] }}</td>
    <td><x-badge :status="$l['estado']" /></td>
    <td class="u-mono">{{ $l['keywords_count'] }}</td>
    <td class="u-mono">{{ number_format($l['volumen_total']) }}</td>
    <td class="u-mono">{{ $l['kd_promedio'] ?? '—' }}</td>
    <td class="u-mono">{{ $l['cpc_promedio'] !== null ? '$'.number_format($l['cpc_promedio'], 2) : '—' }}</td>
    <td class="u-mono">{{ $l['posicion_promedio'] !== null ? '#'.$l['posicion_promedio'] : '—' }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ implode(', ', $l['fuentes']) ?: '—' }}</span></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar lista" data-edit-lista="{{ $l['id'] }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar lista" style="color:var(--text-danger);" data-delete-lista="{{ $l['id'] }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
{{--
    class="table__empty" here is a deliberate reuse of global.js's pagination
    exclusion: initTablePagination()'s dataRows() skips any <tr> with that
    class, which keeps this subrow from being counted/paginated as if it were
    a real data row (it would otherwise silently break the 15-per-page slicing
    and the "Mostrando X–Y de Z" count). keywords.css neutralizes the class's
    visual styling (centered muted placeholder text) for this element only.
--}}
<tr class="table__empty" data-lista-subrow="{{ $l['id'] }}" hidden>
    <td colspan="11" style="padding:0; background:var(--color-secondary);">
        <div style="padding: var(--space-4) var(--space-4) var(--space-4) var(--space-8);">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--space-2); margin-bottom:var(--space-3);">
                <div class="record-modal__section-label" style="margin:0;">Keywords de esta lista</div>
                <div style="display:flex; gap:var(--space-2);">
                    <button type="button" class="btn btn--secondary btn--sm" data-open-import-modal="{{ $l['id'] }}">
                        <i class="fa-solid fa-file-import"></i> Importar keywords
                    </button>
                    <button type="button" class="btn btn--primary btn--sm" data-add-keyword-to-lista="{{ $l['id'] }}" data-add-keyword-cliente="{{ $l['cliente_id'] }}">
                        <i class="fa-solid fa-plus"></i> Añadir palabra clave a esta lista
                    </button>
                </div>
            </div>

            <div class="empty-state" data-lista-keywords-empty="{{ $l['id'] }}" style="padding: var(--space-6);" {{ empty($l['keywords']) ? '' : 'hidden' }}>
                <p class="empty-state__text" style="margin-bottom:0;">Esta lista aún no tiene keywords.</p>
            </div>
            <div class="table-wrap" data-lista-keywords-table="{{ $l['id'] }}" {{ empty($l['keywords']) ? 'hidden' : '' }}>
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
                    <tbody data-lista-keywords-rows="{{ $l['id'] }}">
                        @foreach ($l['keywords'] as $k)
                            @include('admin.keywords._keyword-row', ['k' => $k])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </td>
</tr>
