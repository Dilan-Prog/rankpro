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

        foreach (Navigation::groups() as $links) {
            foreach ($links as $link) {
                if (! Route::has($link['route'])) {
                    continue;
                }
                $items[] = [
                    'label' => $link['label'],
                    'url' => route($link['route']),
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
            ->orderBy('empresa')
            ->limit(self::LIMIT)
            ->get(['id', 'empresa', 'nombre'])
            ->map(fn (Cliente $cliente) => [
                'label' => $cliente->empresa ?: $cliente->nombre,
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
                'meta' => $keyword->cliente?->empresa ?: $keyword->cliente?->nombre,
                'url' => route('admin.keywords.index'),
            ])
            ->all();
    }
}
