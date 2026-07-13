<tr data-optimizacion-id="{{ $o->id }}"
    data-optimizacion-fecha="{{ $o->fecha->format('Y-m-d') }}"
    data-optimizacion-tipo="{{ $o->tipo }}"
    data-optimizacion-descripcion="{{ $o->descripcion }}"
    data-optimizacion-resultado="{{ $o->resultado }}">
    <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $o->fecha->format('Y-m-d') }}</td>
    <td>{{ \App\Support\Labels::tipoOptimizacion($o->tipo) }}</td>
    <td><span style="font-size:var(--text-sm);">{{ $o->descripcion }}</span></td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $o->resultado ?? '—' }}</span></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-optimizacion="{{ $o->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-optimizacion="{{ $o->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
