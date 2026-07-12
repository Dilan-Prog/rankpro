<tr data-qa-id="{{ $qa->id }}">
    <td>{{ \App\Support\Labels::tipoPruebaQa($qa->tipo_prueba->value) }}</td>
    <td><x-badge :status="$qa->resultado" /></td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $qa->notas ?? '—' }}</span></td>
    <td>
        <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-qa="{{ $qa->id }}">
            <i class="fa-solid fa-trash"></i>
        </button>
    </td>
</tr>
