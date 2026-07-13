<tr data-creativo-id="{{ $cr->id }}"
    data-creativo-titulo="{{ $cr->titulo }}"
    data-creativo-copy="{{ $cr->copy }}"
    data-creativo-tipo="{{ $cr->tipo }}"
    data-creativo-url-creativo="{{ $cr->url_creativo }}"
    data-creativo-ab-testing="{{ $cr->ab_testing ? '1' : '' }}"
    data-creativo-estado="{{ $cr->estado->value }}">
    <td>{{ $cr->titulo }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ \Illuminate\Support\Str::limit($cr->copy, 60) ?: '—' }}</span></td>
    <td style="text-transform:capitalize;">{{ $cr->tipo }}</td>
    <td><span class="u-mono" style="color:var(--color-primary); font-size:var(--text-xs)">{{ $cr->url_creativo ?? '—' }}</span></td>
    <td>{{ $cr->ab_testing ? 'Sí' : 'No' }}</td>
    <td><x-badge :status="$cr->estado" /></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-creativo="{{ $cr->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-creativo="{{ $cr->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
