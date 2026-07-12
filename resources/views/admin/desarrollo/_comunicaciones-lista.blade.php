@if ($comunicaciones->isEmpty())
    <div class="empty-state" data-comunicaciones-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-comments"></i></div>
        <p class="empty-state__text">Sin comunicaciones registradas todavía.</p>
    </div>
@endif
<div data-comunicaciones-list {{ $comunicaciones->isEmpty() ? 'hidden' : '' }} style="display:flex; flex-direction:column; gap: var(--space-3); padding: var(--space-4);">
    @foreach ($comunicaciones->sortByDesc('fecha') as $comunicacion)
        @include('admin.desarrollo._comunicacion-item', ['comunicacion' => $comunicacion])
    @endforeach
</div>
