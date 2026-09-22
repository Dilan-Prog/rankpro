<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Finanza;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\FinanzasMetrics;
use App\Support\Reglas\Finanzas as ReglasFinanzas;
use Illuminate\Http\Request;

class FinanzasApiController extends ControladorApi
{
    public function index(Request $request)
    {
        $query = Finanza::query();

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_emision', '>=', $request->date('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->date('fecha_hasta'));
        }

        return $this->listar($query, $request, new ConsultaOpciones(
            buscarEn: ['concepto'],
            filtrosExactos: ['cliente_id', 'servicio_id', 'estado', 'tipo'],
            ordenables: ['id', 'fecha_emision', 'monto', 'created_at', 'updated_at'],
            ordenPorDefecto: '-fecha_emision',
            incluibles: ['cliente', 'servicio'],
        ));
    }

    public function show(Finanza $finanza)
    {
        return Respuesta::recurso(Serializador::modelo($finanza));
    }

    public function store(Request $request)
    {
        $finanza = Finanza::create($request->validate(ReglasFinanzas::guardar()));

        return Respuesta::recurso(Serializador::modelo($finanza->fresh()), 201);
    }

    public function update(Request $request, Finanza $finanza)
    {
        $finanza->update($request->validate(ReglasFinanzas::guardar()));

        return Respuesta::recurso(Serializador::modelo($finanza->fresh()));
    }

    public function destroy(Finanza $finanza)
    {
        $finanza->delete();

        return Respuesta::eliminado();
    }

    /** Atajo: marca la finanza como pagada hoy (o en la fecha_pago dada) sin mandar el registro completo. */
    public function pagar(Request $request, Finanza $finanza)
    {
        $data = $request->validate([
            'fecha_pago' => ['nullable', 'date'],
        ]);

        $finanza->update([
            'estado' => 'pagado',
            'fecha_pago' => $data['fecha_pago'] ?? now()->toDateString(),
        ]);

        return Respuesta::recurso(Serializador::modelo($finanza->fresh()));
    }

    /** Resumen simple de cartera de ingresos: cuánto está pendiente, pagado y vencido. */
    public function resumen()
    {
        $ingresos = Finanza::where('tipo', 'ingreso');

        return Respuesta::recurso([
            'pendiente' => (float) (clone $ingresos)->where('estado', 'pendiente')->sum('monto'),
            'pagado' => (float) (clone $ingresos)->where('estado', 'pagado')->sum('monto'),
            'vencido' => (float) (clone $ingresos)->where('estado', 'vencido')->sum('monto'),
            'mrr' => FinanzasMetrics::mrr(),
        ]);
    }
}
