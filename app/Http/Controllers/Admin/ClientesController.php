<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientesController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::with('servicios')
            ->orderBy('nombre')
            ->get()
            ->map(function (Cliente $cliente) {
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
                    'forma_pago' => $cliente->forma_pago,
                    'metodo_pago' => $cliente->metodo_pago,
                    'notas' => $cliente->notas,
                ];
            });

        return view('admin.clientes.index', [
            'pageTitle' => 'CRM — Clientes',
            'clientes' => $clientes,
            'activos' => $clientes->where('estado', 'activo')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.clientes.create', ['pageTitle' => 'Nuevo Cliente']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $cliente = Cliente::create($data);

        return redirect()->route('admin.clientes.index')->with('status', "Cliente \"{$cliente->nombre}\" creado correctamente.");
    }

    public function show(Cliente $cliente): View
    {
        return view('admin.clientes.show', ['pageTitle' => $cliente->nombre, 'cliente' => $cliente]);
    }

    public function edit(Cliente $cliente): View
    {
        return view('admin.clientes.edit', ['pageTitle' => 'Editar Cliente', 'cliente' => $cliente]);
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $data = $this->validated($request);

        $cliente->update($data);

        return redirect()->route('admin.clientes.index')->with('status', "Cliente \"{$cliente->nombre}\" actualizado correctamente.");
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->delete();

        return redirect()->route('admin.clientes.index')->with('status', "Cliente \"{$cliente->nombre}\" eliminado.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:activo,pausado,cancelado'],
            'fecha_inicio_contrato' => ['nullable', 'date'],
            'fecha_renovacion_contrato' => ['nullable', 'date'],
            'forma_pago' => ['nullable', 'in:mensual,trimestral,anual'],
            'metodo_pago' => ['nullable', 'in:transferencia,tarjeta,efectivo,paypal'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
