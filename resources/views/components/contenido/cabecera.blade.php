{{-- Cabecera común de los editores de Contenido (documento y tabla): migas, nombre editable y zona de
     acciones (el slot). Es un componente y no un @include porque necesita el slot de acciones propias. --}}
@props(['elemento', 'migas', 'urlVolver', 'urlRenombrar'])
<div class="cm-cabecera">
    <nav class="cm-migas" aria-label="Ruta de carpetas">
        <a href="{{ route('admin.contenido.index') }}" class="cm-migas__item">Contenido</a>
        @foreach ($migas as $miga)
            <span class="cm-migas__sep">/</span>
            <a href="{{ route('admin.contenido.index', ['carpeta' => $miga->id]) }}" class="cm-migas__item">{{ $miga->nombre }}</a>
        @endforeach
    </nav>

    <div class="cm-cabecera__fila">
        <a href="{{ $urlVolver }}" class="btn btn--ghost btn--sm btn--icon" title="Volver a la carpeta" aria-label="Volver a la carpeta"><i class="fa-solid fa-arrow-left"></i></a>
        <input type="text" class="input cm-cabecera__nombre" value="{{ $elemento->nombre }}" maxlength="255"
               aria-label="Nombre" data-cm-nombre-editor data-url="{{ $urlRenombrar }}">
        <span class="cm-estado" data-cm-estado role="status" aria-live="polite">Sin cambios</span>
        <div class="cm-cabecera__acciones">
            {{ $slot }}
        </div>
    </div>
</div>
