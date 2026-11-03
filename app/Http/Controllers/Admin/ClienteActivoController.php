<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ClienteActivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClienteActivoController extends Controller
{
    /**
     * Guarda en sesión el cliente elegido en el selector del menú y vuelve a
     * la página desde la que se cambió. Un valor vacío quita la selección.
     */
    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ]);

        ClienteActivo::establecer($datos['cliente_id'] ?? null);

        return redirect()->to($this->urlDeRetorno());
    }

    /**
     * Página desde la que se cambió, pero «limpia» de lo que dejaría de tener sentido:
     * - En Contenido (`contenido/{id}`, `?carpeta=`) los ids pertenecen al cliente anterior y darían 404: vuelve al índice.
     * - Un `?cliente_id=`/`?cliente=` explícito gana sobre el cliente activo (Propuestas, Tablero) y el selector
     *   parecería no funcionar: se quita de la URL de retorno.
     */
    private function urlDeRetorno(): string
    {
        $previa = url()->previous();
        $partes = parse_url($previa) ?: [];
        $ruta = trim($partes['path'] ?? '', '/');

        $mismoHost = ($partes['host'] ?? null) === request()->getHost();
        if (! $mismoHost) {
            return route('admin.inicio');
        }

        if (preg_match('#(^|/)admin/contenido/.+#', $ruta) || str_contains($partes['query'] ?? '', 'carpeta=')) {
            return route('admin.contenido.index');
        }

        parse_str($partes['query'] ?? '', $query);
        unset($query['cliente_id'], $query['cliente']);

        $base = ($partes['scheme'] ?? 'http').'://'.$partes['host'].(isset($partes['port']) ? ':'.$partes['port'] : '').($partes['path'] ?? '/');

        return $query ? $base.'?'.http_build_query($query) : $base;
    }
}
