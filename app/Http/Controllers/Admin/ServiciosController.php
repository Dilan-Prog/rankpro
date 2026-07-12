<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiciosController extends Controller
{
    public function index(): View
    {
        $servicios = Servicio::with('cliente')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Servicio $servicio) => [
                'id' => $servicio->id,
                'cliente' => $servicio->cliente?->nombre ?? '—',
                'nombre' => $servicio->nombre,
                'tipo' => $servicio->tipo,
                'estado' => $servicio->estado->value,
                'fecha_inicio' => $servicio->fecha_inicio?->format('Y-m-d'),
                'precio_mensual' => (float) $servicio->precio_mensual,
            ]);

        return view('admin.servicios.index', [
            'pageTitle' => 'Gestión de Servicios',
            'servicios' => $servicios,
            'activos' => $servicios->where('estado', 'activo')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.servicios.create', [
            'pageTitle' => 'Nuevo Servicio',
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $servicio = Servicio::create($data);

        return redirect()->route('admin.servicios.index')->with('status', "Servicio \"{$servicio->nombre}\" creado correctamente.");
    }

    public function show(Servicio $servicio): View
    {
        return view('admin.servicios.show', ['pageTitle' => $servicio->nombre, 'servicio' => $servicio]);
    }

    public function edit(Servicio $servicio): View
    {
        return view('admin.servicios.edit', [
            'pageTitle' => 'Editar Servicio',
            'servicio' => $servicio,
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function update(Request $request, Servicio $servicio): RedirectResponse
    {
        $data = $this->validated($request);

        $servicio->update($data);

        return redirect()->route('admin.servicios.index')->with('status', "Servicio \"{$servicio->nombre}\" actualizado correctamente.");
    }

    public function destroy(Servicio $servicio): RedirectResponse
    {
        $servicio->delete();

        return redirect()->route('admin.servicios.index')->with('status', "Servicio \"{$servicio->nombre}\" eliminado.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'tipo' => ['required', 'in:seo,google_ads,meta_ads,tiktok_ads,rediseno,software'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio_mensual' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:activo,pausado,cancelado'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);
    }
}
