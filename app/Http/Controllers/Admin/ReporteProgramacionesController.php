<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\CorreoPlantilla;
use App\Models\ReporteProgramacion;
use App\Services\Reportes\EnviadorReporteProgramado;
use App\Services\Reportes\GestorReporteProgramaciones;
use App\Support\Reglas\ReporteProgramaciones as ReglasProgramaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla y acciones de los envíos mensuales de reportes. La lógica de envío
 * vive en EnviadorReporteProgramado (la misma que usa el comando programado).
 */
class ReporteProgramacionesController extends Controller
{
    public function __construct(private EnviadorReporteProgramado $enviador, private GestorReporteProgramaciones $gestor)
    {
    }

    public function index(): View
    {
        $programaciones = ReporteProgramacion::with('cliente')
            ->orderByDesc('activo')
            ->orderBy('proximo_envio_en')
            ->get()
            ->map(fn (ReporteProgramacion $p) => $p->toRow());

        return view('admin.reportes.programados.index', [
            'pageTitle' => 'Reportes programados',
            'programaciones' => $programaciones,
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
            'plantillas' => CorreoPlantilla::where('estado', 'activa')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $prog = $this->gestor->crear($data, $request->user()->id);

        return response()->json($prog->fresh('cliente')->toRow(), 201);
    }

    public function update(Request $request, ReporteProgramacion $programacion): JsonResponse
    {
        $data = $this->validated($request);

        $programacion = $this->gestor->actualizar($programacion, $data);

        return response()->json($programacion->fresh('cliente')->toRow());
    }

    public function destroy(ReporteProgramacion $programacion): JsonResponse
    {
        $programacion->delete();

        return response()->json(['deleted' => true]);
    }

    public function alternar(ReporteProgramacion $programacion): JsonResponse
    {
        $programacion->activo ? $this->gestor->pausar($programacion) : $this->gestor->activar($programacion);

        return response()->json($programacion->fresh('cliente')->toRow());
    }

    public function probar(Request $request, ReporteProgramacion $programacion): JsonResponse
    {
        ['ok' => $ok, 'mensaje' => $mensaje] = $this->gestor->probar($programacion, $request->user());

        return response()->json(
            ['message' => $mensaje, 'programacion' => $programacion->fresh('cliente')->toRow()],
            $ok ? 200 : 422
        );
    }

    public function enviarAhora(ReporteProgramacion $programacion): JsonResponse
    {
        // Doble clic o dos pestañas: el segundo no envía, se avisa en vez de duplicar el correo al cliente.
        if (! $this->enviador->ejecutar($programacion, manual: true)) {
            return response()->json(['message' => EnviadorReporteProgramado::MENSAJE_EN_CURSO], 409);
        }

        $row = $programacion->fresh('cliente')->toRow();

        return response()->json([
            'message' => $row['ultimo_ok'] ? 'Reporte enviado.' : $row['ultimo_resultado'],
            'programacion' => $row,
        ], $row['ultimo_ok'] ? 200 : 422);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        ReglasProgramaciones::normalizarDestinatarios($request);

        // Nombres legibles: sin ellos el formulario mostraba "cliente id" o "destinatarios.0".
        return $request->validate(ReglasProgramaciones::guardar(), [], [
            'cliente_id' => 'cliente',
            'plantilla_id' => 'plantilla de correo',
            'dia_mes' => 'día del mes',
            'destinatarios' => 'destinatarios',
            'destinatarios.*' => 'destinatario',
        ]);
    }
}
