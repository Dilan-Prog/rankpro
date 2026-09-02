{{--
    Command palette (Cmd/Ctrl+K). Included once in layouts/admin.blade.php
    (it's a full-screen overlay, not per-page). Wired by
    resources/js/global.js's initCommandPalette(), which filters the JSON
    index embedded below via #agencyos-search-index
    (see App\Support\CommandPaletteIndex).
--}}
<div class="command-palette-overlay" id="commandPaletteOverlay" hidden>
    <div class="command-palette" role="dialog" aria-modal="true" aria-label="Buscar">
        <div class="command-palette__input-row">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" class="command-palette__input" id="commandPaletteInput"
                   placeholder="Buscar módulos, clientes, keywords..." autocomplete="off">
            <span class="command-palette__kbd">ESC</span>
        </div>
        <div class="command-palette__results" id="commandPaletteResults"></div>
    </div>
</div>

<script id="agencyos-search-index" type="application/json">{!! json_encode(\App\Support\CommandPaletteIndex::build(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
