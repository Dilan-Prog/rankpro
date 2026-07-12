{{--
    Generic data table shell. Usage:
    <x-data-table :headers="['Empresa', 'Contacto', 'Estado']">
        @foreach ($clients as $client)
            <tr>...</tr>
        @endforeach
    </x-data-table>
    For the no-results state, wrap the caller's @foreach in @forelse and
    render a `.table__empty` <tr><td colspan="..."> in the @empty branch.
--}}
@props(['headers' => []])
<div {{ $attributes->merge(['class' => 'table-wrap']) }}>
    <table class="table">
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
