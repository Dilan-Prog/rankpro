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

        return back();
    }
}
