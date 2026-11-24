@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/configuracion.css')
@endsection

{{--
    Redes sociales del pie. Formulario clásico que guarda la lista entera; el JS
    (redes-sociales.js) solo agrega, quita y reordena filas. Sin JS se puede editar lo
    que hay y siempre queda una fila vacía para agregar.
--}}
@php
    $filas = old('redes') ?? $redes->map->only(['red', 'nombre', 'url', 'activa'])->all();

    // old() no trae los checkbox apagados: sin esto volverían encendidos.
    if (old('redes')) {
        $filas = array_map(static fn ($f) => ['activa' => ! empty($f['activa'])] + (array) $f, $filas);
    }

    $siguiente = $filas === [] ? 0 : max(array_map('intval', array_keys($filas))) + 1;
    $filas[$siguiente] = [];
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Redes sociales</h1>
            <p class="page-header__subtitle">Elige un icono, pega el enlace y aparecerá en el pie del sitio público.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    @error('redes')<div class="form-status"><span class="field__error">{{ $message }}</span></div>@enderror

    <form method="POST" action="{{ route('admin.configuracion.redes.update') }}" class="card card--padded cfg-redes"
          data-configuracion-redes data-siguiente="{{ $siguiente + 1 }}" data-maximo="{{ $maximo }}">
        @csrf
        @method('PUT')

        <ol class="cfg-redes__lista" data-redes-lista>
            @foreach ($filas as $i => $fila)
                @include('admin.configuracion._red-fila', ['i' => $i, 'fila' => $fila])
            @endforeach
        </ol>

        <template data-redes-plantilla>
            @include('admin.configuracion._red-fila', ['i' => '__I__', 'fila' => []])
        </template>

        <p class="field__hint">Las filas sin enlace no se guardan. Los perfiles activos también se declaran en los datos estructurados del sitio, para que Google los relacione con la empresa.</p>

        <div class="cfg-redes__acciones">
            <button type="button" class="btn btn--secondary" data-redes-agregar><i class="fa-solid fa-plus"></i> Agregar red</button>
            <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
@endsection

@section('scripts')
    @vite('resources/js/redes-sociales.js')
@endsection
