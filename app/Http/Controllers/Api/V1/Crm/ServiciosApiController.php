<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Reglas\Servicios as ReglasServicios;
use Illuminate\Http\Request;

class ServiciosApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(Servicio::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre', 'descripcion'],
            filtrosExactos: ['cliente_id', 'responsable_id', 'estado', 'tipo'],
            ordenables: ['id', 'nombre', 'precio_mensual', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'responsable', 'eventos'],
        ));
    }

    public function show(Servicio $servicio)
    {
        return Respuesta::recurso(Serializador::modelo($servicio));
    }

    public function store(Request $request)
    {
        $servicio = Servicio::create($request->validate(ReglasServicios::guardar()));
        $this->logEvento($servicio, 'Servicio creado.');

        return Respuesta::recurso(Serializador::modelo($servicio->fresh()), 201);
    }

    public function update(Request $request, Servicio $servicio)
    {
        $estadoAnterior = $servicio->estado;
        $precioAnterior = (string) $servicio->precio_mensual;

        $servicio->update($request->validate(ReglasServicios::guardar()));

        if ($servicio->estado !== $estadoAnterior) {
            $this->logEvento($servicio, "Estado cambiado a {$servicio->estado->value}.");
        }
        if ((string) $servicio->precio_mensual !== $precioAnterior) {
            $this->logEvento($servicio, 'Precio mensual actualizado a $'.number_format((float) $servicio->precio_mensual, 2).' MXN.');
        }

        return Respuesta::recurso(Serializador::modelo($servicio->fresh()));
    }

    public function destroy(Servicio $servicio)
    {
        $servicio->delete();

        return Respuesta::eliminado();
    }

    public function eventos(Request $request, Servicio $servicio)
    {
        return $this->listar(ServicioEvento::where('servicio_id', $servicio->id), $request, new ConsultaOpciones(
            ordenables: ['id', 'created_at'],
            ordenPorDefecto: '-created_at',
            incluibles: ['usuario'],
        ));
    }

    /** Misma bitácora que Admin\ServiciosController::logEvento(). */
    private function logEvento(Servicio $servicio, string $descripcion): void
    {
        ServicioEvento::create([
            'servicio_id' => $servicio->id,
            'usuario_id' => auth()->id(),
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }
}
