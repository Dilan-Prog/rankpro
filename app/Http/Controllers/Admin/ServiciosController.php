<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoClienteServicio;
use App\Enums\TipoServicio;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiciosController extends Controller
{
    public function index(): View
    {
        $servicios = Servicio::with(['cliente', 'responsable', 'eventos'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Servicio $servicio) => $this->toRow($servicio));

        return view('admin.servicios.index', [
            'pageTitle' => 'Gestión de Servicios',
            'servicios' => $servicios,
            'activos' => $servicios->where('estado', 'activo')->count(),
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
            'usuarios' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $servicio = Servicio::create($this->validated($request));
        $this->logEvento($servicio, 'Servicio creado.');

        return response()->json($this->toRow($servicio->fresh(['cliente', 'responsable', 'eventos'])), 201);
    }

    public function update(Request $request, Servicio $servicio): JsonResponse
    {
        $estadoAnterior = $servicio->estado;
        $precioAnterior = (string) $servicio->precio_mensual;

        $servicio->update($this->validated($request));

        if ($servicio->estado !== $estadoAnterior) {
            $this->logEvento($servicio, "Estado cambiado a {$servicio->estado->value}.");
        }
        if ((string) $servicio->precio_mensual !== $precioAnterior) {
            $this->logEvento($servicio, 'Precio mensual actualizado a $' . number_format((float) $servicio->precio_mensual, 2) . ' MXN.');
        }

        return response()->json($this->toRow($servicio->fresh(['cliente', 'responsable', 'eventos'])));
    }

    public function destroy(Servicio $servicio): JsonResponse
    {
        $servicio->delete();

        return response()->json(['deleted' => true]);
    }

    /** Shared shape for index()'s server-rendered rows and store()/update()'s AJAX responses. */
    private function toRow(Servicio $servicio): array
    {
        $mesesActivos = $servicio->fecha_inicio
            ? max(0, (int) $servicio->fecha_inicio->diffInMonths(now()))
            : 0;
        $precio = (float) $servicio->precio_mensual;

        return [
            'id' => $servicio->id,
            'cliente_id' => $servicio->cliente_id,
            'cliente' => $servicio->cliente?->nombre ?? '—',
            'responsable_id' => $servicio->responsable_id,
            'responsable_nombre' => $servicio->responsable?->name,
            'nombre' => $servicio->nombre,
            'descripcion' => $servicio->descripcion,
            'tipo' => $servicio->tipo->value,
            'estado' => $servicio->estado->value,
            'fecha_inicio' => $servicio->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $servicio->fecha_fin?->format('Y-m-d'),
            'precio_mensual' => $precio,
            'anualizado' => $precio * 12,
            'meses_activos' => $mesesActivos,
            'ingreso_acumulado' => $precio * $mesesActivos,
            'eventos' => $servicio->eventos->map(fn (ServicioEvento $e) => [
                'descripcion' => $e->descripcion,
                'fecha' => $e->created_at->format('Y-m-d H:i'),
                'usuario' => $e->usuario?->name,
            ])->all(),
        ];
    }

    private function logEvento(Servicio $servicio, string $descripcion): void
    {
        ServicioEvento::create([
            'servicio_id' => $servicio->id,
            'usuario_id' => auth()->id(),
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'tipo' => ['required', Rule::enum(TipoServicio::class)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio_mensual' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', Rule::enum(EstadoClienteServicio::class)],
            'fecha_inicio' => ['nullable', 'date', 'required_with:fecha_fin'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);
    }
}
