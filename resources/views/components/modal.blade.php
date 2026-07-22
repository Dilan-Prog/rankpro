{{--
    Generic dismissible modal. Usage:
    <x-modal id="clientModal">
        <x-slot:header><h2>{{ $client->company }}</h2></x-slot:header>
        ...body content...
    </x-modal>
    Open from JS with AgencyOS.openModal('clientModal'); dismissal (overlay
    click, [data-modal-close], Escape key) is wired globally by global.js.
    Pass size="lg" for a large 80vw x 80vh variant (e.g. spreadsheet-like
    editors that need real screen space) — default size is unchanged.
--}}
@props(['id', 'size' => 'default'])
<div class="modal-overlay" id="{{ $id }}" hidden>
    <div class="modal {{ $size === 'lg' ? 'modal--lg' : '' }}" role="dialog" aria-modal="true">
        <div class="modal__header">
            <div>{{ $header ?? '' }}</div>
            <button type="button" class="modal__close" data-modal-close="{{ $id }}" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal__body">
            {{ $slot }}
        </div>
    </div>
</div>
