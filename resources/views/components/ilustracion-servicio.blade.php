{{-- Adornos del hero por servicio: sin texto ni cifras, para no aparentar datos reales. --}}
@if ($tipo === 'panel')
    @include('components.ilustracion-panel')
@else
<div class="page-hero__adorno" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 400" focusable="false">
    @switch ($tipo)
        @case ('seo')
            <rect x="1" y="1" width="518" height="398" rx="24" fill="#fff" stroke="#E3E9E6"/>
            <rect x="24" y="24" width="472" height="48" rx="24" fill="#F4F7F5"/>
            <circle cx="52" cy="47" r="8" fill="none" stroke="#66756E" stroke-width="2.5"/><path d="M58 53l6 6" stroke="#66756E" stroke-width="2.5" stroke-linecap="round"/>
            <rect x="78" y="43" width="180" height="10" rx="5" fill="#C9D3CE"/>
            @foreach ([[92, true], [194, false], [286, false]] as [$y, $primero])
                <rect x="24" y="{{ $y }}" width="472" height="{{ $primero ? 88 : 78 }}" rx="16" fill="{{ $primero ? '#E6F4EE' : '#fff' }}" stroke="{{ $primero ? '#0F9D6E' : '#EEF2F0' }}" stroke-width="{{ $primero ? 2 : 1 }}"/>
                <circle cx="58" cy="{{ $y + 40 }}" r="16" fill="{{ $primero ? '#0F9D6E' : '#EEF2F0' }}"/>
                <rect x="92" y="{{ $y + 18 }}" width="90" height="7" rx="3.5" fill="#C9D3CE"/>
                <rect x="92" y="{{ $y + 33 }}" width="{{ $primero ? 250 : 200 }}" height="13" rx="6.5" fill="{{ $primero ? '#0B7D58' : '#66756E' }}"/>
                <rect x="92" y="{{ $y + 55 }}" width="320" height="7" rx="3.5" fill="{{ $primero ? '#C6E7D9' : '#E3E9E6' }}"/>
            @endforeach
            <path d="M52 136l6-7 6 7M58 130v15" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case ('app')
            <rect x="262" y="60" width="180" height="300" rx="30" fill="#fff" stroke="#E3E9E6"/>
            <rect x="280" y="86" width="144" height="90" rx="16" fill="#0B1712"/><rect x="296" y="106" width="70" height="10" rx="5" fill="#4FD1A5"/><rect x="296" y="126" width="104" height="7" rx="3.5" fill="#3A5347"/>
            @foreach ([192, 240, 288] as $y)
                <rect x="280" y="{{ $y }}" width="144" height="38" rx="12" fill="#F4F7F5"/><circle cx="302" cy="{{ $y + 19 }}" r="9" fill="#C6E7D9"/><rect x="320" y="{{ $y + 15 }}" width="76" height="8" rx="4" fill="#C9D3CE"/>
            @endforeach
            <rect x="88" y="20" width="206" height="360" rx="36" fill="#0E1A15"/><rect x="97" y="29" width="188" height="342" rx="28" fill="#fff"/><rect x="156" y="38" width="70" height="16" rx="8" fill="#0E1A15"/>
            <rect x="115" y="74" width="96" height="13" rx="6.5" fill="#0E1A15"/><rect x="115" y="96" width="140" height="8" rx="4" fill="#E3E9E6"/>
            <rect x="115" y="120" width="152" height="96" rx="18" fill="#E6F4EE"/><circle cx="191" cy="168" r="24" fill="#0F9D6E"/><path d="M181 168l7 7 14-14" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="115" y="230" width="71" height="58" rx="14" fill="#F4F7F5"/><rect x="196" y="230" width="71" height="58" rx="14" fill="#F4F7F5"/>
            <rect x="128" y="246" width="34" height="8" rx="4" fill="#C9D3CE"/><rect x="128" y="262" width="45" height="10" rx="5" fill="#0B7D58"/><rect x="209" y="246" width="30" height="8" rx="4" fill="#C9D3CE"/><rect x="209" y="262" width="45" height="10" rx="5" fill="#0B7D58"/>
            <rect x="115" y="304" width="152" height="42" rx="14" fill="#0B7D58"/><rect x="157" y="320" width="68" height="9" rx="4.5" fill="#fff"/>
            @break
        @case ('velocidad')
            <rect x="1" y="1" width="518" height="398" rx="24" fill="#fff" stroke="#E3E9E6"/>
            <path d="M120 224a140 140 0 0 1 280 0" fill="none" stroke="#EEF2F0" stroke-width="30" stroke-linecap="round"/>
            <path d="M120 224a140 140 0 0 1 244-93" fill="none" stroke="#0F9D6E" stroke-width="30" stroke-linecap="round"/>
            <path d="M260 224l82-70" stroke="#0E1A15" stroke-width="8" stroke-linecap="round"/><circle cx="260" cy="224" r="16" fill="#0E1A15"/>
            @foreach ([[24, 300], [188, 240], [352, 190]] as $i => [$x, $w])
                <rect x="{{ $x }}" y="276" width="144" height="98" rx="16" fill="#F4F7F5"/><circle cx="{{ $x + 24 }}" cy="304" r="8" fill="#0F9D6E"/><rect x="{{ $x + 42 }}" y="299" width="62" height="10" rx="5" fill="#C9D3CE"/>
                <rect x="{{ $x + 16 }}" y="330" width="112" height="10" rx="5" fill="#E3E9E6"/><rect x="{{ $x + 16 }}" y="330" width="{{ $w / 4 + 16 }}" height="10" rx="5" fill="#0B7D58"/>
            @endforeach
            @break
        @case ('social')
            <rect x="276" y="24" width="220" height="300" rx="24" fill="#0B1712"/><circle cx="386" cy="150" r="34" fill="#13241C" stroke="#1F3329"/><path d="M377 134l26 16-26 16z" fill="#4FD1A5"/><rect x="300" y="262" width="120" height="10" rx="5" fill="#B5C4BD"/><rect x="300" y="282" width="80" height="8" rx="4" fill="#3A5347"/>
            <rect x="24" y="56" width="276" height="320" rx="24" fill="#fff" stroke="#E3E9E6"/>
            <circle cx="62" cy="94" r="18" fill="#C6E7D9"/><rect x="92" y="83" width="92" height="9" rx="4.5" fill="#0E1A15"/><rect x="92" y="98" width="60" height="7" rx="3.5" fill="#C9D3CE"/>
            <rect x="44" y="128" width="236" height="152" rx="16" fill="#E6F4EE"/><rect x="66" y="160" width="130" height="16" rx="8" fill="#0B7D58"/><rect x="66" y="188" width="172" height="10" rx="5" fill="#9CD3BD"/><rect x="66" y="208" width="140" height="10" rx="5" fill="#9CD3BD"/>
            <path d="M62 316c-10-8-18-12-18-20a9 9 0 0 1 18-3 9 9 0 0 1 18 3c0 8-8 12-18 20z" fill="#0F9D6E"/><rect x="96" y="298" width="58" height="9" rx="4.5" fill="#C9D3CE"/>
            <rect x="44" y="332" width="236" height="26" rx="13" fill="#F4F7F5"/><rect x="60" y="341" width="104" height="8" rx="4" fill="#C9D3CE"/>
            @break
    @endswitch
    </svg>
</div>
@endif
