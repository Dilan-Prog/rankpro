<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Models\ReporteEnlace;
use App\Services\Reportes\Armador;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Enlaces de solo lectura a la versión para cliente. El token en claro viaja una sola
 * vez, en la respuesta que lo crea: después ya no hay forma de volver a verlo (solo se
 * guarda su hash), así que un enlace perdido se revoca y se crea otro.
 */
class ReporteEnlacesController extends Controller
{
    public function store(Reporte $reporte, Armador $armador): JsonResponse
    {
        if ($armador->armarCliente($reporte)['resumen'] === null) {
            return response()->json(['message' => 'Este reporte no tiene una sección «Resumen para el cliente» visible: no hay nada que compartir.'], 422);
        }

        $nuevo = ReporteEnlace::crearPara($reporte, Auth::id());

        return response()->json([
            'enlace' => $nuevo['enlace']->toRow(),
            'url' => $nuevo['url'],
            'aviso' => 'Copia el enlace ahora: por seguridad no se vuelve a mostrar.',
        ], 201);
    }

    public function destroy(Reporte $reporte, ReporteEnlace $enlace): JsonResponse
    {
        // El enlace debe ser de ESTE reporte: la URL no puede revocar el de otro cliente.
        abort_unless($enlace->reporte_id === $reporte->id, 404);

        if ($enlace->revocado_en === null) {
            $enlace->forceFill(['revocado_en' => now()])->save();
        }

        return response()->json($enlace->toRow());
    }
}
