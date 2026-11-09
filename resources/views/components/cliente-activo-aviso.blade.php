{{--
    Aviso de «cliente activo»: las listas de Propuestas y Desarrollo solo muestran lo del cliente
    elegido en el selector del menú (grupo Marca). Sin este aviso quien llega a una lista «vacía» no
    sabe que está filtrada; el botón vuelve a mostrar todos los clientes.
    Uso: <x-cliente-activo-aviso :cliente="$clienteActivo" />  (cliente = modelo o null)
    `nota` aclara lo que el filtro deja fuera (p. ej. eventos de la agencia en el Calendario).
--}}
@props(['cliente' => null, 'nota' => null])
@if ($cliente)
    <div class="aviso-cliente" role="status">
        <span class="aviso-cliente__texto">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            Mostrando solo a <strong>{{ $cliente->nombre_comercial }}</strong>, el cliente activo del menú.
            @if ($nota)
                {{ $nota }}
            @endif
        </span>
        <form method="POST" action="{{ route('admin.cliente-activo') }}">
            @csrf
            <input type="hidden" name="cliente_id" value="">
            <button type="submit" class="btn btn--sm">Ver todos los clientes</button>
        </form>
    </div>
@endif
