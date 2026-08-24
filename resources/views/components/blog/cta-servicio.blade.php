@props(['servicios' => [], 'titulo' => 'El servicio detrás de este artículo'])

@if (! empty($servicios))
    <aside class="cta-servicio" aria-labelledby="cta-servicio-titulo">
        <h2 class="cta-servicio__titulo" id="cta-servicio-titulo">{{ $titulo }}</h2>
        <p class="cta-servicio__texto">
            Si prefieres que lo hagamos nosotros, esto es lo que trabajamos en RankPro.
        </p>

        <ul class="cta-servicio__lista">
            @foreach ($servicios as $servicio)
                <li>
                    <a class="cta-servicio__item" href="{{ $servicio['url'] }}">
                        <span class="cta-servicio__icono {{ $servicio['gradient'] }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $servicio['icon'] !!}</svg>
                        </span>
                        <span>
                            <span class="cta-servicio__nombre">{{ $servicio['nombre'] }}</span>
                            <span class="cta-servicio__resumen">{{ $servicio['resumen'] }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <a href="{{ route('contacto') }}" class="btn btn-primary">Solicitar propuesta</a>
    </aside>
@endif
