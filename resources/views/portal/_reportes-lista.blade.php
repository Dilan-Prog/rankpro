{{-- Lista de entregas de reporte. Recibe $reportes (App\Support\Portal\Reportes::listar) y $token. --}}
<ul class="portal-lista">
    @foreach ($reportes as $r)
        <li class="portal-lista__fila" data-reporte-entrega="{{ $r['id'] }}">
            <div class="portal-lista__principal">
                <strong>{{ $r['titulo'] }}</strong>
                <span class="portal-lista__meta">
                    @if ($r['folio']){{ $r['folio'] }} · @endif{{ $r['area'] }}@if ($r['periodo']) · {{ $r['periodo'] }}@endif
                    <br>{{ $r['formato_label'] }} · versión {{ $r['version'] }}@if ($r['fecha']) · {{ fecha_mx($r['fecha']) }}@endif
                </span>
            </div>
            <a class="portal-boton" href="{{ route('portal.reportes.descargar', ['token' => $token, 'entrega' => $r['id']]) }}" rel="nofollow noopener noreferrer">Descargar PDF</a>
        </li>
    @endforeach
</ul>
