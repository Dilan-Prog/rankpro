@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/keywords.css')
@endsection

@section('content')
    @php
        $nombreCliente = $cliente->nombre_comercial;
        $pos = fn ($v) => $v === null ? '—' : '#'.$v;
    @endphp
    <div class="kwx">
    <a href="{{ route('admin.keywords.index') }}" class="kwc-volver"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Banco de Keywords</a>

    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $nombreCliente }}</h1>
            <p class="page-header__subtitle">
                {{ $kpi['listas'] }} {{ $kpi['listas'] === 1 ? 'lista' : 'listas' }} · {{ $kpi['keywords'] }} {{ $kpi['keywords'] === 1 ? 'keyword' : 'keywords' }}
            </p>
        </div>
        <div class="kwx-cabecera__botones">
            <a href="{{ route('admin.clientes.show', $cliente) }}" class="kwx-btn">
                <i class="fa-solid fa-building" aria-hidden="true"></i> Ficha del cliente
            </a>
        </div>
    </div>

    <div class="kwc-kpis">
        <x-stat-card label="Keywords" :value="$kpi['keywords']" sub="sin contar descartadas" icon="fa-key" color="primary" />
        <x-stat-card label="Listas" :value="$kpi['listas']" sub="de palabras clave" icon="fa-list" color="teal" />
        <x-stat-card label="En top 10" :value="$kpi['top10']" sub="según la última medición" icon="fa-trophy" color="emerald" fuente="Mediciones de posición" :fecha="$ultimaMedicion" fecha-sin="Sin fecha de medición" />
        <x-stat-card label="Posición media" :value="$pos($kpi['posicion_media'])" sub="keywords con posición" icon="fa-bullseye" color="amber" fuente="Mediciones de posición" :fecha="$ultimaMedicion" fecha-sin="Sin fecha de medición" />
    </div>

    <div class="card kwc-contacto">
        <dl class="kwx-ficha">
            <div><dt>Contacto</dt><dd>{{ $cliente->contacto_nombre ?: '—' }}</dd></div>
            <div><dt>Correo</dt><dd>{{ $cliente->email ?: '—' }}</dd></div>
            <div><dt>Teléfono</dt><dd class="u-mono">{{ $cliente->telefono ?: '—' }}</dd></div>
            <div><dt>Estado</dt><dd>@if ($cliente->estado)<x-badge :status="$cliente->estado" />@else — @endif</dd></div>
        </dl>
    </div>

    @forelse ($listas as $lista)
        @php $filas = $filasPorLista->get($lista->id, collect()); @endphp
        <section class="card kwc-lista">
            <div class="kwc-lista__cab">
                <div>
                    <div class="kwc-lista__titulo">
                        <h2>{{ $lista->nombre }}</h2>
                        <x-badge :status="$lista->estado->value" />
                    </div>
                    <p class="kwc-lista__meta">
                        {{ $lista->canal ?: 'Sin canal' }} · {{ $lista->responsable?->name ?? 'Sin responsable' }} · actualizada {{ fecha_mx($lista->updated_at) }}
                    </p>
                </div>
                <div class="kwx-cabecera__botones">
                    <a href="{{ route('admin.keywords.index') }}" class="kwx-btn kwx-btn--sm">Ver en el banco</a>
                    <a href="{{ route('admin.keywords.exportar-lista', $lista) }}" class="kwx-btn kwx-btn--sm">
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Exportar CSV
                    </a>
                </div>
            </div>
            @include('admin.keywords._cliente-tabla', ['filas' => $filas, 'vacio' => 'Esta lista aún no tiene palabras clave.'])
        </section>
    @empty
        <div class="kwx-vacio kwx-vacio--tarjeta">
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            <p>Este cliente aún no tiene listas de palabras clave.</p>
        </div>
    @endforelse

    @if ($sinLista->isNotEmpty())
        <section class="card kwc-lista">
            <div class="kwc-lista__cab">
                <div>
                    <div class="kwc-lista__titulo"><h2>Sin lista asignada</h2></div>
                    <p class="kwc-lista__meta">Keywords del cliente que aún no pertenecen a ninguna lista.</p>
                </div>
            </div>
            @include('admin.keywords._cliente-tabla', ['filas' => $sinLista, 'vacio' => ''])
        </section>
    @endif
    </div>
@endsection
