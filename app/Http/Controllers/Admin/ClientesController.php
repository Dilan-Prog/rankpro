<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoClienteServicio;
use App\Enums\FormaPago;
use App\Enums\MetodoPago;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientesController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::with('servicios')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Cliente $cliente) => $this->toRow($cliente));

        return view('admin.clientes.index', [
            'pageTitle' => 'CRM — Clientes',
            'clientes' => $clientes,
            'activos' => $clientes->where('estado', 'activo')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $cliente = Cliente::create($this->validated($request));

        return response()->json($this->toRow($cliente), 201);
    }

    public function show(Cliente $cliente): View
    {
        return view('admin.clientes.show', ['pageTitle' => $cliente->nombre, 'cliente' => $cliente]);
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
        $mrr = $cliente->servicios
            ->where('estado', 'activo')
            ->sum('precio_mensual');

        return [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'empresa' => $cliente->empresa,
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
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($cliente?->id)->where(fn ($q) => $q->whereNull('deleted_at'))],
            'telefono' => ['nullable', 'string', 'max:30'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', Rule::enum(EstadoClienteServicio::class)],
            'fecha_inicio_contrato' => ['nullable', 'date'],
            'fecha_renovacion_contrato' => ['nullable', 'date'],
            'forma_pago' => ['nullable', Rule::enum(FormaPago::class)],
            'metodo_pago' => ['nullable', Rule::enum(MetodoPago::class)],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
