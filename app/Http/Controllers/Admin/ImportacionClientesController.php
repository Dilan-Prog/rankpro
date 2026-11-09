<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Clientes\ImportadorClientes;
use App\Support\Keywords\ImportadorKeywords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Importación de clientes por CSV: la vista previa no guarda nada y la importación crea solo las filas nuevas.
 * Las dos reciben el mismo texto/archivo, así que lo que se ve en la vista previa es lo que se importa.
 */
class ImportacionClientesController extends Controller
{
    public function vistaPrevia(Request $request, ImportadorClientes $importador): JsonResponse
    {
        return $this->procesar($request, fn (array $lineas) => $importador->analizar($lineas), 200);
    }

    public function importar(Request $request, ImportadorClientes $importador): JsonResponse
    {
        return $this->procesar($request, fn (array $lineas) => $importador->importar($lineas), 201);
    }

    private function procesar(Request $request, callable $accion, int $estadoOk): JsonResponse
    {
        $request->validate([
            'texto' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $lineas = ImportadorKeywords::lineas(
            $request->hasFile('archivo')
                ? file($request->file('archivo')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                : (string) $request->input('texto', '')
        );

        if ($lineas === []) {
            return response()->json(['message' => 'Pega o sube un CSV con al menos una fila.'], 422);
        }

        try {
            $resultado = $accion($lineas);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Importar sin ninguna fila nueva no es un éxito silencioso: se responde 422 con el detalle por fila.
        if ($estadoOk === 201 && ($resultado['creados'] ?? 0) === 0) {
            return response()->json($resultado + ['message' => 'No se creó ningún cliente: revisa los duplicados y errores.'], 422);
        }

        return response()->json($resultado, $estadoOk);
    }
}
