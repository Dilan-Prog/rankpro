<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\GscConexion;
use App\Support\ClienteActivo;
use App\Support\FinanzasMetrics;
use App\Support\Plantillas\Giros;
use App\Support\Reglas\Clientes as ReglasClientes;
use App\Support\Tablero\FichaCliente;
use App\Support\Tablero\ResumenCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientesController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::with('servicios')
            ->ordenadoPorNombreComercial()
            ->get()
            ->map(fn (Cliente $cliente) => $this->toRow($cliente));

        return view('admin.clientes.index', [
            'pageTitle' => 'CRM — Clientes',
            'giros' => Giros::todos(),
            // Solo se ofrece crear la propiedad de Search Console si ya hay una llave de Google guardada.
            'gscDisponible' => (bool) GscConexion::actual()?->tieneLlave(),
            'clientes' => $clientes,
            'activos' => $clientes->where('estado', 'activo')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $cliente = Cliente::create($this->validated($request));

        return response()->json($this->toRow($cliente), 201);
    }

    /**
     * Resumen de cliente: ficha mínima + tablero con semáforo por bloque. Trabaja con el cliente de la URL, no con
     * el activo del menú; la vista ofrece fijarlo como activo.
     */
    public function show(Request $request, Cliente $cliente): View
    {
        $usuario = $request->user();

        return view('admin.clientes.show', [
            'pageTitle' => $cliente->nombre_comercial,
            'cliente' => $cliente,
            'ficha' => (new FichaCliente())->datos($cliente, $usuario),
            'resumen' => (new ResumenCliente())->calcular($cliente, $usuario),
            'esClienteActivo' => ClienteActivo::id() === $cliente->id,
            'portal' => \App\Support\Portal\Administracion::datos($cliente),
        ]);
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($this->validated($request, $cliente));

        return response()->json($this->toRow($cliente->fresh()));
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        $cliente->delete();

        return response()->json(['deleted' => true]);
    }

    /** Shared shape for the index's server-rendered rows and store()/update()'s AJAX responses. */
    private function toRow(Cliente $cliente): array
    {
        // Antes ->where('estado', 'activo') sobre la colección: estado es un enum y `==` contra el string nunca iguala, así que el MRR salía siempre 0.
        $mrr = FinanzasMetrics::mrrDeServicios($cliente->servicios);

        return [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            // Lo que el CRM muestra como principal; `nombre` queda como contacto secundario.
            'nombre_comercial' => $cliente->nombre_comercial,
            // `nombre` guarda a la persona: solo es contacto si no es lo mismo que el nombre comercial.
            'contacto' => filled($cliente->contacto_nombre) ? $cliente->contacto_nombre : ($cliente->nombre !== $cliente->nombre_comercial ? $cliente->nombre : null),
            'empresa' => $cliente->empresa,
            'giro' => $cliente->giro,
            'sitio_web' => $cliente->sitio_web,
            'ciudad' => $cliente->ciudad,
            // Aditivo: el CRM marca los perfiles que el alta rápida dejó a medias.
            'perfil_completo' => $cliente->perfilCompleto(),
            'perfil_faltantes' => array_values($cliente->camposFaltantes()),
            'email' => $cliente->email,
            'telefono' => $cliente->telefono,
            'contacto_nombre' => $cliente->contacto_nombre,
            'estado' => $cliente->estado->value,
            'servicios' => $cliente->servicios->pluck('tipo')->unique()->values()->all(),
            'mrr' => (float) $mrr,
            'fecha_inicio_contrato' => $cliente->fecha_inicio_contrato?->format('Y-m-d'),
            'fecha_renovacion_contrato' => $cliente->fecha_renovacion_contrato?->format('Y-m-d'),
            'forma_pago' => $cliente->forma_pago?->value,
            'metodo_pago' => $cliente->metodo_pago?->value,
            'notas' => $cliente->notas,
        ];
    }

    private function validated(Request $request, ?Cliente $cliente = null): array
    {
        $datos = $request->validate(ReglasClientes::guardar($cliente));
        // El mismo formato que deja el asistente de alta (con https://), de donde se precargan SEO, Search Console y reportes.
        if (array_key_exists('sitio_web', $datos)) {
            $datos['sitio_web'] = \App\Support\Clientes\Sitio::normalizar($datos['sitio_web']);
        }

        return $datos;
    }
}
