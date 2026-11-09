<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoCobro;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use App\Models\User;
use App\Support\ClienteActivo;
use App\Support\Reglas\Servicios as ReglasServicios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiciosController extends Controller
{
    public function index(): View
    {
        $clienteActivo = ClienteActivo::modelo();

        $servicios = Servicio::with(['cliente', 'responsable', 'eventos'])
            ->when($clienteActivo, fn ($q) => $q->where('cliente_id', $clienteActivo->id))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Servicio $servicio) => $this->toRow($servicio));

        return view('admin.servicios.index', [
            'pageTitle' => 'Gestión de Servicios',
            'clienteActivo' => $clienteActivo,
            'servicios' => $servicios,
            'activos' => $servicios->where('estado', 'activo')->count(),
            'clientes' => Cliente::ordenadoPorNombreComercial()->get(['id', 'nombre', 'empresa'])->pluck('nombre_comercial', 'id'),
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
        $cobroAnterior = $servicio->tipo_cobro;

        $servicio->update($this->validated($request));

        if ($servicio->estado !== $estadoAnterior) {
            $this->logEvento($servicio, "Estado cambiado a {$servicio->estado->value}.");
        }
        if ($servicio->tipo_cobro !== $cobroAnterior) {
            $this->logEvento($servicio, "Tipo de cobro cambiado a {$servicio->tipo_cobro->label()}.");
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
        // diffInMonths de Carbon 2 es absoluto: un inicio futuro daría meses positivos, y tras fecha_fin el servicio ya no acumula.
        $corte = $servicio->fecha_fin && $servicio->fecha_fin->lt(now()) ? $servicio->fecha_fin : now();
        $mesesActivos = $servicio->fecha_inicio && $servicio->fecha_inicio->lte($corte)
            ? (int) $servicio->fecha_inicio->diffInMonths($corte)
            : 0;
        $precio = (float) $servicio->precio_mensual;
        $tipoCobro = $servicio->tipo_cobro ?? TipoCobro::Mensual;
        $esMensual = $tipoCobro === TipoCobro::Mensual;

        return [
            'id' => $servicio->id,
            'cliente_id' => $servicio->cliente_id,
            'cliente' => $servicio->cliente?->nombre_comercial ?? '—',
            'responsable_id' => $servicio->responsable_id,
            'responsable_nombre' => $servicio->responsable?->name,
            'nombre' => $servicio->nombre,
            'descripcion' => $servicio->descripcion,
            'tipo' => $servicio->tipo->value,
            'estado' => $servicio->estado->value,
            'fecha_inicio' => $servicio->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $servicio->fecha_fin?->format('Y-m-d'),
            'tipo_cobro' => $tipoCobro->value,
            'tipo_cobro_label' => $tipoCobro->label(),
            'precio_mensual' => $precio,
            // Anualizar o acumular por meses solo tiene sentido en cobro mensual; en único/gratuito sería inventar ingreso.
            'anualizado' => $esMensual ? $precio * 12 : null,
            'meses_activos' => $mesesActivos,
            'ingreso_acumulado' => $esMensual ? $precio * $mesesActivos : null,
            'aporta_mrr' => $servicio->aportaMrr(),
            'vencido' => $servicio->estaVencido(),
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
        return ReglasServicios::normalizar($request->validate(ReglasServicios::guardar()));
    }
}
