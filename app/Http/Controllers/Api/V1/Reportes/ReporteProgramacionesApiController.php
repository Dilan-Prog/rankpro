<?php

namespace App\Http\Controllers\Api\V1\Reportes;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\ReporteProgramacion;
use App\Services\Reportes\EnviadorReporteProgramado;
use App\Services\Reportes\GestorReporteProgramaciones;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Reglas\ReporteProgramaciones as ReglasProgramaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/reportes/programados: envíos mensuales de reportes por correo. Mismas
 * reglas y operaciones que el panel (Reglas\ReporteProgramaciones y
 * GestorReporteProgramaciones). `enviar` y `probar` son SÍNCRONOS y bloquean la
 * petición mientras se genera el PDF y sale el correo (como `enviar` de Correo).
 */
class ReporteProgramacionesApiController extends ControladorApi
{
    public function __construct(
        private GestorReporteProgramaciones $gestor,
        private EnviadorReporteProgramado $enviador,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = ReporteProgramacion::query()->with('cliente');
        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('ultimo_ok')) {
            $query->where('ultimo_ok', filter_var($request->input('ultimo_ok'), FILTER_VALIDATE_BOOLEAN));
        }

        return $this->listar($query, $request, new ConsultaOpciones(
            filtrosExactos: ['cliente_id', 'area'],
            ordenables: ['id', 'proximo_envio_en', 'ultimo_envio_en', 'created_at', 'updated_at'],
            ordenPorDefecto: 'proximo_envio_en',
        ));
    }

    public function show(ReporteProgramacion $programacion): JsonResponse
    {
        return Respuesta::recurso(Serializador::modelo($programacion));
    }

    public function store(Request $request): JsonResponse
    {
        ReglasProgramaciones::normalizarDestinatarios($request);
        $data = $request->validate(ReglasProgramaciones::guardar());

        $prog = $this->gestor->crear($data, $request->user()?->id);

        return Respuesta::recurso(Serializador::modelo($prog->fresh('cliente')), 201);
    }

    /** PUT admite cambios parciales: lo que no se envía conserva su valor actual. */
    public function update(Request $request, ReporteProgramacion $programacion): JsonResponse
    {
        $actual = [
            'cliente_id' => $programacion->cliente_id,
            'area' => $programacion->area->value,
            'plantilla_id' => $programacion->plantilla_id,
            'asunto' => $programacion->asunto,
            'destinatarios' => $programacion->destinatarios,
            'dia_mes' => $programacion->dia_mes,
            'hora' => substr((string) $programacion->hora, 0, 5),
        ];
        $request->merge(array_diff_key($actual, $request->all()));

        ReglasProgramaciones::normalizarDestinatarios($request);
        $data = $request->validate(ReglasProgramaciones::guardar());

        $this->gestor->actualizar($programacion, $data);

        return Respuesta::recurso(Serializador::modelo($programacion->fresh('cliente')));
    }

    public function destroy(ReporteProgramacion $programacion): JsonResponse
    {
        $programacion->delete();

        return Respuesta::eliminado();
    }

    public function pausar(ReporteProgramacion $programacion): JsonResponse
    {
        $this->gestor->pausar($programacion);

        return Respuesta::recurso(Serializador::modelo($programacion->fresh('cliente')));
    }

    public function activar(ReporteProgramacion $programacion): JsonResponse
    {
        $this->gestor->activar($programacion);

        return Respuesta::recurso(Serializador::modelo($programacion->fresh('cliente')));
    }

    /** "Enviar ahora": manda el reporte del periodo a los destinatarios; 422 si no salió (el motivo queda en ultimo_resultado). */
    public function enviar(ReporteProgramacion $programacion): JsonResponse
    {
        if (! $this->enviador->ejecutar($programacion, manual: true)) {
            return Respuesta::mensaje(EnviadorReporteProgramado::MENSAJE_EN_CURSO, 409);
        }

        $row = Serializador::modelo($programacion->fresh('cliente'));

        if (! $row['ultimo_ok']) {
            return Respuesta::mensaje((string) $row['ultimo_resultado'], 422, ['data' => $row]);
        }

        return Respuesta::recurso($row);
    }

    /** Manda el reporte solo al correo del dueño del token; no toca el último envío. */
    public function probar(Request $request, ReporteProgramacion $programacion): JsonResponse
    {
        ['ok' => $ok, 'mensaje' => $mensaje] = $this->gestor->probar($programacion, $request->user());

        $row = Serializador::modelo($programacion->fresh('cliente'));

        return $ok
            ? Respuesta::recurso($row, 200, ['message' => $mensaje])
            : Respuesta::mensaje($mensaje, 422, ['data' => $row]);
    }
}
