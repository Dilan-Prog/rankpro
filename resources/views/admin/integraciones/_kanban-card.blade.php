@php
    [$icono, $color] = \App\Support\Labels::tipoConversionIcono($conversion->tipo->value);
    $iconPrefix = $conversion->tipo->value === 'whatsapp' ? 'fa-brands' : 'fa-solid';
    $identificador = $conversion->gclid ?? $conversion->gbraid ?? $conversion->wbraid;
@endphp
<div class="kanban__card" draggable="true" data-kanban-card data-conversion-id="{{ $conversion->id }}">
    <div class="kanban__card-top">
        <span class="kanban__card-icon" style="background: {{ $color }}22; color: {{ $color }};">
            <i class="{{ $iconPrefix }} {{ $icono }}"></i>
        </span>
        <span class="kanban__card-title" title="{{ $conversion->campanaNombre() }}">
            {{ $conversion->campanaNombre() ?? 'Sin campaña' }}
        </span>
        <span class="kanban__card-date">{{ fecha_mx($conversion->created_at) }}</span>
    </div>
    <div class="kanban__card-meta">
        <span>{{ \App\Support\Labels::tipoConversion($conversion->tipo->value) }}</span>
        @if ($identificador)
            <span class="u-mono" title="{{ $identificador }}">{{ \Illuminate\Support\Str::limit($identificador, 14) }}</span>
        @else
            <span class="conv-chip conv-chip--error" title="Sin gclid, gbraid ni wbraid Google no puede atribuirla">No exportable</span>
        @endif
    </div>
    @if ($conversion->valor !== null)
        <div class="kanban__card-valor">${{ number_format($conversion->valor, 2) }} {{ $conversion->moneda }}</div>
    @endif
    @if (! empty($conversion->datos_personalizados))
        <div class="kanban__card-tags">
            @foreach ($conversion->datos_personalizados as $colId => $valor)
                @if ($valor && $columnasPersonalizadas->has($colId))
                    <span class="kanban__card-tag">{{ $columnasPersonalizadas[$colId]->nombre }}: {{ $valor }}</span>
                @endif
            @endforeach
        </div>
    @endif
</div>
