<div class="empty-state" data-onpage-empty {{ $acciones->isEmpty() ? '' : 'hidden' }}>
    <div class="empty-state__icon"><i class="fa-solid fa-list-check"></i></div>
    <p class="empty-state__text">Sin acciones On-Page registradas todavía.</p>
</div>
<div class="onpage-list" data-onpage-list style="display:flex; flex-direction:column; gap: var(--space-3);" {{ $acciones->isEmpty() ? 'hidden' : '' }}>
    @foreach ($acciones as $accion)
        @include('admin.seo._onpage-item', ['accion' => $accion])
    @endforeach
</div>
