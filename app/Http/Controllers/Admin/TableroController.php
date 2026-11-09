<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Support\ClienteActivo;
use App\Support\Tablero\TableroCliente;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tablero de lectura por cliente (sección de Reportes). No escribe nada: toda
 * la cuenta vive en TableroCliente para poder probarla sin HTTP.
 */
class TableroController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::query()->ordenadoPorNombreComercial()->get(['id', 'empresa', 'nombre']);

        // ?cliente= manda sobre el cliente activo del menú; un id inválido o inexistente cae al activo.
        $pedido = $request->query('cliente');
        $cliente = is_string($pedido) && ctype_digit($pedido) ? $clientes->firstWhere('id', (int) $pedido) : null;
        $cliente ??= ClienteActivo::id() ? $clientes->firstWhere('id', ClienteActivo::id()) : null;

        $datos = $cliente ? (new TableroCliente())->calcular($cliente) : null;

        return view('admin.reportes.tablero', [
            'pageTitle' => 'Tablero por cliente',
            'clientes' => $clientes,
            'cliente' => $cliente,
            'datos' => $datos,
        ]);
    }
}
