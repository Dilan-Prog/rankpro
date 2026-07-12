{{--
    Alert / notification component. Two variants:
    - "item"   (default) — a row inside an <ul class="alert-list">, e.g. the
                Dashboard's alert feed:
                <x-alert icon="fa-triangle-exclamation" color="#F59E0B">message</x-alert>
    - "banner" — a full-width callout box, e.g. the Integraciones "coming soon" notice:
                <x-alert variant="banner" icon="fa-bolt" color="#F59E0B">message</x-alert>
--}}
@props(['icon', 'color' => '#94A3B8', 'variant' => 'item'])
@if ($variant === 'banner')
    <div class="alert-banner" style="--alert-color: {{ $color }}">
        <i class="fa-solid {{ $icon }}"></i>
        <p>{{ $slot }}</p>
    </div>
@else
    <li class="alert-item">
        <i class="fa-solid {{ $icon }}" style="color: {{ $color }}"></i>
        <span class="alert-item__msg">{{ $slot }}</span>
    </li>
@endif
