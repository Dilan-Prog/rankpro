<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Keyword;
use Illuminate\Support\Facades\Route;

/**
 * Builds the JSON blob embedded in layouts/admin.blade.php that powers the
 * Cmd/Ctrl+K command palette (resources/js/global.js -> initCommandPalette).
 *
 * No dedicated search endpoint exists (or is needed) for this: the system
 * has no JSON/API routes anywhere, everything is Blade or a form
 * POST/redirect, and at agency-CRM scale (not a multi-tenant SaaS) an
 * embedded blob filtered client-side is simpler than a controller and
 * avoids a network round-trip per keystroke. Collections are capped
 * defensively so this can't silently balloon page weight as the agency
 * grows — if that cap is ever hit in practice, upgrade to a real search
 * endpoint instead of raising it further.
 */
class CommandPaletteIndex
{
    private const LIMIT = 200;

    public static function build(): array
    {
        return [
            'modulos' => self::modulos(),
            'clientes' => self::clientes(),
            'keywords' => self::keywords(),
        ];
    }

    private static function modulos(): array
    {
        $items = [];

        $vistas = [];

        foreach (Navigation::groups() as $links) {
            foreach ($links as $link) {
                if (! Route::has($link['route'])) {
                    continue;
                }
                // «Resumen» sin cliente activo y «Reportes del cliente» apuntan a una pantalla que ya
                // tiene su propia entrada: se omiten para que la paleta no repita destinos.
                $url = Navigation::url($link);
                if (isset($vistas[$url])) {
                    continue;
                }
                $vistas[$url] = true;
                $items[] = [
                    // Las etiquetas cortas del menú («Lista», «Todos») no dicen nada fuera de su grupo.
                    'label' => $link['paleta'] ?? $link['label'],
                    'url' => $url,
                ];
            }
        }

        return $items;
    }

    /**
     * Clientes y keywords van en el HTML de cada página del panel: sin este filtro cualquier rol
     * (aunque no tenga CRM ni Keywords) recibiría hasta 200 nombres de cada tipo en el JSON.
     */
    private static function puede(string $modulo): bool
    {
        return auth()->user()?->tienePermiso($modulo) === true;
    }

    private static function clientes(): array
    {
        if (! self::puede('clientes')) {
            return [];
        }

        return Cliente::query()
            ->ordenadoPorNombreComercial()
            ->limit(self::LIMIT)
            ->get(['id', 'empresa', 'nombre', 'contacto_nombre'])
            ->map(fn (Cliente $cliente) => [
                'label' => $cliente->nombre_comercial,
                // Se busca también por la persona de contacto, aunque no se muestre como etiqueta.
                'busqueda' => collect([$cliente->nombre, $cliente->contacto_nombre])->filter()->unique()->implode(' '),
                'url' => route('admin.clientes.show', $cliente->id),
            ])
            ->all();
    }

    private static function keywords(): array
    {
        if (! self::puede('keywords')) {
            return [];
        }

        return Keyword::query()
            ->with('cliente:id,empresa,nombre')
            ->orderBy('keyword')
            ->limit(self::LIMIT)
            ->get(['id', 'keyword', 'cliente_id'])
            ->map(fn (Keyword $keyword) => [
                'label' => $keyword->keyword,
                'meta' => $keyword->cliente?->nombre_comercial,
                'url' => route('admin.keywords.index'),
            ])
            ->all();
    }
}
